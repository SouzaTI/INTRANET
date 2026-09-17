<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);

if ($usuarioId <= 0) {
    http_response_code(401);
    die('Sessão inválida ou expirada.');
}

$nomeUsuario = $_SESSION['user_name'] ?? 'Usuário';
$setorUsuario = $_SESSION['setor_principal'] ?? 'GERAL';
$isAdmin = !empty($_SESSION['is_admin']);

/*
|--------------------------------------------------------------------------
| GRUPOS DO USUÁRIO
|--------------------------------------------------------------------------
| Usa a mesma relação usuarios_grupos já utilizada pela intranet.
*/
$gruposIds = [];

try {
    $stmt = $pdo_intra->prepare("
        SELECT DISTINCT grupo_id
          FROM usuarios_grupos
         WHERE usuario_id = ?
         ORDER BY grupo_id ASC
    ");
    $stmt->execute([$usuarioId]);
    $gruposIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $e) {
    $gruposIds = [];
}

$primeiroGrupoId = $gruposIds[0] ?? 0;

$sqlAcessoTotal = <<<SQL
INSERT INTO governanca_permissoes
    (alvo_tipo, alvo_id, estrutura_codigo, inclui_descendentes, nivel_acesso)
VALUES
    ('USUARIO', {$usuarioId}, '*', 1, 'VISUALIZAR');
SQL;

$sqlAnaliseDados = <<<SQL
INSERT INTO governanca_permissoes
    (alvo_tipo, alvo_id, estrutura_codigo, inclui_descendentes, nivel_acesso)
VALUES
    ('USUARIO', {$usuarioId}, 'CS-TI-DADOS', 1, 'VISUALIZAR');
SQL;

$sqlPowerBIUsuario = <<<SQL
INSERT INTO governanca_permissoes
    (alvo_tipo, alvo_id, estrutura_codigo, inclui_descendentes, nivel_acesso)
VALUES
    ('USUARIO', {$usuarioId}, 'CS-TI-PBI', 1, 'VISUALIZAR');
SQL;

$sqlPowerBIGrupo = $primeiroGrupoId > 0
    ? <<<SQL
INSERT INTO governanca_permissoes
    (alvo_tipo, alvo_id, estrutura_codigo, inclui_descendentes, nivel_acesso)
VALUES
    ('GRUPO', {$primeiroGrupoId}, 'CS-TI-PBI', 1, 'VISUALIZAR');
SQL
    : "-- Este usuário não possui grupo vinculado em usuarios_grupos.";

$sqlAtivar = <<<SQL
UPDATE governanca_configuracoes
   SET valor = '1'
 WHERE chave = 'permissoes_ativas';
SQL;

$sqlDesativar = <<<SQL
UPDATE governanca_configuracoes
   SET valor = '0'
 WHERE chave = 'permissoes_ativas';
SQL;
?>

<main class="flex-1 min-w-0 overflow-y-auto bg-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <div class="mb-6">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-600">Governança</p>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                Meu acesso / IDs
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Página de apoio para descobrir o ID do usuário logado e montar as permissões da Governança.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

            <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Usuário logado</p>
                        <h2 class="mt-1 text-xl font-black text-slate-900">
                            <?= htmlspecialchars($nomeUsuario) ?>
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Setor: <strong><?= htmlspecialchars($setorUsuario) ?></strong>
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">USER ID</p>
                        <div class="mt-1 inline-flex items-center gap-2">
                            <span class="px-4 py-2 rounded-xl bg-blue-50 border border-blue-200 text-blue-700 text-2xl font-black">
                                <?= $usuarioId ?>
                            </span>
                            <button
                                type="button"
                                onclick="copiarTexto('<?= $usuarioId ?>', this)"
                                class="px-3 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-blue-600 transition">
                                Copiar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-[10px] font-black uppercase text-slate-400">Administrador</p>
                        <p class="mt-1 text-sm font-black <?= $isAdmin ? 'text-emerald-600' : 'text-slate-700' ?>">
                            <?= $isAdmin ? 'SIM' : 'NÃO' ?>
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-[10px] font-black uppercase text-slate-400">Grupos vinculados</p>
                        <p class="mt-1 text-sm font-black text-slate-700">
                            <?= count($gruposIds) ?>
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                        <p class="text-[10px] font-black uppercase text-slate-400">Permissão atual</p>
                        <p class="mt-1 text-sm font-black text-slate-700">
                            <?= $isAdmin ? 'Acesso total (admin)' : 'Usuário comum' ?>
                        </p>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                    IDs dos grupos
                </p>

                <?php if (empty($gruposIds)): ?>
                    <div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                        Nenhum grupo encontrado para este usuário.
                    </div>
                <?php else: ?>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($gruposIds as $grupoId): ?>
                            <button
                                type="button"
                                onclick="copiarTexto('<?= $grupoId ?>', this)"
                                class="px-3 py-2 rounded-xl bg-slate-100 border border-slate-200 text-slate-800 text-sm font-black hover:bg-blue-50 hover:border-blue-200 hover:text-blue-700 transition"
                                title="Clique para copiar">
                                Grupo <?= $grupoId ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="mt-4 text-[11px] leading-5 text-slate-500">
                    O ID de grupo é usado quando você quiser liberar uma estrutura para todos os membros daquele grupo.
                </p>
            </section>
        </div>

        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-lg font-black text-slate-900">SQL pronto para teste</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Os comandos abaixo já estão preenchidos com o ID do usuário que abriu esta página.
                </p>
            </div>

            <div class="p-5 grid grid-cols-1 xl:grid-cols-2 gap-4">

                <?php
                $blocos = [
                    [
                        'titulo' => 'Governança inteira — usuário',
                        'descricao' => 'Libera todos os nós para este usuário sem transformá-lo em administrador.',
                        'sql' => $sqlAcessoTotal,
                    ],
                    [
                        'titulo' => 'Análise de Dados — usuário',
                        'descricao' => 'Libera Análise de Dados e todos os descendentes.',
                        'sql' => $sqlAnaliseDados,
                    ],
                    [
                        'titulo' => 'Power BI — usuário',
                        'descricao' => 'Libera somente Power BI e os nós abaixo dele.',
                        'sql' => $sqlPowerBIUsuario,
                    ],
                    [
                        'titulo' => 'Power BI — primeiro grupo',
                        'descricao' => $primeiroGrupoId > 0
                            ? "Usa automaticamente o primeiro grupo encontrado: ID {$primeiroGrupoId}."
                            : 'Não há grupo vinculado para montar este exemplo.',
                        'sql' => $sqlPowerBIGrupo,
                    ],
                    [
                        'titulo' => 'Ativar permissões',
                        'descricao' => 'Execute somente depois de cadastrar pelo menos a permissão do usuário de teste.',
                        'sql' => $sqlAtivar,
                    ],
                    [
                        'titulo' => 'Desativar permissões',
                        'descricao' => 'Volta imediatamente para o modo em que todo usuário autenticado vê tudo.',
                        'sql' => $sqlDesativar,
                    ],
                ];
                ?>

                <?php foreach ($blocos as $i => $bloco): ?>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-200 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-black text-slate-900">
                                    <?= htmlspecialchars($bloco['titulo']) ?>
                                </h3>
                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    <?= htmlspecialchars($bloco['descricao']) ?>
                                </p>
                            </div>

                            <button
                                type="button"
                                onclick="copiarBloco('sql-<?= $i ?>', this)"
                                class="shrink-0 px-3 py-1.5 rounded-lg bg-slate-900 text-white text-[10px] font-black uppercase tracking-wider hover:bg-blue-600 transition">
                                Copiar SQL
                            </button>
                        </div>

                        <pre id="sql-<?= $i ?>" class="m-0 p-4 overflow-x-auto text-xs leading-5 text-slate-200 bg-slate-950 whitespace-pre-wrap"><?= htmlspecialchars($bloco['sql']) ?></pre>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="mt-5 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4">
            <p class="text-sm font-black text-blue-900">Ordem segura para o teste</p>
            <p class="mt-1 text-xs leading-5 text-blue-800">
                1. Abra esta página com o usuário TESTE e anote o USER ID.
                2. Rode a permissão desejada para esse usuário.
                3. Só depois rode o comando <strong>Ativar permissões</strong>.
                4. Entre novamente com o usuário TESTE e valide a árvore.
                5. Se algo ficar errado, rode <strong>Desativar permissões</strong>.
            </p>
        </div>

    </div>
</main>

<script>
async function copiarTexto(texto, botao) {
    try {
        await navigator.clipboard.writeText(texto);
        confirmarCopia(botao);
    } catch (e) {
        fallbackCopia(texto, botao);
    }
}

async function copiarBloco(id, botao) {
    const el = document.getElementById(id);
    if (!el) return;
    copiarTexto(el.innerText, botao);
}

function fallbackCopia(texto, botao) {
    const temp = document.createElement('textarea');
    temp.value = texto;
    temp.style.position = 'fixed';
    temp.style.opacity = '0';
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    temp.remove();
    confirmarCopia(botao);
}

function confirmarCopia(botao) {
    if (!botao) return;

    const original = botao.dataset.original || botao.innerText;
    botao.dataset.original = original;
    botao.innerText = 'Copiado ✓';

    setTimeout(() => {
        botao.innerText = original;
    }, 1500);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
