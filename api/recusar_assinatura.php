<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/DocumentoCentralAssinatura.php';
header('Content-Type: application/json; charset=utf-8');

function sair(bool $ok, string $msg, int $http = 200): never {
    http_response_code($http);
    echo json_encode(compact('ok', 'msg'), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])) sair(false, 'Acesso negado.', 403);

$envelopeId = (int) ($_POST['envelope_id'] ?? 0);
$motivo = trim((string) ($_POST['justificativa'] ?? ''));
$uid = (int) $_SESSION['user_id'];
if ($envelopeId <= 0 || mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) sair(false, 'Informe uma justificativa entre 5 e 500 caracteres.', 422);

$pdo_intra->beginTransaction();
try {
    $stmt = $pdo_intra->prepare("SELECT af.id FROM assinaturas_fluxo af
        JOIN sistemas_assinaturas sa ON sa.id = af.fk_assinatura
        WHERE af.fk_assinatura = ? AND af.glpi_user_id = ? AND af.status = 'pendente'
          AND sa.status NOT IN ('concluido','cancelado') FOR UPDATE");
    $stmt->execute([$envelopeId, $uid]);
    $fluxoId = $stmt->fetchColumn();
    if (!$fluxoId) throw new DomainException('Este envelope não está pendente para você.');

    $pdo_intra->prepare("UPDATE assinaturas_fluxo SET status = 'recusado', justificativa_recusa = ?, assinado_em = NOW() WHERE id = ?")
        ->execute([$motivo, $fluxoId]);
    $pdo_intra->prepare("UPDATE assinaturas_fluxo SET status = 'recusado', justificativa_recusa = 'Fluxo encerrado por recusa anterior'
        WHERE fk_assinatura = ? AND status IN ('pendente','aguardando')")->execute([$envelopeId]);
    $pdo_intra->prepare("UPDATE sistemas_assinaturas SET status = 'cancelado' WHERE id = ?")->execute([$envelopeId]);
    $pdo_intra->prepare("INSERT INTO assinatura_eventos
        (envelope_id, fluxo_id, glpi_user_id, evento, descricao, ip_origem, user_agent)
        VALUES (?, ?, ?, 'RECUSADO', ?, ?, ?)")
        ->execute([$envelopeId, $fluxoId, $uid, $motivo, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    documentoCentralInterromperAssinatura(
        $pdo_intra,
        $envelopeId,
        $uid,
        'Assinatura recusada: ' . $motivo
    );
    $pdo_intra->commit();
    sair(true, 'Documento recusado e fluxo encerrado.');
} catch (DomainException $e) {
    if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
    sair(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
    error_log('Assinaturas/recusar: ' . $e->getMessage());
    sair(false, 'Não foi possível registrar a recusa.', 500);
}
