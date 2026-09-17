<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/GovernancaExcelReader.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$ehAdmin = !empty($_SESSION['is_admin']);

if ($usuarioId <= 0) {
    http_response_code(401);
    die('Sessão inválida.');
}

if (!$ehAdmin) {
    http_response_code(403);
    die('Acesso restrito ao administrador da intranet.');
}

if (empty($_SESSION['governanca_import_csrf'])) {
    $_SESSION['governanca_import_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['governanca_import_csrf'];
$xlsxPath = ROOT_PATH . 'Matriz_Estrutura_Responsabilidades.xlsx';

$erro = null;
$sucesso = null;
$preview = null;

function govImportNormalizeFunctionKey(string $estruturaCodigo, string $nome): string {
    return govExcelNormalize($estruturaCodigo) . '|' . govExcelNormalize($nome);
}

function govImportBuildPreview(string $xlsxPath): array {
    if (!is_file($xlsxPath)) {
        throw new RuntimeException(
            'Arquivo Matriz_Estrutura_Responsabilidades.xlsx não encontrado na raiz da intranet.'
        );
    }

    $sheets = govExcelReadWorkbook($xlsxPath);

    $estruturas = govExcelRowsToObjects(govExcelFindSheet($sheets, 'ESTRUTURA'));
    $pessoas = govExcelRowsToObjects(govExcelFindSheet($sheets, 'PESSOAS'));
    $responsabilidades = govExcelRowsToObjects(govExcelFindSheet($sheets, 'RESPONSABILIDADES'));
    $relacionamentos = govExcelRowsToObjects(govExcelFindSheet($sheets, 'RELACIONAMENTOS'));

    if (!$estruturas || !$pessoas || !$responsabilidades) {
        throw new RuntimeException(
            'As abas ESTRUTURA, PESSOAS e RESPONSABILIDADES precisam estar preenchidas.'
        );
    }

    $pessoasPorCodigo = [];
    $funcoes = [];
    $pessoaFuncoes = [];

    foreach ($pessoas as $pessoa) {
        $codigo = trim((string) ($pessoa['ID_PESSOA'] ?? ''));
        $estruturaCodigo = trim((string) ($pessoa['ID_ESTRUTURA'] ?? ''));
        $funcaoNome = trim((string) ($pessoa['FUNÇÃO / CARGO'] ?? ''));

        if ($codigo === '') {
            continue;
        }

        $pessoasPorCodigo[$codigo] = $pessoa;

        if ($estruturaCodigo !== '' && $funcaoNome !== '') {
            $fKey = govImportNormalizeFunctionKey($estruturaCodigo, $funcaoNome);

            if (!isset($funcoes[$fKey])) {
                $funcoes[$fKey] = [
                    'estrutura_codigo' => $estruturaCodigo,
                    'nome' => $funcaoNome,
                ];
            }

            $pessoaFuncoes[] = [
                'pessoa_codigo' => $codigo,
                'funcao_key' => $fKey,
            ];
        }
    }

    $atividades = [];
    $atribuicoes = [];
    $papeis = [];
    $atividadePapeis = [];

    foreach ($responsabilidades as $resp) {
        $pid = trim((string) ($resp['ID_PESSOA'] ?? ''));

        if ($pid !== '' && isset($pessoasPorCodigo[$pid])) {
            if (empty($resp['PESSOA (AUTOMÁTICO)'])) {
                $resp['PESSOA (AUTOMÁTICO)'] = $pessoasPorCodigo[$pid]['NOME'] ?? '';
            }

            if (empty($resp['FUNÇÃO (AUTOMÁTICO)'])) {
                $resp['FUNÇÃO (AUTOMÁTICO)'] =
                    $pessoasPorCodigo[$pid]['FUNÇÃO / CARGO'] ?? '';
            }
        }

        $hash = govExcelActivityHash($resp);

        if (!isset($atividades[$hash])) {
            $atividades[$hash] = $resp;
        }

        $papel = trim((string) ($resp['PAPEL'] ?? ''));
        if ($papel === '') {
            $papel = 'Principal';
        }

        $papeis[$papel] = ($papeis[$papel] ?? 0) + 1;
        $atividadePapeis[$hash][] = $papel;

        $atribuicoes[] = $resp;
    }

    $semPrincipal = [];

    foreach ($atividades as $hash => $atividade) {
        $roles = array_map(
            static fn($v) => govExcelNormalize((string) $v),
            $atividadePapeis[$hash] ?? []
        );

        if (!in_array('principal', $roles, true)) {
            $semPrincipal[] = [
                'estrutura' => $atividade['ID_ESTRUTURA'] ?? '',
                'macroprocesso' => $atividade['MACROPROCESSO'] ?? '',
                'processo' => $atividade['PROCESSO'] ?? '',
                'atividade' => $atividade['ATIVIDADE'] ?? '',
            ];
        }
    }

    $multiplosPrincipais = 0;

    foreach ($atividadePapeis as $roles) {
        $qtd = 0;

        foreach ($roles as $role) {
            if (govExcelNormalize((string) $role) === 'principal') {
                $qtd++;
            }
        }

        if ($qtd > 1) {
            $multiplosPrincipais++;
        }
    }

    return [
        'estruturas_rows' => $estruturas,
        'pessoas_rows' => $pessoas,
        'responsabilidades_rows' => $responsabilidades,
        'relacionamentos_rows' => $relacionamentos,

        'funcoes' => $funcoes,
        'pessoa_funcoes' => $pessoaFuncoes,
        'atividades' => $atividades,
        'atribuicoes' => $atribuicoes,

        'counts' => [
            'estruturas' => count($estruturas),
            'funcoes' => count($funcoes),
            'pessoas' => count($pessoas),
            'pessoa_funcoes' => count($pessoaFuncoes),
            'atividades' => count($atividades),
            'atribuicoes' => count($atribuicoes),
            'relacionamentos' => count($relacionamentos),
            'liderancas' => 0,
        ],

        'papeis' => $papeis,
        'sem_principal' => $semPrincipal,
        'multiplos_principais' => $multiplosPrincipais,

        'arquivo_hash' => hash_file('sha256', $xlsxPath),
        'arquivo_nome' => basename($xlsxPath),
        'arquivo_modificado' => date('d/m/Y H:i:s', (int) filemtime($xlsxPath)),
    ];
}

function govImportTableCount(PDO $pdo, string $table): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

function govImportEnsureEmpty(PDO $pdo): void {
    $tables = [
        'governanca_funcao_responsabilidades',
        'governanca_atividades',
        'governanca_liderancas',
        'governanca_pessoa_funcoes',
        'governanca_pessoas',
        'governanca_funcoes',
        'governanca_relacionamentos',
        'governanca_estruturas',
    ];

    foreach ($tables as $table) {
        if (govImportTableCount($pdo, $table) > 0) {
            throw new RuntimeException(
                "A tabela {$table} já possui dados. A importação inicial foi bloqueada para evitar sobrescrita."
            );
        }
    }
}

function govImportRun(PDO $pdo, array $preview, int $usuarioId): int {
    govImportEnsureEmpty($pdo);

    $pdo->beginTransaction();

    try {
        $stmtImport = $pdo->prepare("
            INSERT INTO governanca_importacoes
                (
                    arquivo_nome, arquivo_hash, usuario_id, status,
                    estruturas, funcoes, pessoas, pessoa_funcoes,
                    atividades, atribuicoes, relacionamentos,
                    observacoes
                )
            VALUES
                (?, ?, ?, 'PROCESSANDO', ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $obsPreview = json_encode([
            'papeis' => $preview['papeis'],
            'atividades_sem_principal' => count($preview['sem_principal']),
            'atividades_multiplos_principais' => $preview['multiplos_principais'],
            'liderancas_importadas' => 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $c = $preview['counts'];

        $stmtImport->execute([
            $preview['arquivo_nome'],
            $preview['arquivo_hash'],
            $usuarioId,
            $c['estruturas'],
            $c['funcoes'],
            $c['pessoas'],
            $c['pessoa_funcoes'],
            $c['atividades'],
            $c['atribuicoes'],
            $c['relacionamentos'],
            $obsPreview,
        ]);

        $importId = (int) $pdo->lastInsertId();

        // ----------------------------------------------------
        // ESTRUTURAS
        // Primeiro insere sem pai; depois vincula ID_PAI.
        // ----------------------------------------------------
        $estruturaIdPorCodigo = [];

        $stmtEstrutura = $pdo->prepare("
            INSERT INTO governanca_estruturas
                (
                    codigo, pai_id, nome, tipo_no, vinculo,
                    responsavel_empresa, ordem, ativo, descricao,
                    criado_por, atualizado_por
                )
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($preview['estruturas_rows'] as $row) {
            $codigo = trim((string) ($row['ID'] ?? ''));

            if ($codigo === '') {
                continue;
            }

            $stmtEstrutura->execute([
                $codigo,
                trim((string) ($row['NOME'] ?? '')),
                trim((string) ($row['TIPO_NÓ'] ?? '')) ?: null,
                trim((string) ($row['VÍNCULO'] ?? '')) ?: null,
                trim((string) ($row['RESPONSÁVEL / EMPRESA'] ?? '')) ?: null,
                (int) ($row['ORDEM'] ?? 0),
                govExcelBoolSim((string) ($row['ATIVO'] ?? 'Sim')),
                trim((string) ($row['DESCRIÇÃO / OBSERVAÇÕES'] ?? '')) ?: null,
                $usuarioId,
                $usuarioId,
            ]);

            $estruturaIdPorCodigo[$codigo] = (int) $pdo->lastInsertId();
        }

        $stmtPai = $pdo->prepare("
            UPDATE governanca_estruturas
               SET pai_id = ?
             WHERE id = ?
        ");

        foreach ($preview['estruturas_rows'] as $row) {
            $codigo = trim((string) ($row['ID'] ?? ''));
            $paiCodigo = trim((string) ($row['ID_PAI'] ?? ''));

            if (
                $codigo !== ''
                && $paiCodigo !== ''
                && isset($estruturaIdPorCodigo[$codigo], $estruturaIdPorCodigo[$paiCodigo])
            ) {
                $stmtPai->execute([
                    $estruturaIdPorCodigo[$paiCodigo],
                    $estruturaIdPorCodigo[$codigo],
                ]);
            }
        }

        // ----------------------------------------------------
        // FUNÇÕES
        // ----------------------------------------------------
        $funcaoIdPorKey = [];

        $stmtFuncao = $pdo->prepare("
            INSERT INTO governanca_funcoes
                (estrutura_id, nome, ordem, ativo, criado_por, atualizado_por)
            VALUES (?, ?, ?, 1, ?, ?)
        ");

        $ordemFuncaoPorEstrutura = [];

        foreach ($preview['funcoes'] as $key => $funcao) {
            $estruturaCodigo = $funcao['estrutura_codigo'];

            if (!isset($estruturaIdPorCodigo[$estruturaCodigo])) {
                throw new RuntimeException(
                    "Função '{$funcao['nome']}' aponta para estrutura inexistente: {$estruturaCodigo}"
                );
            }

            $ordemFuncaoPorEstrutura[$estruturaCodigo] =
                ($ordemFuncaoPorEstrutura[$estruturaCodigo] ?? 0) + 1;

            $stmtFuncao->execute([
                $estruturaIdPorCodigo[$estruturaCodigo],
                $funcao['nome'],
                $ordemFuncaoPorEstrutura[$estruturaCodigo],
                $usuarioId,
                $usuarioId,
            ]);

            $funcaoIdPorKey[$key] = (int) $pdo->lastInsertId();
        }

        // ----------------------------------------------------
        // PESSOAS + OCUPAÇÃO DE FUNÇÃO
        // ----------------------------------------------------
        $pessoaIdPorCodigo = [];

        $stmtPessoa = $pdo->prepare("
            INSERT INTO governanca_pessoas
                (
                    codigo, glpi_user_id, nome, tipo_vinculo,
                    ativo, observacoes, criado_por, atualizado_por
                )
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($preview['pessoas_rows'] as $row) {
            $codigo = trim((string) ($row['ID_PESSOA'] ?? ''));

            if ($codigo === '') {
                continue;
            }

            $stmtPessoa->execute([
                $codigo,
                trim((string) ($row['NOME'] ?? '')),
                trim((string) ($row['TIPO_VÍNCULO'] ?? '')) ?: null,
                govExcelBoolSim((string) ($row['ATIVO'] ?? 'Sim')),
                trim((string) ($row['OBSERVAÇÕES'] ?? '')) ?: null,
                $usuarioId,
                $usuarioId,
            ]);

            $pessoaIdPorCodigo[$codigo] = (int) $pdo->lastInsertId();
        }

        $stmtPessoaFuncao = $pdo->prepare("
            INSERT INTO governanca_pessoa_funcoes
                (
                    pessoa_id, funcao_id, principal, ordem, ativo,
                    criado_por, atualizado_por
                )
            VALUES (?, ?, 1, ?, 1, ?, ?)
        ");

        foreach ($preview['pessoas_rows'] as $row) {
            $codigoPessoa = trim((string) ($row['ID_PESSOA'] ?? ''));
            $estruturaCodigo = trim((string) ($row['ID_ESTRUTURA'] ?? ''));
            $funcaoNome = trim((string) ($row['FUNÇÃO / CARGO'] ?? ''));

            if ($codigoPessoa === '' || $estruturaCodigo === '' || $funcaoNome === '') {
                continue;
            }

            $fKey = govImportNormalizeFunctionKey($estruturaCodigo, $funcaoNome);

            if (
                !isset($pessoaIdPorCodigo[$codigoPessoa])
                || !isset($funcaoIdPorKey[$fKey])
            ) {
                throw new RuntimeException(
                    "Não foi possível vincular {$codigoPessoa} à função {$funcaoNome}."
                );
            }

            $stmtPessoaFuncao->execute([
                $pessoaIdPorCodigo[$codigoPessoa],
                $funcaoIdPorKey[$fKey],
                (int) ($row['ORDEM'] ?? 0),
                $usuarioId,
                $usuarioId,
            ]);
        }

        // ----------------------------------------------------
        // ATIVIDADES
        // ----------------------------------------------------
        $atividadeIdPorHash = [];

        $stmtAtividade = $pdo->prepare("
            INSERT INTO governanca_atividades
                (
                    estrutura_id,
                    macroprocesso, processo, atividade, subatividade,
                    area_responsavel_texto,
                    criticidade, status, ativo, observacoes,
                    hash_chave,
                    criado_por, atualizado_por
                )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
        ");

        foreach ($preview['atividades'] as $hash => $row) {
            $estruturaCodigo = trim((string) ($row['ID_ESTRUTURA'] ?? ''));

            if (!isset($estruturaIdPorCodigo[$estruturaCodigo])) {
                throw new RuntimeException(
                    "Atividade aponta para estrutura inexistente: {$estruturaCodigo}"
                );
            }

            $stmtAtividade->execute([
                $estruturaIdPorCodigo[$estruturaCodigo],
                trim((string) ($row['MACROPROCESSO'] ?? '')) ?: null,
                trim((string) ($row['PROCESSO'] ?? '')) ?: null,
                trim((string) ($row['ATIVIDADE'] ?? '')),
                trim((string) ($row['SUBATIVIDADE'] ?? '')) ?: null,
                trim((string) ($row['RESPONSÁVEL PRINCIPAL'] ?? '')) ?: null,
                trim((string) ($row['CRITICIDADE'] ?? '')) ?: null,
                trim((string) ($row['STATUS'] ?? '')) ?: null,
                trim((string) ($row['OBSERVAÇÕES'] ?? '')) ?: null,
                $hash,
                $usuarioId,
                $usuarioId,
            ]);

            $atividadeIdPorHash[$hash] = (int) $pdo->lastInsertId();
        }

        // ----------------------------------------------------
        // RESPONSABILIDADES DA FUNÇÃO
        // ----------------------------------------------------
        $pessoasPorCodigo = [];

        foreach ($preview['pessoas_rows'] as $pessoa) {
            $codigo = trim((string) ($pessoa['ID_PESSOA'] ?? ''));

            if ($codigo !== '') {
                $pessoasPorCodigo[$codigo] = $pessoa;
            }
        }

        $stmtResp = $pdo->prepare("
            INSERT INTO governanca_funcao_responsabilidades
                (
                    atividade_id, funcao_id, papel, dominio,
                    origem_id_resp, ativo,
                    criado_por, atualizado_por
                )
            VALUES (?, ?, ?, ?, ?, 1, ?, ?)
        ");

        foreach ($preview['responsabilidades_rows'] as $row) {
            $pid = trim((string) ($row['ID_PESSOA'] ?? ''));
            $funcaoNome = trim((string) ($row['FUNÇÃO (AUTOMÁTICO)'] ?? ''));

            if (
                $funcaoNome === ''
                && $pid !== ''
                && isset($pessoasPorCodigo[$pid])
            ) {
                $funcaoNome = trim(
                    (string) ($pessoasPorCodigo[$pid]['FUNÇÃO / CARGO'] ?? '')
                );
            }

            if (
                $pid === ''
                || !isset($pessoasPorCodigo[$pid])
                || $funcaoNome === ''
            ) {
                throw new RuntimeException(
                    'Responsabilidade sem pessoa/função válida: ' .
                    (string) ($row['ID_RESP'] ?? 'sem ID')
                );
            }

            $estruturaFuncaoCodigo = trim(
                (string) ($pessoasPorCodigo[$pid]['ID_ESTRUTURA'] ?? '')
            );

            $fKey = govImportNormalizeFunctionKey(
                $estruturaFuncaoCodigo,
                $funcaoNome
            );

            $hash = govExcelActivityHash($row);

            if (
                !isset($funcaoIdPorKey[$fKey])
                || !isset($atividadeIdPorHash[$hash])
            ) {
                throw new RuntimeException(
                    'Falha ao mapear responsabilidade: ' .
                    (string) ($row['ID_RESP'] ?? 'sem ID')
                );
            }

            $papel = trim((string) ($row['PAPEL'] ?? ''));
            if ($papel === '') {
                $papel = 'Principal';
            }

            $stmtResp->execute([
                $atividadeIdPorHash[$hash],
                $funcaoIdPorKey[$fKey],
                $papel,
                trim((string) ($row['DOMÍNIO'] ?? '')) ?: null,
                trim((string) ($row['ID_RESP'] ?? '')) ?: null,
                $usuarioId,
                $usuarioId,
            ]);
        }

        // ----------------------------------------------------
        // RELACIONAMENTOS
        // ----------------------------------------------------
        $stmtRel = $pdo->prepare("
            INSERT INTO governanca_relacionamentos
                (
                    codigo,
                    estrutura_origem_id, estrutura_destino_id,
                    tipo_relacao, descricao, criticidade,
                    ativo, observacoes,
                    criado_por, atualizado_por
                )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($preview['relacionamentos_rows'] as $row) {
            $codigo = trim((string) ($row['ID_RELACAO'] ?? ''));
            $origem = trim((string) ($row['ID_ORIGEM'] ?? ''));
            $destino = trim((string) ($row['ID_DESTINO'] ?? ''));

            if ($codigo === '') {
                continue;
            }

            if (
                !isset($estruturaIdPorCodigo[$origem])
                || !isset($estruturaIdPorCodigo[$destino])
            ) {
                throw new RuntimeException(
                    "Relacionamento {$codigo} possui origem/destino inválido."
                );
            }

            $stmtRel->execute([
                $codigo,
                $estruturaIdPorCodigo[$origem],
                $estruturaIdPorCodigo[$destino],
                trim((string) ($row['TIPO_RELACAO'] ?? '')) ?: null,
                trim((string) ($row['DESCRIÇÃO'] ?? '')) ?: null,
                trim((string) ($row['CRITICIDADE'] ?? '')) ?: null,
                govExcelBoolSim((string) ($row['ATIVO'] ?? 'Sim')),
                trim((string) ($row['OBSERVAÇÕES'] ?? '')) ?: null,
                $usuarioId,
                $usuarioId,
            ]);
        }

        $stmtFinish = $pdo->prepare("
            UPDATE governanca_importacoes
               SET status = 'SUCESSO',
                   finalizado_em = NOW()
             WHERE id = ?
        ");
        $stmtFinish->execute([$importId]);

        $pdo->commit();

        return $importId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

try {
    $preview = govImportBuildPreview($xlsxPath);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf'] ?? '';

        if (!hash_equals($csrf, (string) $token)) {
            throw new RuntimeException(
                'Token de segurança inválido. Atualize a página.'
            );
        }

        if (($_POST['acao'] ?? '') === 'importar') {
            $importId = govImportRun($pdo_intra, $preview, $usuarioId);
            $sucesso = "Importação concluída com sucesso. Registro de importação #{$importId}.";
        }
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}
?>

<main class="flex-1 min-w-0 overflow-y-auto bg-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.2em] text-blue-600">
                    Governança · Migração
                </p>
                <h1 class="text-2xl font-black text-slate-900">
                    Importar matriz para o banco
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Migração inicial do Excel para o novo modelo Área → Função → Pessoa → Responsabilidade.
                </p>
            </div>

            <a href="governanca_ti.php"
               class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-700">
                ← Voltar à Governança
            </a>
        </div>

        <?php if ($erro): ?>
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
                <strong>Erro:</strong> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
                <strong>OK:</strong> <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($preview): ?>
            <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-5">
                <div class="px-5 py-4 border-b border-slate-200">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                        Pré-validação
                    </p>
                    <h2 class="mt-1 text-lg font-black text-slate-900">
                        Dados identificados no Excel
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Arquivo: <?= htmlspecialchars($preview['arquivo_nome']) ?>
                        · modificado em <?= htmlspecialchars($preview['arquivo_modificado']) ?>
                    </p>
                </div>

                <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <?php
                    $cards = [
                        ['Estruturas', $preview['counts']['estruturas']],
                        ['Funções / cargos', $preview['counts']['funcoes']],
                        ['Pessoas', $preview['counts']['pessoas']],
                        ['Vínculos pessoa-função', $preview['counts']['pessoa_funcoes']],
                        ['Atividades únicas', $preview['counts']['atividades']],
                        ['Atribuições de função', $preview['counts']['atribuicoes']],
                        ['Relacionamentos', $preview['counts']['relacionamentos']],
                        ['Lideranças', $preview['counts']['liderancas']],
                    ];
                    ?>

                    <?php foreach ($cards as [$label, $value]): ?>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <p class="text-2xl font-black text-slate-900"><?= (int) $value ?></p>
                            <p class="mt-1 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <?= htmlspecialchars($label) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

                <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                        Papéis importados
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($preview['papeis'] as $papel => $qtd): ?>
                            <span class="px-3 py-2 rounded-xl bg-slate-100 border border-slate-200 text-sm font-bold text-slate-700">
                                <?= htmlspecialchars($papel) ?> · <?= (int) $qtd ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="bg-white border border-amber-200 rounded-2xl shadow-sm p-5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-600">
                        Pontos para revisar depois
                    </p>
                    <p class="mt-2 text-sm text-slate-700">
                        <strong><?= count($preview['sem_principal']) ?></strong>
                        atividades não possuem nenhuma função marcada como Principal.
                    </p>
                    <p class="mt-1 text-sm text-slate-700">
                        <strong><?= (int) $preview['multiplos_principais'] ?></strong>
                        atividades possuem mais de um Principal.
                    </p>
                    <p class="mt-1 text-sm text-slate-700">
                        <strong>0</strong> lideranças serão importadas, pois essa informação ainda não existe na matriz.
                    </p>
                </section>
            </div>

            <?php if ($preview['sem_principal']): ?>
                <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-5">
                    <div class="px-5 py-4 border-b border-slate-200">
                        <h2 class="text-base font-black text-slate-900">
                            Atividades sem Principal
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Elas serão importadas normalmente e poderemos corrigir pela futura tela de gestão.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Estrutura</th>
                                    <th class="px-5 py-3">Macroprocesso</th>
                                    <th class="px-5 py-3">Processo</th>
                                    <th class="px-5 py-3">Atividade</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($preview['sem_principal'] as $item): ?>
                                    <tr>
                                        <td class="px-5 py-3 font-bold text-slate-700">
                                            <?= htmlspecialchars($item['estrutura']) ?>
                                        </td>
                                        <td class="px-5 py-3"><?= htmlspecialchars($item['macroprocesso']) ?></td>
                                        <td class="px-5 py-3"><?= htmlspecialchars($item['processo']) ?></td>
                                        <td class="px-5 py-3"><?= htmlspecialchars($item['atividade']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                <h2 class="text-base font-black text-blue-950">
                    Importação inicial
                </h2>
                <p class="mt-1 text-sm leading-6 text-blue-900">
                    O processo só será executado se as novas tabelas estiverem vazias.
                    Se já houver dados, a importação será bloqueada para evitar sobrescrita.
                </p>

                <form method="post" class="mt-4"
                      onsubmit="return confirm('Importar a matriz atual para o banco agora?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="acao" value="importar">

                    <button type="submit"
                            class="px-5 py-3 rounded-xl bg-blue-600 text-white text-sm font-black hover:bg-blue-700">
                        Importar para o banco
                    </button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
