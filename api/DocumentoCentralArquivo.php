<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/DocumentoCentralAuth.php';
require_once dirname(__DIR__) . '/includes/DocumentoCentralStorage.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$documentoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$versaoId = filter_input(INPUT_GET, 'versao_id', FILTER_VALIDATE_INT);
if ($usuarioId <= 0 || !$documentoId) {
    http_response_code(401);
    exit('Acesso não autorizado.');
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
    if (!$documento) {
        throw new RuntimeException('Documento não encontrado.', 404);
    }
    $auth->exigirVer($documento);
    if ($versaoId
        && !$auth->isValidador()
        && (int) $documento['criador_id'] !== $usuarioId) {
        throw new RuntimeException('O histórico de versões é restrito ao autor e aos validadores.', 403);
    }

    $usarAssinado = !$versaoId
        && $documento['status'] === 'PUBLICADO'
        && !empty($documento['assinatura_envelope_id']);

    if ($usarAssinado) {
        $stmtArquivo = $pdo_intra->prepare(
            'SELECT arquivo_atual_path AS arquivo_path, nome_original
               FROM assinatura_documentos
              WHERE envelope_id=?
              ORDER BY id LIMIT 1'
        );
        $stmtArquivo->execute([(int) $documento['assinatura_envelope_id']]);
        $versao = $stmtArquivo->fetch(PDO::FETCH_ASSOC);
        if (!$versao) {
            throw new RuntimeException('Documento assinado não encontrado.', 404);
        }
        $versao['mime_type'] = 'application/pdf';
    } else {
        $idEscolhido = $versaoId ?: (int) (
            $documento['status'] === 'PUBLICADO'
                ? $documento['versao_publicada_id']
                : $documento['versao_atual_id']
        );
        $stmtArquivo = $pdo_intra->prepare('SELECT * FROM documentos_versoes WHERE id=? AND documento_id=?');
        $stmtArquivo->execute([$idEscolhido, $documentoId]);
        $versao = $stmtArquivo->fetch(PDO::FETCH_ASSOC);
        if (!$versao) {
            throw new RuntimeException('Versão não encontrada.', 404);
        }
    }

    $arquivo = documentoCentralResolverArquivo((string) $versao['arquivo_path']);
    $mime = (string) ($versao['mime_type'] ?? 'application/octet-stream');
    $nome = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string) $versao['nome_original'])) ?: 'documento';
    $modo = ($_GET['modo'] ?? 'visualizar') === 'baixar' ? 'attachment' : 'inline';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $modo . '; filename="' . $nome . '"');
    header('Content-Length: ' . filesize($arquivo));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    if (ob_get_length()) {
        ob_clean();
    }
    readfile($arquivo);
} catch (RuntimeException $e) {
    $codigo = in_array($e->getCode(), [401, 403, 404], true) ? $e->getCode() : 400;
    http_response_code($codigo);
    exit($e->getMessage());
}
