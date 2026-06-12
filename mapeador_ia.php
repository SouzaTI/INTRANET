<?php
// mapeador_ia.php
// Script para gerar contexto completo do sistema para IAs

// 1. Configurações
$diretorio_raiz = __DIR__;
$extensoes_permitidas = ['php', 'js', 'css', 'sql', 'html'];
$pastas_ignoradas = ['.git', 'vendor', 'node_modules', 'uploads_fluxo', 'docs', 'img', 'assets'];
$arquivo_saida = 'contexto_intranet.md';

$relatorio = "# 🧠 Relatório de Contexto do Sistema (Para IA)\n";
$relatorio .= "Gerado em: " . date('d/m/Y H:i:s') . "\n\n";
$relatorio .= "Este documento contém a estrutura de pastas e o código-fonte de todos os arquivos cruciais do sistema para análise estrutural, segurança e refatoração.\n\n";

$relatorio .= "---\n\n";
$relatorio .= "## 📂 Estrutura de Diretórios\n```text\n";

// 2. Função para varrer pastas e arquivos
$arquivos_processados = [];

$iterador = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($diretorio_raiz, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

// Mapeia a estrutura em texto
foreach ($iterador as $item) {
    $caminho_relativo = str_replace($diretorio_raiz . DIRECTORY_SEPARATOR, '', $item->getPathname());
    
    // Ignora pastas configuradas
    $ignorar = false;
    foreach ($pastas_ignoradas as $pasta_ign) {
        if (strpos($caminho_relativo, $pasta_ign) === 0) {
            $ignorar = true;
            break;
        }
    }
    if ($ignorar) continue;

    $profundidade = $iterador->getDepth();
    $espaco = str_repeat("  ", $profundidade);
    
    if ($item->isDir()) {
        $relatorio .= $espaco . "📁 " . $item->getBasename() . "/\n";
    } else {
        $ext = strtolower(pathinfo($item->getBasename(), PATHINFO_EXTENSION));
        if (in_array($ext, $extensoes_permitidas)) {
            $relatorio .= $espaco . "📄 " . $item->getBasename() . "\n";
            $arquivos_processados[] = $item->getPathname();
        }
    }
}
$relatorio .= "```\n\n---\n\n";
$relatorio .= "## 💻 Código-Fonte dos Arquivos\n\n";

// 3. Adiciona o conteúdo de cada arquivo
foreach ($arquivos_processados as $caminho_absoluto) {
    // Evita o próprio script de mapeamento
    if (basename($caminho_absoluto) === 'mapeador_ia.php') continue;

    $caminho_relativo = str_replace($diretorio_raiz . DIRECTORY_SEPARATOR, '', $caminho_absoluto);
    $conteudo = file_get_contents($caminho_absoluto);
    $ext = strtolower(pathinfo($caminho_absoluto, PATHINFO_EXTENSION));
    
    // Identifica chamadas de banco e includes (básico)
    preg_match_all('/(include|require|include_once|require_once)[\s\(]+[\'"]([^\'"]+)[\'"]/', $conteudo, $matches_includes);
    $includes = !empty($matches_includes[2]) ? implode(', ', $matches_includes[2]) : 'Nenhum';

    $relatorio .= "### 📄 Arquivo: `{$caminho_relativo}`\n";
    $relatorio .= "- **Conexões (Includes):** {$includes}\n";
    $relatorio .= "```{$ext}\n";
    $relatorio .= $conteudo . "\n";
    $relatorio .= "```\n\n";
}

// 4. Força o Download do Arquivo
header('Content-Type: text/markdown');
header('Content-Disposition: attachment; filename="' . $arquivo_saida . '"');
header('Content-Length: ' . strlen($relatorio));

echo $relatorio;
exit;
?>