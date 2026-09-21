<?php
require_once 'config.php';
require_once __DIR__ . '/services/EmailAssinaturaService.php';
require_once __DIR__ . '/includes/DocumentoCentralAssinatura.php';

$user_id = $_SESSION['user_id'] ?? 0;
$envelope_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (empty($_SESSION['csrf_detalhe_envelope'])) {
    $_SESSION['csrf_detalhe_envelope'] = bin2hex(random_bytes(32));
}

if (!$envelope_id) {
    die("<script>alert('ID inválido!'); window.location.href='minhas_assinaturas.php';</script>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfRecebido = (string) ($_POST['csrf_token'] ?? '');
    if ($csrfRecebido === '' || !hash_equals((string) $_SESSION['csrf_detalhe_envelope'], $csrfRecebido)) {
        http_response_code(419);
        exit('Sua sessão expirou. Atualize a página e tente novamente.');
    }
}

// ── Lógica de Cancelamento do Envelope ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cancelar') {
    $pdo_intra->beginTransaction();
    try {
        $stmtCancelar = $pdo_intra->prepare('SELECT criado_por, status FROM sistemas_assinaturas WHERE id=? FOR UPDATE');
        $stmtCancelar->execute([$envelope_id]);
        $envelopeCancelar = $stmtCancelar->fetch(PDO::FETCH_ASSOC);
        if (!$envelopeCancelar
            || ((int) $envelopeCancelar['criado_por'] !== (int) $user_id && empty($_SESSION['is_admin']))) {
            throw new RuntimeException('Você não possui permissão para cancelar este envelope.');
        }
        if (!in_array($envelopeCancelar['status'], ['aguardando', 'em_andamento'], true)) {
            throw new RuntimeException('Este envelope não pode mais ser cancelado.');
        }

        $pdo_intra->prepare("UPDATE sistemas_assinaturas SET status='cancelado' WHERE id=?")
            ->execute([$envelope_id]);
        $pdo_intra->prepare("UPDATE assinaturas_fluxo
            SET status='recusado', justificativa_recusa='Cancelado pelo criador'
            WHERE fk_assinatura=? AND status IN ('pendente','aguardando')")
            ->execute([$envelope_id]);
        documentoCentralInterromperAssinatura(
            $pdo_intra,
            (int) $envelope_id,
            (int) $user_id,
            'Envelope cancelado pelo responsável do fluxo de assinatura.'
        );
        $pdo_intra->commit();
    } catch (Throwable $e) {
        if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
        http_response_code(422);
        exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }

    registrarLog($pdo_intra, 'CANCELOU ENVELOPE', "Cancelou o envelope ID: $envelope_id");
    header("Location: detalhe_envelope.php?id=$envelope_id&sucesso=cancelado");
    exit;
}

// ── Busca os dados do Envelope ──────────────────────────────────────────────
$stmt = $pdo_intra->prepare("SELECT sa.*,
    EXISTS(SELECT 1 FROM assinaturas_fluxo af WHERE af.fk_assinatura = sa.id AND af.glpi_user_id = ?) AS participa
    FROM sistemas_assinaturas sa WHERE sa.id = ?");
$stmt->execute([$user_id, $envelope_id]);
$envelope = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$envelope || ((int)$envelope['criado_por'] !== (int)$user_id && !$envelope['participa'] && empty($_SESSION['is_admin']))) {
    die("<main class='flex-1 p-10'><div class='bg-red-50 text-red-600 p-6 rounded-2xl font-bold'>Acesso negado ou envelope não encontrado.</div></main>");
}

$pode_gerenciar = (int)$envelope['criado_por'] === (int)$user_id || !empty($_SESSION['is_admin']);
$mensagem_email = null;
$erro_email_tela = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'reenviar_email') {
    if (!$pode_gerenciar || $envelope['status'] !== 'concluido') {
        $erro_email_tela = 'Você não possui permissão para enviar este documento.';
    } else {
        try {
            (new EmailAssinaturaService())->enviarEnvelope($pdo_intra, $pdo_glpi, (int)$envelope_id, true);
            $mensagem_email = 'Documento enviado ao criador, participantes e e-mails adicionais.';
            $stmt->execute([$user_id, $envelope_id]);
            $envelope = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $erro_email_tela = $e->getMessage();
            $pdo_intra->prepare('UPDATE sistemas_assinaturas SET erro_email = ? WHERE id = ?')
                ->execute([$e->getMessage(), $envelope_id]);
            error_log('Assinaturas/reenvio-email: ' . $e->getMessage());
        }
    }
}


include 'includes/header.php';
include 'includes/sidebar.php';

// ── Busca o Fluxo de Assinantes ─────────────────────────────────────────────
$stmt_fluxo = $pdo_intra->prepare("
    SELECT af.*, u.firstname, u.realname 
    FROM assinaturas_fluxo af
    JOIN " . DB_GLPI . ".glpi_users u ON af.glpi_user_id = u.id
    WHERE af.fk_assinatura = ?
    ORDER BY af.ordem ASC, af.id ASC
");
$stmt_fluxo->execute([$envelope_id]);
$assinantes = $stmt_fluxo->fetchAll(PDO::FETCH_ASSOC);

$stmt_docs = $pdo_intra->prepare("SELECT * FROM assinatura_documentos WHERE envelope_id = ? ORDER BY id");
$stmt_docs->execute([$envelope_id]);
$documentos = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

// Configuração visual do Status Principal
$status_cfg = match($envelope['status']) {
    'concluido' => ['bg-emerald-100 text-emerald-700', '✔ Concluído'],
    'cancelado' => ['bg-rose-100 text-rose-600',       '✕ Cancelado'],
    default     => ['bg-amber-100 text-amber-700',     '🕐 Em andamento'],
};
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
    <div class="max-w-5xl mx-auto space-y-6">

        <a href="minhas_assinaturas.php" class="inline-flex items-center gap-2 text-slate-400 hover:text-navy-900 font-bold text-xs uppercase tracking-widest transition-colors mb-4">
            ← Voltar para Assinaturas
        </a>

        <?php if(isset($_GET['sucesso'])): ?>
            <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl font-bold text-sm border border-emerald-100 animate-pulse">
                Ação realizada com sucesso!
            </div>
        <?php endif; ?>

        <?php if ($mensagem_email): ?>
            <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl font-bold text-sm border border-emerald-200">
                <?= htmlspecialchars($mensagem_email) ?>
            </div>
        <?php elseif ($erro_email_tela): ?>
            <div class="bg-rose-50 text-rose-700 p-4 rounded-2xl text-sm border border-rose-200">
                <p class="font-black">O envelope está concluído, mas o e-mail não foi enviado.</p>
                <p class="mt-1 break-words"><?= htmlspecialchars($erro_email_tela) ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                <div>
                    <div class="flex gap-2 mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full <?= $status_cfg[0] ?>">
                            <?= $status_cfg[1] ?>
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full bg-slate-100 text-slate-500">
                            Fluxo <?= ucfirst($envelope['tipo_fluxo']) ?>
                        </span>
                    </div>
                    <h1 class="text-2xl font-black text-navy-900 tracking-tight leading-tight">
                        <?= htmlspecialchars($envelope['titulo']) ?>
                    </h1>
                    <p class="text-xs text-slate-400 font-medium mt-2">
                        Enviado em <?= date('d/m/Y \à\s H:i', strtotime($envelope['criado_em'])) ?>
                    </p>
                </div>
                
                <div class="flex flex-col gap-2 shrink-0">
                    <?php if ($pode_gerenciar && $envelope['status'] === 'concluido'): ?>
                        <form method="POST" onsubmit="return confirm('Enviar novamente para todos os participantes, criador e e-mails adicionais?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_detalhe_envelope']) ?>">
                            <input type="hidden" name="acao" value="reenviar_email">
                            <button type="submit" class="w-full bg-navy-900 hover:bg-corporate-blue text-white px-5 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all shadow-sm">
                                ✉ Enviar documentos por e-mail
                            </button>
                        </form>
                        <?php if (!empty($envelope['email_enviado_em'])): ?>
                            <p class="text-[10px] text-emerald-600 font-bold text-center">Último envio: <?= date('d/m/Y H:i', strtotime($envelope['email_enviado_em'])) ?></p>
                        <?php elseif (!empty($envelope['erro_email'])): ?>
                            <p class="text-[10px] text-rose-600 font-bold max-w-xs break-words">Falha anterior: <?= htmlspecialchars($envelope['erro_email']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ((int)$envelope['criado_por'] === (int)$user_id && ($envelope['status'] === 'aguardando' || $envelope['status'] === 'em_andamento')): ?>
                        <form method="POST" onsubmit="return confirm('ATENÇÃO: Deseja realmente cancelar este envelope? Todas as assinaturas pendentes serão invalidadas e não será possível reverter.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_detalhe_envelope']) ?>">
                            <input type="hidden" name="acao" value="cancelar">
                            <button type="submit" class="w-full bg-white hover:bg-rose-50 border border-slate-200 hover:border-rose-200 text-slate-400 hover:text-rose-500 px-5 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all shadow-sm">
                                🛑 Cancelar Envelope
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] border border-slate-200 p-5 md:p-8 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-4 mb-5">
                <h2 class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Documentos do envelope</h2>
                <span class="shrink-0 px-3 py-1.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest">
                    <?= count($documentos) ?> arquivo<?= count($documentos) === 1 ? '' : 's' ?>
                </span>
            </div>
            <div class="max-h-[28rem] overflow-y-auto overscroll-contain space-y-3 pr-1 md:pr-2">
                <?php foreach ($documentos as $indice => $doc): ?>
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <div class="min-w-0 flex-1">
                            <p class="text-[9px] uppercase font-black tracking-widest text-slate-400 mb-1">Documento <?= $indice + 1 ?> de <?= count($documentos) ?></p>
                            <p class="font-bold text-navy-900 text-sm break-words"><?= htmlspecialchars($doc['nome_original']) ?></p>
                            <p class="text-[10px] uppercase font-black tracking-widest text-slate-400"><?= htmlspecialchars($doc['status']) ?></p>
                        </div>
                        <div class="flex flex-wrap sm:flex-nowrap gap-2 shrink-0">
                            <button type="button" onclick='abrirPDF(<?= (int)$doc['id'] ?>, <?= json_encode($doc['nome_original'], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-blue-50 text-blue-600 font-black text-[10px] uppercase whitespace-nowrap">Visualizar</button>
                            <?php if ($envelope['status'] === 'concluido'): ?>
                                <a href="serve_documento.php?assinatura_doc_id=<?= (int)$doc['id'] ?>&modo=baixar" class="flex-1 sm:flex-none text-center px-4 py-2.5 rounded-xl bg-navy-900 text-white font-black text-[10px] uppercase whitespace-nowrap">Baixar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm">
            <h2 class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 mb-6">Rastreio de Assinaturas</h2>
            
            <div class="space-y-4">
                <?php foreach ($assinantes as $ass): 
                    $foi_assinado = $ass['status'] === 'assinado';
                    $foi_recusado = $ass['status'] === 'recusado' || $envelope['status'] === 'cancelado';
                    
                    $cor_borda = $foi_assinado ? 'border-emerald-500' : ($foi_recusado ? 'border-rose-400' : 'border-slate-200');
                    $cor_bg = $foi_assinado ? 'bg-emerald-50' : ($foi_recusado ? 'bg-rose-50' : 'bg-slate-50');
                    $icone = $foi_assinado ? '✅' : ($foi_recusado ? '❌' : '⏳');
                ?>
                    <div class="flex flex-col md:flex-row md:items-center justify-between p-5 <?= $cor_bg ?> border <?= $cor_borda ?> rounded-2xl gap-4 transition-colors">
                        
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-lg shadow-sm shrink-0">
                                <?= $icone ?>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest mb-0.5">
                                    <?= $envelope['tipo_fluxo'] === 'sequencial' ? "Ordem {$ass['ordem']} • " : "" ?>Destinatário
                                </p>
                                <h3 class="font-bold text-navy-900 text-sm uppercase">
                                    <?= htmlspecialchars($ass['firstname'] . ' ' . $ass['realname']) ?>
                                </h3>
                            </div>
                        </div>

                        <?php if ($foi_assinado): ?>
                            <div class="text-left md:text-right bg-white p-3 rounded-xl border border-emerald-100 shadow-sm min-w-[280px]">
                                <p class="text-[9px] text-emerald-600 font-black uppercase tracking-widest mb-1">✔ Assinado em <?= date('d/m/Y H:i:s', strtotime($ass['assinado_em'])) ?></p>
                                <p class="text-[10px] text-slate-500 font-mono break-all leading-tight">IP: <?= $ass['ip_assinatura'] ?></p>
                                <p class="text-[10px] text-slate-500 font-mono break-all leading-tight mt-1" title="Lacre SHA-256">Lacre: <?= substr($ass['lacre_hash'], 0, 24) ?>...</p>
                            </div>
                        <?php elseif ($foi_recusado): ?>
                            <div class="text-left md:text-right">
                                <span class="text-[10px] font-black uppercase tracking-widest text-rose-500">Cancelado/Recusado</span>
                            </div>
                        <?php else: ?>
                            <div class="text-left md:text-right">
                                <span class="text-[10px] font-black uppercase tracking-widest text-amber-500 animate-pulse">Aguardando Assinatura</span>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</main>

<div id="modalPDF" class="hidden fixed inset-0 z-[100] bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-2 md:p-4">
    <div class="bg-white w-full max-w-6xl h-[92vh] md:h-[88vh] rounded-2xl md:rounded-[2rem] shadow-2xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
        <div class="px-4 md:px-6 py-4 border-b border-slate-100 flex justify-between items-center gap-4 bg-slate-50 shrink-0">
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Visualização do documento</p>
                <h3 id="modalPDFTitulo" class="font-black text-navy-900 text-sm truncate">Documento</h3>
            </div>
            <button onclick="fecharPDF()" class="text-slate-400 hover:text-rose-500 text-2xl font-black">&times;</button>
        </div>
        <iframe id="iframeEnvelope" class="w-full h-full border-0 bg-slate-100"></iframe>
    </div>
</div>

<script>
function abrirPDF(documentoId, nomeDocumento) {
    document.getElementById('modalPDFTitulo').textContent = nomeDocumento || 'Documento';
    document.getElementById('iframeEnvelope').src = 'serve_documento.php?assinatura_doc_id=' + documentoId;
    document.getElementById('modalPDF').classList.remove('hidden');
}
function fecharPDF() {
    document.getElementById('modalPDF').classList.add('hidden');
    document.getElementById('iframeEnvelope').src = '';
}
</script>

<?php include 'includes/footer.php'; ?>
