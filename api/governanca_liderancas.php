<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function govLJson(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function govLError(string $message, int $status = 400): never {
    govLJson(['ok' => false, 'error' => $message], $status);
}

function govLBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $body = json_decode($raw, true);
    if (!is_array($body)) {
        govLError('JSON inválido.');
    }

    return $body;
}

function govLRequireCsrf(): void {
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = (string) ($_SESSION['governanca_csrf'] ?? '');

    if ($token === '' || $expected === '' || !hash_equals($expected, $token)) {
        govLError('Token de segurança inválido. Atualize a página.', 419);
    }
}

function govLIsAdmin(PDO $pdo, int $userId): bool {
    if (!empty($_SESSION['is_admin'])) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT
            MAX(CASE WHEN UP.is_admin = 1 THEN 1 ELSE 0 END) AS individual_admin,
            MAX(CASE WHEN G.is_admin = 1 THEN 1 ELSE 0 END) AS group_admin
        FROM (SELECT ? AS usuario_id) X
        LEFT JOIN usuarios_permissoes UP ON UP.usuario_id = X.usuario_id
        LEFT JOIN usuarios_grupos UG ON UG.usuario_id = X.usuario_id
        LEFT JOIN grupos_intranet G ON G.id = UG.grupo_id
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return !empty($row['individual_admin']) || !empty($row['group_admin']);
}

function govLNextPersonCode(PDO $pdo): string {
    $last = (int) ($pdo->query("
        SELECT MAX(CAST(SUBSTRING(codigo, 2) AS UNSIGNED))
        FROM governanca_pessoas
        WHERE codigo REGEXP '^P[0-9]+$'
    ")->fetchColumn() ?: 0);

    return 'P' . str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
}

function govLStructures(PDO $pdo): array {
    return $pdo->query("
        SELECT E.id, E.codigo, E.nome, P.nome AS pai_nome, E.ordem
        FROM governanca_estruturas E
        LEFT JOIN governanca_estruturas P ON P.id = E.pai_id
        WHERE E.ativo = 1
        ORDER BY COALESCE(P.ordem, E.ordem), E.ordem, E.nome
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govLUsers(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT id, name, firstname, realname
        FROM glpi_users
        WHERE is_active = 1
        ORDER BY firstname, realname, name
    ");

    $users = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $fullName = trim(
            trim((string) ($row['firstname'] ?? '')) . ' '
            . trim((string) ($row['realname'] ?? ''))
        );
        $login = trim((string) ($row['name'] ?? ''));

        $users[] = [
            'id' => (int) $row['id'],
            'login' => $login,
            'nome' => $fullName !== '' ? $fullName : $login,
            'label' => ($fullName !== '' ? $fullName : 'Usuário #' . (int) $row['id'])
                . ($login !== '' ? ' · ' . $login : ''),
        ];
    }

    return $users;
}

function govLRows(PDO $pdo): array {
    return $pdo->query("
        SELECT
            L.id,
            L.estrutura_id,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome,
            L.pessoa_id,
            P.nome AS pessoa_nome,
            P.glpi_user_id,
            L.tipo,
            L.data_inicio,
            L.data_fim,
            L.ativo
        FROM governanca_liderancas L
        JOIN governanca_estruturas E ON E.id = L.estrutura_id
        JOIN governanca_pessoas P ON P.id = L.pessoa_id
        ORDER BY L.ativo DESC, E.nome, P.nome, L.id
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govLLog(
    PDO $pdo,
    int $userId,
    int $structureId,
    int $leadershipId,
    string $action,
    mixed $before,
    mixed $after
): void {
    $stmt = $pdo->prepare("
        INSERT INTO governanca_logs
            (usuario_id, estrutura_id, entidade, entidade_id, acao,
             dados_antes, dados_depois, ip)
        VALUES (?, ?, 'LIDERANCA', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $structureId,
        $leadershipId,
        $action,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
        $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    govLError('Sessão inválida ou expirada.', 401);
}

if (!govLIsAdmin($pdo_intra, $userId)) {
    govLError('Acesso permitido somente para administradores.', 403);
}

$action = trim((string) ($_GET['action'] ?? 'bootstrap'));

try {
    if ($action === 'bootstrap') {
        govLJson([
            'ok' => true,
            'structures' => govLStructures($pdo_intra),
            'users' => govLUsers($pdo_glpi),
            'leaderships' => govLRows($pdo_intra),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        govLError('Método não permitido.', 405);
    }

    govLRequireCsrf();
    $body = govLBody();

    if ($action === 'save') {
        $leadershipIdRequested = (int) ($body['lideranca_id'] ?? 0);
        $personIdRequested = (int) ($body['pessoa_id'] ?? 0);
        $structureCode = trim((string) ($body['estrutura_codigo'] ?? ''));
        $glpiUserId = (int) ($body['glpi_user_id'] ?? 0);
        $personName = trim((string) ($body['pessoa_nome'] ?? ''));
        $type = mb_strtoupper(trim((string) ($body['tipo'] ?? 'LIDER')), 'UTF-8');

        if ($structureCode === '' || $personName === '') {
            govLError('Área e nome do líder são obrigatórios.');
        }

        if (mb_strlen($personName) > 180 || mb_strlen($type) > 40) {
            govLError('Nome ou tipo de liderança ultrapassa o tamanho permitido.');
        }

        $structureStmt = $pdo_intra->prepare("
            SELECT id, codigo, nome
            FROM governanca_estruturas
            WHERE codigo = ? AND ativo = 1
            LIMIT 1
        ");
        $structureStmt->execute([$structureCode]);
        $structure = $structureStmt->fetch(PDO::FETCH_ASSOC);
        if (!$structure) {
            govLError('Área não encontrada ou inativa.', 404);
        }

        if ($glpiUserId > 0) {
            $userStmt = $pdo_glpi->prepare("
                SELECT id
                FROM glpi_users
                WHERE id = ? AND is_active = 1
                LIMIT 1
            ");
            $userStmt->execute([$glpiUserId]);
            if (!$userStmt->fetchColumn()) {
                govLError('Usuário da intranet não encontrado ou inativo.', 404);
            }
        }

        $pdo_intra->beginTransaction();

        try {
            $person = false;

            if ($leadershipIdRequested > 0 && $personIdRequested > 0) {
                $pendingStmt = $pdo_intra->prepare("
                    SELECT P.id, P.glpi_user_id
                    FROM governanca_liderancas L
                    JOIN governanca_pessoas P ON P.id = L.pessoa_id
                    WHERE L.id = ?
                      AND L.pessoa_id = ?
                      AND L.estrutura_id = ?
                      AND L.ativo = 1
                    LIMIT 1
                ");
                $pendingStmt->execute([
                    $leadershipIdRequested,
                    $personIdRequested,
                    (int) $structure['id'],
                ]);
                $person = $pendingStmt->fetch(PDO::FETCH_ASSOC);

                if (!$person) {
                    govLError('A liderança que seria vinculada não foi encontrada.', 404);
                }
            }

            if (!$person && $glpiUserId > 0) {
                $personStmt = $pdo_intra->prepare("
                    SELECT id, glpi_user_id
                    FROM governanca_pessoas
                    WHERE glpi_user_id = ?
                    LIMIT 1
                ");
                $personStmt->execute([$glpiUserId]);
                $person = $personStmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$person) {
                $personStmt = $pdo_intra->prepare("
                    SELECT id, glpi_user_id
                    FROM governanca_pessoas
                    WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?))
                    LIMIT 1
                ");
                $personStmt->execute([$personName]);
                $person = $personStmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($person) {
                $existingGlpi = (int) ($person['glpi_user_id'] ?? 0);
                if ($glpiUserId > 0 && $existingGlpi > 0 && $existingGlpi !== $glpiUserId) {
                    govLError('A pessoa já está vinculada a outro usuário da intranet.', 409);
                }

                if ($glpiUserId > 0) {
                    $duplicateUser = $pdo_intra->prepare("
                        SELECT id
                        FROM governanca_pessoas
                        WHERE glpi_user_id = ? AND id <> ?
                        LIMIT 1
                    ");
                    $duplicateUser->execute([$glpiUserId, (int) $person['id']]);
                    if ($duplicateUser->fetchColumn()) {
                        govLError('Este usuário da intranet já está vinculado a outra pessoa na Governança.', 409);
                    }
                }

                $personId = (int) $person['id'];
                $resolvedGlpiUserId = $glpiUserId > 0
                    ? $glpiUserId
                    : ($existingGlpi > 0 ? $existingGlpi : null);
                $updatePerson = $pdo_intra->prepare("
                    UPDATE governanca_pessoas
                    SET nome = ?, glpi_user_id = ?, ativo = 1, atualizado_por = ?
                    WHERE id = ?
                ");
                $updatePerson->execute([
                    $personName,
                    $resolvedGlpiUserId,
                    $userId,
                    $personId,
                ]);
            } else {
                $insertPerson = $pdo_intra->prepare("
                    INSERT INTO governanca_pessoas
                        (codigo, glpi_user_id, nome, tipo_vinculo, ativo,
                         observacoes, criado_por, atualizado_por)
                    VALUES (?, ?, ?, 'Interno', 1, 'Liderança cadastrada na Governança', ?, ?)
                ");
                $insertPerson->execute([
                    govLNextPersonCode($pdo_intra),
                    $glpiUserId > 0 ? $glpiUserId : null,
                    $personName,
                    $userId,
                    $userId,
                ]);
                $personId = (int) $pdo_intra->lastInsertId();
            }

            $existingStmt = $pdo_intra->prepare("
                SELECT *
                FROM governanca_liderancas
                WHERE estrutura_id = ? AND pessoa_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $existingStmt->execute([(int) $structure['id'], $personId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $leadershipId = (int) $existing['id'];
                $update = $pdo_intra->prepare("
                    UPDATE governanca_liderancas
                    SET tipo = ?, data_inicio = COALESCE(data_inicio, CURDATE()),
                        data_fim = NULL, ativo = 1, atualizado_por = ?
                    WHERE id = ?
                ");
                $update->execute([$type !== '' ? $type : 'LIDER', $userId, $leadershipId]);
                $logAction = empty($existing['ativo']) ? 'REATIVAR' : 'EDITAR';
            } else {
                $insert = $pdo_intra->prepare("
                    INSERT INTO governanca_liderancas
                        (estrutura_id, pessoa_id, tipo, data_inicio, data_fim,
                         ativo, observacoes, criado_por, atualizado_por)
                    VALUES (?, ?, ?, CURDATE(), NULL, 1, NULL, ?, ?)
                ");
                $insert->execute([
                    (int) $structure['id'],
                    $personId,
                    $type !== '' ? $type : 'LIDER',
                    $userId,
                    $userId,
                ]);
                $leadershipId = (int) $pdo_intra->lastInsertId();
                $logAction = 'CRIAR';
            }

            govLLog(
                $pdo_intra,
                $userId,
                (int) $structure['id'],
                $leadershipId,
                $logAction,
                $existing ?: null,
                [
                    'pessoa_id' => $personId,
                    'pessoa_nome' => $personName,
                    'glpi_user_id' => $glpiUserId ?: null,
                    'estrutura_codigo' => $structureCode,
                    'tipo' => $type,
                    'ativo' => 1,
                ]
            );

            $pdo_intra->commit();

            govLJson([
                'ok' => true,
                'message' => $glpiUserId > 0
                    ? 'Liderança cadastrada e vinculada ao usuário da intranet.'
                    : 'Liderança cadastrada. O usuário da intranet ainda precisa ser vinculado.',
            ]);
        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }
            throw $e;
        }
    }

    if ($action === 'deactivate') {
        $leadershipId = (int) ($body['lideranca_id'] ?? 0);
        if ($leadershipId <= 0) {
            govLError('Liderança inválida.');
        }

        $stmt = $pdo_intra->prepare("
            SELECT * FROM governanca_liderancas WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$leadershipId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            govLError('Liderança não encontrada.', 404);
        }

        $update = $pdo_intra->prepare("
            UPDATE governanca_liderancas
            SET ativo = 0, data_fim = CURDATE(), atualizado_por = ?
            WHERE id = ? AND ativo = 1
        ");
        $update->execute([$userId, $leadershipId]);

        govLLog(
            $pdo_intra,
            $userId,
            (int) $row['estrutura_id'],
            $leadershipId,
            'INATIVAR',
            $row,
            ['ativo' => 0, 'data_fim' => date('Y-m-d')]
        );

        govLJson(['ok' => true, 'message' => 'Liderança inativada com sucesso.']);
    }

    govLError('Ação não reconhecida.', 404);
} catch (Throwable $e) {
    error_log('Governança Lideranças: ' . $e->getMessage());
    govLError('Falha ao processar a liderança.', 500);
}
