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
                <h1 class="mt-1 text-xl font-black text-slate-900">Gestão de gestores</h1>
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

<main class="gov-leadership-dark flex-1 min-w-0 min-h-0 overflow-hidden flex flex-col">
    <?php require __DIR__ . '/includes/governanca_nav.php'; ?>

    <section id="gov-leadership" class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-[1500px] mx-auto px-4 sm:px-5 lg:px-6 py-4">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-blue-600">Governança</p>
                    <h1 class="text-2xl font-black text-slate-900">Gestores por área</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        O gestor responde pela área. Líderes operacionais podem ser cadastrados separadamente.
                    </p>
                </div>
                <button id="btnRefreshLeadership" type="button"
                        class="px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-black text-slate-600 hover:border-blue-300 hover:text-blue-700 transition">
                    ↻ Atualizar
                </button>
            </div>

            <div id="leadershipAlert" class="hidden mb-4 rounded-xl border px-4 py-3 text-sm font-semibold"></div>

            <div class="grid grid-cols-1 xl:grid-cols-[330px_minmax(0,1fr)] gap-3 items-start">
                <form id="leadershipForm" class="rounded-2xl p-4 space-y-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-blue-600">Novo vínculo</p>
                        <h2 class="mt-1 text-lg font-black text-slate-900">Definir gestor ou líder</h2>
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
                            <option value="GESTOR">Gestor</option>
                            <option value="LIDER">Líder operacional</option>
                            <option value="DIRETOR">Diretor</option>
                            <option value="RESPONSAVEL">Responsável</option>
                        </select>
                    </div>

                    <button id="saveLeadership" type="submit"
                            class="w-full px-4 py-3 rounded-xl bg-blue-600 text-white text-sm font-black hover:bg-blue-700 disabled:opacity-50 transition">
                        Salvar vínculo
                    </button>
                </form>

                <section class="leadership-panel rounded-2xl overflow-hidden min-w-0">
                    <div class="leadership-panel-head px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cadastros atuais</p>
                            <h2 class="mt-1 text-base font-black text-slate-900">Gestores, líderes e escopos</h2>
                        </div>
                        <span id="leadershipCount" class="px-3 py-1.5 rounded-full bg-slate-100 text-[10px] font-black text-slate-600">0 ativos</span>
                    </div>
                    <div id="leadershipList" class="leader-list">
                        <div class="p-8 text-center text-sm text-slate-500">Carregando lideranças...</div>
                    </div>
                </section>
            </div>
        </div>
    </section>
</main>

<style>
.gov-leadership-dark{
    background-color:#081426;
    background-image:radial-gradient(circle at 1px 1px,rgba(96,165,250,.13) 1px,transparent 0);
    background-size:28px 28px
}
#gov-leadership{color:#cbd5e1}
#gov-leadership h1,#gov-leadership h2{color:#f8fafc!important}
#gov-leadership .text-slate-500{color:#93a4bb!important}
#gov-leadership .text-slate-400{color:#71839b!important}
#gov-leadership .text-blue-600{color:#60a5fa!important}
#btnRefreshLeadership{
    border-color:#334a68!important;background:#12223a!important;color:#dbeafe!important
}
#btnRefreshLeadership:hover{border-color:#60a5fa!important;background:#172b49!important}
#leadershipForm,.leadership-panel{
    border:1px solid #273b55;background:rgba(15,29,49,.97);
    box-shadow:0 14px 36px rgba(2,8,23,.24)
}
#leadershipForm{position:sticky;top:16px}
#gov-leadership .gov-l-label{
    display:block;margin-bottom:4px;color:#93a4bb;font-size:9px;
    font-weight:900;letter-spacing:.08em;text-transform:uppercase
}
#gov-leadership .gov-l-field{
    width:100%;height:38px;border:1px solid #334a68;border-radius:9px;
    background:#09172a;padding:8px 10px;color:#f8fafc;font-size:11px;
    outline:none;color-scheme:dark
}
#gov-leadership .gov-l-field:focus{
    border-color:#60a5fa;box-shadow:0 0 0 3px rgba(59,130,246,.14)
}
#leaderUserHint{font-size:9px!important;line-height:1.35!important}
#saveLeadership{padding:9px 12px!important;border-radius:9px!important;font-size:11px!important}
.leadership-panel-head{border-bottom:1px solid #273b55;background:#102038}
#leadershipCount{background:#193354!important;color:#bfdbfe!important}
#gov-leadership .leader-list{
    padding:9px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px
}
#gov-leadership .leader-list>.p-8{grid-column:1/-1}
#gov-leadership .leader-row{
    min-width:0;padding:10px 11px;display:grid;align-items:center;
    grid-template-columns:minmax(0,1fr) auto;gap:12px;border:1px solid #263a54;
    border-radius:11px;background:#0b192c;transition:.15s ease
}
#gov-leadership .leader-row:hover{border-color:#3e5c7d;background:#10223a}
#gov-leadership .leader-person{display:flex;align-items:center;gap:8px;min-width:0}
#gov-leadership .leader-avatar{
    width:34px;height:34px;flex:0 0 auto;border-radius:9px;display:grid;
    place-items:center;background:#142641;border:1px solid #2e4665;overflow:hidden
}
#gov-leadership .leader-avatar img{
    width:30px;height:30px;display:block;object-fit:contain
}
#gov-leadership .leader-person strong{
    display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    color:#f8fafc;font-size:13px;font-weight:900;line-height:1.2
}
#gov-leadership .leader-person span{
    display:block;margin-top:2px;overflow:hidden;text-overflow:ellipsis;
    white-space:nowrap;color:#a8b8cc;font-size:10px;line-height:1.25
}
#gov-leadership .leader-meta{min-width:132px;text-align:right;flex:0 0 auto}
#gov-leadership .leader-meta strong{
    display:block;color:#bfdbfe;font-size:10px;font-weight:900;
    line-height:1.2;text-transform:uppercase
}
#gov-leadership .leader-meta span{
    display:inline-block;margin-top:5px;padding:4px 6px;border-radius:999px;
    font-size:8px;font-weight:900;line-height:1;text-transform:uppercase
}
#gov-leadership .leader-linked{background:#123c35;color:#6ee7b7}
#gov-leadership .leader-pending{background:#3c2916;color:#fdba74}
#gov-leadership .leader-remove{
    margin:4px 0 0 7px;color:#fb7185;font-size:10px;font-weight:900;
    line-height:1.1
}
#leadershipAlert.border-emerald-200{border-color:#285f55!important;background:#123c35!important;color:#a7f3d0!important}
#leadershipAlert.border-red-200{border-color:#7f3341!important;background:#3b1722!important;color:#fecdd3!important}
@media(max-width:1150px){
    #gov-leadership .leader-list{grid-template-columns:1fr}
    #leadershipForm{position:static}
}
@media(max-width:640px){
    #gov-leadership .leader-row{grid-template-columns:1fr;align-items:flex-start}
    #gov-leadership .leader-meta{min-width:0;text-align:left}
}
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
                    <span class="leader-avatar">
                        <img src="assets/SOL.PNG" alt="" aria-hidden="true">
                    </span>
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
        `).join('') : '<div class="p-8 text-center text-sm text-slate-500">Nenhum gestor ou líder ativo.</div>';
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
            : 'Sem correspondência exata: o responsável será salvo com vínculo de acesso pendente.';
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
            save.textContent = 'Salvar vínculo';
            await load();
        }catch(error){
            showAlert(error.message, true);
        }finally{
            save.disabled = false;
            save.textContent = state.editLeadership
                ? 'Concluir vínculo'
                : 'Salvar vínculo';
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
        if (!button || !confirm('Deseja inativar este vínculo?')) return;
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
