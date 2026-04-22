<?php 
require_once 'config.php';
require_once ROOT_PATH . 'Parsedown.php';
$parsedown = new Parsedown();

include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// 1. Primeiro pegamos o caminho do arquivo
$path = isset($_GET['path']) ? urldecode($_GET['path']) : '';
$path = str_replace(['\\', '//'], '/', $path);

// 2. Identificamos a pasta raiz para validar permissão
$partes_caminho = explode('/', $path);
$pasta_atual = strtoupper($partes_caminho[0]);

$acesso_autorizado = false;

// 3. Verificação de Acesso (Hierarquia de Segurança)
if ($_SESSION['is_admin']) {
    $acesso_autorizado = true;
} elseif ($pasta_atual == $_SESSION['setor_principal']) {
    $acesso_autorizado = true;
} elseif (isset($_SESSION['pastas_extras']) && in_array($pasta_atual, $_SESSION['pastas_extras'])) {
    $acesso_autorizado = true;
}

// 4. Tratamento de Acesso Negado
if (!$acesso_autorizado) {
    // Usando a nova função global de log!
    registrarLog($pdo_intra, 'ACESSO NEGADO', "Tentativa de acessar pasta restrita: $pasta_atual via caminho: $path");
    
    // Exibimos aquela tela bonita de erro (reutilizando o estilo do header)
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

// 5. Se chegou aqui, o acesso é autorizado. Grava log de visualização.
registrarLog($pdo_intra, 'Visualizou Documento', "Abriu: $path");

// 6. Carregamento Dinâmico: Pasta (Cards) ou Arquivo (Leitura)
$file = ROOT_PATH . 'docs/' . $path;
$conteudo_html = "";

// IMPORTANTE: Verificamos se é diretório ANTES de qualquer coisa
$is_directory = is_dir($file);

if ($is_directory) {
    // LISTAGEM DE CARDS (Se for pasta)
    $arquivos = array_diff(scandir($file), array('.', '..', 'index.md'));
    $conteudo_html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
    
    if (empty($arquivos)) {
        $conteudo_html .= '<div class="col-span-full text-center py-20 bg-white/50 rounded-3xl border-2 border-dashed border-slate-200"><p class="text-slate-400 font-medium italic">Esta pasta ainda não possui manuais cadastrados.</p></div>';
    } else {
        foreach ($arquivos as $arq) {
            if (pathinfo($arq, PATHINFO_EXTENSION) == 'md') {
                $nome_limpo = str_replace('.md', '', $arq);
                // Ajuste no link para garantir que o caminho fique correto
                $link_path = trim($path, '/') . '/' . $arq;
                $link = "view.php?path=" . urlencode($link_path);
                
                $conteudo_html .= "
                <a href='$link' class='group bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-corporate-blue transition-all transform hover:-translate-y-1 flex flex-col'>
                    <div class='w-12 h-12 bg-blue-50 text-corporate-blue rounded-xl flex items-center justify-center mb-4 group-hover:bg-corporate-blue group-hover:text-white transition-colors'>
                        <svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>
                    </div>
                    <h3 class='font-bold text-navy-900 mb-2 group-hover:text-corporate-blue'>$nome_limpo</h3>
                    <p class='text-xs text-slate-400 mt-auto flex items-center gap-1'>Clique para visualizar</p>
                </a>";
            }
        }
    }
    $conteudo_html .= '</div>';

} elseif (file_exists($file) && !is_dir($file)) {
    // LEITURA DO MANUAL (Se for arquivo .md)
    $raw = file_get_contents($file);
    
    // Remove o cabeçalho YAML se existir
    $clean = preg_replace('/^---[\s\S]*?---/', '', $raw); 
    
    // Renderiza o Markdown
    $html = $parsedown->text($clean);
    
    // Ajuste de caminhos de imagem e alertas
    $html = preg_replace('/src=["\']\/?img\/(.*?)["\']/', 'src="' . BASE_URL . 'img/$1"', $html);
    $conteudo_html = str_replace(
        [':::info', ':::danger', ':::'], 
        ['<div class="alert alert-info">', '<div class="alert alert-danger">', '</div>'], 
        $html
    );
} else {
    // Caso o arquivo não exista de verdade
    $conteudo_html = "<div class='alert alert-danger'><h3>⚠️ Arquivo não encontrado</h3><p>O caminho $path não é válido.</p></div>";
}

?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-12 document-content conteudo-sensivel">
    
    <?php if ($is_directory): ?>
        <div class="max-w-6xl mx-auto">
            <div class="mb-8">
                <nav class="flex text-sm text-slate-400 font-medium italic mb-2">
                    <span>Documentação</span>
                    <span class="mx-3 text-slate-300">/</span>
                    <span class="text-corporate-blue font-bold"><?php echo str_replace('/', ' / ', $path); ?></span>
                </nav>
                <h2 class="text-3xl font-black text-navy-900 tracking-tight">
                    Documentos em <span class="text-corporate-blue"><?php echo $pasta_atual; ?></span>
                </h2>
            </div>

            <?php echo $conteudo_html; ?>
        </div>

    <?php else: ?>
        <div class="max-w-5xl mx-auto bg-white shadow-sm border border-slate-200 rounded-3xl min-h-[80vh] flex flex-col overflow-hidden">
            
            <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                <nav class="flex text-sm text-slate-400 font-medium italic">
                    <span>Documentação</span>
                    <span class="mx-3 text-slate-300">/</span>
                    <span class="text-corporate-blue font-bold"><?php echo str_replace('/', ' / ', $path); ?></span>
                </nav>
            </div>

            <div class="p-8 md:p-16 prose prose-slate prose-headings:text-navy-900 prose-headings:font-black prose-a:text-corporate-blue max-w-none flex-1">
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

<style>
    /* Reset e melhoria de leitura para a documentação (Substitui o plugin Typography) */
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