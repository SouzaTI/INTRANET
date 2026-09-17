<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$ehAdmin = !empty($_SESSION['is_admin']);

if ($usuarioId <= 0 || !$ehAdmin) {
    http_response_code(403);
    die('Acesso restrito.');
}

function govValCount(PDO $pdo, string $table): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

$counts = [
    'estruturas' => govValCount($pdo_intra, 'governanca_estruturas'),
    'funcoes' => govValCount($pdo_intra, 'governanca_funcoes'),
    'pessoas' => govValCount($pdo_intra, 'governanca_pessoas'),
    'pessoa_funcoes' => govValCount($pdo_intra, 'governanca_pessoa_funcoes'),
    'atividades' => govValCount($pdo_intra, 'governanca_atividades'),
    'atribuicoes' => govValCount($pdo_intra, 'governanca_funcao_responsabilidades'),
    'relacionamentos' => govValCount($pdo_intra, 'governanca_relacionamentos'),
    'liderancas' => govValCount($pdo_intra, 'governanca_liderancas'),
];

$ultimaImport = $pdo_intra->query("
    SELECT *
      FROM governanca_importacoes
     WHERE status = 'SUCESSO'
     ORDER BY id DESC
     LIMIT 1
")->fetch(PDO::FETCH_ASSOC) ?: null;

$papeis = $pdo_intra->query("
    SELECT papel, COUNT(*) AS qtd
      FROM governanca_funcao_responsabilidades
     WHERE ativo = 1
     GROUP BY papel
     ORDER BY qtd DESC, papel ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$semPrincipal = $pdo_intra->query("
    SELECT
        A.id,
        E.codigo AS estrutura_codigo,
        E.nome AS estrutura_nome,
        A.macroprocesso,
        A.processo,
        A.atividade
    FROM governanca_atividades A
    JOIN governanca_estruturas E
      ON E.id = A.estrutura_id
    LEFT JOIN governanca_funcao_responsabilidades FR
      ON FR.atividade_id = A.id
     AND FR.ativo = 1
     AND UPPER(FR.papel) = 'PRINCIPAL'
    WHERE A.ativo = 1
    GROUP BY
        A.id, E.codigo, E.nome,
        A.macroprocesso, A.processo, A.atividade
    HAVING COUNT(FR.id) = 0
    ORDER BY E.nome, A.macroprocesso, A.processo, A.atividade
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$multiplosPrincipais = (int) $pdo_intra->query("
    SELECT COUNT(*)
    FROM (
        SELECT A.id
        FROM governanca_atividades A
        JOIN governanca_funcao_responsabilidades FR
          ON FR.atividade_id = A.id
         AND FR.ativo = 1
         AND UPPER(FR.papel) = 'PRINCIPAL'
        WHERE A.ativo = 1
        GROUP BY A.id
        HAVING COUNT(FR.id) > 1
    ) X
")->fetchColumn();

$funcoesSemOcupante = $pdo_intra->query("
    SELECT
        F.id,
        E.nome AS area,
        F.nome AS funcao
    FROM governanca_funcoes F
    JOIN governanca_estruturas E
      ON E.id = F.estrutura_id
    LEFT JOIN governanca_pessoa_funcoes PF
      ON PF.funcao_id = F.id
     AND PF.ativo = 1
    WHERE F.ativo = 1
    GROUP BY F.id, E.nome, F.nome
    HAVING COUNT(PF.id) = 0
    ORDER BY E.nome, F.nome
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pessoasSemFuncao = $pdo_intra->query("
    SELECT P.codigo, P.nome
      FROM governanca_pessoas P
      LEFT JOIN governanca_pessoa_funcoes PF
        ON PF.pessoa_id = P.id
       AND PF.ativo = 1
     WHERE P.ativo = 1
     GROUP BY P.id, P.codigo, P.nome
    HAVING COUNT(PF.id) = 0
     ORDER BY P.nome
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$okComparacao = null;
$expected = [];

if ($ultimaImport) {
    $expected = [
        'estruturas' => (int) $ultimaImport['estruturas'],
        'funcoes' => (int) $ultimaImport['funcoes'],
        'pessoas' => (int) $ultimaImport['pessoas'],
        'pessoa_funcoes' => (int) $ultimaImport['pessoa_funcoes'],
        'atividades' => (int) $ultimaImport['atividades'],
        'atribuicoes' => (int) $ultimaImport['atribuicoes'],
        'relacionamentos' => (int) $ultimaImport['relacionamentos'],
    ];

    $okComparacao = true;

    foreach ($expected as $key => $value) {
        if (($counts[$key] ?? -1) !== $value) {
            $okComparacao = false;
            break;
        }
    }
}
?>

<main class="flex-1 min-w-0 overflow-y-auto bg-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.2em] text-blue-600">
                    Governança · Validação
                </p>
                <h1 class="text-2xl font-black text-slate-900">
                    Validar banco migrado
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Conferência de contagens, vínculos e qualidade dos dados após a importação.
                </p>
            </div>

            <button onclick="window.location.reload()"
                    class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-700">
                Atualizar validação
            </button>
        </div>

        <?php if ($ultimaImport): ?>
            <div class="mb-5 rounded-2xl border <?= $okComparacao ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' ?> p-5">
                <p class="text-sm font-black <?= $okComparacao ? 'text-emerald-900' : 'text-red-900' ?>">
                    <?= $okComparacao
                        ? 'Contagens do banco conferem com a última importação.'
                        : 'Existe diferença entre a importação registrada e o banco atual.' ?>
                </p>
                <p class="mt-1 text-xs <?= $okComparacao ? 'text-emerald-700' : 'text-red-700' ?>">
                    Importação #<?= (int) $ultimaImport['id'] ?>
                    · <?= htmlspecialchars($ultimaImport['arquivo_nome']) ?>
                    · <?= htmlspecialchars((string) $ultimaImport['finalizado_em']) ?>
                </p>
            </div>
        <?php else: ?>
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                Nenhuma importação concluída foi encontrada.
            </div>
        <?php endif; ?>

        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-5">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-base font-black text-slate-900">Contagens</h2>
            </div>

            <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php foreach ($counts as $key => $actual): ?>
                    <?php
                    $exp = $expected[$key] ?? null;
                    $matches = $exp === null || $exp === $actual;
                    ?>
                    <div class="rounded-xl border <?= $matches ? 'border-slate-200 bg-slate-50' : 'border-red-200 bg-red-50' ?> p-4">
                        <p class="text-2xl font-black text-slate-900"><?= (int) $actual ?></p>
                        <p class="mt-1 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <?= htmlspecialchars(str_replace('_', ' ', $key)) ?>
                        </p>
                        <?php if ($exp !== null): ?>
                            <p class="mt-1 text-[10px] <?= $matches ? 'text-emerald-600' : 'text-red-600' ?>">
                                Esperado: <?= (int) $exp ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
            <section class="bg-white border border-slate-200 rounded-2xl p-5">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                    Papéis
                </p>
                <div class="mt-3 space-y-2">
                    <?php foreach ($papeis as $row): ?>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($row['papel']) ?></span>
                            <span class="text-sm font-black text-slate-900"><?= (int) $row['qtd'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-2xl p-5">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                    Integridade
                </p>
                <div class="mt-3 space-y-2 text-sm text-slate-700">
                    <p><strong><?= count($pessoasSemFuncao) ?></strong> pessoas sem função ativa</p>
                    <p><strong><?= count($funcoesSemOcupante) ?></strong> funções sem ocupante</p>
                    <p><strong><?= count($semPrincipal) ?></strong> atividades sem Principal</p>
                    <p><strong><?= $multiplosPrincipais ?></strong> atividades com múltiplos Principais</p>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-2xl p-5">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                    Liderança
                </p>
                <p class="mt-3 text-3xl font-black text-slate-900">
                    <?= (int) $counts['liderancas'] ?>
                </p>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    A matriz atual não possui líder por área. Essa informação será cadastrada posteriormente pela Governança.
                </p>
            </section>
        </div>

        <?php if ($semPrincipal): ?>
            <section class="bg-white border border-amber-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-amber-200 bg-amber-50">
                    <h2 class="text-base font-black text-amber-950">
                        Atividades sem Principal
                    </h2>
                    <p class="mt-1 text-xs text-amber-800">
                        Não impede a migração. É uma pendência de governança para tratar na futura tela de gestão.
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
                            <?php foreach ($semPrincipal as $item): ?>
                                <tr>
                                    <td class="px-5 py-3 font-bold text-slate-700">
                                        <?= htmlspecialchars($item['estrutura_nome']) ?>
                                        <span class="block text-[10px] text-slate-400">
                                            <?= htmlspecialchars($item['estrutura_codigo']) ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3"><?= htmlspecialchars((string) $item['macroprocesso']) ?></td>
                                    <td class="px-5 py-3"><?= htmlspecialchars((string) $item['processo']) ?></td>
                                    <td class="px-5 py-3"><?= htmlspecialchars($item['atividade']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
