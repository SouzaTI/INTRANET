<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);

if ($usuarioId <= 0) {
    http_response_code(401);
    die('Sessão inválida ou expirada.');
}

if (empty($_SESSION['governanca_csrf'])) {
    $_SESSION['governanca_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['governanca_csrf'];
?>

<main class="flex-1 min-w-0 min-h-0 overflow-hidden bg-slate-100 flex flex-col">
    <?php require __DIR__ . '/includes/governanca_nav.php'; ?>

    <section id="gov-my-area" class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-6 py-5">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-blue-600">Governança</p>
                    <h1 class="text-2xl font-black text-slate-900">Minha Área</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Consulte e gerencie funções e pessoas dentro do seu escopo. Cada pessoa possui uma única função/cargo ativo.
                    </p>
                </div>

                <button id="btnRefresh"
                        type="button"
                        class="px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-black text-slate-600 hover:border-blue-300 hover:text-blue-700 transition">
                    ↻ Atualizar
                </button>
            </div>

            <div id="govAlert" class="hidden mb-4 rounded-xl border px-4 py-3 text-sm font-semibold"></div>
            <div id="myAreaStatus" class="mb-4"></div>

            <div id="myAreaGrid" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="col-span-full rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                    Carregando seu escopo...
                </div>
            </div>
        </div>
    </section>

    <div id="govModalBackdrop" class="gov-modal-backdrop"></div>

    <section id="govModal" class="gov-modal" aria-hidden="true">
        <div class="gov-modal-card">
            <div class="gov-modal-head">
                <div>
                    <p id="govModalKicker">Governança</p>
                    <h2 id="govModalTitle">Cadastro</h2>
                </div>
                <button type="button" id="govModalClose" aria-label="Fechar">×</button>
            </div>

            <form id="functionForm" class="gov-modal-body hidden">
                <input id="functionId" type="hidden">

                <div>
                    <label class="gov-label" for="functionStructure">Estrutura *</label>
                    <select id="functionStructure" class="gov-field" required></select>
                    <p id="functionStructureHelp" class="gov-help"></p>
                </div>

                <div>
                    <label class="gov-label" for="functionName">Função / Cargo *</label>
                    <input id="functionName"
                           class="gov-field"
                           type="text"
                           maxlength="180"
                           required>
                </div>

                <div>
                    <label class="gov-label" for="functionDescription">Descrição</label>
                    <textarea id="functionDescription"
                              class="gov-field gov-textarea"
                              maxlength="5000"></textarea>
                </div>

                <div class="gov-modal-actions">
                    <button type="button" class="gov-btn secondary" data-close-modal>Cancelar</button>
                    <button type="submit" id="saveFunction" class="gov-btn primary">Salvar função</button>
                </div>
            </form>

            <form id="personForm" class="gov-modal-body hidden">
                <input id="personId" type="hidden">
                <input id="personLinkId" type="hidden">

                <div>
                    <label class="gov-label" for="personFunction">Função / Cargo *</label>
                    <select id="personFunction" class="gov-field" required></select>
                </div>

                <div>
                    <label class="gov-label" for="personName">Nome da pessoa *</label>
                    <input id="personName"
                           class="gov-field"
                           type="text"
                           maxlength="180"
                           required>
                </div>

                <div>
                    <label class="gov-label" for="personLinkType">Tipo de vínculo</label>
                    <select id="personLinkType" class="gov-field">
                        <option value="Interno">Interno</option>
                        <option value="Terceiro">Terceiro</option>
                        <option value="Prestador">Prestador</option>
                    </select>
                </div>

                <div>
                    <label class="gov-label" for="personNotes">Observações</label>
                    <textarea id="personNotes"
                              class="gov-field gov-textarea"
                              maxlength="5000"></textarea>
                </div>

                <div class="gov-modal-actions">
                    <button type="button" class="gov-btn secondary" data-close-modal>Cancelar</button>
                    <button type="submit" id="savePerson" class="gov-btn primary">Salvar pessoa</button>
                </div>
            </form>

            <form id="movePersonForm" class="gov-modal-body hidden">
                <input id="movePersonLinkId" type="hidden">

                <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-blue-600">
                        Pessoa
                    </span>
                    <strong id="movePersonName"
                            class="block mt-1 text-sm font-black text-blue-950">
                        —
                    </strong>
                    <span id="movePersonCurrent"
                          class="block mt-1 text-[10px] text-blue-700">
                        —
                    </span>
                </div>

                <div>
                    <label class="gov-label" for="movePersonFunction">
                        Nova função / cargo *
                    </label>
                    <select id="movePersonFunction"
                            class="gov-field"
                            required></select>
                    <p class="gov-help">
                        O vínculo atual será encerrado e um novo vínculo será criado.
                        O histórico anterior será preservado.
                    </p>
                </div>

                <div class="gov-modal-actions">
                    <button type="button"
                            class="gov-btn secondary"
                            data-close-modal>
                        Cancelar
                    </button>
                    <button type="submit"
                            id="saveMovePerson"
                            class="gov-btn primary">
                        Movimentar pessoa
                    </button>
                </div>
            </form>

        </div>
    </section>

    <div id="govConfirmBackdrop" class="gov-modal-backdrop"></div>
    <section id="govConfirm" class="gov-modal" aria-hidden="true">
        <div class="gov-confirm-card">
            <div class="gov-confirm-icon">!</div>
            <h2 id="govConfirmTitle">Confirmar</h2>
            <p id="govConfirmText"></p>
            <div class="gov-modal-actions">
                <button type="button" id="govConfirmCancel" class="gov-btn secondary">Cancelar</button>
                <button type="button" id="govConfirmOk" class="gov-btn danger">Confirmar</button>
            </div>
        </div>
    </section>
</main>

<style>
#gov-my-area .gov-area-card{
    border:1px solid #e2e8f0;border-radius:16px;background:#fff;
    padding:18px;box-shadow:0 2px 7px rgba(15,23,42,.035)
}
#gov-my-area .gov-area-code{
    color:#94a3b8;font-size:9px;font-weight:900;
    letter-spacing:.08em;text-transform:uppercase
}
#gov-my-area .gov-area-title{
    margin-top:4px;color:#0f172a;font-size:17px;font-weight:900
}
#gov-my-area .gov-area-badge{
    padding:5px 7px;border-radius:999px;background:#ecfdf5;
    color:#059669;font-size:8px;font-weight:900;text-transform:uppercase
}
#gov-my-area .gov-area-stats{
    margin-top:14px;display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));gap:8px
}
#gov-my-area .gov-area-stat{
    padding:12px 8px;border-radius:12px;background:#f8fafc;text-align:center
}
#gov-my-area .gov-area-stat strong{
    display:block;color:#0f172a;font-size:18px;font-weight:900
}
#gov-my-area .gov-area-stat span{
    display:block;margin-top:3px;color:#94a3b8;font-size:8px;
    font-weight:900;text-transform:uppercase
}
#gov-my-area .gov-area-actions{
    margin-top:14px;display:flex;align-items:center;gap:7px;flex-wrap:wrap
}
#gov-my-area .gov-card-btn{
    padding:8px 10px;border-radius:9px;font-size:10px;font-weight:900;
    transition:.15s ease
}
#gov-my-area .gov-card-btn.primary{
    border:1px solid #2563eb;background:#2563eb;color:#fff
}
#gov-my-area .gov-card-btn.secondary{
    border:1px solid #cbd5e1;background:#fff;color:#475569
}
#gov-my-area .gov-card-btn.primary:hover{background:#1d4ed8}
#gov-my-area .gov-card-btn.secondary:hover{
    border-color:#93c5fd;color:#1d4ed8
}
#gov-my-area .gov-area-note{
    margin-left:auto;color:#94a3b8;font-size:8px
}
#gov-my-area .gov-current-section{
    margin-top:14px;padding:12px;border:1px solid #dbeafe;
    border-radius:12px;background:#f8fbff
}
#gov-my-area .gov-current-title{
    margin-bottom:8px;display:flex;align-items:center;
    justify-content:space-between;gap:10px
}
#gov-my-area .gov-current-title strong{
    color:#1d4ed8;font-size:10px;font-weight:900;
    letter-spacing:.08em;text-transform:uppercase
}
#gov-my-area .gov-current-title span{color:#94a3b8;font-size:8px}
#gov-my-area .gov-current-scroll{
    max-height:290px;overflow:auto;padding-right:3px
}
#gov-my-area .gov-current-scroll::-webkit-scrollbar{width:7px}
#gov-my-area .gov-current-scroll::-webkit-scrollbar-thumb{
    background:#cbd5e1;border:2px solid transparent;
    background-clip:padding-box;border-radius:999px
}
#gov-my-area .gov-existing-list{
    display:flex;flex-direction:column;gap:8px
}
#gov-my-area .gov-existing-function{
    padding:11px 12px;border:1px solid #e2e8f0;
    border-radius:11px;background:#fff
}
#gov-my-area .gov-existing-function-head{
    display:flex;align-items:flex-start;justify-content:space-between;gap:10px
}
#gov-my-area .gov-existing-function-title{min-width:0;margin-right:auto}
#gov-my-area .gov-existing-function-title strong{
    display:block;color:#1e293b;font-size:10px;font-weight:900
}
#gov-my-area .gov-existing-function-title small{
    display:block;margin-top:2px;color:#94a3b8;font-size:8px
}
#gov-my-area .gov-existing-resp{
    flex:0 0 auto;padding:4px 6px;border-radius:999px;
    background:#eff6ff;color:#2563eb;font-size:8px;font-weight:900
}
#gov-my-area .gov-inline-actions{
    display:flex;align-items:center;gap:4px;flex-wrap:wrap
}
#gov-my-area .gov-mini-btn{
    border:1px solid #e2e8f0;border-radius:7px;background:#fff;
    padding:5px 7px;color:#64748b;font-size:8px;font-weight:900;
    cursor:pointer
}
#gov-my-area .gov-mini-btn:hover{
    border-color:#bfdbfe;color:#1d4ed8;background:#eff6ff
}
#gov-my-area .gov-mini-btn.danger:hover{
    border-color:#fecaca;color:#b91c1c;background:#fef2f2
}
#gov-my-area .gov-existing-people{
    margin-top:9px;display:flex;flex-direction:column;gap:5px
}
#gov-my-area .gov-person-row{
    display:flex;align-items:center;gap:7px;padding:6px 7px;
    border:1px solid #f1f5f9;border-radius:9px;background:#f8fafc
}
#gov-my-area .gov-person-chip{
    min-width:0;margin-right:auto;display:flex;align-items:center;gap:6px;
    color:#475569;font-size:8px;font-weight:850
}
#gov-my-area .gov-person-chip .mini-avatar{
    width:20px;height:20px;border-radius:50%;display:grid;place-items:center;
    background:#dbeafe;color:#2563eb;font-size:6px;font-weight:900
}
#gov-my-area .gov-empty-existing{
    padding:13px;border:1px dashed #cbd5e1;border-radius:10px;
    color:#94a3b8;font-size:9px;text-align:center;background:#f8fafc
}
#gov-my-area .gov-inactive-section{
    margin-top:9px;padding:10px;border:1px solid #fed7aa;
    border-radius:11px;background:#fffaf5
}
#gov-my-area .gov-inactive-title{
    display:flex;align-items:center;justify-content:space-between;
    gap:8px;margin-bottom:7px
}
#gov-my-area .gov-inactive-title strong{
    color:#c2410c;font-size:9px;font-weight:900;
    letter-spacing:.06em;text-transform:uppercase
}
#gov-my-area .gov-inactive-title span{
    color:#94a3b8;font-size:8px
}
#gov-my-area .gov-inactive-row{
    display:flex;align-items:center;gap:8px;padding:7px 0;
    border-top:1px solid #ffedd5
}
#gov-my-area .gov-inactive-row:first-of-type{border-top:0}
#gov-my-area .gov-inactive-copy{
    min-width:0;margin-right:auto
}
#gov-my-area .gov-inactive-copy strong{
    display:block;color:#7c2d12;font-size:9px;font-weight:900
}
#gov-my-area .gov-inactive-copy small{
    display:block;margin-top:2px;color:#a16207;font-size:8px
}
#gov-my-area .gov-reactivate-btn{
    flex:0 0 auto;border:1px solid #fdba74;border-radius:8px;
    background:#fff;color:#c2410c;padding:6px 8px;
    font-size:8px;font-weight:900;cursor:pointer
}
#gov-my-area .gov-reactivate-btn:hover{
    background:#fff7ed;border-color:#fb923c
}

/* Modal */
.gov-modal-backdrop{
    position:fixed;inset:0;z-index:89;opacity:0;pointer-events:none;
    background:rgba(15,23,42,.30);transition:.2s ease
}
.gov-modal-backdrop.show{opacity:1;pointer-events:auto}
.gov-modal{
    position:fixed;inset:0;z-index:90;display:grid;place-items:center;
    padding:18px;opacity:0;pointer-events:none;transition:.18s ease
}
.gov-modal.show{opacity:1;pointer-events:auto}
.gov-modal-card{
    width:min(520px,96vw);max-height:min(720px,92vh);overflow:auto;
    border:1px solid #e2e8f0;border-radius:18px;background:#fff;
    box-shadow:0 28px 70px rgba(15,23,42,.24)
}
.gov-modal-head{
    padding:17px 18px 14px;display:flex;align-items:flex-start;
    justify-content:space-between;gap:12px;border-bottom:1px solid #e2e8f0
}
.gov-modal-head p{
    margin:0 0 3px;color:#2563eb;font-size:9px;font-weight:900;
    letter-spacing:.13em;text-transform:uppercase
}
.gov-modal-head h2{
    margin:0;color:#0f172a;font-size:19px;font-weight:900
}
.gov-modal-head button{
    width:32px;height:32px;border:1px solid #e2e8f0;border-radius:9px;
    background:#fff;color:#64748b;font-size:21px;cursor:pointer
}
.gov-modal-body{
    padding:18px;display:flex;flex-direction:column;gap:14px
}
.gov-modal-body.hidden{display:none!important}
.gov-label{
    display:block;margin-bottom:5px;color:#475569;
    font-size:10px;font-weight:900
}
.gov-field{
    width:100%;min-height:41px;padding:9px 11px;border:1px solid #cbd5e1;
    border-radius:10px;outline:0;background:#fff;color:#334155;font-size:12px
}
.gov-field:disabled{background:#f8fafc;color:#94a3b8}
.gov-field:focus{
    border-color:#60a5fa;box-shadow:0 0 0 3px rgba(37,99,235,.08)
}
.gov-textarea{min-height:92px;resize:vertical}
.gov-help{margin:5px 0 0;color:#94a3b8;font-size:9px}
.gov-modal-actions{
    padding-top:4px;display:flex;justify-content:flex-end;gap:8px
}
.gov-btn{
    min-height:38px;padding:0 13px;border-radius:9px;
    font-size:11px;font-weight:900
}
.gov-btn.primary{border:1px solid #2563eb;background:#2563eb;color:#fff}
.gov-btn.secondary{border:1px solid #cbd5e1;background:#fff;color:#475569}
.gov-btn.danger{border:1px solid #dc2626;background:#dc2626;color:#fff}
.gov-btn:disabled{opacity:.55;cursor:not-allowed}
.gov-confirm-card{
    width:min(420px,94vw);padding:20px;border-radius:18px;background:#fff;
    border:1px solid #e2e8f0;box-shadow:0 28px 70px rgba(15,23,42,.24)
}
.gov-confirm-icon{
    width:38px;height:38px;border-radius:50%;display:grid;place-items:center;
    background:#fff1f2;color:#be123c;font-size:16px;font-weight:900
}
.gov-confirm-card h2{
    margin:12px 0 0;color:#0f172a;font-size:18px;font-weight:900
}
.gov-confirm-card p{
    margin:7px 0 14px;color:#64748b;font-size:11px;line-height:1.5
}
@media(max-width:700px){
    #gov-my-area .gov-area-stats{grid-template-columns:repeat(3,1fr)}
    #gov-my-area .gov-area-actions .gov-area-note{
        width:100%;margin-left:0
    }
}
</style>

<script>
(() => {
    const DATA_API = 'api/governanca_dados.php';
    const MANAGE_API = 'api/governanca_gestao.php';
    const CSRF = <?= json_encode(
        $csrf,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    const status = document.getElementById('myAreaStatus');
    const grid = document.getElementById('myAreaGrid');
    const alertBox = document.getElementById('govAlert');
    const refresh = document.getElementById('btnRefresh');

    const modal = document.getElementById('govModal');
    const modalBackdrop = document.getElementById('govModalBackdrop');
    const modalClose = document.getElementById('govModalClose');
    const modalKicker = document.getElementById('govModalKicker');
    const modalTitle = document.getElementById('govModalTitle');

    const functionForm = document.getElementById('functionForm');
    const functionId = document.getElementById('functionId');
    const functionStructure = document.getElementById('functionStructure');
    const functionStructureHelp = document.getElementById('functionStructureHelp');
    const functionName = document.getElementById('functionName');
    const functionDescription = document.getElementById('functionDescription');
    const saveFunction = document.getElementById('saveFunction');

    const personForm = document.getElementById('personForm');
    const personId = document.getElementById('personId');
    const personLinkId = document.getElementById('personLinkId');
    const personFunction = document.getElementById('personFunction');
    const personName = document.getElementById('personName');
    const personLinkType = document.getElementById('personLinkType');
    const personNotes = document.getElementById('personNotes');
    const savePerson = document.getElementById('savePerson');

    const movePersonForm = document.getElementById('movePersonForm');
    const movePersonLinkId = document.getElementById('movePersonLinkId');
    const movePersonName = document.getElementById('movePersonName');
    const movePersonCurrent = document.getElementById('movePersonCurrent');
    const movePersonFunction = document.getElementById('movePersonFunction');
    const saveMovePerson = document.getElementById('saveMovePerson');

    const confirmModal = document.getElementById('govConfirm');
    const confirmBackdrop = document.getElementById('govConfirmBackdrop');
    const confirmTitle = document.getElementById('govConfirmTitle');
    const confirmText = document.getElementById('govConfirmText');
    const confirmCancel = document.getElementById('govConfirmCancel');
    const confirmOk = document.getElementById('govConfirmOk');

    const state = {
        payload:null,
        manage:null,
        modalMode:null,
        confirmAction:null
    };

    const esc = v => String(v ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');

    const initials = name => {
        const p = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!p.length) return '—';
        if (p.length === 1) return p[0].slice(0,2).toUpperCase();
        return (p[0][0] + p[p.length-1][0]).toUpperCase();
    };

    function showAlert(message,type='success'){
        alertBox.className =
            'mb-4 rounded-xl border px-4 py-3 text-sm font-semibold ' +
            (
                type === 'error'
                    ? 'border-red-200 bg-red-50 text-red-700'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'
            );

        alertBox.textContent = message;
        alertBox.classList.remove('hidden');

        window.clearTimeout(showAlert.timer);
        showAlert.timer = window.setTimeout(() => {
            alertBox.classList.add('hidden');
        },4500);
    }

    async function request(url,options={}){
        const response = await fetch(url,{
            cache:'no-store',
            credentials:'same-origin',
            headers:{
                Accept:'application/json',
                ...(options.body ? {
                    'Content-Type':'application/json',
                    'X-CSRF-Token':CSRF
                } : {}),
                ...(options.headers || {})
            },
            ...options
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.ok){
            const error = new Error(
                payload?.error || ('HTTP ' + response.status)
            );
            error.payload = payload;
            error.status = response.status;
            throw error;
        }

        return payload;
    }

    function setSaving(button,saving,label){
        button.disabled = saving;
        button.textContent = saving ? 'Salvando...' : label;
    }

    function closeModal(){
        modal.classList.remove('show');
        modalBackdrop.classList.remove('show');
        modal.setAttribute('aria-hidden','true');
        state.modalMode = null;
    }

    function openMainModal(){
        modal.classList.add('show');
        modalBackdrop.classList.add('show');
        modal.setAttribute('aria-hidden','false');
    }

    function fillStructures(preferred=''){
        const structures = state.manage?.structures || [];

        functionStructure.innerHTML = structures.map(s => `
            <option value="${esc(s.codigo)}"${
                String(s.codigo) === String(preferred)
                    ? ' selected'
                    : ''
            }>
                ${esc(s.nome)} (${esc(s.codigo)})
            </option>
        `).join('');
    }

    function functionsOrdered(preferredCode=''){
        const fns = [...(state.manage?.functions || [])];

        return fns.sort((a,b) => {
            const pa = String(a.estrutura_codigo) === String(preferredCode) ? 0 : 1;
            const pb = String(b.estrutura_codigo) === String(preferredCode) ? 0 : 1;
            if (pa !== pb) return pa - pb;

            const area = String(a.estrutura_nome || '').localeCompare(
                String(b.estrutura_nome || ''),
                'pt-BR'
            );
            if (area !== 0) return area;

            return String(a.nome || '').localeCompare(
                String(b.nome || ''),
                'pt-BR'
            );
        });
    }

    function fillFunctionSelect(select,preferredCode='',selectedId=0){
        const fns = functionsOrdered(preferredCode);

        select.innerHTML = fns.length
            ? fns.map(fn => `
                <option value="${Number(fn.id)}"${
                    Number(fn.id) === Number(selectedId)
                        ? ' selected'
                        : ''
                }>
                    ${esc(fn.estrutura_nome)} → ${esc(fn.nome)}
                </option>
            `).join('')
            : '<option value="">Nenhuma função disponível</option>';
    }

    function openCreateFunction(code=''){
        state.modalMode = 'create_function';
        modalKicker.textContent = 'Cadastro';
        modalTitle.textContent = 'Nova função / cargo';

        functionForm.classList.remove('hidden');
        personForm.classList.add('hidden');
        movePersonForm.classList.add('hidden');

        functionId.value = '';
        fillStructures(code);
        functionStructure.disabled = false;
        functionStructureHelp.textContent = '';
        functionName.value = '';
        functionDescription.value = '';
        saveFunction.textContent = 'Salvar função';

        openMainModal();
        setTimeout(() => functionName.focus(),80);
    }

    function openEditFunction(id){
        const fn = (state.payload?.data?.funcoes || [])
            .find(item => Number(item.id) === Number(id));

        if (!fn) return;

        state.modalMode = 'edit_function';
        modalKicker.textContent = 'Edição';
        modalTitle.textContent = 'Editar função / cargo';

        functionForm.classList.remove('hidden');
        personForm.classList.add('hidden');
        movePersonForm.classList.add('hidden');

        functionId.value = String(fn.id);
        fillStructures(fn.estrutura_codigo);
        functionStructure.value = fn.estrutura_codigo;
        functionStructure.disabled = true;
        functionStructureHelp.textContent =
            'Para preservar responsabilidades e histórico, a área da função não é alterada nesta tela.';
        functionName.value = fn.nome || '';
        functionDescription.value = fn.descricao || '';
        saveFunction.textContent = 'Salvar alterações';

        openMainModal();
        setTimeout(() => functionName.focus(),80);
    }

    function openCreatePerson(code=''){
        state.modalMode = 'create_person';
        modalKicker.textContent = 'Cadastro';
        modalTitle.textContent = 'Nova pessoa';

        functionForm.classList.add('hidden');
        personForm.classList.remove('hidden');
        movePersonForm.classList.add('hidden');

        personId.value = '';
        personLinkId.value = '';
        fillFunctionSelect(personFunction,code,0);
        personFunction.disabled = false;
        personName.value = '';
        personLinkType.value = 'Interno';
        personNotes.value = '';
        savePerson.textContent = 'Salvar pessoa';

        openMainModal();
        setTimeout(() => personName.focus(),80);
    }

    function openEditPerson(functionIdValue,linkIdValue,personIdValue){
        const fn = (state.payload?.data?.funcoes || [])
            .find(item => Number(item.id) === Number(functionIdValue));

        const person = fn?.ocupantes?.find(
            p =>
                Number(p.id) === Number(personIdValue)
                && Number(p.vinculo_id) === Number(linkIdValue)
        );

        if (!fn || !person) {
            showAlert(
                'Não consegui localizar os dados atuais deste vínculo.',
                'error'
            );
            return;
        }

        state.modalMode = 'edit_person_link';
        modalKicker.textContent = 'Edição';
        modalTitle.textContent = 'Editar pessoa / vínculo';

        functionForm.classList.add('hidden');
        personForm.classList.remove('hidden');
        movePersonForm.classList.add('hidden');

        personId.value = String(person.id);
        personLinkId.value = String(person.vinculo_id);
        fillFunctionSelect(
            personFunction,
            fn.estrutura_codigo,
            fn.id
        );
        personFunction.disabled = true;
        personName.value = person.nome || '';
        personLinkType.value = person.tipo_vinculo || 'Interno';
        personNotes.value = person.observacoes || '';
        savePerson.textContent = 'Salvar alterações';

        openMainModal();
        setTimeout(() => personName.focus(),80);
    }

    function openMovePerson(functionIdValue,linkIdValue,personIdValue){
        const fn = (state.payload?.data?.funcoes || [])
            .find(item => Number(item.id) === Number(functionIdValue));

        const person = fn?.ocupantes?.find(
            p =>
                Number(p.id) === Number(personIdValue)
                && Number(p.vinculo_id) === Number(linkIdValue)
        );

        if (!fn || !person) {
            showAlert(
                'Não consegui localizar o vínculo atual desta pessoa.',
                'error'
            );
            return;
        }

        state.modalMode = 'move_person_link';
        modalKicker.textContent = 'Movimentação';
        modalTitle.textContent = 'Movimentar pessoa';

        functionForm.classList.add('hidden');
        personForm.classList.add('hidden');
        movePersonForm.classList.remove('hidden');

        movePersonLinkId.value = String(person.vinculo_id);
        movePersonName.textContent = person.nome || 'Pessoa';
        movePersonCurrent.textContent =
            `${fn.estrutura_nome || fn.estrutura_codigo} → ${fn.nome}`;

        const available = functionsOrdered('')
            .filter(item => Number(item.id) !== Number(fn.id));

        movePersonFunction.innerHTML = available.length
            ? available.map(item => `
                <option value="${Number(item.id)}">
                    ${esc(item.estrutura_nome)} → ${esc(item.nome)}
                </option>
            `).join('')
            : '<option value="">Nenhuma outra função disponível</option>';

        openMainModal();
    }

    function openConfirm(title,text,action){
        state.confirmAction = action;
        confirmTitle.textContent = title;
        confirmText.textContent = text;
        confirmModal.classList.add('show');
        confirmBackdrop.classList.add('show');
        confirmModal.setAttribute('aria-hidden','false');
    }

    function closeConfirm(){
        confirmModal.classList.remove('show');
        confirmBackdrop.classList.remove('show');
        confirmModal.setAttribute('aria-hidden','true');
        state.confirmAction = null;
    }

    function statsForStructure(code){
        const data = state.payload?.data || {};
        const fns = (data.funcoes || []).filter(
            fn => String(fn.estrutura_codigo || '') === String(code)
        );

        const people = new Set();
        fns.forEach(fn => {
            (fn.ocupantes || []).forEach(p => people.add(String(p.id)));
        });

        const resp = (data.responsabilidades || []).filter(
            r => String(r.ID_ESTRUTURA || '') === String(code)
        ).length;

        return {
            functions:fns.length,
            people:people.size,
            responsibilities:resp
        };
    }

    function renderCurrentRegistrations(code,data,canManageThis){
        const functions = (data.funcoes || []).filter(
            fn => String(fn.estrutura_codigo || '') === String(code)
        );

        const body = functions.length
            ? functions.map(fn => {
                const occupants = fn.ocupantes || [];

                const peopleHtml = occupants.length
                    ? occupants.map(person => `
                        <div class="gov-person-row">
                            <div class="gov-person-chip">
                                <span class="mini-avatar">${esc(initials(person.nome))}</span>
                                <span>${esc(person.nome)}</span>
                            </div>

                            ${
                                canManageThis
                                    ? `
                                    <div class="gov-inline-actions">
                                        <button type="button"
                                                class="gov-mini-btn"
                                                data-edit-person="${Number(person.id)}"
                                                data-edit-link="${Number(person.vinculo_id || 0)}"
                                                data-edit-person-function="${Number(fn.id)}">
                                            Editar
                                        </button>
                                        <button type="button"
                                                class="gov-mini-btn"
                                                data-move-person="${Number(person.id)}"
                                                data-move-link="${Number(person.vinculo_id || 0)}"
                                                data-move-person-function="${Number(fn.id)}">
                                            Movimentar
                                        </button>

                                        <button type="button"
                                                class="gov-mini-btn danger"
                                                data-deactivate-link="${Number(person.vinculo_id || 0)}"
                                                data-person-name="${esc(person.nome)}">
                                            Inativar
                                        </button>
                                    </div>
                                    `
                                    : ''
                            }
                        </div>
                    `).join('')
                    : `
                        <div class="gov-person-row">
                            <div class="gov-person-chip" style="color:#d97706">
                                Sem ocupante
                            </div>
                        </div>
                    `;

                return `
                    <div class="gov-existing-function">
                        <div class="gov-existing-function-head">
                            <div class="gov-existing-function-title">
                                <strong>${esc(fn.nome || 'Função')}</strong>
                                <small>${esc(fn.descricao || 'Sem descrição cadastrada')}</small>
                            </div>

                            <span class="gov-existing-resp">
                                ${Number(fn.responsabilidades || 0)} resp.
                            </span>
                        </div>

                        ${
                            canManageThis
                                ? `
                                <div class="gov-inline-actions" style="margin-top:8px;">
                                    <button type="button"
                                            class="gov-mini-btn"
                                            data-edit-function="${Number(fn.id)}">
                                        Editar função
                                    </button>

                                    <button type="button"
                                            class="gov-mini-btn danger"
                                            data-deactivate-function="${Number(fn.id)}"
                                            data-function-name="${esc(fn.nome)}">
                                        Inativar função
                                    </button>
                                </div>
                                `
                                : ''
                        }

                        <div class="gov-existing-people">
                            ${peopleHtml}
                        </div>
                    </div>
                `;
            }).join('')
            : `
                <div class="gov-empty-existing">
                    Nenhuma função/cargo cadastrada nesta estrutura.
                </div>
            `;

        return `
            <div class="gov-current-section">
                <div class="gov-current-title">
                    <strong>Cadastros atuais</strong>
                    <span>Funções e ocupantes desta estrutura</span>
                </div>

                <div class="gov-current-scroll">
                    <div class="gov-existing-list">
                        ${body}
                    </div>
                </div>
            </div>
        `;
    }

    function renderStatus(){
        const manage = state.manage || {};
        const canManage = Boolean(manage.can_manage);

        status.innerHTML = `
            <div class="rounded-2xl border ${
                canManage
                    ? 'border-emerald-200 bg-emerald-50'
                    : 'border-blue-200 bg-blue-50'
            } p-4 flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <strong class="text-sm ${
                        canManage
                            ? 'text-emerald-900'
                            : 'text-blue-900'
                    }">
                        ${
                            canManage
                                ? 'Você possui acesso de gerenciamento.'
                                : 'Seu acesso nesta tela é somente consulta.'
                        }
                    </strong>
                    <p class="mt-1 text-xs ${
                        canManage
                            ? 'text-emerald-700'
                            : 'text-blue-700'
                    }">
                        ${
                            manage.is_admin
                                ? 'Administrador — todas as estruturas podem ser gerenciadas.'
                                : canManage
                                    ? `${(manage.structures || []).length} estrutura(s) dentro do seu escopo GERENCIAR.`
                                    : 'Nenhuma estrutura possui nível GERENCIAR para este usuário.'
                        }
                    </p>
                </div>

                <span class="px-3 py-1.5 rounded-full bg-white/80 text-[10px] font-black uppercase tracking-wider">
                    ${canManage ? 'GERENCIAR' : 'VISUALIZAR'}
                </span>
            </div>
        `;
    }

    function renderInactiveItems(code,canManageThis){
        if (!canManageThis) return '';

        const inactiveFunctions = (state.manage?.inactive_functions || [])
            .filter(item =>
                String(item.estrutura_codigo || '') === String(code)
            );

        const inactiveLinks = (state.manage?.inactive_links || [])
            .filter(item =>
                String(item.estrutura_codigo || '') === String(code)
            );

        if (!inactiveFunctions.length && !inactiveLinks.length) {
            return '';
        }

        const functionHtml = inactiveFunctions.map(item => `
            <div class="gov-inactive-row">
                <div class="gov-inactive-copy">
                    <strong>${esc(item.nome)}</strong>
                    <small>Função/cargo inativa</small>
                </div>
                <button type="button"
                        class="gov-reactivate-btn"
                        data-reactivate-function="${Number(item.id)}"
                        data-reactivate-function-name="${esc(item.nome)}">
                    Reativar função
                </button>
            </div>
        `).join('');

        const linkHtml = inactiveLinks.map(item => `
            <div class="gov-inactive-row">
                <div class="gov-inactive-copy">
                    <strong>${esc(item.pessoa_nome)}</strong>
                    <small>${esc(item.funcao_nome)} · vínculo inativo</small>
                </div>
                <button type="button"
                        class="gov-reactivate-btn"
                        data-reactivate-link="${Number(item.vinculo_id)}"
                        data-reactivate-person-name="${esc(item.pessoa_nome)}">
                    Reativar
                </button>
            </div>
        `).join('');

        return `
            <div class="gov-inactive-section">
                <div class="gov-inactive-title">
                    <strong>Itens inativos</strong>
                    <span>Podem ser reativados sem novo cadastro</span>
                </div>
                ${functionHtml}
                ${linkHtml}
            </div>
        `;
    }

    function renderCards(){
        const data = state.payload?.data || {};
        const manageable = new Set(
            (state.manage?.structures || [])
                .map(s => String(s.codigo))
        );

        let structures = data.estrutura || [];

        if (state.manage?.can_manage){
            structures = structures.filter(
                s => manageable.has(String(s.ID || ''))
            );
        }else{
            const roots = new Set(
                state.payload?.access?.roots || []
            );

            if (roots.size){
                structures = structures.filter(
                    s => roots.has(String(s.ID || ''))
                );
            }else{
                structures = structures.filter(
                    s => String(s.ID_PAI || '').trim() !== ''
                );
            }
        }

        if (!structures.length){
            grid.innerHTML = `
                <div class="col-span-full rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                    Nenhuma estrutura disponível neste escopo.
                </div>
            `;
            return;
        }

        grid.innerHTML = structures.map(s => {
            const code = String(s.ID || '');
            const st = statsForStructure(code);
            const canManageThis = manageable.has(code);

            return `
                <article class="gov-area-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="gov-area-code">${esc(code)}</p>
                            <h2 class="gov-area-title">${esc(s.NOME || code)}</h2>
                        </div>

                        <span class="gov-area-badge">
                            ${canManageThis ? 'Gerenciar' : 'Consulta'}
                        </span>
                    </div>

                    <div class="gov-area-stats">
                        <div class="gov-area-stat">
                            <strong>${st.functions}</strong>
                            <span>Funções</span>
                        </div>
                        <div class="gov-area-stat">
                            <strong>${st.people}</strong>
                            <span>Pessoas</span>
                        </div>
                        <div class="gov-area-stat">
                            <strong>${st.responsibilities}</strong>
                            <span>Resp.</span>
                        </div>
                    </div>

                    ${
                        canManageThis
                            ? `
                            <div class="gov-area-actions">
                                <button type="button"
                                        class="gov-card-btn primary"
                                        data-add-function="${esc(code)}">
                                    + Função
                                </button>

                                <button type="button"
                                        class="gov-card-btn secondary"
                                        data-add-person="${esc(code)}">
                                    + Pessoa
                                </button>

                                <span class="gov-area-note">
                                    Cadastro protegido por permissão
                                </span>
                            </div>
                            `
                            : ''
                    }

                    ${renderCurrentRegistrations(
                        code,
                        data,
                        canManageThis
                    )}

                    ${renderInactiveItems(
                        code,
                        canManageThis
                    )}
                </article>
            `;
        }).join('');

        grid.querySelectorAll('[data-add-function]').forEach(btn => {
            btn.addEventListener('click',() => {
                openCreateFunction(btn.dataset.addFunction || '');
            });
        });

        grid.querySelectorAll('[data-add-person]').forEach(btn => {
            btn.addEventListener('click',() => {
                openCreatePerson(btn.dataset.addPerson || '');
            });
        });

        grid.querySelectorAll('[data-edit-function]').forEach(btn => {
            btn.addEventListener('click',() => {
                openEditFunction(Number(btn.dataset.editFunction));
            });
        });


        grid.querySelectorAll('[data-edit-person]').forEach(btn => {
            btn.addEventListener('click',() => {
                openEditPerson(
                    Number(btn.dataset.editPersonFunction),
                    Number(btn.dataset.editLink),
                    Number(btn.dataset.editPerson)
                );
            });
        });

        grid.querySelectorAll('[data-move-person]').forEach(btn => {
            btn.addEventListener('click',() => {
                openMovePerson(
                    Number(btn.dataset.movePersonFunction),
                    Number(btn.dataset.moveLink),
                    Number(btn.dataset.movePerson)
                );
            });
        });

        grid.querySelectorAll('[data-deactivate-function]').forEach(btn => {
            btn.addEventListener('click',() => {
                const id = Number(btn.dataset.deactivateFunction);
                const name = btn.dataset.functionName || 'esta função';

                openConfirm(
                    'Inativar função',
                    `Deseja inativar "${name}"? A ação será bloqueada se ainda existirem ocupantes ou responsabilidades ativas.`,
                    async () => {
                        const result = await request(
                            MANAGE_API + '?action=deactivate_function',
                            {
                                method:'POST',
                                body:JSON.stringify({funcao_id:id})
                            }
                        );

                        showAlert(result.message);
                        await load();
                    }
                );
            });
        });

        grid.querySelectorAll('[data-deactivate-link]').forEach(btn => {
            btn.addEventListener('click',() => {
                const id = Number(btn.dataset.deactivateLink);
                const name = btn.dataset.personName || 'esta pessoa';

                openConfirm(
                    'Encerrar vínculo',
                    `Deseja encerrar o vínculo de ${name} com esta função? Se for o último vínculo ativo, a pessoa também será inativada.`,
                    async () => {
                        const result = await request(
                            MANAGE_API + '?action=deactivate_person_link',
                            {
                                method:'POST',
                                body:JSON.stringify({vinculo_id:id})
                            }
                        );

                        showAlert(result.message);
                        await load();
                    }
                );
            });
        });

        grid.querySelectorAll('[data-reactivate-function]').forEach(btn => {
            btn.addEventListener('click',() => {
                const id = Number(btn.dataset.reactivateFunction);
                const name = btn.dataset.reactivateFunctionName || 'esta função';

                openConfirm(
                    'Reativar função',
                    `Deseja reativar "${name}"?`,
                    async () => {
                        const result = await request(
                            MANAGE_API + '?action=reactivate_function',
                            {
                                method:'POST',
                                body:JSON.stringify({funcao_id:id})
                            }
                        );

                        showAlert(result.message);
                        await load();
                    }
                );
            });
        });

        grid.querySelectorAll('[data-reactivate-link]').forEach(btn => {
            btn.addEventListener('click',() => {
                const id = Number(btn.dataset.reactivateLink);
                const name = btn.dataset.reactivatePersonName || 'esta pessoa';

                openConfirm(
                    'Reativar pessoa',
                    `Deseja reativar ${name} neste vínculo?`,
                    async () => {
                        const result = await request(
                            MANAGE_API + '?action=reactivate_person_link',
                            {
                                method:'POST',
                                body:JSON.stringify({vinculo_id:id})
                            }
                        );

                        showAlert(result.message);
                        await load();
                    }
                );
            });
        });
    }

    async function load(){
        refresh.disabled = true;

        try{
            const [dataPayload,managePayload] =
                await Promise.all([
                    request(DATA_API + '?t=' + Date.now()),
                    request(
                        MANAGE_API
                        + '?action=scope&t='
                        + Date.now()
                    )
                ]);

            state.payload = dataPayload;
            state.manage = managePayload;

            renderStatus();
            renderCards();

        }catch(error){
            console.error(error);

            grid.innerHTML = `
                <div class="col-span-full rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    ${esc(error.message || 'Falha ao carregar sua área.')}
                </div>
            `;
        }finally{
            refresh.disabled = false;
        }
    }

    functionForm.addEventListener('submit',async event => {
        event.preventDefault();

        const editing =
            state.modalMode === 'edit_function';

        setSaving(
            saveFunction,
            true,
            editing ? 'Salvar alterações' : 'Salvar função'
        );

        try{
            const action = editing
                ? 'update_function'
                : 'create_function';

            const body = editing
                ? {
                    funcao_id:Number(functionId.value),
                    nome:functionName.value.trim(),
                    descricao:functionDescription.value.trim()
                }
                : {
                    estrutura_codigo:functionStructure.value,
                    nome:functionName.value.trim(),
                    descricao:functionDescription.value.trim()
                };

            const result = await request(
                MANAGE_API + '?action=' + action,
                {
                    method:'POST',
                    body:JSON.stringify(body)
                }
            );

            closeModal();
            showAlert(result.message);
            await load();

        }catch(error){
            showAlert(error.message,'error');
        }finally{
            setSaving(
                saveFunction,
                false,
                editing ? 'Salvar alterações' : 'Salvar função'
            );
        }
    });

    personForm.addEventListener('submit',async event => {
        event.preventDefault();

        const editing =
            state.modalMode === 'edit_person_link';

        setSaving(
            savePerson,
            true,
            editing ? 'Salvar alterações' : 'Salvar pessoa'
        );

        try{
            const action = editing
                ? 'update_person_link'
                : 'create_person';

            const body = editing
                ? {
                    vinculo_id:Number(personLinkId.value),
                    nome:personName.value.trim(),
                    tipo_vinculo:personLinkType.value,
                    observacoes:personNotes.value.trim()
                }
                : {
                    funcao_id:Number(personFunction.value),
                    nome:personName.value.trim(),
                    tipo_vinculo:personLinkType.value,
                    observacoes:personNotes.value.trim()
                };

            const result = await request(
                MANAGE_API + '?action=' + action,
                {
                    method:'POST',
                    body:JSON.stringify(body)
                }
            );

            closeModal();
            showAlert(result.message);
            await load();

        }catch(error){
            showAlert(error.message,'error');
        }finally{
            setSaving(
                savePerson,
                false,
                editing ? 'Salvar alterações' : 'Salvar pessoa'
            );
        }
    });


    movePersonForm.addEventListener('submit',async event => {
        event.preventDefault();

        const linkId = Number(movePersonLinkId.value || 0);
        const newFunctionId = Number(movePersonFunction.value || 0);

        if (!linkId || !newFunctionId) {
            showAlert('Selecione a nova função/cargo.','error');
            return;
        }

        setSaving(saveMovePerson,true,'Movimentar pessoa');

        try{
            const result = await request(
                MANAGE_API + '?action=move_person_link',
                {
                    method:'POST',
                    body:JSON.stringify({
                        vinculo_id:linkId,
                        nova_funcao_id:newFunctionId
                    })
                }
            );

            closeModal();
            showAlert(result.message);
            await load();

        }catch(error){
            showAlert(error.message,'error');
        }finally{
            setSaving(saveMovePerson,false,'Movimentar pessoa');
        }
    });

    confirmOk.addEventListener('click',async () => {
        if (typeof state.confirmAction !== 'function') {
            return;
        }

        const action = state.confirmAction;
        confirmOk.disabled = true;
        confirmOk.textContent = 'Processando...';

        try{
            closeConfirm();
            await action();
        }catch(error){
            showAlert(error.message,'error');
        }finally{
            confirmOk.disabled = false;
            confirmOk.textContent = 'Confirmar';
        }
    });

    refresh.addEventListener('click',load);
    modalClose.addEventListener('click',closeModal);
    modalBackdrop.addEventListener('click',closeModal);
    confirmCancel.addEventListener('click',closeConfirm);
    confirmBackdrop.addEventListener('click',closeConfirm);

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click',closeModal);
    });

    window.addEventListener('keydown',event => {
        if (event.key === 'Escape'){
            closeModal();
            closeConfirm();
        }
    });

    load();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
