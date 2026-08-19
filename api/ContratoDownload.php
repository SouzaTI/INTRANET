<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/ContratoAuth.php';
require_once __DIR__ . '/ContratoStorage.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$contratoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($usuarioId <= 0 || !$contratoId) {
    http_response_code(401);
    exit('Acesso não autorizado.');
}

try {
    $auth = new ContratoAuth($pdo_intra, $usuarioId, $admin);
    $auth->exigir('baixar_anexo');
    $auth->exigirAcessoContrato((int) $contratoId);

    $stmt = $pdo_intra->prepare('SELECT arquivo_path FROM contratos WHERE id = ? LIMIT 1');
    $stmt->execute([$contratoId]);
    $arquivoRelativo = (string) $stmt->fetchColumn();

    $arquivo = contratosResolverArquivo($arquivoRelativo);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="contrato-' . (int) $contratoId . '.pdf"');
    header('Content-Length: ' . filesize($arquivo));
    header('X-Content-Type-Options: nosniff');
    readfile($arquivo);
} catch (RuntimeException $e) {
    http_response_code(in_array($e->getCode(), [401, 403, 404], true) ? $e->getCode() : 403);
    exit($e->getMessage());
}
