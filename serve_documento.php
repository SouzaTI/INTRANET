<?php
// serve_documento.php - VERSÃO CORRIGIDA E BLINDADA
require_once 'config.php'; 

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Acesso negado: Usuário não autenticado.");
}

$doc_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$doc_id) die("Documento inválido.");

// 1. Busca dados do documento
$stmt = $pdo_intra->prepare("SELECT * FROM docs_fluxo_simples WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) die("Arquivo não encontrado.");

// 2. VERIFICAÇÃO DE GRUPO (O SEGREDO DA T.I.)
$stmt_ti = $pdo_intra->prepare("
    SELECT COUNT(*) FROM usuarios_grupos ug 
    JOIN grupos_intranet g ON ug.grupo_id = g.id 
    WHERE ug.usuario_id = ? AND g.nome = 'FACILITIES & T.I'
");
$stmt_ti->execute([$_SESSION['user_id']]);
$is_ti = ($stmt_ti->fetchColumn() > 0);

$is_admin = ($_SESSION['is_admin'] ?? false);
$is_dono = ($doc['usuario_id'] == $_SESSION['user_id']);
$pode_baixar = ($is_admin || $is_dono);
$eh_aprovado = ($doc['status'] === 'Aprovado');

// 3. Regras de Permissão
// Admins, T.I e Donos (para docs pendentes) têm acesso total
$setor_doc = strtoupper($doc['setor_origem']);
$pertence_ao_setor = ($setor_doc === $_SESSION['setor_principal'] || in_array($setor_doc, $_SESSION['pastas_extras'] ?? []));

$pode_acessar = ($is_admin || $is_ti || $is_dono || ($eh_aprovado && $pertence_ao_setor));

if (!$pode_acessar) {
    http_response_code(403);
    die("Acesso negado: Você não possui permissão para este recurso.");
}

// 4. Servir arquivo
$caminho_arquivo = __DIR__ . '/uploads_fluxo/' . $doc['nome_arquivo'];
if (!file_exists($caminho_arquivo)) die("Erro: Arquivo não localizado.");

// 1. FORÇAR MIME TYPE PARA PDF (Corrigindo o download forçado)
$extensao = strtolower(pathinfo($caminho_arquivo, PATHINFO_EXTENSION));
if ($extensao === 'pdf') {
    header('Content-Type: application/pdf');
} else {
    // Fallback para outros tipos (docx, png, etc)
    header('Content-Type: ' . mime_content_type($caminho_arquivo));
}

// 2. FORÇAR EXIBIÇÃO OU DOWNLOAD

$modo = $_GET['modo'] ?? 'visualizar';

// Vamos padronizar o nome bonitinho do arquivo para ele não baixar com o nome do Hash
$nome_limpo = preg_replace('/[^A-Za-z0-9\-]/', '_', $doc['titulo'] ?? 'Documento_Oficial');
$nome_download = $nome_limpo . '_V' . ($doc['versao_atual'] ?? '1') . '.' . $extensao;

if ($modo === 'baixar' && $pode_baixar) {
    // Força o download com o nome legível
    header('Content-Disposition: attachment; filename="' . $nome_download . '"');
} else {
    // Exibe no visualizador. Se o navegador não souber abrir (tipo .xlsx), 
    // ele vai baixar usando o nome bonito em vez de basename($doc['nome_arquivo'])
    header('Content-Disposition: inline; filename="' . $nome_download . '"');
}

// 3. Segurança adicional para impedir download automático indevido
header('X-Content-Type-Options: nosniff');

// 🔥 O TRUQUE DE MESTRE CONTRA ARQUIVOS CORROMPIDOS:
// Se tiver algum espaço em branco sobrando de outros arquivos de include (como o config.php),
// o ob_clean() joga no lixo para garantir que só os bytes do arquivo puro vão pro navegador.
if (ob_get_length()) {
    ob_clean();
}
flush();

// Agora sim, entrega o arquivo limpo!
readfile($caminho_arquivo);
exit;