<?php 
require_once 'config.php';
require_once ROOT_PATH . 'Parsedown.php';
$parsedown = new Parsedown();

include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// 1. Pegamos o caminho do arquivo (Se estiver vazio, estamos na "Visão Geral")
$path = isset($_GET['path']) ? urldecode($_GET['path']) : '';
$path = str_replace(['\\', '//'], '/', $path);
$full_path = ROOT_PATH . 'docs/' . $path;

$modo_exibicao = '';
$dados_tela = [];
$acesso_autorizado = false;

// 2. MODO 1: VISÃO GERAL (RAIZ) - Mostra as pastas permitidas
if ($path === '') {
    $modo_exibicao = 'pastas';
    $acesso_autorizado = true; // Todo mundo pode ver a raiz, mas só as pastas permitidas
    
    $todas_pastas = array_diff(scandir(ROOT_PATH . 'docs/'), array('.', '..', 'index.md'));
    
    foreach ($todas_pastas as $p) {
        if (is_dir(ROOT_PATH . 'docs/' . $p)) {
            $pasta_upper = strtoupper($p);
            $tem_permissao = false;
            
            // A MÁGICA DA PERMISSÃO AQUI: Avalia pasta por pasta
            if ($_SESSION['is_admin']) {
                $tem_permissao = true;
            } elseif ($pasta_upper == $_SESSION['setor_principal']) {
                $tem_permissao = true;
            } elseif (isset($_SESSION['pastas_extras']) && in_array($pasta_upper, $_SESSION['pastas_extras'])) {
                $tem_permissao = true;
            }
            
            if ($tem_permissao) {
                // Conta quantos arquivos tem lá dentro
                $arquivos_na_pasta = glob(ROOT_PATH . 'docs/' . $p . '/*.{md,pdf}', GLOB_BRACE);
                $dados_tela[] = [
                    'nome' => $p,
                    'qtd' => $arquivos_na_pasta ? count($arquivos_na_pasta) : 0
                ];
            }
        }
    }
} 
// 3. MODO 2 OU 3: ENTRANDO EM UMA PASTA OU ARQUIVO ESPECÍFICO
else {
    $partes_caminho = explode('/', $path);
    $pasta_atual = strtoupper($partes_caminho[0]);

    // Verificação de Acesso (Hierarquia de Segurança original mantida!)
    if ($_SESSION['is_admin']) {
        $acesso_autorizado = true;
    } elseif ($pasta_atual == $_SESSION['setor_principal']) {
        $acesso_autorizado = true;
    } elseif (isset($_SESSION['pastas_extras']) && in_array($pasta_atual, $_SESSION['pastas_extras'])) {
        $acesso_autorizado = true;
    }

    if (!$acesso_autorizado) {
        registrarLog($pdo_intra, 'ACESSO NEGADO', "Tentativa de acessar pasta restrita: $pasta_atual via caminho: $path");
        
        echo "
        <main class='flex-1 flex items-center justify-center bg-slate-100'>
            <div class='text-center p-12 bg-white rounded-3xl shadow-xl max-w-md border border-slate-200'>
                <div class='mb-6'><img src='img/logo.svg' class='h-12 mx-auto'></div>
                <div class='text-red-500 text-5xl mb-4'>🚫</div>
                <h2 class='text-2xl font-bold text-navy-900 mb-2'>Acesso Restrito</h2>
                <p class='text-slate-500 mb-6'>Você não tem permissão para visualizar documentos do setor <b>$pasta_atual</b>.</p>
                <a href='index.php' class='px-6 py-3 bg-corporate-blue text-white rounded-xl font-bold'>Voltar ao Início</a>
            </div>
        </main>";
        include 'includes/footer.php';
        exit;
    }

    if (is_dir($full_path)) {
        $modo_exibicao = 'arquivos';
        $arquivos = array_diff(scandir($full_path), array('.', '..', 'index.md'));
        foreach ($arquivos as $arq) {
            $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
            if ($ext == 'md' || $ext == 'pdf') {
                $dados_tela[] = $arq;
            }
        }
    } else {
        $modo_exibicao = 'leitura';
        registrarLog($pdo_intra, 'Visualizou Documento', "Abriu: $path");
    }
}
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-12 document-content conteudo-sensivel">
    
    <?php if ($modo_exibicao === 'pastas' || $modo_exibicao === 'arquivos'): ?>
        <div class="max-w-6xl mx-auto">
            <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
                <div>
                    <?php if ($modo_exibicao === 'pastas'): ?>
                        <nav class="flex text-sm text-slate-400 font-medium italic mb-2">
                            <span>Documentação</span>
                            <span class="mx-3 text-slate-300">/</span>
                            <span class="text-corporate-blue font-bold truncate">Visão Geral</span>
                        </nav>
                        <h2 class="text-3xl font-black text-navy-900 tracking-tight">Biblioteca de Manuais</h2>
                    <?php else: ?>
                        <nav class="flex text-sm text-slate-400 font-medium italic mb-2">
                            <a href="view.php" class="hover:text-corporate-blue transition-colors">Documentação</a>
                            <span class="mx-3 text-slate-300">/</span>
                            <span class="text-corporate-blue font-bold truncate"><?php echo str_replace('/', ' / ', $path); ?></span>
                        </nav>
                        <h2 class="text-3xl font-black text-navy-900 tracking-tight">
                            Arquivos em <span class="text-corporate-blue"><?php echo explode('/', $path)[0]; ?></span>
                        </h2>
                    <?php endif; ?>
                </div>
                
                <!-- Barra de Pesquisa JS -->
                <input type="text" id="searchInput" placeholder="<?= $modo_exibicao === 'pastas' ? 'Pesquisar setores...' : 'Pesquisar manuais...' ?>" 
                       class="px-5 py-3 rounded-xl border border-slate-200 text-sm w-full md:w-72 focus:ring-2 focus:ring-corporate-blue outline-none shadow-sm">
            </div>

            <div id="gridDocs" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php if (empty($dados_tela)): ?>
                    <div class="col-span-full text-center py-20 bg-white/50 rounded-3xl border-2 border-dashed border-slate-200">
                        <p class="text-slate-400 font-medium italic">Nenhum registro encontrado nesta área.</p>
                    </div>
                <?php else: ?>

                    <?php if ($modo_exibicao === 'pastas'): ?>
                        <!-- RENDERIZA AS PASTAS -->
                        <?php foreach ($dados_tela as $pasta): ?>
                            <a href="view.php?path=<?= urlencode($pasta['nome']) ?>" 
                               class="doc-card group relative bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-corporate-blue transition-all transform hover:-translate-y-1 flex flex-col items-center justify-center text-center min-h-[180px]">
                                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4 transition-colors bg-blue-50 text-corporate-blue group-hover:bg-corporate-blue group-hover:text-white">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                                </div>
                                <h3 class="font-black text-navy-900 mb-1 group-hover:text-corporate-blue uppercase tracking-widest text-sm"><?= htmlspecialchars($pasta['nome']) ?></h3>
                                <p class="text-[10px] font-black text-slate-400 uppercase bg-slate-50 px-3 py-1 rounded-full mt-2"><?= $pasta['qtd'] ?> Arquivo(s)</p>
                            </a>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <!-- RENDERIZA OS ARQUIVOS (.MD E .PDF) -->
                        <?php foreach ($dados_tela as $arq): 
                            $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
                            $nome_limpo = str_replace(['.md', '.pdf'], '', $arq);
                            $link_path = trim($path, '/') . '/' . $arq;
                            $link = "view.php?path=" . urlencode($link_path);
                            
                            $cor_fundo = $ext == 'pdf' ? 'bg-red-50 text-red-600 group-hover:bg-red-600' : 'bg-blue-50 text-corporate-blue group-hover:bg-corporate-blue';
                            $icone = $ext == 'pdf' 
                                ? '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>'
                                : '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
                            
                            $tag = $ext == 'pdf' ? '<span class="absolute top-4 right-4 text-[9px] font-black bg-red-100 text-red-600 px-2 py-1 rounded uppercase tracking-widest">PDF</span>' : '<span class="absolute top-4 right-4 text-[9px] font-black bg-blue-100 text-corporate-blue px-2 py-1 rounded uppercase tracking-widest">WIKI</span>';
                        ?>
                            <a href='<?= $link ?>' class='doc-card group relative bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-slate-300 transition-all transform hover:-translate-y-1 flex flex-col'>
                                <?= $tag ?>
                                <div class='w-12 h-12 rounded-xl flex items-center justify-center mb-4 transition-colors <?= $cor_fundo ?> group-hover:text-white'>
                                    <?= $icone ?>
                                </div>
                                <h3 class='font-bold text-navy-900 mb-2 group-hover:text-corporate-blue'><?= htmlspecialchars($nome_limpo) ?></h3>
                                <p class='text-xs text-slate-400 mt-auto flex items-center gap-1'>Clique para leitura</p>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($modo_exibicao === 'leitura'): ?>
        <!-- ==========================================
             MODO LEITURA (PDF OU MARKDOWN)
             ========================================== -->
        <?php
            $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
            $is_pdf = ($ext == 'pdf');
            $conteudo_html = '';

            if ($ext == 'md') {
                $raw = file_get_contents($full_path);
                $clean = preg_replace('/^---[\s\S]*?---/', '', $raw); 
                $html = $parsedown->text($clean);
                $html = preg_replace('/src=["\']\/?img\/(.*?)["\']/', 'src="' . BASE_URL . 'img/$1"', $html);
                $conteudo_html = str_replace(
                    [':::info', ':::danger', ':::'], 
                    ['<div class="alert alert-info">', '<div class="alert alert-danger">', '</div>'], 
                    $html
                );
            } elseif ($ext == 'pdf') {
                $url_pdf = 'docs/' . implode('/', array_map('rawurlencode', explode('/', $path)));
                $conteudo_html = '<div class="w-full h-[85vh] rounded-2xl overflow-hidden border border-slate-200 bg-slate-800 flex items-center justify-center shadow-inner"><iframe src="' . $url_pdf . '#toolbar=0" class="w-full h-full bg-slate-100" frameborder="0"></iframe></div>';
            } else {
                $conteudo_html = "<div class='alert alert-danger'><h3>⚠️ Formato não suportado</h3></div>";
            }
        ?>
        <div class="max-w-[95%] 2xl:max-w-[1600px] mx-auto bg-white shadow-sm border border-slate-200 rounded-3xl min-h-[80vh] flex flex-col overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <nav class="flex text-sm text-slate-400 font-medium italic">
                    <a href="view.php" class="hover:text-corporate-blue transition-colors">Documentação</a>
                    <span class="mx-3 text-slate-300">/</span>
                    <span class="text-corporate-blue font-bold truncate max-w-lg"><?php echo str_replace('/', ' / ', $path); ?></span>
                </nav>
                <?php if($is_pdf): ?>
                    <span class="bg-red-100 text-red-600 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border border-red-200">Visão de PDF</span>
                <?php endif; ?>
            </div>

            <div class="<?php echo $is_pdf ? 'p-4 bg-slate-50' : 'p-8 md:p-16 prose prose-slate prose-headings:text-navy-900 prose-headings:font-black prose-a:text-corporate-blue max-w-none'; ?> flex-1">
                <?php echo $conteudo_html; ?>
            </div>

            <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 mt-auto">
                <p class="text-xs text-slate-400 text-center font-medium uppercase tracking-widest">
                    🛡️ Documento Gerenciado pela NAVE • Acesso Restrito
                </p>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
// Filtro de busca unificado para Pastas e Arquivos
const searchInput = document.getElementById('searchInput');
if(searchInput) {
    searchInput.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.doc-card').forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            card.style.display = title.includes(term) ? 'flex' : 'none';
        });
    });
}
</script>

<style>
    /* Reset e melhoria de leitura para a documentação Markdown */
    .prose p { margin-bottom: 1.5rem; line-height: 1.8; color: #475569; font-size: 1.05rem; }
    .prose li { margin-bottom: 0.5rem; line-height: 1.6; color: #475569; }
    .prose ul { list-style-type: disc; margin-left: 1.5rem; margin-bottom: 1.5rem; }
    .prose ol { list-style-type: decimal; margin-left: 1.5rem; margin-bottom: 1.5rem; }
    .prose img { margin: 2.5rem auto; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-width: 100%; display: block; border: 1px solid #f1f5f9; }
    .prose h1 { margin-top: 2.5rem; margin-bottom: 1rem; font-size: 2rem; color: #0f172a; }
    .prose h2 { margin-top: 2rem; margin-bottom: 1rem; font-size: 1.5rem; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; }
    .prose pre { background: #0f172a; color: #f8fafc; padding: 1rem; border-radius: 0.75rem; overflow-x: auto; margin-bottom: 1.5rem; }
    .prose code { font-family: 'Fira Code', monospace; font-size: 0.9em; background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 0.25rem; color: #2563eb; font-weight: bold; }
    .prose pre code { background: transparent; color: inherit; padding: 0; font-weight: normal; }
</style>

<?php include 'includes/footer.php'; ?>