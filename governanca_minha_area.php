<?php
// GOVERNANCA MINHA AREA FINAL - CADASTROS ATUAIS VISIVEIS
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

<!-- GOVERNANCA_MINHA_AREA_V4 -->
<main class="flex-1 min-w-0 min-h-0 overflow-hidden bg-slate-100 flex flex-col">
    <?php require __DIR__ . '/includes/governanca_nav.php'; ?>

    <section id="gov-my-area" class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-6 py-5">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-blue-600">Governança</p>
                    <div class="flex items-center gap-2"><h1 class="text-2xl font-black text-slate-900">Minha Área</h1><span style="font-size:8px;font-weight:900;color:#2563eb;background:#eff6ff;border:1px solid #bfdbfe;border-radius:999px;padding:3px 6px;">FINAL</span></div>
                    <p class="mt-1 text-sm text-slate-500">
                        Gerencie funções e ocupantes dentro das estruturas em que você possui permissão.
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

    <!-- Modal -->
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
                <div>
                    <label class="gov-label" for="functionStructure">Estrutura *</label>
                    <select id="functionStructure" class="gov-field" required></select>
                </div>

                <div>
                    <label class="gov-label" for="functionName">Função / Cargo *</label>
                    <input id="functionName"
                           class="gov-field"
                           type="text"
                           maxlength="180"
                           placeholder="Ex.: Analista Fiscal"
                           required>
                </div>

                <div>
                    <label class="gov-label" for="functionDescription">Descrição</label>
                    <textarea id="functionDescription"
                              class="gov-field gov-textarea"
                              maxlength="5000"
                              placeholder="Descreva brevemente o objetivo desta função."></textarea>
                </div>

                <div class="gov-modal-actions">
                    <button type="button" class="gov-btn secondary" data-close-modal>Cancelar</button>
                    <button type="submit" id="saveFunction" class="gov-btn primary">Salvar função</button>
                </div>
            </form>

            <form id="personForm" class="gov-modal-body hidden">
                <div>
                    <label class="gov-label" for="personFunction">Função / Cargo *</label>
                    <select id="personFunction" class="gov-field" required></select>
                    <p class="gov-help">A pessoa será cadastrada e vinculada a esta função.</p>
                </div>

                <div>
                    <label class="gov-label" for="personName">Nome da pessoa *</label>
                    <input id="personName"
                           class="gov-field"
                           type="text"
                           maxlength="180"
                           placeholder="Nome completo"
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
                              maxlength="5000"
                              placeholder="Informação adicional, se necessário."></textarea>
                </div>

                <div class="gov-modal-actions">
                    <button type="button" class="gov-btn secondary" data-close-modal>Cancelar</button>
                    <button type="submit" id="savePerson" class="gov-btn primary">Salvar pessoa</button>
                </div>
            </form>
        </div>
    </section>
</main>

<style>
#gov-my-area .gov-area-card{
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    padding:18px;
    box-shadow:0 2px 7px rgba(15,23,42,.035);
}
#gov-my-area .gov-area-code{
    color:#94a3b8;
    font-size:9px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
}
#gov-my-area .gov-area-title{
    margin-top:4px;
    color:#0f172a;
    font-size:17px;
    font-weight:900;
}
#gov-my-area .gov-area-badge{
    padding:5px 7px;
    border-radius:999px;
    background:#ecfdf5;
    color:#059669;
    font-size:8px;
    font-weight:900;
    text-transform:uppercase;
}
#gov-my-area .gov-area-stats{
    margin-top:14px;
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:8px;
}
#gov-my-area .gov-area-stat{
    padding:12px 8px;
    border-radius:12px;
    background:#f8fafc;
    text-align:center;
}
#gov-my-area .gov-area-stat strong{
    display:block;
    color:#0f172a;
    font-size:18px;
    font-weight:900;
}
#gov-my-area .gov-area-stat span{
    display:block;
    margin-top:3px;
    color:#94a3b8;
    font-size:8px;
    font-weight:900;
    text-transform:uppercase;
}
#gov-my-area .gov-area-actions{
    margin-top:14px;
    display:flex;
    align-items:center;
    gap:7px;
    flex-wrap:wrap;
}
#gov-my-area .gov-card-btn{
    padding:8px 10px;
    border-radius:9px;
    font-size:10px;
    font-weight:900;
    transition:.15s ease;
}
#gov-my-area .gov-card-btn.primary{
    border:1px solid #2563eb;
    background:#2563eb;
    color:#fff;
}
#gov-my-area .gov-card-btn.primary:hover{background:#1d4ed8}
#gov-my-area .gov-card-btn.secondary{
    border:1px solid #cbd5e1;
    background:#fff;
    color:#475569;
}
#gov-my-area .gov-card-btn.secondary:hover{
    border-color:#93c5fd;
    color:#1d4ed8;
}
#gov-my-area .gov-area-note{
    margin-left:auto;
    color:#94a3b8;
    font-size:8px;
}
#gov-my-area .gov-current-section{
    margin-top:14px;
    padding:12px;
    border:1px solid #dbeafe;
    border-radius:12px;
    background:#f8fbff;
}
#gov-my-area .gov-current-title{
    margin-bottom:8px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
#gov-my-area .gov-current-title strong{
    color:#334155;
    font-size:9px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
}
#gov-my-area .gov-current-title span{
    color:#94a3b8;
    font-size:8px;
}
#gov-my-area .gov-current-scroll{
    max-height:235px;
    overflow:auto;
    padding-right:3px;
}
#gov-my-area .gov-current-scroll::-webkit-scrollbar{width:7px}
#gov-my-area .gov-current-scroll::-webkit-scrollbar-thumb{
    background:#cbd5e1;
    border:2px solid transparent;
    background-clip:padding-box;
    border-radius:999px;
}
#gov-my-area .gov-existing-list{
    display:flex;
    flex-direction:column;
    gap:7px;
}
#gov-my-area .gov-existing-function{
    padding:11px 12px;
    border:1px solid #e2e8f0;
    border-radius:11px;
    background:#fff;
}
#gov-my-area .gov-existing-function-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
}
#gov-my-area .gov-existing-function-title{min-width:0}
#gov-my-area .gov-existing-function-title strong{
    display:block;
    color:#1e293b;
    font-size:10px;
    font-weight:900;
}
#gov-my-area .gov-existing-function-title small{
    display:block;
    margin-top:2px;
    color:#94a3b8;
    font-size:8px;
}
#gov-my-area .gov-existing-resp{
    flex:0 0 auto;
    padding:4px 6px;
    border-radius:999px;
    background:#eff6ff;
    color:#2563eb;
    font-size:8px;
    font-weight:900;
}
#gov-my-area .gov-existing-people{
    margin-top:8px;
    display:flex;
    flex-wrap:wrap;
    gap:5px;
}
#gov-my-area .gov-person-chip{
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:5px 7px;
    border:1px solid #e2e8f0;
    border-radius:999px;
    background:#f8fafc;
    color:#475569;
    font-size:8px;
    font-weight:800;
}
#gov-my-area .gov-person-chip .mini-avatar{
    width:18px;
    height:18px;
    border-radius:50%;
    display:grid;
    place-items:center;
    background:#dbeafe;
    color:#2563eb;
    font-size:6px;
    font-weight:900;
}
#gov-my-area .gov-empty-existing{
    padding:13px;
    border:1px dashed #cbd5e1;
    border-radius:10px;
    color:#94a3b8;
    font-size:9px;
    text-align:center;
    background:#f8fafc;
}

/* modal */
.gov-modal-backdrop{
    position:fixed;
    inset:0;
    z-index:89;
    opacity:0;
    pointer-events:none;
    background:rgba(15,23,42,.30);
    transition:.2s ease;
}
.gov-modal-backdrop.show{
    opacity:1;
    pointer-events:auto;
}
.gov-modal{
    position:fixed;
    inset:0;
    z-index:90;
    display:grid;
    place-items:center;
    padding:18px;
    opacity:0;
    pointer-events:none;
    transition:.18s ease;
}
.gov-modal.show{
    opacity:1;
    pointer-events:auto;
}
.gov-modal-card{
    width:min(520px,96vw);
    max-height:min(720px,92vh);
    overflow:auto;
    border:1px solid #e2e8f0;
    border-radius:18px;
    background:#fff;
    box-shadow:0 28px 70px rgba(15,23,42,.24);
}
.gov-modal-head{
    padding:17px 18px 14px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px solid #e2e8f0;
}
.gov-modal-head p{
    margin:0 0 3px;
    color:#2563eb;
    font-size:9px;
    font-weight:900;
    letter-spacing:.13em;
    text-transform:uppercase;
}
.gov-modal-head h2{
    margin:0;
    color:#0f172a;
    font-size:19px;
    font-weight:900;
}
.gov-modal-head button{
    width:32px;
    height:32px;
    border:1px solid #e2e8f0;
    border-radius:9px;
    background:#fff;
    color:#64748b;
    font-size:21px;
    cursor:pointer;
}
.gov-modal-body{
    padding:18px;
    display:flex;
    flex-direction:column;
    gap:14px;
}
.gov-modal-body.hidden{display:none!important}
.gov-label{
    display:block;
    margin-bottom:5px;
    color:#475569;
    font-size:10px;
    font-weight:900;
}
.gov-field{
    width:100%;
    min-height:41px;
    padding:9px 11px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    outline:0;
    background:#fff;
    color:#334155;
    font-size:12px;
}
.gov-field:focus{
    border-color:#60a5fa;
    box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
.gov-textarea{
    min-height:92px;
    resize:vertical;
}
.gov-help{
    margin:5px 0 0;
    color:#94a3b8;
    font-size:9px;
}
.gov-modal-actions{
    padding-top:4px;
    display:flex;
    justify-content:flex-end;
    gap:8px;
}
.gov-btn{
    min-height:38px;
    padding:0 13px;
    border-radius:9px;
    font-size:11px;
    font-weight:900;
}
.gov-btn.primary{
    border:1px solid #2563eb;
    background:#2563eb;
    color:#fff;
}
.gov-btn.secondary{
    border:1px solid #cbd5e1;
    background:#fff;
    color:#475569;
}
.gov-btn:disabled{
    opacity:.55;
    cursor:not-allowed;
}
</style>

<script>
(() => {
    const DATA_API = 'api/governanca_dados.php';
    const MANAGE_API = 'api/governanca_gestao.php';
    const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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
    const functionStructure = document.getElementById('functionStructure');
    const functionName = document.getElementById('functionName');
    const functionDescription = document.getElementById('functionDescription');
    const saveFunction = document.getElementById('saveFunction');

    const personForm = document.getElementById('personForm');
    const personFunction = document.getElementById('personFunction');
    const personName = document.getElementById('personName');
    const personLinkType = document.getElementById('personLinkType');
    const personNotes = document.getElementById('personNotes');
    const savePerson = document.getElementById('savePerson');

    const state = {
        payload:null,
        manage:null
    };

    const esc = v => String(v ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');

    const initials = name => {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '—';
        if (parts.length === 1) return parts[0].slice(0,2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
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

    function openModal(type,preferredCode=''){
        if (!state.manage?.can_manage){
            showAlert('Seu usuário não possui permissão para gerenciar estruturas.','error');
            return;
        }

        functionForm.classList.toggle('hidden',type !== 'function');
        personForm.classList.toggle('hidden',type !== 'person');

        if (type === 'function'){
            modalKicker.textContent = 'Cadastro';
            modalTitle.textContent = 'Nova função / cargo';
            fillStructureOptions(preferredCode);
            functionName.value = '';
            functionDescription.value = '';
            setTimeout(() => functionName.focus(),80);
        }else{
            modalKicker.textContent = 'Cadastro';
            modalTitle.textContent = 'Nova pessoa';
            fillFunctionOptions(preferredCode);
            personName.value = '';
            personLinkType.value = 'Interno';
            personNotes.value = '';
            setTimeout(() => personName.focus(),80);
        }

        modal.classList.add('show');
        modalBackdrop.classList.add('show');
        modal.setAttribute('aria-hidden','false');
    }

    function closeModal(){
        modal.classList.remove('show');
        modalBackdrop.classList.remove('show');
        modal.setAttribute('aria-hidden','true');
    }

    function fillStructureOptions(preferredCode=''){
        const structures = state.manage?.structures || [];

        functionStructure.innerHTML = structures.map(s => `
            <option value="${esc(s.codigo)}"${String(s.codigo) === String(preferredCode) ? ' selected' : ''}>
                ${esc(s.nome)} (${esc(s.codigo)})
            </option>
        `).join('');

        if (
            preferredCode
            && !structures.some(s => String(s.codigo) === String(preferredCode))
        ){
            functionStructure.value = structures[0]?.codigo || '';
        }
    }

    function fillFunctionOptions(preferredCode=''){
        const functions = state.manage?.functions || [];

        const ordered = [...functions].sort((a,b) => {
            const preferredA = String(a.estrutura_codigo) === String(preferredCode) ? 0 : 1;
            const preferredB = String(b.estrutura_codigo) === String(preferredCode) ? 0 : 1;

            if (preferredA !== preferredB) return preferredA - preferredB;

            const areaCmp = String(a.estrutura_nome || '').localeCompare(
                String(b.estrutura_nome || ''),
                'pt-BR'
            );

            if (areaCmp !== 0) return areaCmp;

            return String(a.nome || '').localeCompare(
                String(b.nome || ''),
                'pt-BR'
            );
        });

        personFunction.innerHTML = ordered.length
            ? ordered.map(fn => `
                <option value="${Number(fn.id)}">
                    ${esc(fn.estrutura_nome)} → ${esc(fn.nome)}
                </option>
            `).join('')
            : '<option value="">Cadastre uma função primeiro</option>';
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
                                ? 'Administrador da intranet — todas as estruturas podem ser gerenciadas.'
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

    function renderCurrentRegistrations(code,data){
        const functions = (data.funcoes || []).filter(
            fn => String(fn.estrutura_codigo || '') === String(code)
        );

        let rowsHtml = '';

        if (!functions.length){
            rowsHtml = `
                <div class="gov-empty-existing">
                    Nenhuma função/cargo cadastrada nesta estrutura.
                </div>
            `;
        }else{
            rowsHtml = functions.map(fn => {
                const occupants = fn.ocupantes || [];

                const occupantsHtml = occupants.length
                    ? occupants.map(person => `
                        <span class="gov-person-chip">
                            <span class="mini-avatar">${esc(initials(person.nome))}</span>
                            ${esc(person.nome)}
                        </span>
                    `).join('')
                    : `
                        <span class="gov-person-chip" style="color:#d97706">
                            Sem ocupante
                        </span>
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

                        <div class="gov-existing-people">
                            ${occupantsHtml}
                        </div>
                    </div>
                `;
            }).join('');
        }

        return `
            <div class="gov-current-section">
                <div class="gov-current-title">
                    <strong style="color:#1d4ed8;font-size:10px;">CADASTROS ATUAIS</strong>
                    <span>Funções e ocupantes desta estrutura</span>
                </div>

                <div class="gov-current-scroll">
                    <div class="gov-existing-list">
                        ${rowsHtml}
                    </div>
                </div>
            </div>
        `;
    }

    function renderCards(){
        const data = state.payload?.data || {};
        const manage = state.manage || {};
        const manageable = new Set(
            (manage.structures || []).map(s => String(s.codigo))
        );

        let structures = data.estrutura || [];

        if (manage.can_manage){
            structures = structures.filter(
                s => manageable.has(String(s.ID || ''))
            );
        }else{
            const roots = new Set(state.payload?.access?.roots || []);

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

                    ${renderCurrentRegistrations(code,data)}
                    </div>
                </article>
            `;
        }).join('');

        grid.querySelectorAll('[data-add-function]').forEach(button => {
            button.addEventListener('click',() => {
                openModal('function',button.dataset.addFunction || '');
            });
        });

        grid.querySelectorAll('[data-add-person]').forEach(button => {
            button.addEventListener('click',() => {
                openModal('person',button.dataset.addPerson || '');
            });
        });
    }

    async function load(){
        refresh.disabled = true;

        try{
            const [dataPayload,managePayload] = await Promise.all([
                request(DATA_API + '?t=' + Date.now()),
                request(MANAGE_API + '?action=scope&t=' + Date.now())
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

    function setSaving(button,saving,label){
        button.disabled = saving;
        button.textContent = saving ? 'Salvando...' : label;
    }

    functionForm.addEventListener('submit',async event => {
        event.preventDefault();

        const structureCode = functionStructure.value;
        const name = functionName.value.trim();
        const description = functionDescription.value.trim();

        if (!structureCode || !name) return;

        setSaving(saveFunction,true,'Salvar função');

        try{
            const result = await request(
                MANAGE_API + '?action=create_function',
                {
                    method:'POST',
                    body:JSON.stringify({
                        estrutura_codigo:structureCode,
                        nome:name,
                        descricao:description
                    })
                }
            );

            closeModal();
            showAlert(result.message || 'Função cadastrada.');
            await load();

        }catch(error){
            showAlert(error.message || 'Falha ao cadastrar a função.','error');
        }finally{
            setSaving(saveFunction,false,'Salvar função');
        }
    });

    personForm.addEventListener('submit',async event => {
        event.preventDefault();

        const functionId = Number(personFunction.value || 0);
        const name = personName.value.trim();

        if (!functionId || !name){
            showAlert('Selecione a função e informe o nome da pessoa.','error');
            return;
        }

        setSaving(savePerson,true,'Salvar pessoa');

        try{
            const result = await request(
                MANAGE_API + '?action=create_person',
                {
                    method:'POST',
                    body:JSON.stringify({
                        funcao_id:functionId,
                        nome:name,
                        tipo_vinculo:personLinkType.value,
                        observacoes:personNotes.value.trim()
                    })
                }
            );

            closeModal();
            showAlert(result.message || 'Pessoa cadastrada.');
            await load();

        }catch(error){
            showAlert(error.message || 'Falha ao cadastrar a pessoa.','error');
        }finally{
            setSaving(savePerson,false,'Salvar pessoa');
        }
    });

    refresh.addEventListener('click',load);

    modalClose.addEventListener('click',closeModal);
    modalBackdrop.addEventListener('click',closeModal);

    document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click',closeModal);
    });

    window.addEventListener('keydown',event => {
        if (event.key === 'Escape') closeModal();
    });

    load();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
