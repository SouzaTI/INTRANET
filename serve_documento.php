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
$eh_aprovado = ($doc['status'] === 'Aprovado');

// 3. Regras de Permissão
// Admins, T.I e Donos (para docs pendentes) têm acesso total
$pode_acessar = ($is_admin || $is_ti || $is_dono || $eh_aprovado);

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

// 2. FORÇAR EXIBIÇÃO (INLINE)
// Se não for download explícito, forçamos o "inline" para abrir na aba
$modo = $_GET['modo'] ?? 'visualizar';

if ($modo === 'baixar' && $pode_baixar) {
    // Força o download com o nome legível
    $nome_download = ($doc['titulo'] ?? 'documento_oficial') . '.' . $extensao;
    header('Content-Disposition: attachment; filename="' . $nome_download . '"');
} else {
    // Exibe no visualizador do navegador (a parte crucial para o seu caso)
    header('Content-Disposition: inline; filename="' . basename($doc['nome_arquivo']) . '"');
}

// 3. Segurança adicional para impedir download automático
header('X-Content-Type-Options: nosniff');

readfile($caminho_arquivo);
exit;