<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/DocumentoCentralAuth.php';
require_once dirname(__DIR__) . '/includes/DocumentoCentralStorage.php';
require_once dirname(__DIR__) . '/includes/DocumentoMarkdownRenderer.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$documentoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$caminhoSolicitado = documentoMarkdownNormalizarMidia((string) ($_GET['arquivo'] ?? ''));
if ($usuarioId <= 0 || !$documentoId || $caminhoSolicitado === null) {
    http_response_code(400);
    exit('Imagem inválida.');
}

try {
    $auth = new DocumentoCentralAuth(
        $pdo_intra,
        $usuarioId,
        isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true,
        $_SESSION
    );
    $stmt = $pdo_intra->prepare('SELECT * FROM documentos_central WHERE id=?');
    $stmt->execute([$documentoId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$documento) throw new RuntimeException('Documento não encontrado.', 404);
    $auth->exigirVer($documento);

    $versaoId = (int) ($documento['status'] === 'PUBLICADO'
        ? $documento['versao_publicada_id']
        : $documento['versao_atual_id']);
    $stmtVersao = $pdo_intra->prepare('SELECT arquivo_path, nome_original FROM documentos_versoes WHERE id=? AND documento_id=?');
    $stmtVersao->execute([$versaoId, $documentoId]);
    $versao = $stmtVersao->fetch(PDO::FETCH_ASSOC);
    if (!$versao || strtolower(pathinfo((string) $versao['nome_original'], PATHINFO_EXTENSION)) !== 'md') {
        throw new RuntimeException('Origem Markdown não encontrada.', 404);
    }
    $markdown = file_get_contents(documentoCentralResolverArquivo((string) $versao['arquivo_path']));
    if ($markdown === false || !in_array($caminhoSolicitado, documentoMarkdownExtrairMidias($markdown), true)) {
        throw new RuntimeException('A imagem não pertence a este documento.', 403);
    }

    $base = realpath(dirname(__DIR__) . '/img');
    $arquivo = realpath(dirname(__DIR__) . '/' . $caminhoSolicitado);
    if (!$base || !$arquivo || !is_file($arquivo)
        || strncasecmp($arquivo, rtrim($base, '\\/') . DIRECTORY_SEPARATOR, strlen(rtrim($base, '\\/') . DIRECTORY_SEPARATOR)) !== 0) {
        throw new RuntimeException('Imagem não encontrada.', 404);
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($arquivo);
    if (!str_starts_with($mime, 'image/')) throw new RuntimeException('Formato de mídia inválido.', 415);

    $etag = '"' . hash_file('sha256', $arquivo) . '"';
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($arquivo));
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($arquivo)) . '"');
    header('Cache-Control: private, max-age=3600');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    readfile($arquivo);
} catch (RuntimeException $e) {
    http_response_code(in_array($e->getCode(), [403, 404, 415], true) ? $e->getCode() : 400);
    exit($e->getMessage());
}
