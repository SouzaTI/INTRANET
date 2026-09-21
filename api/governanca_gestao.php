<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function govMJson(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function govMError(
    string $message,
    int $status = 400,
    array $extra = []
): never {
    govMJson(array_merge([
        'ok' => false,
        'error' => $message,
    ], $extra), $status);
}

function govMBody(): array {
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $body = json_decode($raw, true);

    if (!is_array($body)) {
        govMError('JSON inválido.');
    }

    return $body;
}

function govMCsrf(): void {
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = (string) ($_SESSION['governanca_csrf'] ?? '');

    if (
        $expected === ''
        || $token === ''
        || !hash_equals($expected, $token)
    ) {
        govMError(
            'Token de segurança inválido. Atualize a página e tente novamente.',
            419
        );
    }
}

function govMIsAdmin(PDO $pdo, int $userId): bool {
    if (!empty($_SESSION['is_admin'])) {
        return true;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                MAX(CASE WHEN UP.is_admin = 1 THEN 1 ELSE 0 END) AS individual_admin,
                MAX(CASE WHEN G.is_admin = 1 THEN 1 ELSE 0 END) AS group_admin
            FROM (SELECT ? AS usuario_id) X
            LEFT JOIN usuarios_permissoes UP
                   ON UP.usuario_id = X.usuario_id
            LEFT JOIN usuarios_grupos UG
                   ON UG.usuario_id = X.usuario_id
            LEFT JOIN grupos_intranet G
                   ON G.id = UG.grupo_id
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return !empty($row['individual_admin'])
            || !empty($row['group_admin']);
    } catch (Throwable $e) {
        return false;
    }
}

function govMStructures(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT
            E.id,
            E.codigo,
            E.pai_id,
            P.codigo AS pai_codigo,
            E.nome,
            E.ordem,
            E.ativo
        FROM governanca_estruturas E
        LEFT JOIN governanca_estruturas P
               ON P.id = E.pai_id
        WHERE E.ativo = 1
        ORDER BY
            CASE WHEN E.pai_id IS NULL THEN 0 ELSE 1 END,
            E.ordem ASC,
            E.nome ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govMGroupIds(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT grupo_id
        FROM usuarios_grupos
        WHERE usuario_id = ?
    ");
    $stmt->execute([$userId]);

    return array_values(array_unique(array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
    )));
}

function govMGrants(
    PDO $pdo,
    int $userId,
    array $groupIds
): array {
    $conditions = [
        "(alvo_tipo = 'USUARIO' AND alvo_id = ?)"
    ];
    $params = [$userId];

    if ($groupIds) {
        $placeholders = implode(
            ',',
            array_fill(0, count($groupIds), '?')
        );

        $conditions[] =
            "(alvo_tipo = 'GRUPO' AND alvo_id IN ($placeholders))";

        foreach ($groupIds as $groupId) {
            $params[] = $groupId;
        }
    }

    $sql = "
        SELECT
            estrutura_codigo,
            inclui_descendentes,
            nivel_acesso
        FROM governanca_permissoes
        WHERE ativo = 1
          AND nivel_acesso = 'GERENCIAR'
          AND (" . implode(' OR ', $conditions) . ")
        ORDER BY id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govMScope(PDO $pdo, int $userId): array {
    $structures = govMStructures($pdo);
    $isAdmin = govMIsAdmin($pdo, $userId);

    $byCode = [];
    $children = [];

    foreach ($structures as $row) {
        $code = (string) $row['codigo'];
        $byCode[$code] = $row;

        $parentCode = (string) ($row['pai_codigo'] ?? '');

        if (!isset($children[$parentCode])) {
            $children[$parentCode] = [];
        }

        $children[$parentCode][] = $code;
    }

    if ($isAdmin) {
        return [
            'is_admin' => true,
            'codes' => array_fill_keys(array_keys($byCode), true),
            'structures' => $structures,
        ];
    }

    $groupIds = govMGroupIds($pdo, $userId);
    $grants = govMGrants($pdo, $userId, $groupIds);
    $allowed = [];

    $addDescendants = function (
        string $code
    ) use (
        &$addDescendants,
        &$allowed,
        $children
    ): void {
        foreach ($children[$code] ?? [] as $childCode) {
            if (isset($allowed[$childCode])) {
                continue;
            }

            $allowed[$childCode] = true;
            $addDescendants($childCode);
        }
    };

    foreach ($grants as $grant) {
        $code = trim((string) ($grant['estrutura_codigo'] ?? ''));

        if ($code === '*') {
            $allowed = array_fill_keys(array_keys($byCode), true);
            break;
        }

        if ($code === '' || !isset($byCode[$code])) {
            continue;
        }

        $allowed[$code] = true;

        if (!empty($grant['inclui_descendentes'])) {
            $addDescendants($code);
        }
    }

    $manageable = array_values(array_filter(
        $structures,
        static fn(array $row): bool =>
            isset($allowed[(string) $row['codigo']])
    ));

    return [
        'is_admin' => false,
        'codes' => $allowed,
        'structures' => $manageable,
    ];
}

function govMRequireStructure(
    PDO $pdo,
    int $userId,
    string $code
): array {
    $scope = govMScope($pdo, $userId);

    if (!isset($scope['codes'][$code])) {
        govMError(
            'Você não possui permissão GERENCIAR para esta estrutura.',
            403
        );
    }

    foreach ($scope['structures'] as $row) {
        if ((string) $row['codigo'] === $code) {
            return $row;
        }
    }

    govMError('Estrutura não encontrada ou inativa.', 404);
}

function govMFunctionAny(
    PDO $pdo,
    int $functionId
): array {
    $stmt = $pdo->prepare("
        SELECT
            F.id,
            F.estrutura_id,
            F.nome,
            F.descricao,
            F.ordem,
            F.ativo,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome
        FROM governanca_funcoes F
        JOIN governanca_estruturas E
          ON E.id = F.estrutura_id
        WHERE F.id = ?
        LIMIT 1
    ");
    $stmt->execute([$functionId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        govMError('Função/cargo não encontrada.', 404);
    }

    return $row;
}

function govMFunction(
    PDO $pdo,
    int $functionId
): array {
    $row = govMFunctionAny($pdo, $functionId);

    if (empty($row['ativo'])) {
        govMError('Função/cargo não encontrada ou inativa.', 404);
    }

    return $row;
}

function govMLinkAny(
    PDO $pdo,
    int $linkId
): array {
    $stmt = $pdo->prepare("
        SELECT
            PF.id AS vinculo_id,
            PF.pessoa_id,
            PF.funcao_id,
            PF.principal,
            PF.ordem,
            PF.data_inicio,
            PF.data_fim,
            PF.ativo AS vinculo_ativo,

            P.codigo AS pessoa_codigo,
            P.nome AS pessoa_nome,
            P.tipo_vinculo,
            P.observacoes AS pessoa_observacoes,
            P.ativo AS pessoa_ativa,

            F.nome AS funcao_nome,
            F.ativo AS funcao_ativa,
            F.estrutura_id,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome
        FROM governanca_pessoa_funcoes PF
        JOIN governanca_pessoas P
          ON P.id = PF.pessoa_id
        JOIN governanca_funcoes F
          ON F.id = PF.funcao_id
        JOIN governanca_estruturas E
          ON E.id = F.estrutura_id
        WHERE PF.id = ?
        LIMIT 1
    ");
    $stmt->execute([$linkId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        govMError('Vínculo não encontrado.', 404);
    }

    return $row;
}

function govMLink(
    PDO $pdo,
    int $linkId
): array {
    $row = govMLinkAny($pdo, $linkId);

    if (
        empty($row['vinculo_ativo'])
        || empty($row['pessoa_ativa'])
    ) {
        govMError('Vínculo não encontrado ou inativo.', 404);
    }

    return $row;
}

function govMLog(
    PDO $pdo,
    int $userId,
    ?int $structureId,
    string $entity,
    ?int $entityId,
    string $action,
    mixed $before,
    mixed $after
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO governanca_logs
                (
                    usuario_id,
                    estrutura_id,
                    entidade,
                    entidade_id,
                    acao,
                    dados_antes,
                    dados_depois,
                    ip
                )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $structureId,
            $entity,
            $entityId,
            $action,
            $before === null
                ? null
                : json_encode(
                    $before,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
            $after === null
                ? null
                : json_encode(
                    $after,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log(
            'Governança: falha ao registrar log: '
            . $e->getMessage()
        );
    }
}

function govMNextPersonCode(PDO $pdo): string {
    $stmt = $pdo->query("
        SELECT MAX(
            CAST(SUBSTRING(codigo, 2) AS UNSIGNED)
        )
        FROM governanca_pessoas
        WHERE codigo REGEXP '^P[0-9]+$'
    ");

    $last = (int) ($stmt->fetchColumn() ?: 0);

    return 'P' . str_pad(
        (string) ($last + 1),
        3,
        '0',
        STR_PAD_LEFT
    );
}

function govMFunctionsInScope(
    PDO $pdo,
    array $scopeCodes
): array {
    if (!$scopeCodes) {
        return [];
    }

    $codes = array_keys($scopeCodes);
    $placeholders = implode(
        ',',
        array_fill(0, count($codes), '?')
    );

    $stmt = $pdo->prepare("
        SELECT
            F.id,
            F.nome,
            F.descricao,
            F.ordem,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome
        FROM governanca_funcoes F
        JOIN governanca_estruturas E
          ON E.id = F.estrutura_id
        WHERE F.ativo = 1
          AND E.ativo = 1
          AND E.codigo IN ($placeholders)
        ORDER BY E.nome ASC, F.ordem ASC, F.nome ASC
    ");

    $stmt->execute($codes);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govMInactiveFunctions(
    PDO $pdo,
    array $scopeCodes
): array {
    if (!$scopeCodes) {
        return [];
    }

    $codes = array_keys($scopeCodes);
    $placeholders = implode(
        ',',
        array_fill(0, count($codes), '?')
    );

    $stmt = $pdo->prepare("
        SELECT
            F.id,
            F.nome,
            F.descricao,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome
        FROM governanca_funcoes F
        JOIN governanca_estruturas E
          ON E.id = F.estrutura_id
        WHERE F.ativo = 0
          AND E.ativo = 1
          AND E.codigo IN ($placeholders)
        ORDER BY E.nome ASC, F.nome ASC
    ");
    $stmt->execute($codes);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govMInactiveLinks(
    PDO $pdo,
    array $scopeCodes
): array {
    if (!$scopeCodes) {
        return [];
    }

    $codes = array_keys($scopeCodes);
    $placeholders = implode(
        ',',
        array_fill(0, count($codes), '?')
    );

    $sql = "
        SELECT
            PF.id AS vinculo_id,
            PF.pessoa_id,
            PF.funcao_id,
            PF.data_inicio,
            PF.data_fim,
            P.codigo AS pessoa_codigo,
            P.nome AS pessoa_nome,
            P.ativo AS pessoa_ativa,
            F.nome AS funcao_nome,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome
        FROM governanca_pessoa_funcoes PF
        JOIN governanca_pessoas P
          ON P.id = PF.pessoa_id
        JOIN governanca_funcoes F
          ON F.id = PF.funcao_id
        JOIN governanca_estruturas E
          ON E.id = F.estrutura_id
        WHERE PF.ativo = 0
          AND F.ativo = 1
          AND E.ativo = 1
          AND E.codigo IN ($placeholders)
          AND PF.id = (
              SELECT MAX(PF2.id)
              FROM governanca_pessoa_funcoes PF2
              WHERE PF2.pessoa_id = PF.pessoa_id
                AND PF2.funcao_id = PF.funcao_id
                AND PF2.ativo = 0
          )
          AND NOT EXISTS (
              SELECT 1
              FROM governanca_pessoa_funcoes PFA
              WHERE PFA.pessoa_id = PF.pessoa_id
                AND PFA.funcao_id = PF.funcao_id
                AND PFA.ativo = 1
                AND (
                    PFA.data_fim IS NULL
                    OR PFA.data_fim >= CURDATE()
                )
          )
        ORDER BY
            E.nome ASC,
            F.nome ASC,
            P.nome ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($codes);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govMPeopleInScope(
    PDO $pdo,
    array $scopeCodes,
    bool $isAdmin
): array {
    if ($isAdmin) {
        $stmt = $pdo->query("
            SELECT
                P.id,
                P.codigo,
                P.nome,
                P.tipo_vinculo,
                P.observacoes
            FROM governanca_pessoas P
            WHERE P.ativo = 1
            ORDER BY P.nome ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (!$scopeCodes) {
        return [];
    }

    $codes = array_keys($scopeCodes);
    $placeholders = implode(
        ',',
        array_fill(0, count($codes), '?')
    );

    $sql = "
        SELECT DISTINCT
            P.id,
            P.codigo,
            P.nome,
            P.tipo_vinculo,
            P.observacoes
        FROM governanca_pessoas P
        WHERE P.ativo = 1
          AND (
              NOT EXISTS (
                  SELECT 1
                  FROM governanca_pessoa_funcoes PF0
                  WHERE PF0.pessoa_id = P.id
                    AND PF0.ativo = 1
                    AND (
                        PF0.data_fim IS NULL
                        OR PF0.data_fim >= CURDATE()
                    )
              )
              OR EXISTS (
                  SELECT 1
                  FROM governanca_pessoa_funcoes PF
                  JOIN governanca_funcoes F
                    ON F.id = PF.funcao_id
                  JOIN governanca_estruturas E
                    ON E.id = F.estrutura_id
                  WHERE PF.pessoa_id = P.id
                    AND PF.ativo = 1
                    AND F.ativo = 1
                    AND E.ativo = 1
                    AND E.codigo IN ($placeholders)
                    AND (
                        PF.data_fim IS NULL
                        OR PF.data_fim >= CURDATE()
                    )
              )
          )
        ORDER BY P.nome ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($codes);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    govMError('Sessão inválida ou expirada.', 401);
}

$action = trim((string) ($_GET['action'] ?? 'scope'));

try {
    if ($action === 'scope') {
        $scope = govMScope($pdo_intra, $userId);

        govMJson([
            'ok' => true,
            'is_admin' => (bool) $scope['is_admin'],
            'can_manage' => !empty($scope['codes']),
            'structures' => array_map(
                static fn(array $row): array => [
                    'id' => (int) $row['id'],
                    'codigo' => (string) $row['codigo'],
                    'pai_codigo' => (string) (
                        $row['pai_codigo'] ?? ''
                    ),
                    'nome' => (string) $row['nome'],
                    'ordem' => (int) $row['ordem'],
                ],
                $scope['structures']
            ),
            'functions' => govMFunctionsInScope(
                $pdo_intra,
                $scope['codes']
            ),
            'people' => govMPeopleInScope(
                $pdo_intra,
                $scope['codes'],
                (bool) $scope['is_admin']
            ),
            'inactive_functions' => govMInactiveFunctions(
                $pdo_intra,
                $scope['codes']
            ),
            'inactive_links' => govMInactiveLinks(
                $pdo_intra,
                $scope['codes']
            ),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        govMError('Método não permitido.', 405);
    }

    govMCsrf();
    $body = govMBody();

    if ($action === 'create_function') {
        $structureCode = trim(
            (string) ($body['estrutura_codigo'] ?? '')
        );
        $name = trim((string) ($body['nome'] ?? ''));
        $description = trim(
            (string) ($body['descricao'] ?? '')
        );

        if ($structureCode === '') {
            govMError('Selecione a estrutura.');
        }

        if ($name === '') {
            govMError('Informe o nome da função/cargo.');
        }

        if (mb_strlen($name) > 180) {
            govMError(
                'O nome da função/cargo deve ter no máximo 180 caracteres.'
            );
        }

        if (mb_strlen($description) > 5000) {
            govMError(
                'A descrição deve ter no máximo 5.000 caracteres.'
            );
        }

        $structure = govMRequireStructure(
            $pdo_intra,
            $userId,
            $structureCode
        );
        $structureId = (int) $structure['id'];

        $check = $pdo_intra->prepare("
            SELECT id, ativo
            FROM governanca_funcoes
            WHERE estrutura_id = ?
              AND nome = ?
            LIMIT 1
        ");
        $check->execute([$structureId, $name]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            govMError(
                !empty($existing['ativo'])
                    ? 'Já existe uma função/cargo com este nome nesta estrutura.'
                    : 'Já existe uma função inativa com este nome.',
                409
            );
        }

        $orderStmt = $pdo_intra->prepare("
            SELECT COALESCE(MAX(ordem), 0) + 1
            FROM governanca_funcoes
            WHERE estrutura_id = ?
        ");
        $orderStmt->execute([$structureId]);
        $order = (int) $orderStmt->fetchColumn();

        $stmt = $pdo_intra->prepare("
            INSERT INTO governanca_funcoes
                (
                    estrutura_id,
                    nome,
                    descricao,
                    ordem,
                    ativo,
                    criado_por,
                    atualizado_por
                )
            VALUES (?, ?, ?, ?, 1, ?, ?)
        ");

        $stmt->execute([
            $structureId,
            $name,
            $description !== '' ? $description : null,
            $order,
            $userId,
            $userId,
        ]);

        $functionId = (int) $pdo_intra->lastInsertId();

        govMLog(
            $pdo_intra,
            $userId,
            $structureId,
            'FUNCAO',
            $functionId,
            'CRIAR',
            null,
            [
                'estrutura_codigo' => $structureCode,
                'nome' => $name,
                'descricao' => $description,
            ]
        );

        govMJson([
            'ok' => true,
            'message' => 'Função/cargo cadastrada com sucesso.',
        ], 201);
    }

    if ($action === 'update_function') {
        $functionId = (int) ($body['funcao_id'] ?? 0);
        $name = trim((string) ($body['nome'] ?? ''));
        $description = trim(
            (string) ($body['descricao'] ?? '')
        );

        if ($functionId <= 0 || $name === '') {
            govMError('Função e nome são obrigatórios.');
        }

        if (mb_strlen($name) > 180) {
            govMError(
                'O nome da função/cargo deve ter no máximo 180 caracteres.'
            );
        }

        $function = govMFunction($pdo_intra, $functionId);

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $function['estrutura_codigo']
        );

        $duplicate = $pdo_intra->prepare("
            SELECT id
            FROM governanca_funcoes
            WHERE estrutura_id = ?
              AND nome = ?
              AND id <> ?
              AND ativo = 1
            LIMIT 1
        ");
        $duplicate->execute([
            (int) $function['estrutura_id'],
            $name,
            $functionId,
        ]);

        if ($duplicate->fetchColumn()) {
            govMError(
                'Já existe outra função/cargo ativa com este nome nesta estrutura.',
                409
            );
        }

        $before = [
            'nome' => (string) $function['nome'],
            'descricao' => (string) (
                $function['descricao'] ?? ''
            ),
        ];

        $stmt = $pdo_intra->prepare("
            UPDATE governanca_funcoes
            SET
                nome = ?,
                descricao = ?,
                atualizado_por = ?
            WHERE id = ?
              AND ativo = 1
        ");
        $stmt->execute([
            $name,
            $description !== '' ? $description : null,
            $userId,
            $functionId,
        ]);

        govMLog(
            $pdo_intra,
            $userId,
            (int) $function['estrutura_id'],
            'FUNCAO',
            $functionId,
            'EDITAR',
            $before,
            [
                'nome' => $name,
                'descricao' => $description,
            ]
        );

        govMJson([
            'ok' => true,
            'message' => 'Função/cargo atualizada com sucesso.',
        ]);
    }

    if ($action === 'deactivate_function') {
        $functionId = (int) ($body['funcao_id'] ?? 0);

        if ($functionId <= 0) {
            govMError('Função inválida.');
        }

        $function = govMFunction($pdo_intra, $functionId);

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $function['estrutura_codigo']
        );

        $stmt = $pdo_intra->prepare("
            SELECT
                (
                    SELECT COUNT(*)
                    FROM governanca_pessoa_funcoes PF
                    WHERE PF.funcao_id = ?
                      AND PF.ativo = 1
                      AND (
                          PF.data_fim IS NULL
                          OR PF.data_fim >= CURDATE()
                      )
                ) AS ocupantes,
                (
                    SELECT COUNT(*)
                    FROM governanca_funcao_responsabilidades FR
                    WHERE FR.funcao_id = ?
                      AND FR.ativo = 1
                ) AS responsabilidades
        ");
        $stmt->execute([$functionId, $functionId]);
        $deps = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $occupants = (int) ($deps['ocupantes'] ?? 0);
        $responsibilities = (int) (
            $deps['responsabilidades'] ?? 0
        );

        if ($occupants > 0 || $responsibilities > 0) {
            govMError(
                'Esta função não pode ser inativada enquanto possuir ocupantes ou responsabilidades ativas.',
                409,
                [
                    'ocupantes' => $occupants,
                    'responsabilidades' => $responsibilities,
                ]
            );
        }

        $stmt = $pdo_intra->prepare("
            UPDATE governanca_funcoes
            SET ativo = 0, atualizado_por = ?
            WHERE id = ?
        ");
        $stmt->execute([$userId, $functionId]);

        govMLog(
            $pdo_intra,
            $userId,
            (int) $function['estrutura_id'],
            'FUNCAO',
            $functionId,
            'INATIVAR',
            $function,
            ['ativo' => 0]
        );

        govMJson([
            'ok' => true,
            'message' => 'Função/cargo inativada com sucesso.',
        ]);
    }

    if ($action === 'create_person') {
        $functionId = (int) ($body['funcao_id'] ?? 0);
        $name = trim((string) ($body['nome'] ?? ''));
        $linkType = trim(
            (string) ($body['tipo_vinculo'] ?? 'Interno')
        );
        $notes = trim(
            (string) ($body['observacoes'] ?? '')
        );

        if ($functionId <= 0) {
            govMError('Selecione a função/cargo.');
        }

        if ($name === '') {
            govMError('Informe o nome da pessoa.');
        }

        $function = govMFunction($pdo_intra, $functionId);

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $function['estrutura_codigo']
        );

        $personCheck = $pdo_intra->prepare("
            SELECT id, codigo
            FROM governanca_pessoas
            WHERE ativo = 1
              AND LOWER(TRIM(nome)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $personCheck->execute([$name]);
        $existingPerson = $personCheck->fetch(PDO::FETCH_ASSOC);

        if ($existingPerson) {
            govMError(
                'Já existe uma pessoa ativa com este nome. Cada pessoa pode possuir apenas uma função/cargo ativo. Para alterar a função ou o setor, edite o vínculo atual.',
                409,
                [
                    'existing_person_id' =>
                        (int) $existingPerson['id'],
                ]
            );
        }

        $pdo_intra->beginTransaction();

        try {
            $personId = 0;
            $code = '';

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = govMNextPersonCode($pdo_intra);

                try {
                    $stmt = $pdo_intra->prepare("
                        INSERT INTO governanca_pessoas
                            (
                                codigo,
                                nome,
                                tipo_vinculo,
                                ativo,
                                observacoes,
                                criado_por,
                                atualizado_por
                            )
                        VALUES (?, ?, ?, 1, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $code,
                        $name,
                        $linkType !== '' ? $linkType : null,
                        $notes !== '' ? $notes : null,
                        $userId,
                        $userId,
                    ]);

                    $personId =
                        (int) $pdo_intra->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    if ((string) $e->getCode() !== '23000') {
                        throw $e;
                    }

                    usleep(30000);
                }
            }

            if ($personId <= 0) {
                throw new RuntimeException(
                    'Não foi possível gerar o código da pessoa.'
                );
            }

            $orderStmt = $pdo_intra->prepare("
                SELECT COALESCE(MAX(ordem), 0) + 1
                FROM governanca_pessoa_funcoes
                WHERE funcao_id = ?
            ");
            $orderStmt->execute([$functionId]);
            $order = (int) $orderStmt->fetchColumn();

            $linkStmt = $pdo_intra->prepare("
                INSERT INTO governanca_pessoa_funcoes
                    (
                        pessoa_id,
                        funcao_id,
                        principal,
                        ordem,
                        data_inicio,
                        ativo,
                        criado_por,
                        atualizado_por
                    )
                VALUES (?, ?, 1, ?, CURDATE(), 1, ?, ?)
            ");
            $linkStmt->execute([
                $personId,
                $functionId,
                $order,
                $userId,
                $userId,
            ]);

            $linkId = (int) $pdo_intra->lastInsertId();

            govMLog(
                $pdo_intra,
                $userId,
                (int) $function['estrutura_id'],
                'PESSOA',
                $personId,
                'CRIAR_E_VINCULAR',
                null,
                [
                    'codigo' => $code,
                    'nome' => $name,
                    'funcao_id' => $functionId,
                    'vinculo_id' => $linkId,
                ]
            );

            $pdo_intra->commit();

            govMJson([
                'ok' => true,
                'message' =>
                    'Pessoa cadastrada e vinculada com sucesso.',
            ], 201);

        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }

            throw $e;
        }
    }

    if ($action === 'link_existing_person') {
        govMError(
            'Esta operação foi desativada. Cada pessoa pode possuir apenas uma função/cargo ativo. Para mudar de função ou setor, edite o vínculo atual.',
            409
        );
    }

    if ($action === 'update_person_link') {
        $linkId = (int) ($body['vinculo_id'] ?? 0);
        $name = trim((string) ($body['nome'] ?? ''));
        $linkType = trim((string) ($body['tipo_vinculo'] ?? ''));
        $notes = trim((string) ($body['observacoes'] ?? ''));

        if ($linkId <= 0 || $name === '') {
            govMError('Vínculo e nome são obrigatórios.');
        }

        $oldLink = govMLink($pdo_intra, $linkId);

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $oldLink['estrutura_codigo']
        );

        $before = [
            'nome' => (string) $oldLink['pessoa_nome'],
            'tipo_vinculo' => (string) ($oldLink['tipo_vinculo'] ?? ''),
            'observacoes' => (string) ($oldLink['pessoa_observacoes'] ?? ''),
            'funcao_id' => (int) $oldLink['funcao_id'],
        ];

        $stmt = $pdo_intra->prepare("
            UPDATE governanca_pessoas
            SET
                nome = ?,
                tipo_vinculo = ?,
                observacoes = ?,
                atualizado_por = ?
            WHERE id = ?
              AND ativo = 1
        ");
        $stmt->execute([
            $name,
            $linkType !== '' ? $linkType : null,
            $notes !== '' ? $notes : null,
            $userId,
            (int) $oldLink['pessoa_id'],
        ]);

        govMLog(
            $pdo_intra,
            $userId,
            (int) $oldLink['estrutura_id'],
            'PESSOA',
            (int) $oldLink['pessoa_id'],
            'EDITAR',
            $before,
            [
                'nome' => $name,
                'tipo_vinculo' => $linkType,
                'observacoes' => $notes,
                'funcao_id' => (int) $oldLink['funcao_id'],
            ]
        );

        govMJson([
            'ok' => true,
            'message' => 'Pessoa atualizada com sucesso.',
        ]);
    }

    if ($action === 'move_person_link') {
        $linkId = (int) ($body['vinculo_id'] ?? 0);
        $newFunctionId = (int) ($body['nova_funcao_id'] ?? 0);

        if ($linkId <= 0 || $newFunctionId <= 0) {
            govMError('Vínculo atual e nova função são obrigatórios.');
        }

        $oldLink = govMLink($pdo_intra, $linkId);

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $oldLink['estrutura_codigo']
        );

        $newFunction = govMFunction(
            $pdo_intra,
            $newFunctionId
        );

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $newFunction['estrutura_codigo']
        );

        if ((int) $oldLink['funcao_id'] === $newFunctionId) {
            govMError('A pessoa já está nesta função/cargo.', 409);
        }

        $otherActive = $pdo_intra->prepare("
            SELECT
                PF.id,
                F.nome AS funcao_nome,
                E.nome AS estrutura_nome
            FROM governanca_pessoa_funcoes PF
            JOIN governanca_funcoes F
              ON F.id = PF.funcao_id
            JOIN governanca_estruturas E
              ON E.id = F.estrutura_id
            WHERE PF.pessoa_id = ?
              AND PF.id <> ?
              AND PF.ativo = 1
              AND (PF.data_fim IS NULL OR PF.data_fim >= CURDATE())
            LIMIT 1
        ");
        $otherActive->execute([
            (int) $oldLink['pessoa_id'],
            $linkId,
        ]);

        $otherActiveRow = $otherActive->fetch(PDO::FETCH_ASSOC);

        if ($otherActiveRow) {
            govMError(
                'Esta pessoa já possui outro vínculo ativo em '
                . (string) $otherActiveRow['estrutura_nome']
                . ' / '
                . (string) $otherActiveRow['funcao_nome']
                . '. Encerre o vínculo duplicado antes de movimentar.',
                409
            );
        }

        $pdo_intra->beginTransaction();

        try {
            $closeStmt = $pdo_intra->prepare("
                UPDATE governanca_pessoa_funcoes
                SET
                    ativo = 0,
                    data_fim = CURDATE(),
                    atualizado_por = ?
                WHERE id = ?
                  AND ativo = 1
            ");
            $closeStmt->execute([
                $userId,
                $linkId,
            ]);

            $orderStmt = $pdo_intra->prepare("
                SELECT COALESCE(MAX(ordem), 0) + 1
                FROM governanca_pessoa_funcoes
                WHERE funcao_id = ?
            ");
            $orderStmt->execute([$newFunctionId]);
            $order = (int) $orderStmt->fetchColumn();

            $newLinkStmt = $pdo_intra->prepare("
                INSERT INTO governanca_pessoa_funcoes
                    (
                        pessoa_id,
                        funcao_id,
                        principal,
                        ordem,
                        data_inicio,
                        data_fim,
                        ativo,
                        criado_por,
                        atualizado_por
                    )
                VALUES (?, ?, 1, ?, CURDATE(), NULL, 1, ?, ?)
            ");
            $newLinkStmt->execute([
                (int) $oldLink['pessoa_id'],
                $newFunctionId,
                $order,
                $userId,
                $userId,
            ]);

            $newLinkId = (int) $pdo_intra->lastInsertId();

            govMLog(
                $pdo_intra,
                $userId,
                (int) $newFunction['estrutura_id'],
                'PESSOA_FUNCAO',
                $newLinkId,
                'MOVIMENTAR',
                [
                    'vinculo_id' => $linkId,
                    'estrutura_codigo' => (string) $oldLink['estrutura_codigo'],
                    'funcao_id' => (int) $oldLink['funcao_id'],
                    'funcao_nome' => (string) $oldLink['funcao_nome'],
                ],
                [
                    'vinculo_id' => $newLinkId,
                    'estrutura_codigo' => (string) $newFunction['estrutura_codigo'],
                    'funcao_id' => $newFunctionId,
                    'funcao_nome' => (string) $newFunction['nome'],
                ]
            );

            $pdo_intra->commit();

            govMJson([
                'ok' => true,
                'message' => 'Pessoa movimentada para a nova função com sucesso.',
            ]);

        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }

            throw $e;
        }
    }

    if ($action === 'deactivate_person_link') {
        $linkId = (int) ($body['vinculo_id'] ?? 0);

        if ($linkId <= 0) {
            govMError('Vínculo inválido.');
        }

        $link = govMLink($pdo_intra, $linkId);

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $link['estrutura_codigo']
        );

        $pdo_intra->beginTransaction();

        try {
            $stmt = $pdo_intra->prepare("
                UPDATE governanca_pessoa_funcoes
                SET
                    ativo = 0,
                    data_fim = CURDATE(),
                    atualizado_por = ?
                WHERE id = ?
                  AND ativo = 1
            ");
            $stmt->execute([$userId, $linkId]);

            $remainingStmt = $pdo_intra->prepare("
                SELECT COUNT(*)
                FROM governanca_pessoa_funcoes
                WHERE pessoa_id = ?
                  AND ativo = 1
                  AND (
                      data_fim IS NULL
                      OR data_fim >= CURDATE()
                  )
            ");
            $remainingStmt->execute([
                (int) $link['pessoa_id']
            ]);
            $remaining = (int) $remainingStmt->fetchColumn();

            $personInactivated = false;

            if ($remaining === 0) {
                $personStmt = $pdo_intra->prepare("
                    UPDATE governanca_pessoas
                    SET ativo = 0, atualizado_por = ?
                    WHERE id = ?
                ");
                $personStmt->execute([
                    $userId,
                    (int) $link['pessoa_id'],
                ]);

                $personInactivated = true;
            }

            govMLog(
                $pdo_intra,
                $userId,
                (int) $link['estrutura_id'],
                'PESSOA_FUNCAO',
                $linkId,
                'INATIVAR',
                $link,
                [
                    'vinculo_ativo' => 0,
                    'pessoa_inativada' =>
                        $personInactivated,
                ]
            );

            $pdo_intra->commit();

            govMJson([
                'ok' => true,
                'message' => $personInactivated
                    ? 'Vínculo inativado. Como era o último vínculo ativo, a pessoa também foi inativada.'
                    : 'Vínculo inativado com sucesso.',
            ]);

        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }

            throw $e;
        }
    }

    if ($action === 'reactivate_function') {
        $functionId = (int) ($body['funcao_id'] ?? 0);

        if ($functionId <= 0) {
            govMError('Função inválida.');
        }

        $function = govMFunctionAny($pdo_intra, $functionId);

        if (!empty($function['ativo'])) {
            govMError('Esta função já está ativa.', 409);
        }

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $function['estrutura_codigo']
        );

        $duplicate = $pdo_intra->prepare("
            SELECT id
            FROM governanca_funcoes
            WHERE estrutura_id = ?
              AND nome = ?
              AND id <> ?
              AND ativo = 1
            LIMIT 1
        ");
        $duplicate->execute([
            (int) $function['estrutura_id'],
            (string) $function['nome'],
            $functionId,
        ]);

        if ($duplicate->fetchColumn()) {
            govMError(
                'Não foi possível reativar porque já existe outra função ativa com o mesmo nome nesta estrutura.',
                409
            );
        }

        $stmt = $pdo_intra->prepare("
            UPDATE governanca_funcoes
            SET ativo = 1, atualizado_por = ?
            WHERE id = ?
              AND ativo = 0
        ");
        $stmt->execute([$userId, $functionId]);

        govMLog(
            $pdo_intra,
            $userId,
            (int) $function['estrutura_id'],
            'FUNCAO',
            $functionId,
            'REATIVAR',
            ['ativo' => 0],
            ['ativo' => 1]
        );

        govMJson([
            'ok' => true,
            'message' => 'Função/cargo reativada com sucesso.',
        ]);
    }

    if ($action === 'reactivate_person_link') {
        $linkId = (int) ($body['vinculo_id'] ?? 0);

        if ($linkId <= 0) {
            govMError('Vínculo inválido.');
        }

        $link = govMLinkAny($pdo_intra, $linkId);

        if (!empty($link['vinculo_ativo'])) {
            govMError('Este vínculo já está ativo.', 409);
        }

        if (empty($link['funcao_ativa'])) {
            govMError(
                'A função deste vínculo está inativa. Reative a função antes da pessoa.',
                409
            );
        }

        govMRequireStructure(
            $pdo_intra,
            $userId,
            (string) $link['estrutura_codigo']
        );

        $activeLink = $pdo_intra->prepare("
            SELECT PF.id, F.nome AS funcao_nome, E.nome AS estrutura_nome
            FROM governanca_pessoa_funcoes PF
            JOIN governanca_funcoes F ON F.id = PF.funcao_id
            JOIN governanca_estruturas E ON E.id = F.estrutura_id
            WHERE PF.pessoa_id = ?
              AND PF.id <> ?
              AND PF.ativo = 1
              AND (PF.data_fim IS NULL OR PF.data_fim >= CURDATE())
            LIMIT 1
        " );
        $activeLink->execute([
            (int) $link['pessoa_id'],
            $linkId,
        ]);
        $activeLinkRow = $activeLink->fetch(PDO::FETCH_ASSOC);

        if ($activeLinkRow) {
            govMError(
                'Esta pessoa já possui uma função/cargo ativo em '
                . (string) $activeLinkRow['estrutura_nome']
                . ' / '
                . (string) $activeLinkRow['funcao_nome']
                . '. Para reativar este vínculo, encerre primeiro o vínculo atual.',
                409
            );
        }

        $pdo_intra->beginTransaction();

        try {
            $personStmt = $pdo_intra->prepare("
                UPDATE governanca_pessoas
                SET ativo = 1, atualizado_por = ?
                WHERE id = ?
            ");
            $personStmt->execute([
                $userId,
                (int) $link['pessoa_id'],
            ]);

            $linkStmt = $pdo_intra->prepare("
                UPDATE governanca_pessoa_funcoes
                SET
                    ativo = 1,
                    data_fim = NULL,
                    atualizado_por = ?
                WHERE id = ?
                  AND ativo = 0
            ");
            $linkStmt->execute([
                $userId,
                $linkId,
            ]);

            govMLog(
                $pdo_intra,
                $userId,
                (int) $link['estrutura_id'],
                'PESSOA_FUNCAO',
                $linkId,
                'REATIVAR',
                [
                    'vinculo_ativo' => 0,
                    'pessoa_ativa' => (int) $link['pessoa_ativa'],
                ],
                [
                    'vinculo_ativo' => 1,
                    'pessoa_ativa' => 1,
                ]
            );

            $pdo_intra->commit();

            govMJson([
                'ok' => true,
                'message' => 'Pessoa e vínculo reativados com sucesso.',
            ]);

        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }

            throw $e;
        }
    }

    govMError('Ação não reconhecida.', 404);

} catch (Throwable $e) {
    error_log(
        'Governança Gestão: ' . $e->getMessage()
    );

    govMError(
        'Falha ao processar a operação de Governança.',
        500
    );
}
