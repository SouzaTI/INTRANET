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

    if ($modo === 'inline'
        && !empty($_GET['render_markdown'])
        && strtolower(pathinfo((string) $versao['nome_original'], PATHINFO_EXTENSION)) === 'md') {
        require_once dirname(__DIR__) . '/Parsedown.php';
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true);
        $conteudo = file_get_contents($arquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível ler o documento.', 404);
        }
        $titulo = htmlspecialchars(pathinfo((string) $versao['nome_original'], PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8');
        $html = $parsedown->text($conteudo);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:");
        echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $titulo . '</title><style>'
            . 'body{margin:0;background:#f8fafc;color:#1e293b;font:15px/1.7 Inter,Arial,sans-serif}'
            . 'article{max-width:980px;margin:0 auto;padding:36px 42px 70px;background:#fff;min-height:100vh;box-sizing:border-box}'
            . 'h1,h2,h3,h4{color:#0f172a;line-height:1.25;margin:1.5em 0 .6em}h1{font-size:2em;border-bottom:2px solid #e2e8f0;padding-bottom:.35em}'
            . 'h2{font-size:1.5em;border-bottom:1px solid #e2e8f0;padding-bottom:.3em}a{color:#2563eb}p,ul,ol,blockquote,pre,table{margin:1em 0}'
            . 'blockquote{border-left:4px solid #93c5fd;margin-left:0;padding:.5em 1em;background:#eff6ff;color:#475569}'
            . 'code{background:#f1f5f9;border-radius:5px;padding:.15em .35em;font-family:Consolas,monospace}pre{overflow:auto;background:#0f172a;color:#e2e8f0;padding:16px;border-radius:10px}pre code{background:transparent;padding:0}'
            . 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:8px 10px;text-align:left}th{background:#f1f5f9}hr{border:0;border-top:1px solid #cbd5e1}'
            . '@media(max-width:640px){article{padding:24px 20px}}'
            . '</style></head><body><article>' . $html . '</article></body></html>';
        exit;
    }

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
