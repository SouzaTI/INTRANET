<?php
declare(strict_types=1);

require_once 'config.php';
require_once __DIR__ . '/includes/DocumentoCentralAuth.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
if ($usuarioId <= 0) {
    header('Location: login.php');
    exit;
}

$auth = new DocumentoCentralAuth(
    $pdo_intra,
    $usuarioId,
    isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true,
    $_SESSION
);
$csrf = documentoCentralCsrfToken();
$aba = (string) ($_GET['aba'] ?? 'biblioteca');
$abasValidas = ['biblioteca', 'meus', 'pendencias', 'fila', 'assinaturas'];
if (!in_array($aba, $abasValidas, true) || (!$auth->isValidador() && $aba === 'fila')) {
    $aba = 'biblioteca';
}

$selectBase = "SELECT d.*, v.numero AS versao_numero, v.nome_original, v.mime_type, v.tamanho_bytes,
    TRIM(CONCAT(COALESCE(uc.firstname,''), ' ', COALESCE(uc.realname,''))) AS criador_nome,
    TRIM(CONCAT(COALESCE(ur.firstname,''), ' ', COALESCE(ur.realname,''))) AS responsavel_nome,
    (SELECT COUNT(*) FROM documentos_versoes vv WHERE vv.documento_id=d.id) AS total_versoes,
    (SELECT instrucoes FROM documentos_tarefas tt WHERE tt.documento_id=d.id AND tt.status='PENDENTE' ORDER BY tt.id DESC LIMIT 1) AS instrucao_atual
FROM documentos_central d
LEFT JOIN documentos_versoes v ON v.id=d.versao_atual_id
LEFT JOIN " . DB_GLPI . ".glpi_users uc ON uc.id=d.criador_id
LEFT JOIN " . DB_GLPI . ".glpi_users ur ON ur.id=d.responsavel_id";

if ($auth->isValidador()) {
    $stmt = $pdo_intra->query($selectBase . ' ORDER BY d.atualizado_em DESC');
} else {
    $setores = $auth->setores();
    $partes = ['d.criador_id = ?'];
    $params = [$usuarioId];
    if ($setores) {
        $marcadores = implode(',', array_fill(0, count($setores), '?'));
        $partes[] = "(d.status='PUBLICADO' AND (UPPER(d.setor) IN ($marcadores) OR UPPER(d.setor)='GERAL'))";
        array_push($params, ...$setores);
    } else {
        $partes[] = "(d.status='PUBLICADO' AND UPPER(d.setor)='GERAL')";
    }
    $stmt = $pdo_intra->prepare($selectBase . ' WHERE ' . implode(' OR ', $partes) . ' ORDER BY d.atualizado_em DESC');
    $stmt->execute($params);
}
$todosDocumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tarefasUsuario = [];
$stmtTarefas = $pdo_intra->prepare("SELECT documento_id FROM documentos_tarefas WHERE status='PENDENTE' AND usuario_responsavel_id=?");
$stmtTarefas->execute([$usuarioId]);
$tarefasUsuario = array_fill_keys(array_map('intval', $stmtTarefas->fetchAll(PDO::FETCH_COLUMN)), true);

$colecoes = [
    'biblioteca' => [],
    'meus' => [],
    'pendencias' => [],
    'fila' => [],
    'assinaturas' => [],
];
foreach ($todosDocumentos as $documento) {
    $id = (int) $documento['id'];
    if ($documento['status'] === 'PUBLICADO') $colecoes['biblioteca'][] = $documento;
    if ((int) $documento['criador_id'] === $usuarioId) $colecoes['meus'][] = $documento;
    if (isset($tarefasUsuario[$id])) $colecoes['pendencias'][] = $documento;
    if ($auth->isValidador() && in_array($documento['status'], ['EM_VALIDACAO', 'EM_ANALISE'], true)) $colecoes['fila'][] = $documento;
    if ($documento['status'] === 'AGUARDANDO_ASSINATURAS') $colecoes['assinaturas'][] = $documento;
}
$documentos = $colecoes[$aba];

$pastasBiblioteca = [];
$setorSelecionado = '';
$exibirPastas = false;
if ($aba === 'biblioteca') {
    foreach ($documentos as $documentoBiblioteca) {
        $nomeSetor = trim((string) $documentoBiblioteca['setor']) ?: 'GERAL';
        $pastasBiblioteca[$nomeSetor] = ($pastasBiblioteca[$nomeSetor] ?? 0) + 1;
    }
    uksort($pastasBiblioteca, 'strnatcasecmp');

    $setorSolicitado = mb_strtoupper(trim((string) ($_GET['setor'] ?? '')), 'UTF-8');
    foreach (array_keys($pastasBiblioteca) as $nomeSetor) {
        if (mb_strtoupper($nomeSetor, 'UTF-8') === $setorSolicitado) {
            $setorSelecionado = $nomeSetor;
            break;
        }
    }
    $exibirPastas = $setorSelecionado === '';
    if (!$exibirPastas) {
        $documentos = array_values(array_filter(
            $documentos,
            static fn(array $documento): bool => mb_strtoupper(trim((string) $documento['setor']), 'UTF-8')
                === mb_strtoupper($setorSelecionado, 'UTF-8')
        ));
    } else {
        $documentos = [];
    }
}

$porPagina = $aba === 'biblioteca' ? 12 : 8;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$totalDocumentos = count($documentos);
$totalPaginas = max(1, (int) ceil($totalDocumentos / $porPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);
if (!$exibirPastas) {
    $documentos = array_slice($documentos, ($paginaAtual - 1) * $porPagina, $porPagina);
}

$eventos = [];
$versoes = [];
if ($documentos) {
    $documentosComHistorico = array_filter(
        $documentos,
        static fn(array $documento): bool => $auth->isValidador()
            || (int) $documento['criador_id'] === $usuarioId
    );
    $ids = array_map('intval', array_column($documentosComHistorico, 'id'));
}
if (!empty($ids)) {
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtEventos = $pdo_intra->prepare("SELECT e.*, TRIM(CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,''))) AS usuario_nome
        FROM documentos_eventos e LEFT JOIN " . DB_GLPI . ".glpi_users u ON u.id=e.usuario_id
        WHERE e.documento_id IN ($marcadores) ORDER BY e.criado_em DESC, e.id DESC");
    $stmtEventos->execute($ids);
    foreach ($stmtEventos->fetchAll(PDO::FETCH_ASSOC) as $evento) $eventos[(int) $evento['documento_id']][] = $evento;

    $stmtVersoes = $pdo_intra->prepare("SELECT * FROM documentos_versoes WHERE documento_id IN ($marcadores) ORDER BY numero DESC");
    $stmtVersoes->execute($ids);
    foreach ($stmtVersoes->fetchAll(PDO::FETCH_ASSOC) as $versao) $versoes[(int) $versao['documento_id']][] = $versao;
}

$setoresFormulario = $auth->setores();
if ($auth->isValidador()) {
    $setoresFormulario = array_values(array_filter(array_map('trim', $pdo_intra->query(
        "SELECT DISTINCT SETOR FROM matriz_comunicacao WHERE SETOR IS NOT NULL AND TRIM(SETOR)<>'' ORDER BY SETOR"
    )->fetchAll(PDO::FETCH_COLUMN))));
}

$usuariosAssinatura = [];
if ($auth->isValidador()) {
    $usuariosAssinatura = $pdo_glpi->query("SELECT id, TRIM(CONCAT(COALESCE(firstname,''), ' ', COALESCE(realname,''))) AS nome
        FROM glpi_users WHERE is_active=1 AND is_deleted=0 ORDER BY firstname, realname")->fetchAll(PDO::FETCH_ASSOC);
}

function docStatusVisual(string $status): array
{
    return match ($status) {
        'EM_VALIDACAO' => ['Aguardando T.I.', 'bg-amber-100 text-amber-800'],
        'EM_ANALISE' => ['Em análise pelo T.I.', 'bg-blue-100 text-blue-800'],
        'AJUSTES_SOLICITADOS' => ['Aguardando correção do autor', 'bg-orange-100 text-orange-800'],
        'AGUARDANDO_ASSINATURAS' => ['Aguardando assinaturas', 'bg-violet-100 text-violet-800'],
        'PUBLICADO' => ['Publicado', 'bg-emerald-100 text-emerald-800'],
        'REJEITADO' => ['Rejeitado', 'bg-rose-100 text-rose-800'],
        'ARQUIVADO' => ['Arquivado', 'bg-slate-200 text-slate-700'],
        default => ['Rascunho', 'bg-slate-100 text-slate-700'],
    };
}

function docResponsavelVisual(array $doc): string
{
    return match ($doc['responsavel_tipo']) {
        'TI' => 'Fila de validação do T.I.',
        'VALIDADOR' => trim((string) $doc['responsavel_nome']) ?: 'Validador do T.I.',
        'CRIADOR' => trim((string) $doc['criador_nome']) ?: 'Autor do documento',
        'ASSINATURAS' => 'Assinantes do envelope #' . (int) $doc['assinatura_envelope_id'],
        default => 'Fluxo concluído',
    };
}

function docTipoArquivo(string $nomeOriginal, string $mimeType): array
{
    $extensao = mb_strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION), 'UTF-8');
    return match ($extensao) {
        'pdf' => ['PDF', 'bg-rose-50 border-rose-200 text-rose-700'],
        'doc', 'docx' => ['WORD', 'bg-blue-50 border-blue-200 text-blue-700'],
        'xls', 'xlsx' => ['EXCEL', 'bg-emerald-50 border-emerald-200 text-emerald-700'],
        'jpg', 'jpeg', 'png', 'gif', 'webp' => ['IMG', 'bg-violet-50 border-violet-200 text-violet-700'],
        'mp4', 'webm', 'mov' => ['VÍDEO', 'bg-fuchsia-50 border-fuchsia-200 text-fuchsia-700'],
        'md', 'txt' => ['TEXTO', 'bg-slate-100 border-slate-200 text-slate-700'],
        default => str_starts_with($mimeType, 'image/')
            ? ['IMG', 'bg-violet-50 border-violet-200 text-violet-700']
            : ['ARQ', 'bg-slate-100 border-slate-200 text-slate-700'],
    };
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-8">
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[.25em] text-blue-600">Governança documental</p>
            <h1 class="text-3xl font-black text-navy-900 tracking-tight">Central de Documentos</h1>
            <p class="text-sm text-slate-500 mt-1">Consulte documentos do seu setor, envie processos e acompanhe cada responsabilidade.</p>
        </div>
        <button type="button" onclick="document.getElementById('modalNovoDocumento').classList.remove('hidden')"
                class="rounded-xl bg-navy-900 px-5 py-3 text-xs font-black uppercase tracking-wider text-white shadow-lg hover:bg-blue-700">
            + Enviar documento
        </button>
    </div>

    <?php if (!empty($_GET['sucesso'])): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800"><?= htmlspecialchars((string) $_GET['sucesso']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['erro'])): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800"><?= htmlspecialchars((string) $_GET['erro']) ?></div>
    <?php endif; ?>

    <nav class="sticky top-0 z-20 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur">
        <?php
        $abas = [
            'biblioteca' => ['Biblioteca do setor', count($colecoes['biblioteca'])],
            'meus' => ['Meus envios', count($colecoes['meus'])],
            'pendencias' => ['Pendências comigo', count($colecoes['pendencias'])],
            'assinaturas' => ['Aguardando assinaturas', count($colecoes['assinaturas'])],
        ];
        if ($auth->isValidador()) $abas['fila'] = ['Fila do T.I.', count($colecoes['fila'])];
        foreach ($abas as $chave => [$rotulo, $quantidade]):
        ?>
            <a href="documentos.php?aba=<?= urlencode($chave) ?>"
               class="whitespace-nowrap rounded-xl px-4 py-2.5 text-xs font-black <?= $aba === $chave ? 'bg-navy-900 text-white' : 'text-slate-500 hover:bg-slate-100' ?>">
                <?= htmlspecialchars($rotulo) ?> <span class="ml-1 opacity-70"><?= (int) $quantidade ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($aba === 'biblioteca'): ?>
        <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
            <a href="documentos.php?aba=biblioteca" class="rounded-lg px-2 py-1 hover:bg-white hover:text-blue-700">📁 Biblioteca</a>
            <?php if ($setorSelecionado !== ''): ?>
                <span class="text-slate-300">/</span>
                <span class="rounded-lg bg-white px-2 py-1 text-navy-900 shadow-sm"><?= htmlspecialchars($setorSelecionado) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($exibirPastas): ?>
        <?php if (!$pastasBiblioteca): ?>
            <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white p-16 text-center">
                <div class="text-4xl mb-3">📂</div>
                <p class="font-black text-slate-700">Nenhuma pasta disponível para o seu acesso.</p>
            </div>
        <?php else: ?>
            <section>
                <div class="mb-3 flex items-center justify-between gap-4">
                    <div><h2 class="font-black text-navy-900">Pastas por setor</h2><p class="text-xs text-slate-400">Abra uma pasta para consultar os documentos publicados.</p></div>
                    <span class="text-xs font-bold text-slate-400"><?= count($pastasBiblioteca) ?> pasta(s)</span>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <?php foreach ($pastasBiblioteca as $nomePasta => $quantidadePasta): ?>
                        <a href="documentos.php?<?= htmlspecialchars(http_build_query(['aba' => 'biblioteca', 'setor' => $nomePasta])) ?>"
                           class="group flex min-h-32 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-14 w-16 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-4xl transition group-hover:bg-amber-100">📁</span>
                            <span class="min-w-0"><strong class="block break-words text-sm font-black text-navy-900"><?= htmlspecialchars($nomePasta) ?></strong><small class="mt-1 block font-bold text-slate-400"><?= (int) $quantidadePasta ?> documento(s)</small></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php elseif (!$documentos): ?>
        <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white p-16 text-center">
            <div class="text-4xl mb-3">📂</div>
            <p class="font-black text-slate-700">Nenhum documento nesta área.</p>
            <p class="text-sm text-slate-400 mt-1">Quando houver movimentações, elas aparecerão aqui.</p>
        </div>
    <?php elseif ($aba === 'biblioteca'): ?>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3">
                <div><h2 class="text-sm font-black text-navy-900"><?= htmlspecialchars($setorSelecionado) ?></h2><p class="text-[11px] text-slate-400"><?= $totalDocumentos ?> documento(s) publicado(s)</p></div>
                <a href="documentos.php?aba=biblioteca" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600 hover:border-blue-300">← Todas as pastas</a>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($documentos as $doc):
                    $docId = (int) $doc['id'];
                    $urlVisualizar = 'api/DocumentoCentralArquivo.php?id=' . $docId . '&modo=visualizar';
                    $urlBaixar = 'api/DocumentoCentralArquivo.php?id=' . $docId . '&modo=baixar';
                    [$tipoArquivo, $classeTipoArquivo] = docTipoArquivo((string) $doc['nome_original'], (string) $doc['mime_type']);
                ?>
                    <div class="flex flex-col gap-3 px-5 py-4 transition hover:bg-blue-50/40 md:flex-row md:items-center">
                        <div class="flex min-w-0 flex-1 items-center gap-4">
                            <span class="flex h-12 w-14 shrink-0 items-center justify-center rounded-xl border text-[10px] font-black tracking-tight <?= $classeTipoArquivo ?>"><?= htmlspecialchars($tipoArquivo) ?></span>
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-black text-navy-900"><?= htmlspecialchars((string) $doc['titulo']) ?></h3>
                                <p class="mt-1 text-[11px] font-medium text-slate-400"><?= htmlspecialchars($tipoArquivo) ?> · <?= htmlspecialchars((string) $doc['tipo']) ?> · V<?= (int) $doc['versao_numero'] ?> · <?= htmlspecialchars(trim((string) $doc['criador_nome']) ?: 'Sistema') ?></p>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" class="btn-preview rounded-xl bg-blue-600 px-4 py-2 text-xs font-black text-white hover:bg-blue-700"
                                    data-url="<?= htmlspecialchars($urlVisualizar, ENT_QUOTES) ?>"
                                    data-download="<?= htmlspecialchars($urlBaixar, ENT_QUOTES) ?>"
                                    data-title="<?= htmlspecialchars((string) $doc['titulo'], ENT_QUOTES) ?>"
                                    data-name="<?= htmlspecialchars((string) $doc['nome_original'], ENT_QUOTES) ?>"
                                    data-mime="<?= htmlspecialchars((string) $doc['mime_type'], ENT_QUOTES) ?>">Visualizar</button>
                            <a href="<?= htmlspecialchars($urlBaixar) ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-600 hover:bg-white">Baixar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <?php foreach ($documentos as $doc):
            [$statusTexto, $statusClasse] = docStatusVisual((string) $doc['status']);
            [$tipoArquivo, $classeTipoArquivo] = docTipoArquivo((string) $doc['nome_original'], (string) $doc['mime_type']);
            $docId = (int) $doc['id'];
            $podeAnalisar = $auth->isValidador() && $doc['status'] === 'EM_ANALISE'
                && ($auth->isAdmin() || (int) $doc['responsavel_id'] === $usuarioId);
        ?>
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase <?= $statusClasse ?>"><?= htmlspecialchars($statusTexto) ?></span>
                            <span class="rounded-full border px-2.5 py-1 text-[9px] font-black uppercase <?= $classeTipoArquivo ?>"><?= htmlspecialchars($tipoArquivo) ?></span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-black uppercase text-slate-500"><?= htmlspecialchars((string) $doc['setor']) ?></span>
                            <span class="text-[10px] font-bold text-slate-400">V<?= (int) $doc['versao_numero'] ?></span>
                        </div>
                        <h2 class="truncate text-lg font-black text-navy-900"><?= htmlspecialchars((string) $doc['titulo']) ?></h2>
                        <p class="text-xs text-slate-500 mt-1">Enviado por <?= htmlspecialchars(trim((string) $doc['criador_nome']) ?: 'Sistema') ?></p>
                    </div>
                    <button type="button" class="btn-preview shrink-0 rounded-xl bg-blue-50 px-3 py-2 text-xs font-black text-blue-700 hover:bg-blue-100"
                            data-url="api/DocumentoCentralArquivo.php?id=<?= $docId ?>&modo=visualizar"
                            data-download="api/DocumentoCentralArquivo.php?id=<?= $docId ?>&modo=baixar"
                            data-title="<?= htmlspecialchars((string) $doc['titulo'], ENT_QUOTES) ?>"
                            data-name="<?= htmlspecialchars((string) $doc['nome_original'], ENT_QUOTES) ?>"
                            data-mime="<?= htmlspecialchars((string) $doc['mime_type'], ENT_QUOTES) ?>">Visualizar</button>
                </div>

                <?php if (!empty($doc['descricao'])): ?><p class="text-sm text-slate-600"><?= nl2br(htmlspecialchars((string) $doc['descricao'])) ?></p><?php endif; ?>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Responsabilidade atual</p>
                    <p class="mt-1 text-sm font-black text-slate-800"><?= htmlspecialchars(docResponsavelVisual($doc)) ?></p>
                    <?php if (!empty($doc['instrucao_atual'])): ?><p class="mt-2 text-xs text-orange-700"><?= nl2br(htmlspecialchars((string) $doc['instrucao_atual'])) ?></p><?php endif; ?>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="api/DocumentoCentralArquivo.php?id=<?= $docId ?>&modo=baixar" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Baixar atual</a>
                    <?php if ($auth->isValidador() && $doc['status'] === 'EM_VALIDACAO'): ?>
                        <form action="api/DocumentoCentralController.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="acao" value="assumir"><input type="hidden" name="documento_id" value="<?= $docId ?>">
                            <button class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-black text-white">Assumir validação</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ((int) $doc['criador_id'] === $usuarioId && $doc['status'] === 'AJUSTES_SOLICITADOS'): ?>
                    <form action="api/DocumentoCentralController.php" method="post" enctype="multipart/form-data" class="rounded-2xl border border-orange-200 bg-orange-50 p-4 space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="acao" value="reenviar"><input type="hidden" name="documento_id" value="<?= $docId ?>">
                        <p class="text-xs font-black uppercase text-orange-800">Enviar versão corrigida</p>
                        <textarea name="mensagem" rows="2" required placeholder="Resuma as correções realizadas" class="w-full rounded-xl border border-orange-200 bg-white p-3 text-sm"></textarea>
                        <input type="file" name="documento" required class="w-full text-xs" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png,.mp4">
                        <button class="w-full rounded-xl bg-orange-600 py-2.5 text-xs font-black text-white">Reenviar ao T.I.</button>
                    </form>
                <?php endif; ?>

                <?php if ($podeAnalisar): ?>
                    <div class="grid gap-3 md:grid-cols-2">
                        <form action="api/DocumentoCentralController.php" method="post" class="rounded-2xl border border-orange-200 p-4 space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="acao" value="solicitar_ajustes"><input type="hidden" name="documento_id" value="<?= $docId ?>">
                            <p class="text-xs font-black uppercase text-orange-700">Devolver ao autor</p>
                            <textarea name="mensagem" required minlength="5" rows="3" placeholder="Liste os ajustes necessários" class="w-full rounded-xl border border-orange-200 p-3 text-xs"></textarea>
                            <button class="w-full rounded-xl bg-orange-600 py-2 text-xs font-black text-white">Solicitar ajustes</button>
                        </form>
                        <form action="api/DocumentoCentralController.php" method="post" class="rounded-2xl border border-emerald-200 p-4 space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="documento_id" value="<?= $docId ?>">
                            <p class="text-xs font-black uppercase text-emerald-700">Aprovar versão V<?= (int) $doc['versao_numero'] ?></p>
                            <label class="flex gap-2 text-xs font-bold text-slate-600"><input type="checkbox" name="exige_assinatura" value="1" onchange="this.closest('form').querySelector('.assinantes-box').classList.toggle('hidden', !this.checked)"> Exigir assinaturas antes de publicar</label>
                            <div class="assinantes-box hidden">
                                <label class="text-[10px] font-black uppercase text-slate-400">Assinantes em ordem</label>
                                <select name="assinantes[]" multiple size="5" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs">
                                    <?php foreach ($usuariosAssinatura as $usuario): ?><option value="<?= (int) $usuario['id'] ?>"><?= htmlspecialchars((string) $usuario['nome']) ?></option><?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-[10px] text-slate-400">Use Ctrl para selecionar mais de um. A ordem visual será usada no fluxo.</p>
                            </div>
                            <button class="w-full rounded-xl bg-emerald-600 py-2 text-xs font-black text-white">Aprovar e continuar</button>
                        </form>
                    </div>
                    <form action="api/DocumentoCentralController.php" method="post" class="flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="acao" value="rejeitar"><input type="hidden" name="documento_id" value="<?= $docId ?>">
                        <input name="mensagem" required minlength="5" placeholder="Motivo da rejeição" class="min-w-0 flex-1 rounded-xl border border-rose-200 px-3 py-2 text-xs">
                        <button onclick="return confirm('Rejeitar este documento?')" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-black text-white">Rejeitar</button>
                    </form>
                <?php endif; ?>

                <?php if ($auth->isValidador() || (int) $doc['criador_id'] === $usuarioId): ?>
                    <details class="border-t border-slate-100 pt-3">
                        <summary class="cursor-pointer text-xs font-black text-slate-500">Versões e histórico</summary>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <?php foreach ($versoes[$docId] ?? [] as $versao): ?>
                                    <a href="api/DocumentoCentralArquivo.php?id=<?= $docId ?>&versao_id=<?= (int) $versao['id'] ?>&modo=baixar" class="block rounded-lg bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600">V<?= (int) $versao['numero'] ?> · <?= htmlspecialchars((string) $versao['nome_original']) ?></a>
                                <?php endforeach; ?>
                            </div>
                            <div class="space-y-2">
                                <?php foreach (array_slice($eventos[$docId] ?? [], 0, 8) as $evento): ?>
                                    <div class="border-l-2 border-blue-200 pl-3 text-xs"><p class="font-black text-slate-700"><?= htmlspecialchars((string) $evento['acao']) ?></p><p class="text-slate-400"><?= date('d/m/Y H:i', strtotime((string) $evento['criado_em'])) ?> · <?= htmlspecialchars(trim((string) $evento['usuario_nome']) ?: 'Sistema') ?></p><?php if ($evento['mensagem']): ?><p class="mt-1 text-slate-600"><?= nl2br(htmlspecialchars((string) $evento['mensagem'])) ?></p><?php endif; ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$exibirPastas && $totalPaginas > 1): ?>
        <nav class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm" aria-label="Paginação dos documentos">
            <p class="text-xs font-bold text-slate-500">Página <?= $paginaAtual ?> de <?= $totalPaginas ?> · <?= $totalDocumentos ?> item(ns)</p>
            <div class="flex items-center gap-1">
                <?php
                $paramsPagina = ['aba' => $aba];
                if ($setorSelecionado !== '') $paramsPagina['setor'] = $setorSelecionado;
                $inicioPagina = max(1, $paginaAtual - 2);
                $fimPagina = min($totalPaginas, $paginaAtual + 2);
                ?>
                <?php if ($paginaAtual > 1): ?><a class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-600" href="documentos.php?<?= htmlspecialchars(http_build_query($paramsPagina + ['pagina' => $paginaAtual - 1])) ?>">←</a><?php endif; ?>
                <?php for ($pagina = $inicioPagina; $pagina <= $fimPagina; $pagina++): ?>
                    <a class="rounded-lg px-3 py-2 text-xs font-black <?= $pagina === $paginaAtual ? 'bg-navy-900 text-white' : 'border border-slate-200 text-slate-600' ?>" href="documentos.php?<?= htmlspecialchars(http_build_query($paramsPagina + ['pagina' => $pagina])) ?>"><?= $pagina ?></a>
                <?php endfor; ?>
                <?php if ($paginaAtual < $totalPaginas): ?><a class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-600" href="documentos.php?<?= htmlspecialchars(http_build_query($paramsPagina + ['pagina' => $paginaAtual + 1])) ?>">→</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</div>
</main>

<div id="modalPreviewDocumento" class="hidden fixed inset-0 z-[110] bg-slate-950/70 p-3 md:p-6" role="dialog" aria-modal="true" aria-labelledby="tituloPreviewDocumento">
    <div class="mx-auto flex h-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 px-5 py-3">
            <div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-widest text-blue-600">Visualização do documento</p><h2 id="tituloPreviewDocumento" class="truncate text-base font-black text-navy-900">Documento</h2></div>
            <div class="flex shrink-0 items-center gap-2"><a id="baixarPreviewDocumento" href="#" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-600">Baixar</a><button type="button" id="fecharPreviewDocumento" class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-xl text-slate-500" aria-label="Fechar visualização">&times;</button></div>
        </div>
        <div id="conteudoPreviewDocumento" class="min-h-0 flex-1 bg-slate-100"></div>
    </div>
</div>

<div id="modalNovoDocumento" class="hidden fixed inset-0 z-[100] bg-slate-950/60 p-4 overflow-y-auto">
    <div class="mx-auto mt-8 max-w-2xl rounded-3xl bg-white p-7 shadow-2xl">
        <div class="flex items-center justify-between mb-5"><div><p class="text-[10px] font-black uppercase tracking-widest text-blue-600">Novo envio</p><h2 class="text-xl font-black text-navy-900">Enviar para validação</h2></div><button type="button" onclick="document.getElementById('modalNovoDocumento').classList.add('hidden')" class="text-2xl text-slate-400">&times;</button></div>
        <form action="api/DocumentoCentralController.php" method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="acao" value="novo">
            <div><label class="text-xs font-black uppercase text-slate-500">Título</label><input name="titulo" required maxlength="255" class="mt-1 w-full rounded-xl border border-slate-200 p-3"></div>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs font-black uppercase text-slate-500">Setor</label><select name="setor" required class="mt-1 w-full rounded-xl border border-slate-200 p-3"><?php foreach ($setoresFormulario as $setor): ?><option value="<?= htmlspecialchars((string) $setor) ?>"><?= htmlspecialchars((string) $setor) ?></option><?php endforeach; ?></select></div>
                <div><label class="text-xs font-black uppercase text-slate-500">Tipo</label><select name="tipo" class="mt-1 w-full rounded-xl border border-slate-200 p-3"><option value="PROCESSO">Processo / procedimento</option><option value="DOCUMENTO">Documento / manual</option></select></div>
            </div>
            <div><label class="text-xs font-black uppercase text-slate-500">Descrição</label><textarea name="descricao" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 p-3" placeholder="Objetivo, contexto e observações para o T.I."></textarea></div>
            <div><label class="text-xs font-black uppercase text-slate-500">Arquivo</label><input type="file" name="documento" required accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png,.mp4" class="mt-1 w-full rounded-xl border border-dashed border-slate-300 p-4 text-sm"><p class="mt-1 text-[10px] text-slate-400">PDF, DOCX, XLSX, JPG, PNG ou MP4 · até 50 MB.</p></div>
            <button class="w-full rounded-xl bg-navy-900 py-3.5 text-xs font-black uppercase tracking-wider text-white">Enviar ao T.I.</button>
        </form>
    </div>
</div>
<script>
(() => {
    const modal = document.getElementById('modalPreviewDocumento');
    const conteudo = document.getElementById('conteudoPreviewDocumento');
    const titulo = document.getElementById('tituloPreviewDocumento');
    const baixar = document.getElementById('baixarPreviewDocumento');

    function fecharPreview() {
        modal.classList.add('hidden');
        conteudo.replaceChildren();
        document.body.classList.remove('overflow-hidden');
    }

    function abrirPreview(botao) {
        const url = botao.dataset.url || '';
        const mime = (botao.dataset.mime || '').toLowerCase();
        const nomeArquivo = (botao.dataset.name || '').toLowerCase();
        titulo.textContent = botao.dataset.title || 'Documento';
        baixar.href = botao.dataset.download || url;
        conteudo.replaceChildren();

        let elemento;
        if (nomeArquivo.endsWith('.md')) {
            elemento = document.createElement('iframe');
            elemento.title = titulo.textContent;
            elemento.className = 'h-full w-full border-0';
            elemento.src = url + '&render_markdown=1';
        } else if (mime.startsWith('text/')) {
            elemento = document.createElement('iframe');
            elemento.title = titulo.textContent;
            elemento.className = 'h-full w-full border-0 bg-white';
            elemento.src = url;
        } else if (mime === 'application/pdf') {
            elemento = document.createElement('iframe');
            elemento.title = titulo.textContent;
            elemento.className = 'h-full w-full border-0';
            elemento.src = url;
        } else if (mime.startsWith('image/')) {
            elemento = document.createElement('img');
            elemento.alt = titulo.textContent;
            elemento.className = 'h-full w-full object-contain p-4';
            elemento.src = url;
        } else if (mime === 'video/mp4') {
            elemento = document.createElement('video');
            elemento.className = 'h-full w-full bg-black object-contain';
            elemento.controls = true;
            elemento.src = url;
        } else {
            elemento = document.createElement('div');
            elemento.className = 'flex h-full flex-col items-center justify-center p-8 text-center';
            const icone = document.createElement('div');
            const ehWord = mime.includes('wordprocessingml') || mime === 'application/msword';
            const ehExcel = mime.includes('spreadsheetml') || mime === 'application/vnd.ms-excel';
            icone.className = 'mb-4 flex h-20 min-w-20 items-center justify-center rounded-2xl border px-4 text-lg font-black ' + (ehWord
                ? 'border-blue-200 bg-blue-50 text-blue-700'
                : (ehExcel ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600'));
            icone.textContent = ehWord ? 'WORD' : (ehExcel ? 'EXCEL' : 'ARQUIVO');
            const mensagem = document.createElement('p');
            mensagem.className = 'font-black text-slate-700';
            mensagem.textContent = (ehWord ? 'O Word' : (ehExcel ? 'O Excel' : 'Este formato')) + ' não possui visualização nativa neste navegador.';
            const instrucao = document.createElement('p');
            instrucao.className = 'mt-2 text-sm text-slate-400';
            instrucao.textContent = 'Use o botão Baixar para abrir o arquivo no aplicativo correspondente.';
            elemento.append(icone, mensagem, instrucao);
        }
        conteudo.appendChild(elemento);
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    document.querySelectorAll('.btn-preview').forEach(botao => botao.addEventListener('click', () => abrirPreview(botao)));
    document.getElementById('fecharPreviewDocumento').addEventListener('click', fecharPreview);
    modal.addEventListener('click', evento => { if (evento.target === modal) fecharPreview(); });
    document.addEventListener('keydown', evento => { if (evento.key === 'Escape' && !modal.classList.contains('hidden')) fecharPreview(); });
})();
</script>
<?php include 'includes/footer.php'; ?>
