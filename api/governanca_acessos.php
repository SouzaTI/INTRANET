<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function govAccessJson(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function govAccessError(string $message, int $status = 400): never {
    govAccessJson(['ok' => false, 'error' => $message], $status);
}

function govAccessIsAdmin(PDO $pdo, int $userId): bool {
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

        return !empty($row['individual_admin']) || !empty($row['group_admin']);
    } catch (Throwable $e) {
        return false;
    }
}

function govAccessValidateCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['governanca_csrf'] ?? '';

    if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
        govAccessError('Token de segurança inválido. Atualize a página e tente novamente.', 419);
    }
}

function govAccessReadJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $body = json_decode($raw, true);

    if (!is_array($body)) {
        govAccessError('JSON inválido.');
    }

    return $body;
}

function govAccessConfig(PDO $pdo): bool {
    $stmt = $pdo->prepare("
        SELECT valor
          FROM governanca_configuracoes
         WHERE chave = 'permissoes_ativas'
         LIMIT 1
    ");
    $stmt->execute();

    return (string) $stmt->fetchColumn() === '1';
}

function govAccessAllPermissions(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT id,
               alvo_tipo,
               alvo_id,
               estrutura_codigo,
               inclui_descendentes,
               nivel_acesso,
               ativo,
               criado_por,
               criado_em,
               atualizado_em
          FROM governanca_permissoes
         ORDER BY alvo_tipo ASC, alvo_id ASC, estrutura_codigo ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govAccessUsers(PDO $pdoGlpi): array {
    try {
        $stmt = $pdoGlpi->query("
            SELECT id, firstname, realname
              FROM glpi_users
             WHERE is_active = 1
             ORDER BY firstname ASC, realname ASC, id ASC
        ");
    } catch (Throwable $e) {
        $stmt = $pdoGlpi->query("
            SELECT id, firstname, realname
              FROM glpi_users
             ORDER BY firstname ASC, realname ASC, id ASC
        ");
    }

    $result = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $first = trim((string) ($row['firstname'] ?? ''));
        $last = trim((string) ($row['realname'] ?? ''));
        $label = trim($first . ' ' . $last);

        if ($label === '') {
            $label = 'Usuário #' . (int) $row['id'];
        }

        $result[] = [
            'id' => (int) $row['id'],
            'label' => $label,
        ];
    }

    return $result;
}

function govAccessGroups(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM grupos_intranet ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $result = [];

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $label = '';

        foreach (['nome', 'name', 'titulo', 'descricao', 'grupo_nome'] as $field) {
            if (!empty($row[$field])) {
                $label = trim((string) $row[$field]);
                break;
            }
        }

        if ($label === '') {
            $label = 'Grupo #' . $id;
        }

        $result[] = [
            'id' => $id,
            'label' => $label,
        ];
    }

    return $result;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    govAccessError('Sessão inválida ou expirada.', 401);
}

if (!govAccessIsAdmin($pdo_intra, $userId)) {
    govAccessError('Acesso permitido somente para administradores da intranet.', 403);
}

$action = trim((string) ($_GET['action'] ?? 'bootstrap'));

try {
    if ($action === 'bootstrap') {
        govAccessJson([
            'ok' => true,
            'permissions_enabled' => govAccessConfig($pdo_intra),
            'users' => govAccessUsers($pdo_glpi),
            'groups' => govAccessGroups($pdo_intra),
            'permissions' => govAccessAllPermissions($pdo_intra),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        govAccessError('Método não permitido.', 405);
    }

    govAccessValidateCsrf();
    $body = govAccessReadJsonBody();

    if ($action === 'set_enabled') {
        $enabled = !empty($body['enabled']);

        $stmt = $pdo_intra->prepare("
            INSERT INTO governanca_configuracoes (chave, valor)
            VALUES ('permissoes_ativas', ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");
        $stmt->execute([$enabled ? '1' : '0']);

        govAccessJson([
            'ok' => true,
            'permissions_enabled' => $enabled,
        ]);
    }

    if ($action === 'delete_permission') {
        $permissionId = (int) ($body['id'] ?? 0);

        if ($permissionId <= 0) {
            govAccessError('ID da permissão inválido.');
        }

        $stmt = $pdo_intra->prepare("
            DELETE FROM governanca_permissoes
             WHERE id = ?
             LIMIT 1
        ");
        $stmt->execute([$permissionId]);

        if ($stmt->rowCount() === 0) {
            govAccessError('Permissão não encontrada.', 404);
        }

        govAccessJson([
            'ok' => true,
            'permissions_all' => govAccessAllPermissions($pdo_intra),
        ]);
    }

    if ($action === 'save') {
        $alvoTipo = strtoupper(trim((string) ($body['alvo_tipo'] ?? '')));
        $alvoId = (int) ($body['alvo_id'] ?? 0);
        $nivelAcesso = strtoupper(trim((string) ($body['nivel_acesso'] ?? 'VISUALIZAR')));
        $permissions = $body['permissions'] ?? [];

        if (!in_array($alvoTipo, ['USUARIO', 'GRUPO'], true)) {
            govAccessError('Tipo de alvo inválido.');
        }

        if ($alvoId <= 0) {
            govAccessError('ID do usuário/grupo inválido.');
        }

        if (!in_array($nivelAcesso, ['VISUALIZAR', 'GERENCIAR'], true)) {
            govAccessError('Nível de acesso inválido.');
        }

        if (!is_array($permissions)) {
            govAccessError('Lista de permissões inválida.');
        }

        if (count($permissions) > 100) {
            govAccessError('Quantidade de permissões acima do limite.');
        }

        $normalized = [];
        $hasFull = false;

        foreach ($permissions as $permission) {
            if (!is_array($permission)) {
                continue;
            }

            $code = trim((string) ($permission['estrutura_codigo'] ?? ''));
            $desc = !empty($permission['inclui_descendentes']) ? 1 : 0;

            if ($code === '') {
                continue;
            }

            if ($code !== '*' && !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $code)) {
                govAccessError('Código de estrutura inválido: ' . $code);
            }

            if ($code === '*') {
                $normalized = [[
                    'estrutura_codigo' => '*',
                    'inclui_descendentes' => 1,
                ]];
                $hasFull = true;
                break;
            }

            $normalized[$code] = [
                'estrutura_codigo' => $code,
                'inclui_descendentes' => $desc,
            ];
        }

        if (!$hasFull) {
            $normalized = array_values($normalized);
        }

        $pdo_intra->beginTransaction();

        try {
            $delete = $pdo_intra->prepare("
                DELETE FROM governanca_permissoes
                 WHERE alvo_tipo = ?
                   AND alvo_id = ?
            ");
            $delete->execute([$alvoTipo, $alvoId]);

            if ($normalized) {
                $insert = $pdo_intra->prepare("
                    INSERT INTO governanca_permissoes
                        (
                            alvo_tipo,
                            alvo_id,
                            estrutura_codigo,
                            inclui_descendentes,
                            nivel_acesso,
                            ativo,
                            criado_por
                        )
                    VALUES (?, ?, ?, ?, ?, 1, ?)
                ");

                foreach ($normalized as $permission) {
                    $insert->execute([
                        $alvoTipo,
                        $alvoId,
                        $permission['estrutura_codigo'],
                        (int) $permission['inclui_descendentes'],
                        $nivelAcesso,
                        $userId,
                    ]);
                }
            }

            $pdo_intra->commit();
        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }
            throw $e;
        }

        govAccessJson([
            'ok' => true,
            'permissions_all' => govAccessAllPermissions($pdo_intra),
        ]);
    }

    govAccessError('Ação desconhecida.', 404);

} catch (Throwable $e) {
    error_log('Governança Acessos: ' . $e->getMessage());
    govAccessError('Falha ao processar a Gestão de Acessos.', 500);
}
