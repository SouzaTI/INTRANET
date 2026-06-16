<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = (int) $_SESSION['user_id'];
$erro    = '';

// ── Garante que a coluna existe (executa apenas se ausente) ───────────────
try {
    $pdo_intra->exec("
        ALTER TABLE usuarios_permissoes
        ADD COLUMN IF NOT EXISTS assinatura_pin VARCHAR(255) NULL DEFAULT NULL
    ");
} catch (PDOException) { /* MySQL < 8.0: ignora se já existir */ }

// ── POST: salva o PIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');

    if (!preg_match('/^\d{4}$/', $pin)) {
        $erro = 'O PIN deve conter exatamente 4 dígitos numéricos.';
    } else {
        $hash = password_hash($pin, PASSWORD_BCRYPT);
        $stmt = $pdo_intra->prepare("
            UPDATE usuarios_permissoes
            SET    assinatura_pin = ?
            WHERE  usuario_id     = ?
        ");
        $stmt->execute([$hash, $user_id]);

        registrarLog($pdo_intra, 'PIN ASSINATURA', 'Colaborador cadastrou/atualizou seu PIN de assinatura digital.');
        header('Location: minhas_assinaturas.php?sucesso_pin=1'); exit;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 flex items-center justify-center p-6 md:p-10">
    <div class="w-full max-w-md space-y-5">

        <!-- Cabeçalho -->
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-3xl bg-navy-900 shadow-lg mb-4">
                <span class="text-3xl">🔐</span>
            </div>
            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 mb-1">Segurança</p>
            <h1 class="text-2xl font-black text-navy-900 uppercase tracking-tighter italic leading-tight">
                Cadastre seu PIN<br>de Assinatura Digital
            </h1>
        </div>

        <!-- Aviso informativo -->
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex gap-3">
            <span class="text-amber-500 text-lg shrink-0 mt-0.5">⚠️</span>
            <p class="text-amber-800 text-[11px] font-bold leading-snug">
                Este PIN será exigido sempre que você assinar um documento digitalmente.
                Guarde-o em segurança — ele não pode ser recuperado, apenas redefinido.
            </p>
        </div>

        <!-- Card do formulário -->
        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-8 space-y-6">

            <?php if ($erro): ?>
            <div class="bg-rose-50 border border-rose-200 rounded-2xl px-4 py-3 text-rose-600 text-[11px] font-bold text-center">
                <?= htmlspecialchars($erro) ?>
            </div>
            <?php endif; ?>

            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 text-center mb-4">
                    Digite seu novo PIN de 4 dígitos
                </p>

                <!-- 4 boxes de dígito -->
                <div id="pinBoxes" class="flex gap-3 justify-center">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <input type="password"
                           inputmode="numeric"
                           maxlength="1"
                           pattern="\d"
                           autocomplete="off"
                           data-pin-index="<?= $i ?>"
                           class="pin-digit w-16 h-16 text-center text-2xl font-black text-navy-900
                                  border-2 border-slate-200 rounded-2xl bg-slate-50
                                  focus:border-corporate-blue focus:bg-white focus:outline-none
                                  transition-all caret-transparent" />
                    <?php endfor; ?>
                </div>

                <!-- Força do PIN -->
                <div class="mt-4 space-y-1.5">
                    <div class="flex gap-1.5" id="forcaBars">
                        <div class="h-1.5 flex-1 rounded-full bg-slate-100" data-bar="0"></div>
                        <div class="h-1.5 flex-1 rounded-full bg-slate-100" data-bar="1"></div>
                        <div class="h-1.5 flex-1 rounded-full bg-slate-100" data-bar="2"></div>
                        <div class="h-1.5 flex-1 rounded-full bg-slate-100" data-bar="3"></div>
                    </div>
                    <p id="forcaLabel" class="text-[10px] font-black uppercase tracking-widest text-slate-300 text-center">
                        Digite os 4 dígitos
                    </p>
                </div>
            </div>

            <!-- Input oculto que o form envia -->
            <form method="POST" id="formPin">
                <input type="hidden" name="pin" id="pinHidden" />
                <button type="submit"
                        id="btnSalvar"
                        disabled
                        class="w-full bg-navy-900 hover:bg-corporate-blue text-white font-black py-4
                               rounded-2xl shadow-md hover:shadow-lg transition-all uppercase
                               tracking-widest text-xs disabled:opacity-40 disabled:cursor-not-allowed
                               disabled:hover:bg-navy-900">
                    ✅ Salvar PIN de Assinatura
                </button>
            </form>
        </div>

        <p class="text-center text-[10px] text-slate-400 font-bold uppercase tracking-widest">
            Já tem um PIN?
            <a href="minhas_assinaturas.php" class="text-corporate-blue hover:underline">Voltar</a>
        </p>

    </div>
</main>

<script>
const digits  = document.querySelectorAll('.pin-digit');
const hidden  = document.getElementById('pinHidden');
const btn     = document.getElementById('btnSalvar');
const barras  = document.querySelectorAll('[data-bar]');
const label   = document.getElementById('forcaLabel');

const FORCA = [
    { cor: 'bg-rose-400',   texto: 'PIN fraco — evite sequências óbvias' },
    { cor: 'bg-amber-400',  texto: 'PIN razoável'                         },
    { cor: 'bg-yellow-400', texto: 'PIN bom'                               },
    { cor: 'bg-emerald-500',texto: 'PIN forte ✔'                          },
];

function calcularForca(pin) {
    if (pin.length < 4) return -1;
    const sequencias = ['0123','1234','2345','3456','4567','5678','6789',
                        '9876','8765','7654','6543','5432','4321','3210'];
    const repetido   = /^(\d)\1{3}$/.test(pin); // ex: 1111
    const sequencial = sequencias.includes(pin);
    if (repetido || sequencial) return 0;
    const digitsUnicos = new Set(pin.split('')).size;
    if (digitsUnicos === 2) return 1;
    if (digitsUnicos === 3) return 2;
    return 3;
}

function atualizar() {
    const pin = [...digits].map(d => d.value).join('');
    hidden.value = pin;

    const completo = pin.length === 4 && [...digits].every(d => /^\d$/.test(d.value));
    btn.disabled   = !completo;

    const nivel = calcularForca(pin);
    barras.forEach((b, i) => {
        b.className = 'h-1.5 flex-1 rounded-full transition-all ' +
            (nivel >= 0 && i <= nivel ? FORCA[nivel].cor : 'bg-slate-100');
    });
    label.textContent = nivel >= 0 ? FORCA[nivel].texto : 'Digite os 4 dígitos';
    label.className   = 'text-[10px] font-black uppercase tracking-widest text-center transition-colors ' +
        (nivel >= 0 ? 'text-slate-600' : 'text-slate-300');
}

digits.forEach((input, idx) => {
    input.addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(-1);
        if (e.target.value && idx < digits.length - 1) digits[idx + 1].focus();
        atualizar();
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !e.target.value && idx > 0) {
            digits[idx - 1].value = '';
            digits[idx - 1].focus();
            atualizar();
        }
        if (e.key === 'Enter' && !btn.disabled) document.getElementById('formPin').submit();
    });
    input.addEventListener('paste', e => {
        if (idx !== 0) return;
        e.preventDefault();
        const pasta = (e.clipboardData || window.clipboardData).getData('text')
                        .replace(/\D/g, '').slice(0, 4);
        pasta.split('').forEach((ch, i) => { if (digits[i]) digits[i].value = ch; });
        (digits[Math.min(pasta.length, digits.length - 1)] || digits[digits.length - 1]).focus();
        atualizar();
    });
});

digits[0].focus();
</script>

<?php include 'includes/footer.php'; ?>