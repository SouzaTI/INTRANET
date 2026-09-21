<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$ehAdmin = !empty($_SESSION['is_admin']);

if ($usuarioId <= 0) {
    http_response_code(401);
    die('Sessão inválida ou expirada.');
}

if (!$ehAdmin) {
    http_response_code(403);
    ?>
    <main class="flex-1 min-w-0 overflow-y-auto bg-slate-100">
        <?php require __DIR__ . '/includes/governanca_nav.php'; ?>
        <div class="max-w-3xl mx-auto px-5 py-10">
            <div class="bg-white border border-red-200 rounded-2xl p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-red-500">Acesso restrito</p>
                <h1 class="mt-1 text-xl font-black text-slate-900">Gestão de lideranças</h1>
                <p class="mt-2 text-sm text-slate-600">Esta página está disponível somente para administradores.</p>
            </div>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (empty($_SESSION['governanca_csrf'])) {
    $_SESSION['governanca_csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['governanca_csrf'];
?>

<main class="flex-1 min-w-0 min-h-0 overflow-hidden bg-slate-100 flex flex-col">
    <?php require __DIR__ . '/includes/governanca_nav.php'; ?>

    <section id="gov-leadership" class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-6 py-5">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-blue-600">Governança</p>
                    <h1 class="text-2xl font-black text-slate-900">Lideranças por área</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        O líder vinculado recebe automaticamente o gerenciamento da área e de suas subáreas.
                    </p>
                </div>
                <button id="btnRefreshLeadership" type="button"
                        class="px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-black text-slate-600 hover:border-blue-300 hover:text-blue-700 transition">
                    ↻ Atualizar
                </button>
            </div>

            <div id="leadershipAlert" class="hidden mb-4 rounded-xl border px-4 py-3 text-sm font-semibold"></div>

            <div class="grid grid-cols-1 xl:grid-cols-[390px_minmax(0,1fr)] gap-5 items-start">
                <form id="leadershipForm" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-blue-600">Novo vínculo</p>
                        <h2 class="mt-1 text-lg font-black text-slate-900">Definir liderança</h2>
                    </div>

                    <div>
                        <label for="leaderStructure" class="gov-l-label">Área *</label>
                        <select id="leaderStructure" class="gov-l-field" required></select>
                    </div>

                    <div>
                        <label for="leaderUser" class="gov-l-label">Nome ou usuário da intranet *</label>
                        <input id="leaderUser" class="gov-l-field" list="leaderUserOptions"
                               autocomplete="off" placeholder="Digite o nome ou login" required>
                        <datalist id="leaderUserOptions"></datalist>
                        <p id="leaderUserHint" class="mt-1.5 text-[11px] leading-4 text-slate-400">
                            Selecione uma sugestão para liberar o acesso automaticamente.
                        </p>
                    </div>

                    <div>
                        <label for="leaderType" class="gov-l-label">Tipo</label>
                        <select id="leaderType" class="gov-l-field">
                            <option value="LIDER">Líder</option>
                            <option value="DIRETOR">Diretor</option>
                            <option value="GESTOR">Gestor</option>
                            <option value="RESPONSAVEL">Responsável</option>
                        </select>
                    </div>

                    <button id="saveLeadership" type="submit"
                            class="w-full px-4 py-3 rounded-xl bg-blue-600 text-white text-sm font-black hover:bg-blue-700 disabled:opacity-50 transition">
                        Salvar liderança
                    </button>
                </form>

                <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden min-w-0">
                    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cadastros atuais</p>
                            <h2 class="mt-1 text-base font-black text-slate-900">Líderes e escopos</h2>
                        </div>
                        <span id="leadershipCount" class="px-3 py-1.5 rounded-full bg-slate-100 text-[10px] font-black text-slate-600">0 ativos</span>
                    </div>
                    <div id="leadershipList" class="divide-y divide-slate-100">
                        <div class="p-8 text-center text-sm text-slate-500">Carregando lideranças...</div>
                    </div>
                </section>
            </div>
        </div>
    </section>
</main>

<style>
#gov-leadership .gov-l-label{display:block;margin-bottom:6px;color:#64748b;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
#gov-leadership .gov-l-field{width:100%;border:1px solid #dbe4ee;border-radius:12px;background:#fff;padding:11px 12px;color:#0f172a;font-size:13px;outline:none}
#gov-leadership .gov-l-field:focus{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
#gov-leadership .leader-row{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px}
#gov-leadership .leader-row:hover{background:#f8fafc}
#gov-leadership .leader-person{display:flex;align-items:center;gap:11px;min-width:0}
#gov-leadership .leader-avatar{width:38px;height:38px;flex:0 0 auto;border-radius:12px;display:grid;place-items:center;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:900}
#gov-leadership .leader-person strong{display:block;color:#0f172a;font-size:13px;font-weight:900}
#gov-leadership .leader-person span{display:block;margin-top:3px;color:#64748b;font-size:11px}
#gov-leadership .leader-meta{text-align:right;flex:0 0 auto}
#gov-leadership .leader-meta strong{display:block;color:#334155;font-size:11px;font-weight:900;text-transform:uppercase}
#gov-leadership .leader-meta span{display:inline-block;margin-top:5px;padding:4px 7px;border-radius:999px;font-size:8px;font-weight:900;text-transform:uppercase}
#gov-leadership .leader-linked{background:#ecfdf5;color:#047857}
#gov-leadership .leader-pending{background:#fff7ed;color:#c2410c}
#gov-leadership .leader-remove{margin-top:7px;color:#dc2626;font-size:10px;font-weight:800}
@media(max-width:640px){#gov-leadership .leader-row{align-items:flex-start;flex-direction:column}#gov-leadership .leader-meta{text-align:left}}
</style>

<script>
(() => {
    const API = 'api/governanca_liderancas.php';
    const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const form = document.getElementById('leadershipForm');
    const structure = document.getElementById('leaderStructure');
    const userInput = document.getElementById('leaderUser');
    const userOptions = document.getElementById('leaderUserOptions');
    const userHint = document.getElementById('leaderUserHint');
    const type = document.getElementById('leaderType');
    const save = document.getElementById('saveLeadership');
    const list = document.getElementById('leadershipList');
    const count = document.getElementById('leadershipCount');
    const alertBox = document.getElementById('leadershipAlert');
    const refresh = document.getElementById('btnRefreshLeadership');

    const state = {users:[], leaderships:[], editLeadership:null};
    const esc = value => String(value ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'",'&#039;');
    const initials = name => {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return parts.length > 1
            ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
            : (parts[0] || '—').slice(0,2).toUpperCase();
    };

    function showAlert(message, error=false){
        alertBox.className = 'mb-4 rounded-xl border px-4 py-3 text-sm font-semibold ' +
            (error ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700');
        alertBox.textContent = message;
        alertBox.classList.remove('hidden');
    }

    async function request(action, options={}){
        const response = await fetch(API + '?action=' + encodeURIComponent(action), {
            cache:'no-store', credentials:'same-origin',
            headers:{Accept:'application/json', ...(options.body ? {'Content-Type':'application/json','X-CSRF-Token':CSRF} : {})},
            ...options
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) throw new Error(payload?.error || 'Falha ao processar a solicitação.');
        return payload;
    }

    function render(){
        const active = state.leaderships.filter(row => Number(row.ativo) === 1);
        count.textContent = active.length + (active.length === 1 ? ' ativo' : ' ativos');
        list.innerHTML = active.length ? active.map(row => `
            <div class="leader-row">
                <div class="leader-person">
                    <span class="leader-avatar">${esc(initials(row.pessoa_nome))}</span>
                    <div class="min-w-0">
                        <strong>${esc(row.pessoa_nome)}</strong>
                        <span>${esc(row.estrutura_nome)} · ${esc(row.estrutura_codigo)}</span>
                    </div>
                </div>
                <div class="leader-meta">
                    <strong>${esc(row.tipo || 'Líder')}</strong>
                    <span class="${row.glpi_user_id ? 'leader-linked' : 'leader-pending'}">
                        ${row.glpi_user_id ? 'Usuário vinculado' : 'Vínculo pendente'}
                    </span>
                    ${row.glpi_user_id ? '' : `
                        <button type="button" class="leader-remove" style="color:#2563eb"
                                data-link-leader="${Number(row.id)}"
                                data-link-person="${Number(row.pessoa_id)}"
                                data-link-name="${esc(row.pessoa_nome)}"
                                data-link-structure="${esc(row.estrutura_codigo)}">
                            Vincular usuário
                        </button>
                    `}
                    <button type="button" class="leader-remove" data-remove-leader="${Number(row.id)}">Inativar</button>
                </div>
            </div>
        `).join('') : '<div class="p-8 text-center text-sm text-slate-500">Nenhuma liderança ativa.</div>';
    }

    async function load(){
        list.innerHTML = '<div class="p-8 text-center text-sm text-slate-500">Carregando lideranças...</div>';
        try{
            const payload = await request('bootstrap');
            state.users = payload.users || [];
            state.leaderships = payload.leaderships || [];
            structure.innerHTML = (payload.structures || []).map(row => `
                <option value="${esc(row.codigo)}">${esc(row.nome)} (${esc(row.codigo)})</option>
            `).join('');
            userOptions.innerHTML = state.users.map(row => `<option value="${esc(row.label)}"></option>`).join('');
            render();
        }catch(error){
            list.innerHTML = '<div class="p-8 text-center text-sm text-red-600">' + esc(error.message) + '</div>';
        }
    }

    userInput.addEventListener('input', () => {
        const selected = state.users.find(row => row.label === userInput.value.trim());
        userHint.textContent = selected
            ? `Usuário localizado: ${selected.login}`
            : 'Sem correspondência exata: a liderança será salva com vínculo de acesso pendente.';
        userHint.className = 'mt-1.5 text-[11px] leading-4 ' + (selected ? 'text-emerald-600' : 'text-orange-600');
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const typed = userInput.value.trim();
        const selected = state.users.find(row => row.label === typed);

        if (state.editLeadership && !selected) {
            showAlert('Selecione um usuário válido da lista para concluir o vínculo.', true);
            return;
        }

        save.disabled = true;
        save.textContent = 'Salvando...';
        try{
            const result = await request('save', {
                method:'POST',
                body:JSON.stringify({
                    estrutura_codigo:structure.value,
                    glpi_user_id:selected?.id || 0,
                    pessoa_nome:state.editLeadership?.personName || selected?.nome || typed,
                    tipo:type.value,
                    lideranca_id:state.editLeadership?.id || 0,
                    pessoa_id:state.editLeadership?.personId || 0
                })
            });
            showAlert(result.message);
            userInput.value = '';
            state.editLeadership = null;
            save.textContent = 'Salvar liderança';
            await load();
        }catch(error){
            showAlert(error.message, true);
        }finally{
            save.disabled = false;
            save.textContent = state.editLeadership
                ? 'Concluir vínculo'
                : 'Salvar liderança';
        }
    });

    list.addEventListener('click', async event => {
        const linkButton = event.target.closest('[data-link-leader]');
        if (linkButton) {
            state.editLeadership = {
                id:Number(linkButton.dataset.linkLeader),
                personId:Number(linkButton.dataset.linkPerson),
                personName:linkButton.dataset.linkName || ''
            };
            structure.value = linkButton.dataset.linkStructure || '';
            userInput.value = '';
            userHint.textContent = `Vinculando ${state.editLeadership.personName}: selecione o usuário correto.`;
            userHint.className = 'mt-1.5 text-[11px] leading-4 text-blue-600';
            save.textContent = 'Concluir vínculo';
            userInput.focus();
            return;
        }

        const button = event.target.closest('[data-remove-leader]');
        if (!button || !confirm('Deseja inativar esta liderança?')) return;
        button.disabled = true;
        try{
            const result = await request('deactivate', {
                method:'POST', body:JSON.stringify({lideranca_id:Number(button.dataset.removeLeader)})
            });
            showAlert(result.message);
            await load();
        }catch(error){
            showAlert(error.message, true);
            button.disabled = false;
        }
    });

    refresh.addEventListener('click', load);
    load();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
