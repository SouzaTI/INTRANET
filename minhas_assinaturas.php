<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// ── Pendentes do usuário logado ──────────────────────────────────────────────
$user_id = $_SESSION['user_id'] ?? 0;

$stmt_pendentes = $pdo_intra->prepare("
    SELECT
        sa.id            AS envelope_id,
        sa.titulo,
        sa.arquivo_path,
        sa.tipo_fluxo,
        sa.criado_em,
        u.firstname      AS criador_nome,
        u.realname       AS criador_sobrenome,
        (SELECT COUNT(*) FROM assinaturas_fluxo WHERE fk_assinatura = sa.id)                        AS total_assinantes,
        (SELECT COUNT(*) FROM assinaturas_fluxo WHERE fk_assinatura = sa.id AND status = 'assinado') AS ja_assinaram
    FROM   assinaturas_fluxo af
    INNER JOIN sistemas_assinaturas sa          ON sa.id = af.fk_assinatura
    INNER JOIN " . DB_GLPI . ".glpi_users u     ON u.id  = sa.criado_por
    WHERE  af.glpi_user_id = :uid
      AND  af.status       = 'pendente'
      AND  sa.status       NOT IN ('concluido','cancelado')
    ORDER  BY sa.criado_em DESC
");
$stmt_pendentes->execute([':uid' => $user_id]);
$pendentes = $stmt_pendentes->fetchAll(PDO::FETCH_ASSOC);

// ── Histórico (já assinados ou recusados pelo usuário) ───────────────────────
$stmt_hist = $pdo_intra->prepare("
    SELECT
        sa.id AS envelope_id,
        sa.titulo,
        sa.tipo_fluxo,
        af.status       AS meu_status,
        af.assinado_em,
        af.lacre_hash
    FROM   assinaturas_fluxo af
    INNER JOIN sistemas_assinaturas sa ON sa.id = af.fk_assinatura
    WHERE  af.glpi_user_id = :uid
      AND  af.status IN ('assinado','recusado')
    ORDER  BY af.assinado_em DESC
    LIMIT  20
");
$stmt_hist->execute([':uid' => $user_id]);
$historico = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
<div class="max-w-6xl mx-auto space-y-10">

    <!-- ── Cabeçalho da página ───────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 mb-1">Portal de Documentos</p>
            <h1 class="text-3xl font-black text-navy-900 uppercase tracking-tighter italic leading-none">
                Assinaturas Digitais
            </h1>
        </div>
        <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-2xl px-5 py-3 shadow-sm">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-[11px] font-black uppercase tracking-widest text-slate-500">
                <?= count($pendentes) ?> aguardando sua assinatura
            </span>
        </div>
    </div>

    <!-- ── SEÇÃO 1 · Pendentes ───────────────────────────────────────────── -->
    <section>
        <h2 class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 mb-4 px-1">
            Pendentes de Assinatura
        </h2>

        <?php if (empty($pendentes)): ?>
        <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm p-14 flex flex-col items-center gap-3">
            <span class="text-5xl">✅</span>
            <p class="font-black text-navy-900 uppercase tracking-tighter text-lg italic">Tudo em dia!</p>
            <p class="text-slate-400 text-sm font-medium">Não há documentos aguardando sua assinatura.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php foreach ($pendentes as $doc):
                $progresso = $doc['total_assinantes'] > 0
                    ? round(($doc['ja_assinaram'] / $doc['total_assinantes']) * 100)
                    : 0;
                $badge_fluxo = $doc['tipo_fluxo'] === 'sequencial'
                    ? ['bg-violet-100 text-violet-700', '⛓ Sequencial']
                    : ['bg-sky-100 text-sky-700',       '⚡ Paralelo'];
            ?>
            <div class="group bg-white rounded-[2rem] border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-6 flex flex-col gap-5">

                <!-- Tipo de fluxo + data -->
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full <?= $badge_fluxo[0] ?>">
                        <?= $badge_fluxo[1] ?>
                    </span>
                    <span class="text-[10px] text-slate-400 font-bold">
                        <?= date('d/m/Y', strtotime($doc['criado_em'])) ?>
                    </span>
                </div>

                <!-- Título -->
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Documento</p>
                    <h3 class="font-black text-navy-900 text-base leading-snug line-clamp-2">
                        <?= htmlspecialchars($doc['titulo']) ?>
                    </h3>
                    <p class="text-slate-400 text-[11px] font-medium mt-1">
                        Enviado por
                        <span class="text-navy-900 font-black">
                            <?= htmlspecialchars($doc['criador_nome'] . ' ' . $doc['criador_sobrenome']) ?>
                        </span>
                    </p>
                </div>

                <!-- Barra de progresso do envelope -->
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Progresso do envelope</span>
                        <span class="text-[9px] font-black text-navy-900"><?= $doc['ja_assinaram'] ?>/<?= $doc['total_assinantes'] ?></span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="h-2 rounded-full bg-gradient-to-r from-corporate-blue to-sky-400 transition-all duration-700"
                             style="width: <?= $progresso ?>%"></div>
                    </div>
                </div>

                <!-- CTA -->
                <button
                    onclick="abrirModal(<?= $doc['envelope_id'] ?>, '<?= addslashes(htmlspecialchars($doc['titulo'])) ?>', '<?= $doc['arquivo_path'] ?>')"
                    class="w-full bg-navy-900 hover:bg-corporate-blue text-white font-black py-4 rounded-2xl shadow-md hover:shadow-lg transition-all uppercase tracking-widest text-xs flex items-center justify-center gap-2 mt-auto">
                    🖊 Revisar e Assinar
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── SEÇÃO 2 · Histórico ───────────────────────────────────────────── -->
    <?php if (!empty($historico)): ?>
    <section>
        <h2 class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 mb-4 px-1">Histórico Recente</h2>
        <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left px-6 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400">Documento</th>
                        <th class="text-left px-6 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 hidden md:table-cell">Fluxo</th>
                        <th class="text-left px-6 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 hidden lg:table-cell">Lacre Digital</th>
                        <th class="text-left px-6 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400">Status</th>
                        <th class="text-left px-6 py-4 text-[9px] font-black uppercase tracking-widest text-slate-400 hidden md:table-cell">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($historico as $h):
                        $status_cls = $h['meu_status'] === 'assinado'
                            ? 'bg-emerald-100 text-emerald-700'
                            : 'bg-rose-100 text-rose-700';
                        $status_txt = $h['meu_status'] === 'assinado' ? '✔ Assinado' : '✕ Recusado';
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-6 py-4 font-black text-navy-900 text-sm leading-tight max-w-[200px] truncate">
                            <?= htmlspecialchars($h['titulo']) ?>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500">
                                <?= ucfirst($h['tipo_fluxo']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 hidden lg:table-cell">
                            <?php if ($h['lacre_hash']): ?>
                            <code class="text-[10px] font-mono text-slate-400 bg-slate-100 px-2 py-1 rounded-lg">
                                <?= substr($h['lacre_hash'], 0, 16) ?>…
                            </code>
                            <?php else: ?>
                            <span class="text-slate-300 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full <?= $status_cls ?>">
                                <?= $status_txt ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-[11px] text-slate-400 font-bold hidden md:table-cell">
                            <?= $h['assinado_em'] ? date('d/m/Y H:i', strtotime($h['assinado_em'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

</div><!-- /max-w -->
</main>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL DE ASSINATURA
════════════════════════════════════════════════════════════════════════════ -->
<div id="modalAssinatura"
     class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="modalTitulo">

    <div class="bg-white w-full max-w-4xl rounded-[2.5rem] shadow-2xl flex flex-col overflow-hidden max-h-[92vh]
                scale-95 opacity-0 transition-all duration-200" id="modalInner">

        <!-- Cabeçalho do modal -->
        <div class="flex items-center justify-between px-8 py-5 border-b border-slate-100 shrink-0">
            <div class="flex items-center gap-3">
                <span class="text-emerald-500 text-xl">🔒</span>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Assinatura Digital Segura</p>
                    <h3 id="modalTitulo" class="font-black text-navy-900 text-sm leading-tight max-w-md truncate"></h3>
                </div>
            </div>
            <button onclick="fecharModal()"
                    class="text-slate-300 hover:text-rose-500 transition-colors text-2xl font-black leading-none px-1"
                    aria-label="Fechar modal">
                &times;
            </button>
        </div>

        <!-- Corpo: preview + PIN lado a lado em telas grandes -->
        <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">

            <!-- Preview do PDF -->
            <div class="flex-1 bg-slate-100 relative min-h-[260px]">
                <iframe id="iframePDF" src="" title="Preview do documento"
                        class="w-full h-full border-0 absolute inset-0"></iframe>
                <div id="loadingPDF"
                     class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-slate-400">
                    <svg class="animate-spin w-8 h-8" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span class="text-xs font-bold uppercase tracking-widest">Carregando documento…</span>
                </div>
            </div>

            <!-- Painel de assinatura -->
            <div class="w-full lg:w-80 shrink-0 flex flex-col gap-6 p-8 border-t lg:border-t-0 lg:border-l border-slate-100 overflow-y-auto">

                <!-- Aviso de revisão -->
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex gap-3">
                    <span class="text-amber-500 text-lg shrink-0 mt-0.5">⚠️</span>
                    <p class="text-amber-800 text-[11px] font-bold leading-snug">
                        Revise o documento ao lado antes de assinar. Sua assinatura é juridicamente vinculante e irrevogável.
                    </p>
                </div>

                <!-- Input PIN -->
                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-3 block">
                        PIN de 4 dígitos
                    </label>

                    <!-- 4 boxes individuais de dígito -->
                    <div class="flex gap-3 justify-center mb-2" id="pinBoxes">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <input type="password"
                               inputmode="numeric"
                               maxlength="1"
                               pattern="\d"
                               autocomplete="off"
                               data-pin-index="<?= $i ?>"
                               class="pin-digit w-14 h-14 text-center text-2xl font-black text-navy-900
                                      border-2 border-slate-200 rounded-2xl bg-slate-50
                                      focus:border-corporate-blue focus:bg-white focus:outline-none
                                      transition-all caret-transparent"
                               aria-label="Dígito <?= $i + 1 ?> do PIN"/>
                        <?php endfor; ?>
                    </div>

                    <!-- Mensagem de erro do PIN -->
                    <p id="pinErro" class="hidden text-rose-600 text-[11px] font-bold text-center mt-2"></p>
                </div>

                <!-- CTA de confirmação -->
                <button id="btnConfirmar"
                        onclick="confirmarAssinatura()"
                        disabled
                        class="w-full bg-navy-900 text-white font-black py-4 rounded-2xl shadow-lg
                               hover:bg-corporate-blue transition-all uppercase tracking-widest text-xs
                               flex items-center justify-center gap-2
                               disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-navy-900">
                    <span id="btnConfirmarTexto">✍ Confirmar Assinatura Digital</span>
                    <svg id="btnSpinner" class="hidden animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4"/>
                        <path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                </button>

                <!-- Botão recusar -->
                <button onclick="fecharModal()"
                        class="w-full text-slate-400 hover:text-rose-500 font-black text-xs uppercase tracking-widest transition-colors py-2">
                    Cancelar e Fechar
                </button>

                <!-- Rodapé de segurança -->
                <div class="mt-auto pt-4 border-t border-slate-100 space-y-1">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Lacre Digital gerado com:</p>
                    <p class="text-[9px] text-slate-300 font-mono leading-relaxed">
                        SHA-256 ( hash do arquivo + ID usuário + IP + timestamp )
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast de feedback ─────────────────────────────────────────────────── -->
<div id="toast"
     class="hidden fixed bottom-8 right-8 z-[60] flex items-center gap-3 px-6 py-4
            rounded-2xl shadow-2xl text-white font-black text-sm uppercase tracking-widest
            transition-all duration-300">
</div>

<script>
// ── Estado ────────────────────────────────────────────────────────────────
let _envelopeId = null;

// ── Abertura do modal ─────────────────────────────────────────────────────
function abrirModal(envelopeId, titulo, arquivoPath) {
    _envelopeId = envelopeId;

    document.getElementById('modalTitulo').textContent = titulo;
    document.getElementById('pinErro').classList.add('hidden');
    document.getElementById('pinErro').textContent = '';

    // Carrega PDF no iframe
    const iframe = document.getElementById('iframePDF');
    const loading = document.getElementById('loadingPDF');
    loading.classList.remove('hidden');
    iframe.src = '';
    iframe.onload = () => loading.classList.add('hidden');
    iframe.src = 'serve_documento.php?path=' + encodeURIComponent(arquivoPath) + '&modo=visualizar';

    // Limpa PIN
    document.querySelectorAll('.pin-digit').forEach(i => i.value = '');
    atualizarBotaoConfirmar();

    // Abre modal
    const modal = document.getElementById('modalAssinatura');
    const inner = document.getElementById('modalInner');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        inner.classList.remove('scale-95', 'opacity-0');
        inner.classList.add('scale-100', 'opacity-100');
    });

    // Foca no primeiro dígito
    document.querySelector('[data-pin-index="0"]').focus();
}

// ── Fechamento do modal ───────────────────────────────────────────────────
function fecharModal() {
    const modal = document.getElementById('modalAssinatura');
    const inner = document.getElementById('modalInner');
    inner.classList.remove('scale-100', 'opacity-100');
    inner.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.getElementById('iframePDF').src = '';
        document.querySelectorAll('.pin-digit').forEach(i => i.value = '');
        atualizarBotaoConfirmar();
        _envelopeId = null;
    }, 180);
}

// ── PIN: navega entre os boxes e habilita botão ───────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const digits = document.querySelectorAll('.pin-digit');

    digits.forEach((input, idx) => {
        input.addEventListener('input', e => {
            // Aceita apenas dígito
            const val = e.target.value.replace(/\D/g, '').slice(-1);
            e.target.value = val;
            if (val && idx < digits.length - 1) digits[idx + 1].focus();
            atualizarBotaoConfirmar();
        });

        input.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                digits[idx - 1].value = '';
                digits[idx - 1].focus();
                atualizarBotaoConfirmar();
            }
            if (e.key === 'Enter') confirmarAssinatura();
        });

        // Permite colar PIN completo de 4 dígitos no primeiro box
        input.addEventListener('paste', e => {
            if (idx !== 0) return;
            e.preventDefault();
            const pasta = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 4);
            pasta.split('').forEach((ch, i) => { if (digits[i]) digits[i].value = ch; });
            (digits[Math.min(pasta.length, digits.length - 1)] || digits[digits.length - 1]).focus();
            atualizarBotaoConfirmar();
        });
    });

    // Fecha ao clicar no backdrop
    document.getElementById('modalAssinatura').addEventListener('click', e => {
        if (e.target === document.getElementById('modalAssinatura')) fecharModal();
    });

    // Fecha com ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !document.getElementById('modalAssinatura').classList.contains('hidden')) {
            fecharModal();
        }
    });
});

function atualizarBotaoConfirmar() {
    const completo = [...document.querySelectorAll('.pin-digit')].every(i => /^\d$/.test(i.value));
    document.getElementById('btnConfirmar').disabled = !completo;
}

// ── Submissão via fetch ───────────────────────────────────────────────────
function confirmarAssinatura() {
    const pin = [...document.querySelectorAll('.pin-digit')].map(i => i.value).join('');
    if (pin.length !== 4 || !_envelopeId) return;

    const btn      = document.getElementById('btnConfirmar');
    const txt      = document.getElementById('btnConfirmarTexto');
    const spinner  = document.getElementById('btnSpinner');
    const erroEl   = document.getElementById('pinErro');

    btn.disabled = true;
    txt.textContent = 'Processando…';
    spinner.classList.remove('hidden');
    erroEl.classList.add('hidden');

    const body = new FormData();
    body.append('envelope_id',  _envelopeId);
    body.append('pin_digitado', pin);

    fetch('api/processar_assinatura.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                mostrarToast('✔ Documento assinado com sucesso!', 'bg-emerald-600');
                fecharModal();
                setTimeout(() => location.reload(), 1800);
            } else {
                erroEl.textContent = data.msg || 'Erro ao processar assinatura.';
                erroEl.classList.remove('hidden');
                // Shake no container de PIN
                const boxes = document.getElementById('pinBoxes');
                boxes.classList.add('animate-[shake_0.35s_ease-in-out]');
                setTimeout(() => boxes.classList.remove('animate-[shake_0.35s_ease-in-out]'), 400);
                // Limpa PIN para nova tentativa
                document.querySelectorAll('.pin-digit').forEach(i => i.value = '');
                document.querySelector('[data-pin-index="0"]').focus();
                atualizarBotaoConfirmar();
            }
        })
        .catch(() => {
            erroEl.textContent = 'Falha de comunicação. Verifique sua conexão.';
            erroEl.classList.remove('hidden');
        })
        .finally(() => {
            btn.disabled = false;
            txt.textContent = '✍ Confirmar Assinatura Digital';
            spinner.classList.add('hidden');
        });
}

// ── Toast helper ──────────────────────────────────────────────────────────
function mostrarToast(msg, cor = 'bg-navy-900') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `fixed bottom-8 right-8 z-[60] flex items-center gap-3 px-6 py-4
                   rounded-2xl shadow-2xl text-white font-black text-sm uppercase tracking-widest
                   transition-all duration-300 ${cor}`;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3500);
}
</script>

<style>
/* Animação de shake para PIN incorreto */
@keyframes shake {
    0%,100% { transform: translateX(0);   }
    20%      { transform: translateX(-6px); }
    40%      { transform: translateX(6px);  }
    60%      { transform: translateX(-4px); }
    80%      { transform: translateX(4px);  }
}
</style>

<?php include 'includes/footer.php'; ?>