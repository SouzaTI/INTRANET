<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function govJson(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function govJsonError(string $message, int $status = 500): never {
    govJson([
        'ok' => false,
        'error' => $message,
    ], $status);
}

if (empty($_SESSION['user_id'])) {
    govJsonError('Sessão inválida ou expirada.', 401);
}

function govTableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
          FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
    ");
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function govGetConfig(PDO $pdo, string $key, string $default = ''): string {
    if (!govTableExists($pdo, 'governanca_configuracoes')) {
        return $default;
    }

    $stmt = $pdo->prepare("
        SELECT valor
          FROM governanca_configuracoes
         WHERE chave = ?
         LIMIT 1
    ");
    $stmt->execute([$key]);

    $value = $stmt->fetchColumn();

    return $value === false ? $default : (string) $value;
}

function govGetUserGroupIds(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT grupo_id
          FROM usuarios_grupos
         WHERE usuario_id = ?
    ");
    $stmt->execute([$userId]);

    return array_values(array_unique(array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    )));
}

function govGetPermissions(PDO $pdo, int $userId, array $groupIds): array {
    if (!govTableExists($pdo, 'governanca_permissoes')) {
        return [];
    }

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
            id,
            alvo_tipo,
            alvo_id,
            estrutura_codigo,
            inclui_descendentes,
            nivel_acesso
        FROM governanca_permissoes
        WHERE ativo = 1
          AND (" . implode(' OR ', $conditions) . ")
        ORDER BY id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govBuildStructureMaps(array $estrutura): array {
    $byId = [];
    $children = [];

    foreach ($estrutura as $row) {
        $id = trim((string) ($row['ID'] ?? ''));

        if ($id === '') {
            continue;
        }

        $byId[$id] = $row;

        $parent = trim((string) ($row['ID_PAI'] ?? ''));

        if ($parent !== '') {
            $children[$parent][] = $id;
        }
    }

    return [$byId, $children];
}

function govAddDescendants(string $id, array $children, array &$set): void {
    foreach ($children[$id] ?? [] as $childId) {
        if (isset($set[$childId])) {
            continue;
        }

        $set[$childId] = true;
        govAddDescendants($childId, $children, $set);
    }
}

function govAddAncestors(string $id, array $byId, array &$set): void {
    $guard = 0;

    while (isset($byId[$id]) && $guard < 100) {
        $parent = trim((string) ($byId[$id]['ID_PAI'] ?? ''));

        if ($parent === '' || isset($set[$parent])) {
            break;
        }

        $set[$parent] = true;
        $id = $parent;
        $guard++;
    }
}

function govYesNo(bool|int|string|null $value): string {
    return !empty($value) ? 'Sim' : 'Não';
}

function govFormatDateTime(?string $value): string {
    if (!$value) {
        return date('d/m/Y H:i:s');
    }

    $time = strtotime($value);

    return $time ? date('d/m/Y H:i:s', $time) : $value;
}

/*
|--------------------------------------------------------------------------
| 1. ESTRUTURA
|--------------------------------------------------------------------------
| O front atual ainda espera as mesmas chaves que existiam no Excel.
| Aqui montamos esse formato diretamente do MariaDB.
*/
function govLoadStructures(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT
            E.id,
            E.codigo,
            P.codigo AS pai_codigo,
            E.nome,
            E.tipo_no,
            E.vinculo,
            E.responsavel_empresa,
            E.ordem,
            E.ativo,
            E.descricao
        FROM governanca_estruturas E
        LEFT JOIN governanca_estruturas P
               ON P.id = E.pai_id
        WHERE E.ativo = 1
        ORDER BY
            CASE WHEN E.pai_id IS NULL THEN 0 ELSE 1 END,
            E.ordem ASC,
            E.nome ASC
    ");

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $rows[] = [
            'ID' => (string) $row['codigo'],
            'ID_PAI' => (string) ($row['pai_codigo'] ?? ''),
            'NOME' => (string) $row['nome'],
            'TIPO_NÓ' => (string) ($row['tipo_no'] ?? ''),
            'VÍNCULO' => (string) ($row['vinculo'] ?? ''),
            'RESPONSÁVEL / EMPRESA' => (string) ($row['responsavel_empresa'] ?? ''),
            'ORDEM' => (string) ((int) $row['ordem']),
            'ATIVO' => govYesNo($row['ativo']),
            'DESCRIÇÃO / OBSERVAÇÕES' => (string) ($row['descricao'] ?? ''),

            // Campos extras para as próximas telas.
            '_DB_ID' => (int) $row['id'],
        ];
    }

    return $rows;
}

/*
|--------------------------------------------------------------------------
| 2. FUNÇÕES + OCUPANTES
|--------------------------------------------------------------------------
*/
function govLoadFunctions(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT
            F.id,
            F.estrutura_id,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome,
            F.nome,
            F.descricao,
            F.ordem,
            F.ativo
        FROM governanca_funcoes F
        JOIN governanca_estruturas E
          ON E.id = F.estrutura_id
        WHERE F.ativo = 1
          AND E.ativo = 1
        ORDER BY E.ordem ASC, F.ordem ASC, F.nome ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govLoadOccupations(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT
            PF.id AS vinculo_id,
            PF.pessoa_id,
            PF.funcao_id,
            PF.principal,
            PF.ordem,
            PF.data_inicio,
            PF.data_fim,
            PF.ativo,

            P.codigo AS pessoa_codigo,
            P.glpi_user_id,
            P.nome AS pessoa_nome,
            P.tipo_vinculo,
            P.observacoes AS pessoa_observacoes,

            F.nome AS funcao_nome,
            F.estrutura_id AS funcao_estrutura_id,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome
        FROM governanca_pessoa_funcoes PF
        JOIN governanca_pessoas P
          ON P.id = PF.pessoa_id
        JOIN governanca_funcoes F
          ON F.id = PF.funcao_id
        JOIN governanca_estruturas E
          ON E.id = F.estrutura_id
        WHERE PF.ativo = 1
          AND P.ativo = 1
          AND F.ativo = 1
          AND E.ativo = 1
          AND (PF.data_fim IS NULL OR PF.data_fim >= CURDATE())
        ORDER BY
            P.nome ASC,
            PF.principal DESC,
            PF.ordem ASC,
            PF.id ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govBuildLegacyPeople(array $occupations): array {
    $people = [];

    foreach ($occupations as $row) {
        $code = trim((string) $row['pessoa_codigo']);

        if ($code === '' || isset($people[$code])) {
            continue;
        }

        $people[$code] = [
            'ID_PESSOA' => $code,
            'NOME' => (string) $row['pessoa_nome'],
            'ID_ESTRUTURA' => (string) $row['estrutura_codigo'],
            'FUNÇÃO / CARGO' => (string) $row['funcao_nome'],
            'TIPO_VÍNCULO' => (string) ($row['tipo_vinculo'] ?? ''),
            'ORDEM' => (string) ((int) $row['ordem']),
            'ATIVO' => 'Sim',
            'OBSERVAÇÕES' => (string) ($row['pessoa_observacoes'] ?? ''),

            // Campos extras.
            '_DB_ID' => (int) $row['pessoa_id'],
            '_FUNCAO_ID' => (int) $row['funcao_id'],
            '_GLPI_USER_ID' => $row['glpi_user_id'] !== null
                ? (int) $row['glpi_user_id']
                : null,
        ];
    }

    return array_values($people);
}

/*
|--------------------------------------------------------------------------
| 3. ATIVIDADES + RESPONSABILIDADES DAS FUNÇÕES
|--------------------------------------------------------------------------
| A responsabilidade agora pertence à função.
|
| Para manter a tela atual funcionando sem alteração, projetamos a função
| sobre o(s) ocupante(s) atual(is). Assim o JSON antigo continua tendo
| ID_PESSOA / PESSOA / FUNÇÃO.
*/
function govLoadFunctionResponsibilities(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT
            FR.id AS atribuicao_id,
            FR.origem_id_resp,
            FR.funcao_id,
            FR.papel,
            FR.dominio,
            FR.observacoes AS atribuicao_observacoes,

            F.nome AS funcao_nome,
            F.estrutura_id AS funcao_estrutura_id,
            FE.codigo AS funcao_estrutura_codigo,

            A.id AS atividade_id,
            A.estrutura_id AS atividade_estrutura_id,
            AE.codigo AS atividade_estrutura_codigo,
            A.macroprocesso,
            A.processo,
            A.atividade,
            A.subatividade,
            A.area_responsavel_texto,
            A.criticidade,
            A.status,
            A.observacoes AS atividade_observacoes
        FROM governanca_funcao_responsabilidades FR
        JOIN governanca_funcoes F
          ON F.id = FR.funcao_id
        JOIN governanca_estruturas FE
          ON FE.id = F.estrutura_id
        JOIN governanca_atividades A
          ON A.id = FR.atividade_id
        JOIN governanca_estruturas AE
          ON AE.id = A.estrutura_id
        WHERE FR.ativo = 1
          AND F.ativo = 1
          AND A.ativo = 1
          AND FE.ativo = 1
          AND AE.ativo = 1
        ORDER BY
            AE.ordem ASC,
            A.macroprocesso ASC,
            A.processo ASC,
            A.atividade ASC,
            FR.id ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function govBuildLegacyResponsibilities(
    array $assignments,
    array $occupations
): array {
    $occupantsByFunction = [];

    foreach ($occupations as $row) {
        $functionId = (int) $row['funcao_id'];
        $occupantsByFunction[$functionId][] = $row;
    }

    $rows = [];

    foreach ($assignments as $assignment) {
        $functionId = (int) $assignment['funcao_id'];
        $occupants = $occupantsByFunction[$functionId] ?? [];

        // Função vaga: a responsabilidade continua existindo.
        if (!$occupants) {
            $occupants = [[
                'pessoa_codigo' => '',
                'pessoa_nome' => '',
            ]];
        }

        foreach ($occupants as $occupant) {
            $legacyId = trim((string) ($assignment['origem_id_resp'] ?? ''));

            if ($legacyId === '') {
                $legacyId = 'DBR' . (int) $assignment['atribuicao_id'];
            }

            // Se houver mais de um ocupante na mesma função, o ID ganha
            // sufixo apenas no JSON legado para permanecer único no front.
            if (count($occupants) > 1 && !empty($occupant['pessoa_codigo'])) {
                $legacyId .= '-' . (string) $occupant['pessoa_codigo'];
            }

            $obsParts = array_filter([
                trim((string) ($assignment['atividade_observacoes'] ?? '')),
                trim((string) ($assignment['atribuicao_observacoes'] ?? '')),
            ]);

            $rows[] = [
                'ID_RESP' => $legacyId,
                'ID_ESTRUTURA' => (string) $assignment['atividade_estrutura_codigo'],
                'ID_PESSOA' => (string) ($occupant['pessoa_codigo'] ?? ''),
                'PESSOA (AUTOMÁTICO)' => (string) ($occupant['pessoa_nome'] ?? ''),
                'FUNÇÃO (AUTOMÁTICO)' => (string) $assignment['funcao_nome'],
                'MACROPROCESSO' => (string) ($assignment['macroprocesso'] ?? ''),
                'PROCESSO' => (string) ($assignment['processo'] ?? ''),
                'ATIVIDADE' => (string) $assignment['atividade'],
                'SUBATIVIDADE' => (string) ($assignment['subatividade'] ?? ''),
                'PAPEL' => (string) ($assignment['papel'] ?? ''),
                'RESPONSÁVEL PRINCIPAL' => (string) ($assignment['area_responsavel_texto'] ?? ''),
                'APOIO / BACKUP' => '',
                'DOMÍNIO' => (string) ($assignment['dominio'] ?? ''),
                'CRITICIDADE' => (string) ($assignment['criticidade'] ?? ''),
                'STATUS' => (string) ($assignment['status'] ?? ''),
                'OBSERVAÇÕES' => implode(' | ', $obsParts),

                // Campos extras.
                '_ATIVIDADE_ID' => (int) $assignment['atividade_id'],
                '_FUNCAO_ID' => $functionId,
                '_ATRIBUICAO_ID' => (int) $assignment['atribuicao_id'],
            ];
        }
    }

    return $rows;
}

/*
|--------------------------------------------------------------------------
| 4. RELACIONAMENTOS
|--------------------------------------------------------------------------
*/
function govLoadRelationships(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT
            R.id,
            R.codigo,
            EO.codigo AS origem_codigo,
            ED.codigo AS destino_codigo,
            R.tipo_relacao,
            R.descricao,
            R.criticidade,
            R.ativo,
            R.observacoes
        FROM governanca_relacionamentos R
        JOIN governanca_estruturas EO
          ON EO.id = R.estrutura_origem_id
        JOIN governanca_estruturas ED
          ON ED.id = R.estrutura_destino_id
        WHERE R.ativo = 1
          AND EO.ativo = 1
          AND ED.ativo = 1
        ORDER BY R.id ASC
    ");

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $rows[] = [
            'ID_RELACAO' => (string) $row['codigo'],
            'ID_ORIGEM' => (string) $row['origem_codigo'],
            'ID_DESTINO' => (string) $row['destino_codigo'],
            'TIPO_RELACAO' => (string) ($row['tipo_relacao'] ?? ''),
            'DESCRIÇÃO' => (string) ($row['descricao'] ?? ''),
            'CRITICIDADE' => (string) ($row['criticidade'] ?? ''),
            'ATIVO' => govYesNo($row['ativo']),
            'OBSERVAÇÕES' => (string) ($row['observacoes'] ?? ''),

            '_DB_ID' => (int) $row['id'],
        ];
    }

    return $rows;
}

/*
|--------------------------------------------------------------------------
| 5. DADOS NOVOS PARA O VISUAL FUTURO
|--------------------------------------------------------------------------
*/
function govBuildFunctionsPayload(
    array $functions,
    array $occupations,
    array $assignments
): array {
    $occupantsByFunction = [];
    $responsibilityCounts = [];

    foreach ($occupations as $row) {
        $occupantsByFunction[(int) $row['funcao_id']][] = [
            'id' => (int) $row['pessoa_id'],
            'codigo' => (string) $row['pessoa_codigo'],
            'nome' => (string) $row['pessoa_nome'],
            'glpi_user_id' => $row['glpi_user_id'] !== null
                ? (int) $row['glpi_user_id']
                : null,
            'principal' => !empty($row['principal']),
        ];
    }

    foreach ($assignments as $row) {
        $fid = (int) $row['funcao_id'];
        $responsibilityCounts[$fid] = ($responsibilityCounts[$fid] ?? 0) + 1;
    }

    $payload = [];

    foreach ($functions as $row) {
        $fid = (int) $row['id'];

        $payload[] = [
            'id' => $fid,
            'estrutura_codigo' => (string) $row['estrutura_codigo'],
            'estrutura_nome' => (string) $row['estrutura_nome'],
            'nome' => (string) $row['nome'],
            'descricao' => (string) ($row['descricao'] ?? ''),
            'ordem' => (int) $row['ordem'],
            'ativo' => !empty($row['ativo']),
            'ocupantes' => $occupantsByFunction[$fid] ?? [],
            'responsabilidades' => (int) ($responsibilityCounts[$fid] ?? 0),
        ];
    }

    return $payload;
}

function govLoadLeaderships(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT
            L.id,
            E.codigo AS estrutura_codigo,
            E.nome AS estrutura_nome,
            P.id AS pessoa_id,
            P.codigo AS pessoa_codigo,
            P.nome AS pessoa_nome,
            P.glpi_user_id,
            L.tipo,
            L.data_inicio,
            L.data_fim
        FROM governanca_liderancas L
        JOIN governanca_estruturas E
          ON E.id = L.estrutura_id
        JOIN governanca_pessoas P
          ON P.id = L.pessoa_id
        WHERE L.ativo = 1
          AND E.ativo = 1
          AND P.ativo = 1
          AND (L.data_fim IS NULL OR L.data_fim >= CURDATE())
        ORDER BY E.nome ASC, L.id ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

try {
    /*
    |--------------------------------------------------------------------------
    | CARREGAMENTO DO BANCO
    |--------------------------------------------------------------------------
    */
    $requiredTables = [
        'governanca_estruturas',
        'governanca_funcoes',
        'governanca_pessoas',
        'governanca_pessoa_funcoes',
        'governanca_atividades',
        'governanca_funcao_responsabilidades',
        'governanca_relacionamentos',
    ];

    foreach ($requiredTables as $table) {
        if (!govTableExists($pdo_intra, $table)) {
            govJsonError("Tabela obrigatória não encontrada: {$table}", 500);
        }
    }

    $estrutura = govLoadStructures($pdo_intra);
    $functionsRaw = govLoadFunctions($pdo_intra);
    $occupationsRaw = govLoadOccupations($pdo_intra);
    $assignmentsRaw = govLoadFunctionResponsibilities($pdo_intra);

    $pessoas = govBuildLegacyPeople($occupationsRaw);
    $responsabilidades = govBuildLegacyResponsibilities(
        $assignmentsRaw,
        $occupationsRaw
    );
    $relacionamentos = govLoadRelationships($pdo_intra);

    if (!$estrutura) {
        govJsonError('Nenhuma estrutura ativa foi encontrada na Governança.', 500);
    }

    /*
    |--------------------------------------------------------------------------
    | CONTROLE DE ACESSO POR NÓ
    |--------------------------------------------------------------------------
    | A mesma regra já validada continua valendo, agora sobre o banco.
    */
    $usuarioId = (int) $_SESSION['user_id'];
    $isAdmin = !empty($_SESSION['is_admin']);
    $permissoesAtivas =
        govGetConfig($pdo_intra, 'permissoes_ativas', '0') === '1';

    $accessScope = 'FULL_ADMIN';
    $allowedRoots = [];
    $nivelAcesso = $isAdmin ? 'GERENCIAR' : 'VISUALIZAR';
    $grants = [];

    $idsPermitidos = [];
    $idsVisiveis = [];

    if (!$isAdmin && !$permissoesAtivas) {
        $accessScope = 'FULL_AUTHENTICATED';
        $nivelAcesso = 'VISUALIZAR';
    }

    if (!$isAdmin && $permissoesAtivas) {
        $groupIds = govGetUserGroupIds($pdo_intra, $usuarioId);
        $permissions = govGetPermissions(
            $pdo_intra,
            $usuarioId,
            $groupIds
        );

        if (!$permissions) {
            govJsonError(
                'Seu usuário não possui acesso à Governança.',
                403
            );
        }

        foreach ($permissions as $permission) {
            $grants[] = [
                'estrutura_codigo' => (string) $permission['estrutura_codigo'],
                'inclui_descendentes' =>
                    !empty($permission['inclui_descendentes']),
                'nivel_acesso' => (string) $permission['nivel_acesso'],
            ];
        }

        $fullPermission = false;

        foreach ($permissions as $permission) {
            if (($permission['estrutura_codigo'] ?? '') === '*') {
                $fullPermission = true;

                if (
                    ($permission['nivel_acesso'] ?? 'VISUALIZAR')
                    === 'GERENCIAR'
                ) {
                    $nivelAcesso = 'GERENCIAR';
                }
            }
        }

        if ($fullPermission) {
            $accessScope = 'FULL_GRANTED';
        } else {
            [$estruturaPorId, $filhosPorId] =
                govBuildStructureMaps($estrutura);

            foreach ($permissions as $permission) {
                $codigo = trim(
                    (string) ($permission['estrutura_codigo'] ?? '')
                );

                if (
                    $codigo === ''
                    || !isset($estruturaPorId[$codigo])
                ) {
                    continue;
                }

                $allowedRoots[] = $codigo;
                $idsPermitidos[$codigo] = true;

                if (!empty($permission['inclui_descendentes'])) {
                    govAddDescendants(
                        $codigo,
                        $filhosPorId,
                        $idsPermitidos
                    );
                }

                if (
                    ($permission['nivel_acesso'] ?? 'VISUALIZAR')
                    === 'GERENCIAR'
                ) {
                    $nivelAcesso = 'GERENCIAR';
                }
            }

            if (!$idsPermitidos) {
                govJsonError(
                    'As permissões do seu usuário não apontam para estruturas válidas.',
                    403
                );
            }

            $idsVisiveis = $idsPermitidos;

            foreach (array_keys($idsPermitidos) as $codigo) {
                govAddAncestors(
                    $codigo,
                    $estruturaPorId,
                    $idsVisiveis
                );
            }

            // Estruturas: nós autorizados + ancestrais apenas de contexto.
            $estrutura = array_values(array_filter(
                $estrutura,
                static function (array $row) use ($idsVisiveis): bool {
                    $id = trim((string) ($row['ID'] ?? ''));

                    return $id !== ''
                        && isset($idsVisiveis[$id]);
                }
            ));

            // Responsabilidades: somente atividades nos nós permitidos.
            $responsabilidades = array_values(array_filter(
                $responsabilidades,
                static function (array $row) use ($idsPermitidos): bool {
                    $estruturaId = trim(
                        (string) ($row['ID_ESTRUTURA'] ?? '')
                    );

                    return $estruturaId !== ''
                        && isset($idsPermitidos[$estruturaId]);
                }
            ));

            /*
             * Descobre funções relevantes:
             * - função lotada diretamente em um nó permitido; OU
             * - função que possui responsabilidade em um nó permitido.
             *
             * Isso é importante porque, por exemplo, a função pode pertencer
             * à área "Análise de Dados", mas executar atividades em "Power BI".
             */
            $functionIdsVisible = [];

            foreach ($functionsRaw as $function) {
                if (
                    isset(
                        $idsPermitidos[
                            (string) $function['estrutura_codigo']
                        ]
                    )
                ) {
                    $functionIdsVisible[(int) $function['id']] = true;
                }
            }

            foreach ($assignmentsRaw as $assignment) {
                if (
                    isset(
                        $idsPermitidos[
                            (string) $assignment['atividade_estrutura_codigo']
                        ]
                    )
                ) {
                    $functionIdsVisible[
                        (int) $assignment['funcao_id']
                    ] = true;
                }
            }

            // Pessoas visíveis = ocupantes das funções relevantes.
            $peopleCodesVisible = [];

            foreach ($occupationsRaw as $occupation) {
                if (
                    isset(
                        $functionIdsVisible[
                            (int) $occupation['funcao_id']
                        ]
                    )
                ) {
                    $peopleCodesVisible[
                        (string) $occupation['pessoa_codigo']
                    ] = true;
                }
            }

            $pessoas = array_values(array_filter(
                $pessoas,
                static function (array $row) use ($peopleCodesVisible): bool {
                    $code = trim(
                        (string) ($row['ID_PESSOA'] ?? '')
                    );

                    return $code !== ''
                        && isset($peopleCodesVisible[$code]);
                }
            ));

            // Relacionamentos: só quando os dois lados estão visíveis.
            $relacionamentos = array_values(array_filter(
                $relacionamentos,
                static function (array $row) use (
                    $idsVisiveis,
                    $idsPermitidos
                ): bool {
                    $origem = trim(
                        (string) ($row['ID_ORIGEM'] ?? '')
                    );
                    $destino = trim(
                        (string) ($row['ID_DESTINO'] ?? '')
                    );

                    if (
                        $origem === ''
                        || $destino === ''
                        || !isset($idsVisiveis[$origem])
                        || !isset($idsVisiveis[$destino])
                    ) {
                        return false;
                    }

                    return isset($idsPermitidos[$origem])
                        || isset($idsPermitidos[$destino]);
                }
            ));

            $accessScope = 'NODE_FILTERED';
            $allowedRoots = array_values(
                array_unique($allowedRoots)
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PAYLOAD NOVO
    |--------------------------------------------------------------------------
    | Já devolvemos também funções/lideranças para o próximo visual.
    | O front atual ignora esses campos, então não quebra.
    */
    $functionsPayload = govBuildFunctionsPayload(
        $functionsRaw,
        $occupationsRaw,
        $assignmentsRaw
    );

    $leaderships = govTableExists(
        $pdo_intra,
        'governanca_liderancas'
    )
        ? govLoadLeaderships($pdo_intra)
        : [];

    if (
        !$isAdmin
        && $permissoesAtivas
        && $accessScope === 'NODE_FILTERED'
    ) {
        $functionsPayload = array_values(array_filter(
            $functionsPayload,
            static function (array $function) use (
                $idsPermitidos,
                $responsabilidades
            ): bool {
                if (
                    isset(
                        $idsPermitidos[
                            (string) $function['estrutura_codigo']
                        ]
                    )
                ) {
                    return true;
                }

                $fid = (int) $function['id'];

                foreach ($responsabilidades as $responsibility) {
                    if (
                        (int) ($responsibility['_FUNCAO_ID'] ?? 0)
                        === $fid
                    ) {
                        return true;
                    }
                }

                return false;
            }
        ));

        $leaderships = array_values(array_filter(
            $leaderships,
            static function (array $leadership) use (
                $idsPermitidos
            ): bool {
                return isset(
                    $idsPermitidos[
                        (string) $leadership['estrutura_codigo']
                    ]
                );
            }
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | FONTE / ÚLTIMA IMPORTAÇÃO
    |--------------------------------------------------------------------------
    */
    $lastImport = null;

    if (govTableExists($pdo_intra, 'governanca_importacoes')) {
        $stmt = $pdo_intra->query("
            SELECT
                id,
                arquivo_nome,
                arquivo_hash,
                finalizado_em
            FROM governanca_importacoes
            WHERE status = 'SUCESSO'
            ORDER BY id DESC
            LIMIT 1
        ");

        $lastImport = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    govJson([
        'ok' => true,

        // Compatibilidade com a tela atual.
        'data' => [
            'estrutura' => $estrutura,
            'pessoas' => $pessoas,
            'responsabilidades' => $responsabilidades,
            'relacionamentos' => $relacionamentos,

            // Estrutura nova para o próximo layout.
            'funcoes' => $functionsPayload,
            'liderancas' => $leaderships,
        ],

        'source' => [
            'type' => 'database',
            'label' => 'MariaDB',
            'modified_at' => govFormatDateTime(
                $lastImport['finalizado_em'] ?? null
            ),
            'import_id' => $lastImport
                ? (int) $lastImport['id']
                : null,
            'original_file' => $lastImport['arquivo_nome'] ?? null,
        ],

        'access' => [
            'user_id' => $usuarioId,
            'is_admin' => $isAdmin,
            'permissions_enabled' => $permissoesAtivas,
            'scope' => $accessScope,
            'level' => $nivelAcesso,
            'roots' => $allowedRoots,
            'grants' => $grants,
        ],
    ]);

} catch (Throwable $e) {
    error_log('Governança DB: ' . $e->getMessage());

    govJsonError(
        'Falha ao carregar a Governança pelo banco de dados: '
        . $e->getMessage()
    );
}
