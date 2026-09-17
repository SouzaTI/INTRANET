<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function govManageJson(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function govManageError(string $message, int $status = 400, array $extra = []): never {
    govManageJson(array_merge([
        'ok' => false,
        'error' => $message,
    ], $extra), $status);
}

function govManageBody(): array {
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $body = json_decode($raw, true);

    if (!is_array($body)) {
        govManageError('JSON inválido.');
    }

    return $body;
}

function govManageCsrf(): void {
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = (string) ($_SESSION['governanca_csrf'] ?? '');

    if (
        $expected === ''
        || $token === ''
        || !hash_equals($expected, $token)
    ) {
        govManageError(
            'Token de segurança inválido. Atualize a página e tente novamente.',
            419
        );
    }
}

function govManageIsAdmin(PDO $pdo, int $userId): bool {
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

function govManageStructures(PDO $pdo): array {
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

function govManageGroupIds(PDO $pdo, int $userId): array {
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

function govManageGrants(PDO $pdo, int $userId, array $groupIds): array {
    $conditions = [
        "(alvo_tipo = 'USUARIO' AND alvo_id = ?)"
    ];
    $params = [$userId];

    if ($groupIds) {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $conditions[] = "(alvo_tipo = 'GRUPO' AND alvo_id IN ($placeholders))";

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

function govManageScope(PDO $pdo, int $userId): array {
    $structures = govManageStructures($pdo);
    $isAdmin = govManageIsAdmin($pdo, $userId);

    $byCode = [];
    $byId = [];
    $children = [];

    foreach ($structures as $row) {
        $code = (string) $row['codigo'];
        $id = (int) $row['id'];

        $byCode[$code] = $row;
        $byId[$id] = $row;

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

    $groupIds = govManageGroupIds($pdo, $userId);
    $grants = govManageGrants($pdo, $userId, $groupIds);

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

function govManageRequireStructure(
    PDO $pdo,
    int $userId,
    string $code
): array {
    $scope = govManageScope($pdo, $userId);

    if (!isset($scope['codes'][$code])) {
        govManageError(
            'Você não possui permissão GERENCIAR para esta estrutura.',
            403
        );
    }

    foreach ($scope['structures'] as $row) {
        if ((string) $row['codigo'] === $code) {
            return $row;
        }
    }

    govManageError('Estrutura não encontrada ou inativa.', 404);
}

function govManageLog(
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
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            $after === null
                ? null
                : json_encode(
                    $after,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log(
            'Governança: falha ao registrar log: ' . $e->getMessage()
        );
    }
}

function govManageNextPersonCode(PDO $pdo): string {
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

function govManageFunctionsInScope(
    PDO $pdo,
    array $scopeCodes
): array {
    if (!$scopeCodes) {
        return [];
    }

    $codes = array_keys($scopeCodes);
    $placeholders = implode(',', array_fill(0, count($codes), '?'));

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

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    govManageError('Sessão inválida ou expirada.', 401);
}

$action = trim((string) ($_GET['action'] ?? 'scope'));

try {
    if ($action === 'scope') {
        $scope = govManageScope($pdo_intra, $userId);

        govManageJson([
            'ok' => true,
            'is_admin' => (bool) $scope['is_admin'],
            'can_manage' => !empty($scope['codes']),
            'structures' => array_map(
                static fn(array $row): array => [
                    'id' => (int) $row['id'],
                    'codigo' => (string) $row['codigo'],
                    'pai_codigo' => (string) ($row['pai_codigo'] ?? ''),
                    'nome' => (string) $row['nome'],
                    'ordem' => (int) $row['ordem'],
                ],
                $scope['structures']
            ),
            'functions' => govManageFunctionsInScope(
                $pdo_intra,
                $scope['codes']
            ),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        govManageError('Método não permitido.', 405);
    }

    govManageCsrf();
    $body = govManageBody();

    if ($action === 'create_function') {
        $structureCode = trim(
            (string) ($body['estrutura_codigo'] ?? '')
        );
        $name = trim((string) ($body['nome'] ?? ''));
        $description = trim(
            (string) ($body['descricao'] ?? '')
        );

        if ($structureCode === '') {
            govManageError('Selecione a estrutura.');
        }

        if ($name === '') {
            govManageError('Informe o nome da função/cargo.');
        }

        if (mb_strlen($name) > 180) {
            govManageError(
                'O nome da função/cargo deve ter no máximo 180 caracteres.'
            );
        }

        if (mb_strlen($description) > 5000) {
            govManageError(
                'A descrição deve ter no máximo 5.000 caracteres.'
            );
        }

        $structure = govManageRequireStructure(
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
            if (!empty($existing['ativo'])) {
                govManageError(
                    'Já existe uma função/cargo com este nome nesta estrutura.',
                    409
                );
            }

            govManageError(
                'Já existe uma função inativa com este nome. A reativação será tratada na etapa de edição.',
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

        govManageLog(
            $pdo_intra,
            $userId,
            $structureId,
            'FUNCAO',
            $functionId,
            'CRIAR',
            null,
            [
                'id' => $functionId,
                'estrutura_codigo' => $structureCode,
                'nome' => $name,
                'descricao' => $description,
                'ordem' => $order,
            ]
        );

        govManageJson([
            'ok' => true,
            'message' => 'Função/cargo cadastrada com sucesso.',
            'function' => [
                'id' => $functionId,
                'estrutura_codigo' => $structureCode,
                'nome' => $name,
                'descricao' => $description,
                'ordem' => $order,
            ],
        ], 201);
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
            govManageError('Selecione a função/cargo.');
        }

        if ($name === '') {
            govManageError('Informe o nome da pessoa.');
        }

        if (mb_strlen($name) > 180) {
            govManageError(
                'O nome da pessoa deve ter no máximo 180 caracteres.'
            );
        }

        if (mb_strlen($linkType) > 80) {
            govManageError(
                'O tipo de vínculo deve ter no máximo 80 caracteres.'
            );
        }

        if (mb_strlen($notes) > 5000) {
            govManageError(
                'As observações devem ter no máximo 5.000 caracteres.'
            );
        }

        $functionStmt = $pdo_intra->prepare("
            SELECT
                F.id,
                F.nome,
                F.estrutura_id,
                E.codigo AS estrutura_codigo,
                E.nome AS estrutura_nome
            FROM governanca_funcoes F
            JOIN governanca_estruturas E
              ON E.id = F.estrutura_id
            WHERE F.id = ?
              AND F.ativo = 1
              AND E.ativo = 1
            LIMIT 1
        ");
        $functionStmt->execute([$functionId]);
        $function = $functionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$function) {
            govManageError('Função/cargo não encontrada.', 404);
        }

        $structureCode = (string) $function['estrutura_codigo'];

        govManageRequireStructure(
            $pdo_intra,
            $userId,
            $structureCode
        );

        /*
         * Evita duplicação acidental simples.
         * Se já houver pessoa ativa com o mesmo nome, não cria outra.
         * Vincular pessoa existente será uma ação própria na próxima etapa.
         */
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
            govManageError(
                'Já existe uma pessoa ativa com este nome. Use a futura opção de vincular pessoa existente.',
                409,
                [
                    'existing_person_id' => (int) $existingPerson['id'],
                    'existing_person_code' => (string) $existingPerson['codigo'],
                ]
            );
        }

        $pdo_intra->beginTransaction();

        try {
            $personId = 0;
            $code = '';

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = govManageNextPersonCode($pdo_intra);

                try {
                    $personStmt = $pdo_intra->prepare("
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

                    $personStmt->execute([
                        $code,
                        $name,
                        $linkType !== '' ? $linkType : null,
                        $notes !== '' ? $notes : null,
                        $userId,
                        $userId,
                    ]);

                    $personId = (int) $pdo_intra->lastInsertId();
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

            govManageLog(
                $pdo_intra,
                $userId,
                (int) $function['estrutura_id'],
                'PESSOA',
                $personId,
                'CRIAR_E_VINCULAR',
                null,
                [
                    'pessoa' => [
                        'id' => $personId,
                        'codigo' => $code,
                        'nome' => $name,
                        'tipo_vinculo' => $linkType,
                    ],
                    'vinculo' => [
                        'id' => $linkId,
                        'funcao_id' => $functionId,
                        'funcao_nome' => (string) $function['nome'],
                        'estrutura_codigo' => $structureCode,
                    ],
                ]
            );

            $pdo_intra->commit();

            govManageJson([
                'ok' => true,
                'message' => 'Pessoa cadastrada e vinculada com sucesso.',
                'person' => [
                    'id' => $personId,
                    'codigo' => $code,
                    'nome' => $name,
                    'tipo_vinculo' => $linkType,
                    'funcao_id' => $functionId,
                    'funcao_nome' => (string) $function['nome'],
                    'estrutura_codigo' => $structureCode,
                ],
            ], 201);

        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }

            throw $e;
        }
    }

    govManageError('Ação não reconhecida.', 404);

} catch (Throwable $e) {
    error_log(
        'Governança Gestão: ' . $e->getMessage()
    );

    govManageError(
        'Falha ao processar a operação de Governança.',
        500
    );
}
