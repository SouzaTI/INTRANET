<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/ContratoAuth.php';
require_once __DIR__ . '/ContratoStorage.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$contratoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$anexoId = filter_input(INPUT_GET, 'anexo_id', FILTER_VALIDATE_INT);

if ($usuarioId <= 0 || !$contratoId) {
    http_response_code(401);
    exit('Acesso não autorizado.');
}

try {
    $auth = new ContratoAuth($pdo_intra, $usuarioId, $admin);
    $auth->exigir('baixar_anexo');
    $auth->exigirAcessoContrato((int) $contratoId);

    if ($anexoId) {
        $stmt = $pdo_intra->prepare(
            'SELECT arquivo_path, nome_original, mime_type
               FROM contratos_anexos
              WHERE id = ? AND contrato_id = ?
              LIMIT 1'
        );
        $stmt->execute([$anexoId, $contratoId]);
        $dadosArquivo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dadosArquivo) {
            throw new RuntimeException('Anexo não encontrado.', 404);
        }
        $arquivoRelativo = (string) $dadosArquivo['arquivo_path'];
        $nomeDownload = (string) $dadosArquivo['nome_original'];
        $mimeType = (string) $dadosArquivo['mime_type'];
    } else {
        // Compatibilidade com contratos criados antes da tabela de anexos.
        $stmt = $pdo_intra->prepare('SELECT arquivo_path FROM contratos WHERE id = ? LIMIT 1');
        $stmt->execute([$contratoId]);
        $arquivoRelativo = (string) $stmt->fetchColumn();
        $nomeDownload = 'contrato-' . (int) $contratoId . '.pdf';
        $mimeType = 'application/pdf';
    }

    $arquivo = contratosResolverArquivo($arquivoRelativo);
    $nomeDownload = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($nomeDownload)) ?: 'contrato.pdf';

    header('Content-Type: ' . ($mimeType === 'application/pdf' ? $mimeType : 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
    header('Content-Length: ' . filesize($arquivo));
    header('X-Content-Type-Options: nosniff');
    readfile($arquivo);
} catch (RuntimeException $e) {
    http_response_code(in_array($e->getCode(), [401, 403, 404], true) ? $e->getCode() : 403);
    exit($e->getMessage());
}
