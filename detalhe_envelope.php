<?php
require_once 'config.php';

$user_id = $_SESSION['user_id'] ?? 0;
$envelope_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$envelope_id) {
    die("<script>alert('ID inválido!'); window.location.href='minhas_assinaturas.php';</script>");
}

// ── Lógica de Cancelamento do Envelope ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cancelar') {
    // 1. Muda o status do envelope para cancelado
    $pdo_intra->prepare("UPDATE sistemas_assinaturas SET status = 'cancelado' WHERE id = ? AND criado_por = ?")
              ->execute([$envelope_id, $user_id]);
    
    // 2. Trava quem ainda não assinou
    $pdo_intra->prepare("UPDATE assinaturas_fluxo SET status = 'recusado' WHERE fk_assinatura = ? AND status = 'pendente'")
              ->execute([$envelope_id]);
              
    registrarLog($pdo_intra, 'CANCELOU ENVELOPE', "Cancelou o envelope ID: $envelope_id", $user_id, $_SERVER['REMOTE_ADDR']);
    header("Location: detalhe_envelope.php?id=$envelope_id&sucesso=cancelado");
    exit;
}

// ── Busca os dados do Envelope ──────────────────────────────────────────────
$stmt = $pdo_intra->prepare("SELECT * FROM sistemas_assinaturas WHERE id = ? AND criado_por = ?");
$stmt->execute([$envelope_id, $user_id]);
$envelope = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$envelope) {
    die("<main class='flex-1 p-10'><div class='bg-red-50 text-red-600 p-6 rounded-2xl font-bold'>Acesso negado ou envelope não encontrado.</div></main>");
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
                    <button onclick="abrirPDF('<?= $envelope['arquivo_path'] ?>')" class="bg-blue-50 hover:bg-corporate-blue text-corporate-blue hover:text-white px-5 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all shadow-sm flex items-center justify-center gap-2">
                        <span>👁️</span> Ver Documento Original
                    </button>
                    
                    <?php if ($envelope['status'] === 'aguardando' || $envelope['status'] === 'em_andamento'): ?>
                        <form method="POST" onsubmit="return confirm('ATENÇÃO: Deseja realmente cancelar este envelope? Todas as assinaturas pendentes serão invalidadas e não será possível reverter.');">
                            <input type="hidden" name="acao" value="cancelar">
                            <button type="submit" class="w-full bg-white hover:bg-rose-50 border border-slate-200 hover:border-rose-200 text-slate-400 hover:text-rose-500 px-5 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all shadow-sm">
                                🛑 Cancelar Envelope
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
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

<div id="modalPDF" class="hidden fixed inset-0 z-[100] bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-4xl h-[85vh] rounded-[2rem] shadow-2xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-black text-navy-900 text-sm">Visualização do Documento</h3>
            <button onclick="fecharPDF()" class="text-slate-400 hover:text-rose-500 text-2xl font-black">&times;</button>
        </div>
        <iframe id="iframeEnvelope" class="w-full h-full border-0 bg-slate-100"></iframe>
    </div>
</div>

<script>
function abrirPDF(path) {
    document.getElementById('iframeEnvelope').src = 'api/serve_envelope.php?path=' + encodeURIComponent(path);
    document.getElementById('modalPDF').classList.remove('hidden');
}
function fecharPDF() {
    document.getElementById('modalPDF').classList.add('hidden');
    document.getElementById('iframeEnvelope').src = '';
}
</script>

<?php include 'includes/footer.php'; ?>