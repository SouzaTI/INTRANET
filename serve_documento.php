<?php
require_once 'config.php';

// BUG 1 FIX: evita conflito quando a sessão já foi iniciada pelo config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Acesso negado: Usuário não autenticado.");
}

// ── Modo Assinaturas: serve PDF por path relativo ─────────────────────────
if (isset($_GET['path'])) {
    $path_raw     = $_GET['path'] ?? '';
    // Sanitiza: remove traversal e permite apenas o diretório autorizado
    $path_real    = realpath(__DIR__ . '/' . $path_raw);
    $dir_permitido = realpath(__DIR__ . '/uploads/assinaturas/');

    if (!$path_real || !$dir_permitido || strncmp($path_real, $dir_permitido, strlen($dir_permitido)) !== 0) {
        http_response_code(403);
        die("Acesso negado: caminho inválido.");
    }
    if (!file_exists($path_real)) {
        http_response_code(404);
        die("Arquivo não encontrado.");
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($path_real) . '"');
    header('X-Content-Type-Options: nosniff');
    if (ob_get_length()) ob_clean();
    flush();
    readfile($path_real);
    exit;
}

// ── Modo original: serve por ID (docs_fluxo_simples) ─────────────────────
$doc_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$doc_id) die("Documento inválido.");

$stmt = $pdo_intra->prepare("SELECT * FROM docs_fluxo_simples WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doc) die("Arquivo não encontrado.");

$stmt_ti = $pdo_intra->prepare("
    SELECT COUNT(*) FROM usuarios_grupos ug
    JOIN grupos_intranet g ON ug.grupo_id = g.id
    WHERE ug.usuario_id = ? AND g.nome = 'FACILITIES & T.I'
");
$stmt_ti->execute([$_SESSION['user_id']]);
$is_ti   = ($stmt_ti->fetchColumn() > 0);
$is_admin = ($_SESSION['is_admin'] ?? false);
$is_dono  = ($doc['usuario_id'] == $_SESSION['user_id']);
$eh_aprovado      = ($doc['status'] === 'Aprovado');
$setor_doc        = strtoupper($doc['setor_origem']);
$pertence_ao_setor = (
    $setor_doc === ($_SESSION['setor_principal'] ?? '') ||
    in_array($setor_doc, $_SESSION['pastas_extras'] ?? [])
);
$pode_acessar = ($is_admin || $is_ti || $is_dono || ($eh_aprovado && $pertence_ao_setor));

if (!$pode_acessar) {
    http_response_code(403);
    die("Acesso negado: Você não possui permissão para este recurso.");
}

$caminho_arquivo = __DIR__ . '/uploads_fluxo/' . $doc['nome_arquivo'];
if (!file_exists($caminho_arquivo)) die("Erro: Arquivo não localizado.");

$extensao    = strtolower(pathinfo($caminho_arquivo, PATHINFO_EXTENSION));
$mime        = $extensao === 'pdf' ? 'application/pdf' : mime_content_type($caminho_arquivo);
$pode_baixar = ($is_admin || $is_dono);
$modo        = $_GET['modo'] ?? 'visualizar';
$nome_limpo  = preg_replace('/[^A-Za-z0-9\-]/', '_', $doc['titulo'] ?? 'Documento_Oficial');
$nome_dl     = $nome_limpo . '_V' . ($doc['versao_atual'] ?? '1') . '.' . $extensao;

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($modo === 'baixar' && $pode_baixar ? 'attachment' : 'inline') . '; filename="' . $nome_dl . '"');
header('X-Content-Type-Options: nosniff');
if (ob_get_length()) ob_clean();
flush();
readfile($caminho_arquivo);
exit;