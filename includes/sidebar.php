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

// Verifica se o usuário tem acesso ao Marketing (Admin ou Setor Marketing)[cite: 10]
$acesso_marketing = ($_SESSION['is_admin'] || $_SESSION['setor_principal'] == 'MARKETING');

// Descobre a pasta atual que está sendo visualizada para manter a sanfona aberta[cite: 10]
$pasta_url_atual = '';
if (isset($_GET['path'])) {
    $partes_path = explode('/', urldecode($_GET['path']));
    $pasta_url_atual = strtoupper($partes_path[0]);
}

// --- INTEGRAÇÃO: BUSCA DE PROCESSOS HOMOLOGADOS (AGRUPADOS POR SETOR) ---
$stmt_aprovados = $pdo_intra->query("
    SELECT id, titulo, versao_atual, setor_origem 
    FROM docs_fluxo_simples 
    WHERE status = 'Aprovado' 
    ORDER BY setor_origem ASC, titulo ASC
");
$aprovados_por_setor = [];
while ($row = $stmt_aprovados->fetch(PDO::FETCH_ASSOC)) {
    $s = !empty($row['setor_origem']) ? strtoupper($row['setor_origem']) : 'GERAL';
    $aprovados_por_setor[$s][] = $row;
}
$is_proc_active = ($current_page == 'visualizar_processo.php');
$setor_atual_sidebar = isset($_GET['setor_origem']) ? urldecode($_GET['setor_origem']) : '';
?>

<div id="mobile-overlay" onclick="toggleMobileMenu()" class="fixed inset-0 bg-black/60 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity opacity-0"></div>

<aside id="sidebar-menu" class="fixed inset-y-0 left-0 z-50 w-64 bg-navy-900 flex flex-col h-full border-r border-navy-700 transition-transform duration-300 transform -translate-x-full lg:relative lg:translate-x-0 lg:flex shrink-0 shadow-2xl lg:shadow-none">
    
    <div class="flex justify-end p-4 lg:hidden">
        <button onclick="toggleMobileMenu()" class="text-slate-400 hover:text-white p-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-8 overflow-y-auto custom-scrollbar">
        
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 px-3">Menu Principal</p>
            <ul class="space-y-1">
                <li><a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'index.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>🏠</span> <span class="text-sm font-semibold">Início</span></a></li>
                <li><a href="matriz.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'matriz.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>📞</span> <span class="text-sm font-semibold">Matriz de Comunicação</span></a></li>
                <li><a href="treinamento.php" class="flex items-center gap-3 px-3 py-2.5 text-slate-400 hover:text-white hover:bg-navy-800 rounded-lg transition-all"><span>🎓</span> <span class="text-sm font-semibold">Cursos & Treinamentos</span></a></li>
                <li><a href="meus_documentos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'meus_documentos.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>📤</span> <span class="text-sm font-semibold">Envio de Processos</span></a></li>    
            </ul>
        </div>

        <?php 
            $tem_permissao_feed = ($_SESSION['is_admin'] || ($_SESSION['pode_postar_feed'] ?? false) || $_SESSION['setor_principal'] == 'MARKETING');
            if ($tem_permissao_feed): 
        ?>
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 px-3">Comunicação</p>
            <ul class="space-y-1">
                <li><a href="admin_marketing.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_marketing.php') ? 'bg-amber-500 text-white shadow-lg shadow-amber-900/20' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>🎨</span> <span class="text-sm font-semibold">Gestão Marketing</span></a></li>
                <li><a href="admin_feed.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_feed.php') ? 'bg-amber-500 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>📢</span> <span class="text-sm font-semibold">Gestão do Feed</span></a></li>
            </ul>
        </div>
        <?php endif; ?>

        <div>
            <button onclick="toggleDocs()" id="tour-documentacao" class="w-full flex items-center justify-between px-3 py-2.5 text-slate-400 hover:text-white hover:bg-navy-800 rounded-lg transition-all">
                <div class="flex items-center gap-3"><span>📂</span> <span class="text-sm font-semibold">Documentação</span></div>
                <span id="docs-arrow" class="transition-transform duration-200 <?php echo $is_docs_active ? 'rotate-90' : ''; ?>">▶</span>
            </button>
            <ul id="docs-menu" class="mt-2 space-y-1 pl-6 <?php echo $is_docs_active ? '' : 'hidden'; ?>">
                
                <li class="mb-2">
                    <a href="view.php" class="flex items-center justify-between py-1.5 px-2 rounded hover:bg-corporate-blue transition-all bg-navy-800 text-[11px] font-black text-white uppercase tracking-widest border border-navy-700 shadow-sm">
                        <span>👁️ Visão Geral</span>
                        <span>↗</span>
                    </a>
                </li>

                <?php foreach ($setores_disponiveis as $setor):
                    // A LÓGICA DE SEGURANÇA CONTINUA INTACTA AQUI
                    $tem_acesso = ($_SESSION['is_admin'] || $_SESSION['setor_principal'] == $setor || (isset($_SESSION['pastas_extras']) && in_array($setor, $_SESSION['pastas_extras'])));
                    if ($tem_acesso):
                        $diretorio_base = $_SERVER['DOCUMENT_ROOT'] . '/intranet/docs/'; 
                        $diretorio_setor = $diretorio_base . $setor;
                        $arquivos_docs = glob($diretorio_setor . '/*.{md,pdf}', GLOB_BRACE);
                        $id_setor_limpo = str_replace([' ', '&', '.'], '_', $setor);
                        $is_this_open = ($pasta_url_atual === $setor);
                ?>
                    <li class="group">
                        <div class="flex items-center justify-between py-1 px-2 rounded hover:bg-navy-800 transition-all">
                            <a href="view.php?path=<?php echo urlencode($setor); ?>" class="text-[11px] font-medium text-slate-500 hover:text-white transition-all uppercase flex-1 py-1"><?php echo $setor; ?></a>
                            <button onclick="event.preventDefault(); event.stopPropagation(); toggleSetor('sub_<?php echo $id_setor_limpo; ?>', 'arrow_<?php echo $id_setor_limpo; ?>')" class="p-1 text-slate-600 hover:text-white transition-all">
                                <span id="arrow_<?php echo $id_setor_limpo; ?>" class="text-[8px] transition-transform block" style="transform: <?php echo $is_this_open ? 'rotate(90deg)' : 'rotate(0deg)'; ?>">▶</span>
                            </button>
                        </div>
                        <ul id="sub_<?php echo $id_setor_limpo; ?>" class="<?php echo $is_this_open ? '' : 'hidden'; ?> pl-4 border-l border-slate-700 space-y-1 my-1">
                            <?php if (!$arquivos_docs || empty($arquivos_docs)): ?><li class="text-[10px] text-slate-600 italic py-1">Nenhum documento</li><?php else: 
                                foreach ($arquivos_docs as $arq): 
                                    $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
                                    $nome_doc = str_replace(['.md', '.pdf'], '', basename($arq));
                                    $icone = ($ext == 'pdf') ? '📕' : '📄';
                            ?>
                                <li><a href="view.php?path=<?php echo urlencode($setor . '/' . basename($arq)); ?>" class="block py-1 text-[10px] text-slate-500 hover:text-blue-400 transition-all truncate"><?php echo $icone; ?> <?php echo str_replace('_', ' ', $nome_doc); ?></a></li>
                            <?php endforeach; endif; ?>
                        </ul>
                    </li>
                <?php endif; endforeach; ?>
            </ul>
        </div>

        <div>
            <button onclick="toggleProcessos()" class="w-full flex items-center justify-between px-3 py-2.5 text-slate-400 hover:text-white hover:bg-navy-800 rounded-lg transition-all">
                <div class="flex items-center gap-3"><span>✅</span> <span class="text-sm font-semibold">Processos Homologados</span></div>
                <span id="processos-arrow" class="transition-transform duration-200 <?= $is_proc_active ? 'rotate-90' : '' ?>">▶</span>
            </button>
            <ul id="processos-menu" class="mt-2 space-y-1 pl-6 <?= $is_proc_active ? '' : 'hidden' ?>">
                
                <li class="mb-2">
                    <a href="visualizar_processo.php" class="flex items-center justify-between py-1.5 px-2 rounded hover:bg-corporate-blue transition-all bg-navy-800 text-[11px] font-black text-white uppercase tracking-widest border border-navy-700 shadow-sm">
                        <span>👁️ Visão Geral</span>
                        <span>↗</span>
                    </a>
                </li>
                
                <?php if (empty($aprovados_por_setor)): ?>
                    <li class="text-[10px] text-slate-600 italic py-1 px-2">Nenhum processo oficial.</li>
                <?php else: foreach ($aprovados_por_setor as $nome_setor => $docs_setor):
                    $id_setor_clean = preg_replace('/[^a-zA-Z0-9]/', '_', $nome_setor);
                    $is_this_proc_open = ($setor_atual_sidebar === $nome_setor);
                ?>
                    <li class="group">
                        <div class="flex items-center justify-between py-1 px-2 rounded hover:bg-navy-800 transition-all">
                            <a href="visualizar_processo.php?setor_origem=<?= urlencode($nome_setor) ?>" class="text-[11px] font-medium text-slate-500 hover:text-white transition-all uppercase flex-1 py-1">
                                📁 <?= htmlspecialchars($nome_setor) ?>
                            </a>
                            <button onclick="event.preventDefault(); event.stopPropagation(); toggleSetor('proc_sub_<?= $id_setor_clean ?>', 'proc_arr_<?= $id_setor_clean ?>')" class="p-1 text-slate-600 hover:text-white transition-all">
                                <span id="proc_arr_<?= $id_setor_clean ?>" class="text-[8px] transition-transform block" style="transform: <?= $is_this_proc_open ? 'rotate(90deg)' : 'rotate(0deg)' ?>">▶</span>
                            </button>
                        </div>
                        <ul id="proc_sub_<?= $id_setor_clean ?>" class="<?= $is_this_proc_open ? '' : 'hidden' ?> pl-4 border-l border-slate-700 space-y-1 my-1">
                            <?php foreach ($docs_setor as $doc_ap): ?>
                                <li>
                                    <a href="visualizar_processo.php?setor_origem=<?= urlencode($nome_setor) ?>&open_doc=<?= $doc_ap['id'] ?>&title=<?= urlencode($doc_ap['titulo']) ?>" class="flex items-center gap-2 py-1.5 px-2 rounded hover:text-blue-400 transition-all group">
                                        <span class="text-emerald-500 text-[10px]">📋</span>
                                        <span class="text-[10px] font-medium text-slate-500 group-hover:text-blue-400 truncate transition-all flex-1"><?= htmlspecialchars($doc_ap['titulo']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>

        <?php if ($_SESSION['is_admin'] || $_SESSION['pode_gerenciar_docs']): ?>
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 px-3">Administração</p>
            <ul class="space-y-1">
                <?php if ($_SESSION['pode_gerenciar_docs']): ?>
                <li><a href="admin_docs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_docs.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>📝</span> <span class="text-sm font-semibold">Gestão de Manuais</span></a></li>
                <?php endif; ?>
                <?php if ($_SESSION['is_admin']): ?>
                <li><a href="admin_gestao.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_gestao.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>🛡️</span> <span class="text-sm font-semibold">Gestão de Acessos</span></a></li>
                <li><a href="admin_logs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'admin_logs.php') ? 'bg-corporate-blue text-white' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>📋</span> <span class="text-sm font-semibold">Logs de Auditoria</span></a></li>
                <li><a href="gestao_fluxo.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?php echo ($current_page == 'gestao_fluxo.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20' : 'text-slate-400 hover:text-white hover:bg-navy-800'; ?>"><span>🛠️</span> <span class="text-sm font-semibold">Aprovações de Processos</span></a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </nav>
</aside>

<script>
// Funções de interface interna da Sidebar[cite: 10]
function toggleDocs() {
    const menu = document.getElementById('docs-menu');
    const arrow = document.getElementById('docs-arrow');
    if(menu && arrow) {
        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-90');
    }
}

function toggleProcessos() {
    const menu  = document.getElementById('processos-menu');
    const arrow = document.getElementById('processos-arrow');
    if (menu && arrow) {
        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-90');
    }
}

function toggleSetor(id, arrowId) {
    const submenu = document.getElementById(id);
    const arrow = document.getElementById(arrowId);
    if(submenu && arrow) {
        submenu.classList.toggle('hidden');
        arrow.style.transform = submenu.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(90deg)';
    }
}
</script>