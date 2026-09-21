<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

session_save_path(sys_get_temp_dir());
$_SERVER['REQUEST_URI'] = '/scripts/configurar_liderancas_governanca.php';
require __DIR__ . '/../config.php';

$apply = in_array('--apply', $argv, true);

$structures = [
    ['CS-DIR', 'Diretoria', 'CS', 10],
    ['CS-GG', 'Gerência Geral', 'CS-DIR', 10],
    ['CS-FAC', 'Facilities', 'CS-GG', 10],
    ['CS-TI', 'T.I', 'CS-GG', 20],
    ['CS-FISC', 'Fiscal', 'CS-GG', 30],
    ['CS-TES', 'Tesouraria', 'CS-GG', 40],
    ['CS-CAR', 'Contas a Receber', 'CS-GG', 50],
    ['CS-CAP', 'Contas a Pagar', 'CS-GG', 60],
    ['CS-RH', 'Recursos Humanos', 'CS-GG', 70],
    ['CS-COM', 'Comercial', 'CS-GG', 80],
];

$retiredStructures = ['CS-FIN'];

$leaders = [
    ['Adelson Silva', null, 'CS-GG', 'LIDER'],
    ['Alex Cunha', 40, 'CS-FAC', 'LIDER'],
    ['Alex Cunha', 40, 'CS-TI', 'LIDER'],
    ['Anderson Souza', 19, 'CS-DIR', 'DIRETOR'],
    ['Daniela Roza', 21, 'CS-FISC', 'LIDER'],
    ['Dayane Correia', 71, 'CS-CAR', 'LIDER'],
    ['Eloise Tancredi', 108, 'CS-RH', 'LIDER'],
    ['Fabio Souza', 23, 'CS-DIR', 'DIRETOR'],
    ['Leila Moreira', 28, 'CS-TES', 'LIDER'],
    ['Milton Michels', null, 'CS-CAP', 'LIDER'],
    ['Wilson Soares', 100, 'CS-COM', 'LIDER'],
];

function nextPersonCode(PDO $pdo): string {
    $last = (int) ($pdo->query("
        SELECT MAX(CAST(SUBSTRING(codigo, 2) AS UNSIGNED))
        FROM governanca_pessoas
        WHERE codigo REGEXP '^P[0-9]+$'
    ")->fetchColumn() ?: 0);

    return 'P' . str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
}

function userExists(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare("SELECT id FROM glpi_users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

$pdo_intra->beginTransaction();

try {
    $rootStmt = $pdo_intra->prepare("
        SELECT id FROM governanca_estruturas WHERE codigo = 'CS' AND ativo = 1 LIMIT 1
    ");
    $rootStmt->execute();
    $rootId = (int) ($rootStmt->fetchColumn() ?: 0);
    if ($rootId <= 0) {
        throw new RuntimeException('Estrutura raiz CS não encontrada.');
    }

    foreach ($structures as [$code, $name, $parentCode, $order]) {
        $parentStmt = $pdo_intra->prepare("
            SELECT id FROM governanca_estruturas WHERE codigo = ? AND ativo = 1 LIMIT 1
        ");
        $parentStmt->execute([$parentCode]);
        $parentId = (int) ($parentStmt->fetchColumn() ?: 0);
        if ($parentId <= 0) {
            throw new RuntimeException("Estrutura pai {$parentCode} não encontrada para {$code}.");
        }

        $find = $pdo_intra->prepare("SELECT id FROM governanca_estruturas WHERE codigo = ? LIMIT 1");
        $find->execute([$code]);
        $id = (int) ($find->fetchColumn() ?: 0);

        if ($id > 0) {
            $updateStructure = $pdo_intra->prepare("
                UPDATE governanca_estruturas
                SET pai_id = ?, nome = ?, ordem = ?, ativo = 1
                WHERE id = ?
            ");
            $updateStructure->execute([$parentId, $name, $order, $id]);
            echo "estrutura ajustada: {$code} | {$parentCode} > {$name}\n";
            continue;
        }

        $insert = $pdo_intra->prepare("
            INSERT INTO governanca_estruturas
                (codigo, pai_id, nome, tipo_no, vinculo, responsavel_empresa,
                 ordem, ativo, descricao, criado_por, atualizado_por)
            VALUES (?, ?, ?, 'AREA', NULL, 'Comercial Souza', ?, 1, NULL, NULL, NULL)
        ");
        $insert->execute([$code, $parentId, $name, $order]);
        echo "estrutura criada: {$code} | {$parentCode} > {$name}\n";
    }

    foreach ($leaders as [$name, $glpiUserId, $structureCode, $type]) {
        if ($glpiUserId !== null && !userExists($pdo_glpi, $glpiUserId)) {
            throw new RuntimeException("Usuário GLPI #{$glpiUserId} não está ativo para {$name}.");
        }

        $structureStmt = $pdo_intra->prepare("
            SELECT id, nome FROM governanca_estruturas WHERE codigo = ? AND ativo = 1 LIMIT 1
        ");
        $structureStmt->execute([$structureCode]);
        $structure = $structureStmt->fetch(PDO::FETCH_ASSOC);
        if (!$structure) {
            throw new RuntimeException("Estrutura {$structureCode} não encontrada.");
        }

        $person = false;
        if ($glpiUserId !== null) {
            $personStmt = $pdo_intra->prepare("
                SELECT id, glpi_user_id FROM governanca_pessoas WHERE glpi_user_id = ? LIMIT 1
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
            $personStmt->execute([$name]);
            $person = $personStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($person) {
            $personId = (int) $person['id'];
            $currentUserId = (int) ($person['glpi_user_id'] ?? 0);
            if ($glpiUserId !== null && $currentUserId > 0 && $currentUserId !== $glpiUserId) {
                throw new RuntimeException("{$name} já está ligado a outro usuário da intranet.");
            }

            $updatePerson = $pdo_intra->prepare("
                UPDATE governanca_pessoas
                SET nome = ?, glpi_user_id = ?, ativo = 1
                WHERE id = ?
            ");
            $resolvedUserId = $glpiUserId ?? ($currentUserId > 0 ? $currentUserId : null);
            $updatePerson->execute([$name, $resolvedUserId, $personId]);
        } else {
            $insertPerson = $pdo_intra->prepare("
                INSERT INTO governanca_pessoas
                    (codigo, glpi_user_id, nome, tipo_vinculo, ativo,
                     observacoes, criado_por, atualizado_por)
                VALUES (?, ?, ?, 'Interno', 1,
                        'Liderança inicial da Governança', NULL, NULL)
            ");
            $insertPerson->execute([nextPersonCode($pdo_intra), $glpiUserId, $name]);
            $personId = (int) $pdo_intra->lastInsertId();
        }

        $leadershipStmt = $pdo_intra->prepare("
            SELECT id FROM governanca_liderancas
            WHERE estrutura_id = ? AND pessoa_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $leadershipStmt->execute([(int) $structure['id'], $personId]);
        $leadershipId = (int) ($leadershipStmt->fetchColumn() ?: 0);

        if ($leadershipId > 0) {
            $updateLeadership = $pdo_intra->prepare("
                UPDATE governanca_liderancas
                SET tipo = ?, data_inicio = COALESCE(data_inicio, CURDATE()),
                    data_fim = NULL, ativo = 1
                WHERE id = ?
            ");
            $updateLeadership->execute([$type, $leadershipId]);
            echo "liderança atualizada: {$name} | {$structure['nome']}\n";
        } else {
            $insertLeadership = $pdo_intra->prepare("
                INSERT INTO governanca_liderancas
                    (estrutura_id, pessoa_id, tipo, data_inicio, data_fim,
                     ativo, observacoes, criado_por, atualizado_por)
                VALUES (?, ?, ?, CURDATE(), NULL, 1, 'Cadastro inicial informado pela gestão', NULL, NULL)
            ");
            $insertLeadership->execute([(int) $structure['id'], $personId, $type]);
            echo "liderança criada: {$name} | {$structure['nome']}\n";
        }

        if ($glpiUserId === null) {
            echo "  pendente vínculo com usuário da intranet\n";
        }
    }

    $desiredByName = [];
    foreach ($leaders as [$name, $glpiUserId, $structureCode, $type]) {
        $desiredByName[$name][] = $structureCode;
    }

    foreach ($desiredByName as $name => $desiredCodes) {
        $placeholders = implode(',', array_fill(0, count($desiredCodes), '?'));
        $params = array_merge([$name], $desiredCodes);
        $cleanup = $pdo_intra->prepare("
            UPDATE governanca_liderancas L
            JOIN governanca_pessoas P ON P.id = L.pessoa_id
            JOIN governanca_estruturas E ON E.id = L.estrutura_id
            SET L.ativo = 0, L.data_fim = CURDATE()
            WHERE LOWER(TRIM(P.nome)) = LOWER(TRIM(?))
              AND L.ativo = 1
              AND E.codigo NOT IN ($placeholders)
        ");
        $cleanup->execute($params);
        if ($cleanup->rowCount() > 0) {
            echo "liderança anterior encerrada: {$name}\n";
        }
    }

    foreach ($retiredStructures as $retiredCode) {
        $retire = $pdo_intra->prepare("
            UPDATE governanca_estruturas
            SET ativo = 0
            WHERE codigo = ? AND ativo = 1
        ");
        $retire->execute([$retiredCode]);
        if ($retire->rowCount() > 0) {
            echo "estrutura anterior desativada: {$retiredCode}\n";
        }
    }

    if ($apply) {
        $pdo_intra->commit();
        echo "APLICADO\n";
    } else {
        $pdo_intra->rollBack();
        echo "SIMULAÇÃO: nenhuma alteração foi gravada. Use --apply para aplicar.\n";
    }
} catch (Throwable $e) {
    if ($pdo_intra->inTransaction()) {
        $pdo_intra->rollBack();
    }
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
