<?php
declare(strict_types=1);

function documentoCentralAssinaturaDisponivel(PDO $pdo): bool
{
    static $disponivel = null;
    if ($disponivel === null) {
        $disponivel = (bool) $pdo->query("SHOW TABLES LIKE 'documentos_central'")->fetchColumn();
    }
    return $disponivel;
}

function documentoCentralConcluirAssinatura(PDO $pdo, int $envelopeId, int $usuarioId): void
{
    if (!documentoCentralAssinaturaDisponivel($pdo)) return;

    $stmt = $pdo->prepare(
        'SELECT id, versao_atual_id, status
           FROM documentos_central
          WHERE assinatura_envelope_id=?
          FOR UPDATE'
    );
    $stmt->execute([$envelopeId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$documento || $documento['status'] !== 'AGUARDANDO_ASSINATURAS') return;

    $pdo->prepare(
        "UPDATE documentos_central
            SET status='PUBLICADO', responsavel_id=NULL, responsavel_tipo='NENHUM',
                versao_publicada_id=versao_atual_id, publicado_em=NOW()
          WHERE id=?"
    )->execute([$documento['id']]);
    $pdo->prepare(
        "UPDATE documentos_tarefas
            SET status='CONCLUIDA', concluido_em=NOW(), concluido_por=?
          WHERE documento_id=? AND status='PENDENTE'"
    )->execute([$usuarioId, $documento['id']]);
    $pdo->prepare(
        "INSERT INTO documentos_eventos
            (documento_id, versao_id, usuario_id, acao, status_anterior, status_novo, mensagem, dados_json)
         VALUES (?, ?, ?, 'ASSINATURAS_CONCLUIDAS', 'AGUARDANDO_ASSINATURAS', 'PUBLICADO', ?, ?)"
    )->execute([
        $documento['id'],
        $documento['versao_atual_id'],
        $usuarioId,
        'Envelope concluído e documento publicado automaticamente.',
        json_encode(['envelope_id' => $envelopeId], JSON_UNESCAPED_UNICODE),
    ]);
}

function documentoCentralInterromperAssinatura(
    PDO $pdo,
    int $envelopeId,
    int $usuarioId,
    string $motivo
): void {
    if (!documentoCentralAssinaturaDisponivel($pdo)) return;

    $stmt = $pdo->prepare(
        'SELECT id, versao_atual_id, status
           FROM documentos_central
          WHERE assinatura_envelope_id=?
          FOR UPDATE'
    );
    $stmt->execute([$envelopeId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$documento || $documento['status'] !== 'AGUARDANDO_ASSINATURAS') return;

    $mensagem = trim($motivo) ?: 'Fluxo de assinatura interrompido.';
    $pdo->prepare(
        "UPDATE documentos_central
            SET status='EM_VALIDACAO', responsavel_id=NULL, responsavel_tipo='TI',
                exige_assinatura=0, aprovado_por=NULL, aprovado_em=NULL
          WHERE id=?"
    )->execute([$documento['id']]);
    $pdo->prepare(
        "UPDATE documentos_tarefas
            SET status='CONCLUIDA', concluido_em=NOW(), concluido_por=?
          WHERE documento_id=? AND status='PENDENTE'"
    )->execute([$usuarioId, $documento['id']]);
    $pdo->prepare(
        "INSERT INTO documentos_tarefas
            (documento_id, tipo, grupo_responsavel, instrucoes)
         VALUES (?, 'VALIDAR', 'TI', ?)"
    )->execute([$documento['id'], $mensagem]);
    $pdo->prepare(
        "INSERT INTO documentos_eventos
            (documento_id, versao_id, usuario_id, acao, status_anterior, status_novo, mensagem, dados_json)
         VALUES (?, ?, ?, 'ASSINATURA_INTERROMPIDA', 'AGUARDANDO_ASSINATURAS', 'EM_VALIDACAO', ?, ?)"
    )->execute([
        $documento['id'],
        $documento['versao_atual_id'],
        $usuarioId,
        $mensagem,
        json_encode(['envelope_id' => $envelopeId], JSON_UNESCAPED_UNICODE),
    ]);
}
