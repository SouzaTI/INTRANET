<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function agendaJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    agendaJson(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$usuarioId = (int) ($_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 0);
$fingerprintEsperado = (string) ($_SESSION['user_fingerprint'] ?? '');
$fingerprintAtual = md5(
    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
    . (string) ($_SERVER['REMOTE_ADDR'] ?? '')
);

if ($usuarioId <= 0 || $fingerprintEsperado === '' || !hash_equals($fingerprintEsperado, $fingerprintAtual)) {
    agendaJson(['ok' => false, 'error' => 'Sessão inválida ou expirada.'], 401);
}

$csrfEsperado = (string) ($_SESSION['dashboard_csrf'] ?? '');
$csrfRecebido = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
if ($csrfEsperado === '' || $csrfRecebido === '' || !hash_equals($csrfEsperado, $csrfRecebido)) {
    agendaJson(['ok' => false, 'error' => 'A sessão do formulário expirou. Atualize a página.'], 403);
}

$stmtAdmin = $pdo_intra->prepare(
    "SELECT MAX(is_admin) FROM (
        SELECT COALESCE(is_admin,0) AS is_admin
          FROM usuarios_permissoes
         WHERE usuario_id=?
        UNION ALL
        SELECT COALESCE(g.is_admin,0)
          FROM usuarios_grupos ug
          JOIN grupos_intranet g ON g.id=ug.grupo_id
         WHERE ug.usuario_id=?
    ) permissoes"
);
$stmtAdmin->execute([$usuarioId, $usuarioId]);
$isAdmin = (bool) $stmtAdmin->fetchColumn();

$titulo = trim((string) ($_POST['titulo'] ?? ''));
$dataTexto = trim((string) ($_POST['data_evento'] ?? ''));
$diaInteiro = (string) ($_POST['dia_inteiro'] ?? '') === '1';
$horaInicio = trim((string) ($_POST['hora_inicio'] ?? ''));
$horaFim = trim((string) ($_POST['hora_fim'] ?? ''));
$visibilidadeSolicitada = mb_strtoupper(trim((string) ($_POST['visibilidade'] ?? 'PESSOAL')), 'UTF-8');
$local = mb_strtoupper(trim((string) ($_POST['local_sala'] ?? 'GERAL')), 'UTF-8');

if (mb_strlen($titulo, 'UTF-8') < 3 || mb_strlen($titulo, 'UTF-8') > 100) {
    agendaJson(['ok' => false, 'error' => 'Informe um título entre 3 e 100 caracteres.'], 422);
}

$dataEvento = DateTimeImmutable::createFromFormat('!Y-m-d', $dataTexto);
$errosData = DateTimeImmutable::getLastErrors();
if (!$dataEvento || ($errosData !== false && ($errosData['warning_count'] > 0 || $errosData['error_count'] > 0))) {
    agendaJson(['ok' => false, 'error' => 'Informe uma data válida.'], 422);
}

$hoje = new DateTimeImmutable('today');
if ($dataEvento < $hoje || $dataEvento > $hoje->modify('+2 years')) {
    agendaJson(['ok' => false, 'error' => 'Escolha uma data entre hoje e os próximos dois anos.'], 422);
}

$locaisPermitidos = ['GERAL', 'SALA_01', 'SALA_02', 'SALA_03'];
if (!in_array($local, $locaisPermitidos, true)) {
    agendaJson(['ok' => false, 'error' => 'Local inválido.'], 422);
}

$visibilidade = ($isAdmin && $visibilidadeSolicitada === 'GERAL') ? 'GERAL' : 'PESSOAL';

if ($diaInteiro) {
    $horaInicio = '';
    $horaFim = '';
} else {
    $horaValida = static fn(string $hora): bool => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora) === 1;
    if (!$horaValida($horaInicio) || !$horaValida($horaFim) || $horaFim <= $horaInicio) {
        agendaJson(['ok' => false, 'error' => 'Informe um horário inicial e final válidos.'], 422);
    }
    if ($local !== 'GERAL' && ($horaInicio < '08:00' || $horaFim > '17:48')) {
        agendaJson(['ok' => false, 'error' => 'As salas podem ser reservadas entre 08:00 e 17:48.'], 422);
    }
}

try {
    if ($local !== 'GERAL') {
        if ($diaInteiro) {
            $stmtConflito = $pdo_intra->prepare(
                "SELECT COUNT(*)
                   FROM agenda_eventos
                  WHERE data_evento=?
                    AND local_sala=?"
            );
            $stmtConflito->execute([$dataTexto, $local]);
        } else {
            $stmtConflito = $pdo_intra->prepare(
                "SELECT COUNT(*)
                   FROM agenda_eventos
                  WHERE data_evento=?
                    AND local_sala=?
                    AND (hora_inicio IS NULL OR (hora_inicio < ? AND hora_fim > ?))"
            );
            $stmtConflito->execute([$dataTexto, $local, $horaFim, $horaInicio]);
        }
        if ((int) $stmtConflito->fetchColumn() > 0) {
            agendaJson(['ok' => false, 'error' => 'A sala escolhida já está ocupada nesse horário.'], 409);
        }
    }

    $stmtSalvar = $pdo_intra->prepare(
        "INSERT INTO agenda_eventos
            (usuario_id, titulo, data_evento, hora_inicio, hora_fim, visibilidade, local_sala, categoria)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'EVENTO')"
    );
    $stmtSalvar->execute([
        $usuarioId,
        $titulo,
        $dataTexto,
        $diaInteiro ? null : $horaInicio . ':00',
        $diaInteiro ? null : $horaFim . ':00',
        $visibilidade,
        $local,
    ]);

    registrarLog(
        $pdo_intra,
        'CRIOU AGENDA',
        sprintf('Criou o compromisso "%s" em %s com visibilidade %s.', $titulo, $dataTexto, $visibilidade)
    );

    agendaJson([
        'ok' => true,
        'message' => $visibilidade === 'GERAL'
            ? 'Compromisso publicado para todos.'
            : 'Compromisso pessoal agendado.',
        'evento' => [
            'id' => (int) $pdo_intra->lastInsertId(),
            'titulo' => $titulo,
            'data' => $dataTexto,
            'hora_inicio' => $diaInteiro ? '' : $horaInicio,
            'hora_fim' => $diaInteiro ? '' : $horaFim,
            'local' => $local,
            'visibilidade' => $visibilidade,
        ],
    ], 201);
} catch (Throwable $erro) {
    error_log('Dashboard/agenda_salvar: ' . $erro->getMessage());
    agendaJson(['ok' => false, 'error' => 'Não foi possível salvar o compromisso agora.'], 500);
}
