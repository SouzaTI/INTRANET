<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/services/CarimbadorService.php';
require_once dirname(__DIR__) . '/services/EmailAssinaturaService.php';

header('Content-Type: application/json; charset=utf-8');

const MAX_TENTATIVAS_SENHA = 3;
const BLOQUEIO_SENHA_SEGUNDOS = 300;

function resposta(bool $ok, string $msg, int $http = 200, array $extra = []): never
{
    http_response_code($http);
    echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])) {
    resposta(false, 'Acesso negado.', 403);
}

$envelopeId = (int) ($_POST['envelope_id'] ?? 0);
$senhaIntranet = (string) ($_POST['senha_intranet'] ?? '');
$usuarioId = (int) $_SESSION['user_id'];
$ip = trim(explode(',', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')[0]);
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

if ($envelopeId <= 0) resposta(false, 'Envelope inválido.', 422);
if ($senhaIntranet === '' || strlen($senhaIntranet) > 4096) {
    resposta(false, 'Informe sua senha atual da intranet.', 422);
}

$arquivosCriados = [];
$concluido = false;
$proximoUsuarioId = null;
$pdo_intra->beginTransaction();

try {
    $stmtEnv = $pdo_intra->prepare("SELECT * FROM sistemas_assinaturas WHERE id = ? FOR UPDATE");
    $stmtEnv->execute([$envelopeId]);
    $envelope = $stmtEnv->fetch(PDO::FETCH_ASSOC);
    if (!$envelope || in_array($envelope['status'], ['concluido', 'cancelado'], true)) {
        throw new DomainException('Envelope indisponível para assinatura.');
    }

    $stmtFluxo = $pdo_intra->prepare("SELECT * FROM assinaturas_fluxo
        WHERE fk_assinatura = ? AND glpi_user_id = ? AND status = 'pendente' FOR UPDATE");
    $stmtFluxo->execute([$envelopeId, $usuarioId]);
    $fluxo = $stmtFluxo->fetch(PDO::FETCH_ASSOC);
    if (!$fluxo) throw new DomainException('O documento não está pendente para você neste momento.');

    $bloqueadoAte = !empty($fluxo['bloqueado_ate'])
        ? strtotime((string) $fluxo['bloqueado_ate'])
        : false;

    if ($bloqueadoAte !== false && $bloqueadoAte > time()) {
        throw new DomainException('Confirmação de senha temporariamente bloqueada. Aguarde 5 minutos.');
    }

    // Ao terminar o bloqueio, inicia um novo ciclo completo de tentativas.
    // O nome da coluna é legado do fluxo antigo por PIN e foi mantido por compatibilidade.
    if ($bloqueadoAte !== false) {
        $pdo_intra->prepare('UPDATE assinaturas_fluxo SET tentativas_pin = 0, bloqueado_ate = NULL WHERE id = ?')
            ->execute([$fluxo['id']]);
        $fluxo['tentativas_pin'] = 0;
        $fluxo['bloqueado_ate'] = null;
    }

    // Reutiliza exatamente a credencial do login da intranet/GLPI.
    // A senha recebida é apenas verificada contra o hash e nunca é armazenada.
    $stmtSenha = $pdo_glpi->prepare('SELECT password FROM glpi_users WHERE id = ? AND is_active = 1 AND is_deleted = 0');
    $stmtSenha->execute([$usuarioId]);
    $senhaHash = $stmtSenha->fetchColumn();
    if (!$senhaHash) throw new DomainException('Não foi possível validar sua conta da intranet.');

    if (!password_verify($senhaIntranet, (string) $senhaHash)) {
        $tentativas = (int) $fluxo['tentativas_pin'] + 1;
        $bloqueio = $tentativas >= MAX_TENTATIVAS_SENHA
            ? date('Y-m-d H:i:s', time() + BLOQUEIO_SENHA_SEGUNDOS)
            : null;
        $pdo_intra->prepare('UPDATE assinaturas_fluxo SET tentativas_pin = ?, bloqueado_ate = ? WHERE id = ?')
            ->execute([$tentativas, $bloqueio, $fluxo['id']]);
        $pdo_intra->commit();
        $senhaIntranet = '';
        resposta(false, $bloqueio ? 'Confirmação bloqueada por 5 minutos após 3 tentativas incorretas.' : 'Senha da intranet incorreta.', 422);
    }

    // Remove a senha da variável assim que a autenticação termina.
    $senhaIntranet = '';

    $stmtNome = $pdo_glpi->prepare("SELECT
        TRIM(CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,''))) AS nome,
        COALESCE(NULLIF(TRIM(l.name), ''), 'Não informado') AS setor,
        COALESCE((
            SELECT ue.email
            FROM glpi_useremails ue
            WHERE ue.users_id = u.id AND ue.email IS NOT NULL AND ue.email <> ''
            ORDER BY ue.is_default DESC, ue.id ASC
            LIMIT 1
        ), 'Não informado') AS email
        FROM glpi_users u
        LEFT JOIN glpi_locations l ON l.id = u.locations_id
        WHERE u.id = ?");
    $stmtNome->execute([$usuarioId]);
    $dadosAssinante = $stmtNome->fetch(PDO::FETCH_ASSOC) ?: [];
    $nome = trim((string) ($dadosAssinante['nome'] ?? '')) ?: ('Usuário #' . $usuarioId);
    $setor = trim((string) ($dadosAssinante['setor'] ?? '')) ?: 'Não informado';
    $emailAssinante = trim((string) ($dadosAssinante['email'] ?? '')) ?: 'Não informado';

    $stmtDocs = $pdo_intra->prepare('SELECT * FROM assinatura_documentos WHERE envelope_id = ? ORDER BY id FOR UPDATE');
    $stmtDocs->execute([$envelopeId]);
    $documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
    if (!$documentos) throw new RuntimeException('Envelope sem documentos cadastrados.');

    $carimbador = new CarimbadorService($_ENV['CARIMBADOR_URL'] ?? 'http://127.0.0.1:5000/api/carimbar');
    $hashesAtuais = [];
    foreach ($documentos as $documento) {
        $entrada = dirname(__DIR__) . '/' . ltrim($documento['arquivo_atual_path'], '/\\');
        if (!is_file($entrada)) throw new RuntimeException('Arquivo não encontrado: ' . $documento['nome_original']);
        if (!hash_equals($documento['hash_atual'], hash_file('sha256', $entrada))) {
            throw new RuntimeException('Integridade inválida: ' . $documento['nome_original']);
        }

        $dir = dirname($entrada);
        $saida = $dir . '/' . pathinfo($entrada, PATHINFO_FILENAME)
            . '_a' . (int) $fluxo['ordem'] . '_' . date('YmdHis') . '.pdf';
        $carimbador->carimbar($entrada, $saida, $nome, $setor, $emailAssinante, (int) $fluxo['ordem'], $ip);
        $arquivosCriados[] = $saida;
        $relativo = str_replace('\\', '/', substr($saida, strlen(dirname(__DIR__)) + 1));
        $novoHash = hash_file('sha256', $saida);
        $hashesAtuais[] = $novoHash;

        $pdo_intra->prepare("UPDATE assinatura_documentos
            SET arquivo_atual_path = ?, hash_atual = ?, status = 'em_assinatura' WHERE id = ?")
            ->execute([$relativo, $novoHash, $documento['id']]);
        $pdo_intra->prepare("INSERT INTO assinatura_eventos
            (envelope_id, documento_id, fluxo_id, glpi_user_id, evento, descricao, ip_origem, user_agent)
            VALUES (?, ?, ?, ?, 'ASSINADO', ?, ?, ?)")
            ->execute([$envelopeId, $documento['id'], $fluxo['id'], $usuarioId, 'Documento assinado por ' . $nome, $ip, $userAgent]);
    }

    $momento = date('Y-m-d H:i:s');
    $lacre = hash('sha256', implode('|', $hashesAtuais) . '|' . $usuarioId . '|' . $momento);
    $pdo_intra->prepare("UPDATE assinaturas_fluxo SET status = 'assinado', lacre_hash = ?,
        ip_assinatura = ?, assinado_em = ?, tentativas_pin = 0, bloqueado_ate = NULL WHERE id = ?")
        ->execute([$lacre, $ip, $momento, $fluxo['id']]);

    if ($envelope['tipo_fluxo'] === 'sequencial') {
        $stmtProx = $pdo_intra->prepare("SELECT id, glpi_user_id FROM assinaturas_fluxo
            WHERE fk_assinatura = ? AND status = 'aguardando' ORDER BY ordem, id LIMIT 1 FOR UPDATE");
        $stmtProx->execute([$envelopeId]);
        $proximo = $stmtProx->fetch(PDO::FETCH_ASSOC);
        if ($proximo) {
            $proximoId = (int)$proximo['id'];
            $proximoUsuarioId = (int)$proximo['glpi_user_id'];
            $pdo_intra->prepare("UPDATE assinaturas_fluxo SET status = 'pendente' WHERE id = ?")
                ->execute([$proximoId]);
        }
    }

    $stmtRestantes = $pdo_intra->prepare("SELECT COUNT(*) FROM assinaturas_fluxo
        WHERE fk_assinatura = ? AND status IN ('pendente','aguardando')");
    $stmtRestantes->execute([$envelopeId]);
    $concluido = (int) $stmtRestantes->fetchColumn() === 0;

    if ($concluido) {
        $pdo_intra->prepare("UPDATE sistemas_assinaturas SET status = 'concluido', concluido_em = NOW() WHERE id = ?")
            ->execute([$envelopeId]);
        $pdo_intra->prepare("UPDATE assinatura_documentos SET status = 'concluido' WHERE envelope_id = ?")
            ->execute([$envelopeId]);
        $pdo_intra->prepare("INSERT INTO assinatura_eventos
            (envelope_id, glpi_user_id, evento, descricao, ip_origem, user_agent)
            VALUES (?, ?, 'CONCLUIDO', 'Fluxo concluído.', ?, ?)")
            ->execute([$envelopeId, $usuarioId, $ip, $userAgent]);
    }

    $pdo_intra->commit();
} catch (DomainException $e) {
    if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
    foreach ($arquivosCriados as $arquivo) if (is_file($arquivo)) @unlink($arquivo);
    resposta(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
    foreach ($arquivosCriados as $arquivo) if (is_file($arquivo)) @unlink($arquivo);
    error_log('Assinaturas/processar: ' . $e->getMessage());
    resposta(false, 'Não foi possível concluir a assinatura.', 500);
}

$falhasEmail = [];
$email = new EmailAssinaturaService();
$notificacoes = [
    ['codigo' => 'DOCUMENTO_ASSINADO', 'usuario' => $usuarioId,
     'acao' => static fn() => $email->enviarDocumentoAssinado($pdo_intra, $pdo_glpi, $envelopeId, $usuarioId)],
];
if ($proximoUsuarioId) {
    $notificacoes[] = ['codigo' => 'NOVO_PENDENTE', 'usuario' => $proximoUsuarioId,
        'acao' => static fn() => $email->enviarNovoPendente($pdo_intra, $pdo_glpi, $envelopeId, $proximoUsuarioId)];
}
if ($concluido) {
    $notificacoes[] = ['codigo' => 'FLUXO_CONCLUIDO', 'usuario' => $usuarioId,
        'acao' => static fn() => $email->enviarEnvelope($pdo_intra, $pdo_glpi, $envelopeId)];
}
foreach ($notificacoes as $notificacao) {
    try {
        $notificacao['acao']();
    } catch (Throwable $e) {
        $falhasEmail[] = $notificacao['codigo'];
        error_log('Assinaturas/email/' . $notificacao['codigo'] . ': ' . $e->getMessage());
        $pdo_intra->prepare("INSERT INTO assinatura_eventos
            (envelope_id, glpi_user_id, evento, descricao, ip_origem) VALUES (?, ?, 'ERRO', ?, ?)")
            ->execute([$envelopeId, $notificacao['usuario'], 'E-mail ' . $notificacao['codigo'] . ' falhou: ' . mb_substr($e->getMessage(), 0, 400), $ip]);
    }
}
$emailAviso = $falhasEmail ? 'Assinatura registrada, mas uma ou mais notificações por e-mail falharam.' : null;

resposta(true, $emailAviso ?: ($concluido ? 'Assinatura registrada e fluxo concluído.' : 'Assinatura registrada. O fluxo avançou.'), 200, [
    'concluido' => $concluido,
    'email_ok' => !$falhasEmail,
    'falhas_email' => $falhasEmail,
]);
