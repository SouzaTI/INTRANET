<?php
// Identifica a página atual para o estado "selecionado"
$current_page = basename($_SERVER['PHP_SELF']);
$is_docs_active = ($current_page == 'view.php' || isset($_GET['path']));

// ESCANEAMENTO DINÂMICO DE PASTAS
$diretorio_docs = __DIR__ . '/../docs/';
$setores_disponiveis = [];

if (is_dir($diretorio_docs)) {
    $pastas = scandir($diretorio_docs);
    foreach ($pastas as $pasta) {
        if ($pasta !== '.' && $pasta !== '..' && is_dir($diretorio_docs . $pasta)) {
            $setores_disponiveis[] = strtoupper($pasta);
        }
    }
}
sort($setores_disponiveis);

// Verifica se o usuário tem acesso ao Marketing (Admin ou Setor Marketing)
$acesso_marketing = ($_SESSION['is_admin'] || $_SESSION['setor_principal'] == 'MARKETING');

// Descobre a pasta atual que está sendo visualizada para manter a sanfona aberta
$pasta_url_atual = '';
if (isset($_GET['path'])) {
    $partes_path = explode('/', urldecode($_GET['path']));
    $pasta_url_atual = strtoupper($partes_path[0]);
}
?>

<aside class="w-64 bg-navy-900 flex flex-col h-full border-r border-navy-700 transition-all duration-300">
    <nav class="flex-1 px-4 py-6 space-y-8 overflow-y-auto custom-scrollbar">
        
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 px-3">Menu Principal</p>
            <ul class="space-y-1">
                <li>
                    <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'index.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>">
                        <span>🏠</span> <span class="text-sm font-semibold">Início</span>
                    </a>
                </li>
                <li>
                    <a href="matriz.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'matriz.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>">
                        <span>📞</span> <span class="text-sm font-semibold">Matriz de Comunicação</span>
                    </a>
                </li>
                <li>
                    <a href="treinamento.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-navy-800 rounded-lg transition-all">
                        <span>🎓</span> <span class="text-sm font-semibold">Cursos & Treinamentos</span>
                    </a>
                </li>    
            </ul>
        </div>

        <?php 
            $tem_permissao_feed = ($_SESSION['is_admin'] || ($_SESSION['pode_postar_feed'] ?? false) || $_SESSION['setor_principal'] == 'MARKETING');
            if ($tem_permissao_feed): 
        ?>
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 px-3">Comunicação</p>
            <ul class="space-y-1">
                <li>
                    <a href="admin_marketing.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_marketing.php') ? 'bg-amber-500 text-white shadow-lg shadow-amber-900/20' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>">
                        <span>🎨</span> <span class="text-sm font-semibold">Gestão Marketing</span>
                    </a>
                </li>
                <li>
                    <a href="admin_feed.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_feed.php') ? 'bg-amber-500 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>">
                        <span>📢</span> <span class="text-sm font-semibold">Gestão do Feed</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <div>
    <button onclick="toggleDocs()" class="w-full flex items-center justify-between px-3 py-2.5 text-slate-400 hover:text-white hover:bg-navy-800 rounded-lg transition-all">
        <div class="flex items-center gap-3">
            <span>📂</span> <span class="text-sm font-semibold">Documentação</span>
        </div>
        <span id="docs-arrow" class="transition-transform duration-200 <?php echo $is_docs_active ? 'rotate-90' : ''; ?>">▶</span>
    </button>
    
    <ul id="docs-menu" class="mt-2 space-y-1 pl-6 <?php echo $is_docs_active ? '' : 'hidden'; ?>">
        <?php
        foreach ($setores_disponiveis as $setor):
            $tem_acesso = ($_SESSION['is_admin'] || 
                           $_SESSION['setor_principal'] == $setor || 
                           (isset($_SESSION['pastas_extras']) && in_array($setor, $_SESSION['pastas_extras'])));

            if ($tem_acesso):
                $diretorio_base = $_SERVER['DOCUMENT_ROOT'] . '/intranet/docs/'; 
                $diretorio_setor = $diretorio_base . $setor;
                
                // CORREÇÃO 1: Agora busca arquivos .md E .pdf
                $arquivos_docs = glob($diretorio_setor . '/*.{md,pdf}', GLOB_BRACE);
                $id_setor_limpo = str_replace([' ', '&', '.'], '_', $setor);
                
                // Verifica se esta é a pasta que o usuário está visualizando agora para manter a sanfona aberta
                $is_this_open = ($pasta_url_atual === $setor);
        ?>
            <li class="group">
                <div class="flex items-center justify-between py-1 px-2 rounded hover:bg-navy-800 transition-all">
                    <a href="view.php?path=<?php echo urlencode($setor); ?>" class="text-[11px] font-medium text-slate-500 hover:text-white transition-all uppercase flex-1 py-1">
                        <?php echo $setor; ?>
                    </a>
                    
                    <button onclick="event.preventDefault(); event.stopPropagation(); toggleSetor('sub_<?php echo $id_setor_limpo; ?>', 'arrow_<?php echo $id_setor_limpo; ?>')" 
                            class="p-1 text-slate-600 hover:text-white transition-all">
                        <span id="arrow_<?php echo $id_setor_limpo; ?>" class="text-[8px] transition-transform block" style="transform: <?php echo $is_this_open ? 'rotate(90deg)' : 'rotate(0deg)'; ?>">▶</span>
                    </button>
                </div>

                <ul id="sub_<?php echo $id_setor_limpo; ?>" class="<?php echo $is_this_open ? '' : 'hidden'; ?> pl-4 border-l border-slate-700 space-y-1 my-1">
                    <?php 
                    if (!$arquivos_docs || empty($arquivos_docs)): 
                    ?>
                        <li class="text-[10px] text-slate-600 italic py-1">Nenhum documento</li>
                    <?php 
                    else: 
                        foreach ($arquivos_docs as $arq): 
                            $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
                            $nome_doc = str_replace(['.md', '.pdf'], '', basename($arq));
                            $icone = ($ext == 'pdf') ? '📕' : '📄';
                            
                            // CORREÇÃO 2: Passa o caminho completo do arquivo para o view.php!
                            $caminho_completo = $setor . '/' . basename($arq);
                    ?>
                        <li>
                            <a href="view.php?path=<?php echo urlencode($caminho_completo); ?>" 
                               class="block py-1 text-[10px] text-slate-500 hover:text-blue-400 transition-all truncate" title="<?php echo $nome_doc; ?>">
                                <?php echo $icone; ?> <?php echo str_replace('_', ' ', $nome_doc); ?>
                            </a>
                        </li>
                    <?php 
                        endforeach; 
                    endif; 
                    ?>
                </ul>
            </li>
        <?php 
            endif;
        endforeach; 
        ?>
    </ul>
</div>

        <?php if ($_SESSION['is_admin'] || $_SESSION['pode_gerenciar_docs']): ?>
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 px-3">Administração</p>
            <ul class="space-y-1">
                
                <?php if ($_SESSION['pode_gerenciar_docs']): ?>
                <li>
                    <a href="admin_docs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_docs.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>">
                        <span>📝</span> <span class="text-sm font-semibold">Gestão de Manuais</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($_SESSION['is_admin']): ?>
                <li>
                    <a href="admin_gestao.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_gestao.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>">
                        <span>🛡️</span> <span class="text-sm font-semibold">Gestão de Acessos</span>
                    </a>
                </li>
                <li>
                    <a href="admin_logs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_logs.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>">
                        <span>📋</span> <span class="text-sm font-semibold">Logs de Auditoria</span>
                    </a>
                </li>
                <?php endif; ?>

            </ul>
        </div>
        <?php endif; ?>

    </nav>
</aside>

<script>
function toggleDocs() {
    const menu = document.getElementById('docs-menu');
    const arrow = document.getElementById('docs-arrow');
    menu.classList.toggle('hidden');
    arrow.classList.toggle('rotate-90');
}

function toggleSetor(id, arrowId) {
    const submenu = document.getElementById(id);
    const arrow = document.getElementById(arrowId);

    submenu.classList.toggle('hidden');
    
    if (submenu.classList.contains('hidden')) {
        arrow.style.transform = 'rotate(0deg)';
    } else {
        arrow.style.transform = 'rotate(90deg)';
    }
}
</script>