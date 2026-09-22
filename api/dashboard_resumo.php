<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DocumentoCentralAuth.php';
require_once __DIR__ . '/ContratoAuth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function dashboardJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dashboardFalha(string $bloco, Throwable $erro, array &$indisponiveis): void
{
    $indisponiveis[] = $bloco;
    error_log('Dashboard/' . $bloco . ': ' . $erro->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    dashboardJson(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$fingerprintEsperado = (string) ($_SESSION['user_fingerprint'] ?? '');
$fingerprintAtual = md5(
    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
    . (string) ($_SERVER['REMOTE_ADDR'] ?? '')
);

if ($usuarioId <= 0 || $fingerprintEsperado === '' || !hash_equals($fingerprintEsperado, $fingerprintAtual)) {
    dashboardJson(['ok' => false, 'error' => 'Sessão inválida ou expirada.'], 401);
}

$resumo = [
    'assinaturas_pendentes' => 0,
    'documentos_pendentes' => 0,
    'documentos_publicados' => 0,
    'envios_em_andamento' => 0,
    'contratos_em_alerta' => 0,
];
$fila = [];
$publicados = [];
$assinaturasRecentes = [];
$agenda = [];
$comunicados = [];
$banners = [];
$indisponiveis = [];
$podeVerContratos = false;

try {
    $stmtTotalAssinaturas = $pdo_intra->prepare(
        "SELECT COUNT(*)
           FROM assinaturas_fluxo af
           JOIN sistemas_assinaturas sa ON sa.id=af.fk_assinatura
          WHERE af.glpi_user_id=?
            AND af.status='pendente'
            AND sa.status NOT IN ('concluido','cancelado')"
    );
    $stmtTotalAssinaturas->execute([$usuarioId]);
    $resumo['assinaturas_pendentes'] = (int) $stmtTotalAssinaturas->fetchColumn();

    $stmt = $pdo_intra->prepare(
        "SELECT sa.id AS envelope_id, sa.titulo, sa.tipo_fluxo, sa.criado_em,
                TRIM(CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,''))) AS criador_nome,
                (SELECT COUNT(*) FROM assinatura_documentos ad WHERE ad.envelope_id=sa.id) AS total_documentos,
                (SELECT COUNT(*) FROM assinaturas_fluxo ax WHERE ax.fk_assinatura=sa.id) AS total_assinantes,
                (SELECT COUNT(*) FROM assinaturas_fluxo ax WHERE ax.fk_assinatura=sa.id AND ax.status='assinado') AS assinados
           FROM assinaturas_fluxo af
           JOIN sistemas_assinaturas sa ON sa.id=af.fk_assinatura
      LEFT JOIN " . DB_GLPI . ".glpi_users u ON u.id=sa.criado_por
          WHERE af.glpi_user_id=?
            AND af.status='pendente'
            AND sa.status NOT IN ('concluido','cancelado')
       ORDER BY af.atualizado_em ASC, sa.criado_em ASC
          LIMIT 6"
    );
    $stmt->execute([$usuarioId]);
    $assinaturasPendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assinaturasPendentes as $item) {
        $fila[] = [
            'tipo' => 'ASSINATURA',
            'id' => (int) $item['envelope_id'],
            'titulo' => (string) $item['titulo'],
            'descricao' => trim((string) $item['criador_nome']) ?: 'Fluxo de assinatura',
            'meta' => (int) $item['total_documentos'] . ' documento(s) · '
                . (int) $item['assinados'] . '/' . (int) $item['total_assinantes'] . ' assinatura(s)',
            'data' => (string) $item['criado_em'],
            'url' => 'detalhe_envelope.php?id=' . (int) $item['envelope_id'],
            'acao' => 'Revisar e assinar',
            'prioridade' => 1,
        ];
    }

    $stmtHistorico = $pdo_intra->prepare(
        "SELECT sa.id AS envelope_id, sa.titulo, af.status, af.assinado_em
           FROM assinaturas_fluxo af
           JOIN sistemas_assinaturas sa ON sa.id=af.fk_assinatura
          WHERE af.glpi_user_id=?
            AND af.status IN ('assinado','recusado')
       ORDER BY af.assinado_em DESC
          LIMIT 5"
    );
    $stmtHistorico->execute([$usuarioId]);
    $assinaturasRecentes = array_map(
        static fn(array $item): array => [
            'id' => (int) $item['envelope_id'],
            'titulo' => (string) $item['titulo'],
            'status' => mb_strtoupper((string) $item['status'], 'UTF-8'),
            'data' => (string) ($item['assinado_em'] ?? ''),
            'url' => 'detalhe_envelope.php?id=' . (int) $item['envelope_id'],
        ],
        $stmtHistorico->fetchAll(PDO::FETCH_ASSOC)
    );

    $stmtEnvios = $pdo_intra->prepare(
        "SELECT COUNT(*)
           FROM sistemas_assinaturas
          WHERE criado_por=?
            AND status NOT IN ('concluido','cancelado')"
    );
    $stmtEnvios->execute([$usuarioId]);
    $resumo['envios_em_andamento'] = (int) $stmtEnvios->fetchColumn();
} catch (Throwable $erro) {
    dashboardFalha('assinaturas', $erro, $indisponiveis);
}

try {
    $documentoAuth = new DocumentoCentralAuth(
        $pdo_intra,
        $usuarioId,
        !empty($_SESSION['is_admin']),
        $_SESSION
    );
    $ehValidador = $documentoAuth->isValidador();
    $ehAdmin = $documentoAuth->isAdmin();

    $stmtTotalPendencias = $pdo_intra->prepare(
        "SELECT COUNT(DISTINCT d.id)
           FROM documentos_central d
      LEFT JOIN documentos_tarefas t
             ON t.documento_id=d.id
            AND t.status='PENDENTE'
          WHERE t.usuario_responsavel_id=?
             OR (?=1 AND (
                    d.status='EM_VALIDACAO'
                    OR (d.status='EM_ANALISE' AND (?=1 OR d.responsavel_id=?))
                ))"
    );
    $stmtTotalPendencias->execute([
        $usuarioId,
        $ehValidador ? 1 : 0,
        $ehAdmin ? 1 : 0,
        $usuarioId,
    ]);
    $resumo['documentos_pendentes'] = (int) $stmtTotalPendencias->fetchColumn();

    $stmtPendencias = $pdo_intra->prepare(
        "SELECT DISTINCT d.id, d.titulo, d.setor, d.status, d.atualizado_em,
                t.tipo AS tarefa_tipo, t.instrucoes
           FROM documentos_central d
      LEFT JOIN documentos_tarefas t
             ON t.documento_id=d.id
            AND t.status='PENDENTE'
          WHERE t.usuario_responsavel_id=?
             OR (?=1 AND (
                    d.status='EM_VALIDACAO'
                    OR (d.status='EM_ANALISE' AND (?=1 OR d.responsavel_id=?))
                ))
       ORDER BY d.atualizado_em ASC
          LIMIT 6"
    );
    $stmtPendencias->execute([
        $usuarioId,
        $ehValidador ? 1 : 0,
        $ehAdmin ? 1 : 0,
        $usuarioId,
    ]);
    $pendenciasDocumento = $stmtPendencias->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pendenciasDocumento as $item) {
        $fila[] = [
            'tipo' => 'DOCUMENTO',
            'id' => (int) $item['id'],
            'titulo' => (string) $item['titulo'],
            'descricao' => (string) ($item['instrucoes'] ?: 'Documento aguardando sua análise'),
            'meta' => trim((string) $item['setor']) . ' · ' . str_replace('_', ' ', (string) $item['status']),
            'data' => (string) $item['atualizado_em'],
            'url' => 'documentos.php?aba=' . ($ehValidador ? 'fila' : 'pendencias'),
            'acao' => $ehValidador ? 'Abrir fila' : 'Resolver pendência',
            'prioridade' => 2,
        ];
    }

    $partesPublicados = ["d.status='PUBLICADO'"];
    $paramsPublicados = [];
    if (!$ehValidador) {
        $visibilidade = ['d.criador_id=?', "UPPER(TRIM(d.setor))='GERAL'"];
        $paramsPublicados[] = $usuarioId;
        $setores = $documentoAuth->setores();
        if ($setores) {
            $marcadores = implode(',', array_fill(0, count($setores), '?'));
            $visibilidade[] = "UPPER(TRIM(d.setor)) IN ($marcadores)";
            array_push($paramsPublicados, ...$setores);
        }
        $partesPublicados[] = '(' . implode(' OR ', $visibilidade) . ')';
    }

    $wherePublicados = implode(' AND ', $partesPublicados);
    $stmtTotalPublicados = $pdo_intra->prepare(
        "SELECT COUNT(*) FROM documentos_central d
          WHERE {$wherePublicados}
            AND COALESCE(d.publicado_em,d.atualizado_em) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stmtTotalPublicados->execute($paramsPublicados);
    $resumo['documentos_publicados'] = (int) $stmtTotalPublicados->fetchColumn();

    $stmtPublicados = $pdo_intra->prepare(
        "SELECT d.id, d.titulo, d.setor, d.tipo,
                COALESCE(d.publicado_em,d.atualizado_em) AS publicado_em
           FROM documentos_central d
          WHERE {$wherePublicados}
       ORDER BY COALESCE(d.publicado_em,d.atualizado_em) DESC
          LIMIT 6"
    );
    $stmtPublicados->execute($paramsPublicados);
    $publicados = array_map(
        static fn(array $item): array => [
            'id' => (int) $item['id'],
            'titulo' => (string) $item['titulo'],
            'setor' => (string) $item['setor'],
            'tipo' => (string) $item['tipo'],
            'data' => (string) $item['publicado_em'],
            'url' => 'documentos.php?aba=biblioteca&setor=' . rawurlencode((string) $item['setor']),
        ],
        $stmtPublicados->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (Throwable $erro) {
    dashboardFalha('documentos', $erro, $indisponiveis);
}

try {
    $stmtAgenda = $pdo_intra->prepare(
        "SELECT id, titulo, data_evento, hora_inicio, hora_fim, local_sala, visibilidade
           FROM agenda_eventos
          WHERE data_evento BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
            AND (visibilidade='GERAL' OR usuario_id=?)
       ORDER BY data_evento ASC, COALESCE(hora_inicio,'00:00:00') ASC
          LIMIT 40"
    );
    $stmtAgenda->execute([$usuarioId]);
    $agenda = array_map(
        static fn(array $item): array => [
            'id' => (int) $item['id'],
            'titulo' => (string) $item['titulo'],
            'data' => (string) $item['data_evento'],
            'hora_inicio' => $item['hora_inicio'] ? substr((string) $item['hora_inicio'], 0, 5) : '',
            'hora_fim' => $item['hora_fim'] ? substr((string) $item['hora_fim'], 0, 5) : '',
            'local' => (string) ($item['local_sala'] ?: 'GERAL'),
            'visibilidade' => (string) $item['visibilidade'],
        ],
        $stmtAgenda->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (Throwable $erro) {
    dashboardFalha('agenda', $erro, $indisponiveis);
}

try {
    $stmtComunicados = $pdo_intra->query(
        "SELECT id, titulo, categoria, COALESCE(resumo,'') AS resumo, data_postagem
           FROM comunicados
          WHERE ativo=1
       ORDER BY data_postagem DESC
          LIMIT 4"
    );
    $comunicados = array_map(
        static fn(array $item): array => [
            'id' => (int) $item['id'],
            'titulo' => (string) $item['titulo'],
            'categoria' => (string) $item['categoria'],
            'resumo' => mb_substr(trim(strip_tags((string) $item['resumo'])), 0, 180),
            'data' => (string) $item['data_postagem'],
        ],
        $stmtComunicados->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (Throwable $erro) {
    dashboardFalha('comunicados', $erro, $indisponiveis);
}

try {
    $stmtBanners = $pdo_intra->query(
        "SELECT id, titulo, imagem_path, data_inicio, data_fim
           FROM banners_marketing
          WHERE ativo=1
            AND CURRENT_DATE() BETWEEN data_inicio AND data_fim
       ORDER BY id DESC
          LIMIT 8"
    );
    foreach ($stmtBanners->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $caminho = ltrim(str_replace('\\', '/', (string) $item['imagem_path']), '/');
        if ($caminho === '' || str_contains($caminho, '..') || !str_starts_with($caminho, 'img/')) {
            continue;
        }
        $banners[] = [
            'id' => (int) $item['id'],
            'titulo' => (string) $item['titulo'],
            'imagem' => $caminho,
            'inicio' => (string) $item['data_inicio'],
            'fim' => (string) $item['data_fim'],
        ];
    }
} catch (Throwable $erro) {
    dashboardFalha('banners', $erro, $indisponiveis);
}

try {
    $contratoAuth = new ContratoAuth($pdo_intra, $usuarioId, !empty($_SESSION['is_admin']));
    $podeVerContratos = $contratoAuth->pode('acessar_modulo') && $contratoAuth->pode('visualizar');
    if ($podeVerContratos) {
        [$filtroContratos, $paramsContratos] = $contratoAuth->filtroContratosSql();
        $stmtContratos = $pdo_intra->prepare(
            "SELECT COUNT(*)
               FROM contratos c
              WHERE {$filtroContratos}
                AND COALESCE(c.status,'ATIVO')='ATIVO'
                AND c.data_vencimento IS NOT NULL
                AND DATEDIFF(c.data_vencimento,CURRENT_DATE()) <=
                    CASE WHEN UPPER(c.setor) LIKE '%FACILITIES%' THEN 90 ELSE 60 END"
        );
        $stmtContratos->execute($paramsContratos);
        $resumo['contratos_em_alerta'] = (int) $stmtContratos->fetchColumn();
    }
} catch (Throwable $erro) {
    dashboardFalha('contratos', $erro, $indisponiveis);
}

usort($fila, static function (array $a, array $b): int {
    return [$a['prioridade'], $a['data']] <=> [$b['prioridade'], $b['data']];
});
$fila = array_slice($fila, 0, 8);

dashboardJson([
    'ok' => true,
    'gerado_em' => date(DATE_ATOM),
    'resumo' => $resumo,
    'fila' => $fila,
    'publicados' => $publicados,
    'assinaturas_recentes' => $assinaturasRecentes,
    'agenda' => $agenda,
    'comunicados' => $comunicados,
    'banners' => $banners,
    'permissoes' => [
        'contratos' => $podeVerContratos,
        'administrador' => !empty($_SESSION['is_admin']),
        'gestao_documentos' => !empty($_SESSION['pode_gerenciar_docs']),
    ],
    'indisponiveis' => array_values(array_unique($indisponiveis)),
]);
