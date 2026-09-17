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
        <div class="max-w-3xl mx-auto px-5 py-10">
            <div class="bg-white border border-red-200 rounded-2xl p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-red-500">Acesso restrito</p>
                <h1 class="mt-1 text-xl font-black text-slate-900">Gestão de acessos da Governança</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Esta página está disponível somente para administradores da intranet.
                </p>
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

$csrf = $_SESSION['governanca_csrf'];
?>

<main class="flex-1 min-w-0 min-h-0 overflow-y-auto bg-slate-100">
    <div class="max-w-[1500px] mx-auto px-4 sm:px-5 lg:px-6 py-5">

        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.2em] text-blue-600">Governança</p>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Gestão de acessos</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Defina quais áreas da Governança cada usuário ou grupo pode visualizar.
                </p>
            </div>

            <a href="governanca_ti.php"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-700 hover:border-blue-300 hover:text-blue-700 transition shadow-sm">
                <span>←</span>
                <span>Voltar à Governança</span>
            </a>
        </div>

        <div id="gov-alert" class="hidden mb-4 rounded-xl border px-4 py-3 text-sm font-semibold"></div>

        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm mb-5 overflow-hidden">
            <div class="px-5 py-4 flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Controle global</p>
                    <h2 class="mt-1 text-base font-black text-slate-900">Permissões por nó</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Quando ativado, cada usuário recebe somente os ramos liberados abaixo.
                    </p>
                </div>

                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <span id="global-status-text" class="text-xs font-black uppercase tracking-wider text-slate-500">
                        Carregando...
                    </span>
                    <span class="relative inline-flex items-center">
                        <input id="global-enabled" type="checkbox" class="peer sr-only">
                        <span class="w-12 h-7 rounded-full bg-slate-300 peer-checked:bg-emerald-500 transition"></span>
                        <span class="absolute left-1 top-1 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
                    </span>
                </label>
            </div>
        </section>

        <div id="access-editor" class="grid grid-cols-1 xl:grid-cols-[420px_minmax(0,1fr)] gap-5">

            <!-- COLUNA ALVO -->
            <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden h-fit">
                <div class="px-5 py-4 border-b border-slate-200">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">1. Quem recebe o acesso</p>
                    <h2 class="mt-1 text-base font-black text-slate-900">Usuário ou grupo</h2>
                </div>

                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl">
                        <button type="button" id="btn-type-user"
                                class="target-type-btn px-3 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition">
                            Usuário
                        </button>
                        <button type="button" id="btn-type-group"
                                class="target-type-btn px-3 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition">
                            Grupo
                        </button>
                    </div>

                    <div class="relative">
                        <label for="target-search" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">
                            Buscar usuário / grupo
                        </label>

                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">⌕</span>
                            <input id="target-search" type="text" autocomplete="off"
                                   placeholder="Digite o nome ou ID..."
                                   class="w-full rounded-xl border border-slate-200 bg-white pl-10 pr-10 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400">

                            <button id="target-search-clear" type="button"
                                    class="hidden absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-700"
                                    title="Limpar busca"
                                    aria-label="Limpar busca">×</button>
                        </div>

                        <p class="mt-2 text-[11px] leading-4 text-slate-400">
                            Digite parte do nome ou o ID e clique no resultado desejado.
                        </p>

                        <div id="target-results"
                             class="hidden absolute left-0 right-0 z-30 mt-2 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
                        </div>

                        <!-- Select interno: usado apenas pela lógica da tela -->
                        <select id="target-select" class="hidden" aria-hidden="true" tabindex="-1">
                            <option value="">Selecione...</option>
                        </select>
                    </div>

                    <div id="target-info" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Selecione um usuário ou grupo.</p>
                    </div>

                    <div>
                        <label for="access-level" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">
                            Nível
                        </label>
                        <select id="access-level"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400">
                            <option value="VISUALIZAR">Visualizar</option>
                            <option value="GERENCIAR">Gerenciar</option>
                        </select>
                        <p class="mt-2 text-[11px] leading-4 text-slate-400">
                            “Gerenciar” já fica gravado para a futura tela de edição da Governança.
                        </p>
                    </div>
                </div>
            </section>

            <!-- COLUNA ESCOPO -->
            <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden min-w-0">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">2. O que pode visualizar</p>
                        <h2 class="mt-1 text-base font-black text-slate-900">Escopo da Governança</h2>
                    </div>

                    <div id="current-permission-badge"
                         class="hidden px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-[10px] font-black uppercase tracking-wider text-blue-700">
                    </div>
                </div>

                <div class="p-5">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-5">

                        <label class="scope-card cursor-pointer rounded-2xl border border-slate-200 p-4 transition hover:border-blue-300">
                            <div class="flex items-start gap-3">
                                <input type="radio" name="scope-mode" value="full" class="mt-1">
                                <div>
                                    <p class="text-sm font-black text-slate-900">Governança inteira</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">
                                        Libera todos os ramos. No banco será salvo como <strong>*</strong>.
                                    </p>
                                </div>
                            </div>
                        </label>

                        <label class="scope-card cursor-pointer rounded-2xl border border-slate-200 p-4 transition hover:border-blue-300">
                            <div class="flex items-start gap-3">
                                <input type="radio" name="scope-mode" value="subtree" class="mt-1">
                                <div>
                                    <p class="text-sm font-black text-slate-900">Área completa</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">
                                        Ex.: Análise de Dados + Power BI + Suporte + Desenvolvimento.
                                    </p>
                                </div>
                            </div>
                        </label>

                        <label class="scope-card cursor-pointer rounded-2xl border border-slate-200 p-4 transition hover:border-blue-300">
                            <div class="flex items-start gap-3">
                                <input type="radio" name="scope-mode" value="custom" class="mt-1">
                                <div>
                                    <p class="text-sm font-black text-slate-900">Personalizado</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">
                                        Combine Power BI, Desenvolvimento ou qualquer outro nó.
                                    </p>
                                </div>
                            </div>
                        </label>

                    </div>

                    <div id="scope-full" class="scope-panel hidden rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <p class="text-sm font-black text-emerald-900">Acesso total à Governança</p>
                        <p class="mt-1 text-xs leading-5 text-emerald-800">
                            O usuário continuará sendo um usuário comum da intranet; esta opção libera apenas a Governança.
                        </p>
                    </div>

                    <div id="scope-subtree" class="scope-panel hidden">
                        <label for="subtree-select" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">
                            Área inicial
                        </label>
                        <select id="subtree-select"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400">
                        </select>

                        <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                            <p class="text-xs text-blue-800">
                                A área selecionada e <strong>todos os seus descendentes</strong> serão liberados.
                                Os pais acima dela aparecerão apenas como caminho hierárquico.
                            </p>
                        </div>
                    </div>

                    <div id="scope-custom" class="scope-panel hidden">
                        <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                            <div>
                                <p class="text-sm font-black text-slate-900">Selecione os nós</p>
                                <p class="text-xs text-slate-500">Para cada item, escolha se as subáreas também serão incluídas.</p>
                            </div>

                            <button type="button" id="clear-custom"
                                    class="px-3 py-2 rounded-lg border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                                Limpar seleção
                            </button>
                        </div>

                        <div id="structure-tree"
                             class="max-h-[470px] overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2">
                            <div class="p-4 text-sm text-slate-500">Carregando estrutura...</div>
                        </div>
                    </div>

                    <div class="mt-6 pt-5 border-t border-slate-200 flex items-center justify-between gap-4 flex-wrap">
                        <button type="button" id="btn-clear-access"
                                class="px-4 py-2.5 rounded-xl border border-red-200 bg-white text-sm font-bold text-red-600 hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            Remover acessos
                        </button>

                        <button type="button" id="btn-save-access"
                                class="px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-black shadow-sm hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            Salvar acesso
                        </button>
                    </div>

                </div>
            </section>
        </div>

        <section class="mt-5 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Auditoria rápida</p>
                    <h2 class="mt-1 text-base font-black text-slate-900">Permissões cadastradas</h2>
                </div>
                <span id="permission-count" class="text-xs font-bold text-slate-500">0 registros</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-[10px] uppercase tracking-wider text-slate-500">
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Alvo</th>
                            <th class="px-5 py-3">Estrutura</th>
                            <th class="px-5 py-3">Subáreas</th>
                            <th class="px-5 py-3">Nível</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="permission-table" class="divide-y divide-slate-100">
                        <tr><td colspan="7" class="px-5 py-5 text-sm text-slate-500">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<script>
(() => {
    const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const API_ACCESS = 'api/governanca_acessos.php';
    const API_DATA = 'api/governanca_dados.php';

    const state = {
        targetType: 'USUARIO',
        users: [],
        groups: [],
        permissions: [],
        structures: [],
        structureById: new Map(),
        childrenById: new Map(),
        enabled: false
    };

    const $ = (id) => document.getElementById(id);

    const els = {
        alert: $('gov-alert'),
        enabled: $('global-enabled'),
        enabledText: $('global-status-text'),
        btnUser: $('btn-type-user'),
        btnGroup: $('btn-type-group'),
        search: $('target-search'),
        searchClear: $('target-search-clear'),
        results: $('target-results'),
        target: $('target-select'),
        targetInfo: $('target-info'),
        accessLevel: $('access-level'),
        subtree: $('subtree-select'),
        tree: $('structure-tree'),
        clearCustom: $('clear-custom'),
        clearAccess: $('btn-clear-access'),
        saveAccess: $('btn-save-access'),
        badge: $('current-permission-badge'),
        table: $('permission-table'),
        count: $('permission-count')
    };

    function showAlert(message, type = 'ok') {
        const palette = {
            ok: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            warn: 'border-amber-200 bg-amber-50 text-amber-800',
            err: 'border-red-200 bg-red-50 text-red-800'
        };

        els.alert.className = 'mb-4 rounded-xl border px-4 py-3 text-sm font-semibold ' + (palette[type] || palette.ok);
        els.alert.textContent = message;
        els.alert.classList.remove('hidden');

        clearTimeout(showAlert.timer);
        showAlert.timer = setTimeout(() => els.alert.classList.add('hidden'), 4500);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function apiGet(action) {
        const response = await fetch(API_ACCESS + '?action=' + encodeURIComponent(action) + '&t=' + Date.now(), {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.error || ('HTTP ' + response.status));
        }

        return payload;
    }

    async function apiPost(action, body) {
        const response = await fetch(API_ACCESS + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF
            },
            body: JSON.stringify(body || {})
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.error || ('HTTP ' + response.status));
        }

        return payload;
    }

    function normalizeStructureRows(rows) {
        state.structures = Array.isArray(rows) ? rows : [];
        state.structureById.clear();
        state.childrenById.clear();

        state.structures.forEach(row => {
            const id = String(row.ID || '').trim();
            const parent = String(row.ID_PAI || '').trim();
            if (!id) return;

            state.structureById.set(id, row);

            if (!state.childrenById.has(parent)) {
                state.childrenById.set(parent, []);
            }
            state.childrenById.get(parent).push(id);
        });

        for (const ids of state.childrenById.values()) {
            ids.sort((a, b) => {
                const ra = state.structureById.get(a) || {};
                const rb = state.structureById.get(b) || {};
                const oa = Number(ra.ORDEM || 999999);
                const ob = Number(rb.ORDEM || 999999);
                if (oa !== ob) return oa - ob;
                return String(ra.NOME || a).localeCompare(String(rb.NOME || b), 'pt-BR');
            });
        }
    }

    function structureName(code) {
        if (code === '*') return 'Governança inteira';
        const row = state.structureById.get(code);
        return row?.NOME || code;
    }

    function orderedStructures() {
        const result = [];
        const visited = new Set();

        function walk(id, depth) {
            if (visited.has(id)) return;
            visited.add(id);

            const row = state.structureById.get(id);
            if (!row) return;

            result.push({ row, depth });

            (state.childrenById.get(id) || []).forEach(child => walk(child, depth + 1));
        }

        const roots = state.structures
            .filter(row => !String(row.ID_PAI || '').trim() || !state.structureById.has(String(row.ID_PAI || '').trim()))
            .map(row => String(row.ID || '').trim())
            .filter(Boolean);

        roots.sort((a, b) => {
            const ra = state.structureById.get(a) || {};
            const rb = state.structureById.get(b) || {};
            return Number(ra.ORDEM || 999999) - Number(rb.ORDEM || 999999);
        });

        roots.forEach(id => walk(id, 0));

        // Qualquer nó órfão que tenha sobrado.
        state.structures.forEach(row => {
            const id = String(row.ID || '').trim();
            if (id && !visited.has(id)) walk(id, 0);
        });

        return result;
    }

    function renderStructureSelectors() {
        const ordered = orderedStructures();

        els.subtree.innerHTML = ordered.map(({row, depth}) => {
            const id = String(row.ID || '').trim();
            const name = String(row.NOME || id);
            const prefix = '— '.repeat(Math.max(0, depth));
            return `<option value="${escapeHtml(id)}">${escapeHtml(prefix + name)}</option>`;
        }).join('');

        els.tree.innerHTML = ordered.map(({row, depth}) => {
            const id = String(row.ID || '').trim();
            const name = String(row.NOME || id);
            const hasChildren = (state.childrenById.get(id) || []).length > 0;

            return `
                <div class="custom-node flex items-center gap-3 px-3 py-2.5 border-b border-slate-200/70 last:border-b-0"
                     style="padding-left:${12 + (depth * 22)}px"
                     data-code="${escapeHtml(id)}">
                    <input type="checkbox"
                           class="node-check w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                           data-code="${escapeHtml(id)}">

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-800 truncate">${escapeHtml(name)}</p>
                        <p class="text-[10px] font-semibold text-slate-400">${escapeHtml(id)}</p>
                    </div>

                    <select class="node-desc rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] font-bold text-slate-600 disabled:opacity-40"
                            data-code="${escapeHtml(id)}"
                            ${hasChildren ? '' : 'disabled'}>
                        <option value="1">+ subáreas</option>
                        <option value="0">somente nó</option>
                    </select>
                </div>
            `;
        }).join('');

        els.tree.querySelectorAll('.node-check').forEach(check => {
            check.addEventListener('change', () => {
                const row = check.closest('.custom-node');
                const select = row?.querySelector('.node-desc');
                if (select && !select.hasAttribute('data-leaf')) {
                    select.classList.toggle('opacity-40', !check.checked);
                }
            });
        });
    }

    function getTargetList() {
        return state.targetType === 'USUARIO' ? state.users : state.groups;
    }

    function syncTargetOptions() {
        const list = getTargetList();

        els.target.innerHTML =
            `<option value="">Selecione...</option>` +
            list.map(item => `<option value="${item.id}">${escapeHtml(item.label)}</option>`).join('');
    }

    function renderTargetResults(forceOpen = false) {
        const term = els.search.value.trim().toLowerCase();
        const list = getTargetList();

        const filtered = list.filter(item => {
            const label = String(item.label || '').toLowerCase();
            const id = String(item.id || '');
            return !term || label.includes(term) || id.includes(term);
        }).slice(0, 15);

        els.searchClear.classList.toggle('hidden', els.search.value.length === 0);

        if (!forceOpen && term === '') {
            els.results.classList.add('hidden');
            els.results.innerHTML = '';
            return;
        }

        if (!filtered.length) {
            els.results.innerHTML = `
                <div class="px-4 py-4 text-sm text-slate-500">
                    Nenhum ${state.targetType === 'USUARIO' ? 'usuário' : 'grupo'} encontrado.
                </div>
            `;
            els.results.classList.remove('hidden');
            return;
        }

        els.results.innerHTML = filtered.map(item => `
            <button type="button"
                    class="target-result w-full text-left px-4 py-3 border-b border-slate-100 last:border-b-0 hover:bg-blue-50 transition"
                    data-id="${item.id}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900 truncate">${escapeHtml(item.label)}</p>
                        <p class="mt-0.5 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            ${state.targetType === 'USUARIO' ? 'Usuário' : 'Grupo'} · ID ${item.id}
                        </p>
                    </div>
                    <span class="text-blue-500 font-black">→</span>
                </div>
            </button>
        `).join('');

        els.results.querySelectorAll('.target-result').forEach(button => {
            button.addEventListener('click', () => {
                chooseTarget(Number(button.dataset.id));
            });
        });

        els.results.classList.remove('hidden');
    }

    function chooseTarget(id, options = {}) {
        const item = getTargetList().find(row => Number(row.id) === Number(id)) || null;

        if (!item) {
            els.target.value = '';
            updateTargetInfo();
            loadSelectedPermissions();
            return;
        }

        els.target.value = String(item.id);
        els.search.value = item.label;
        els.searchClear.classList.remove('hidden');
        els.results.classList.add('hidden');

        updateTargetInfo();
        loadSelectedPermissions();

        if (options.scroll) {
            document.getElementById('access-editor')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    function clearTargetSearch() {
        els.search.value = '';
        els.target.value = '';
        els.searchClear.classList.add('hidden');
        els.results.classList.add('hidden');
        updateTargetInfo();
        loadSelectedPermissions();
        els.search.focus();
    }

    function setTargetType(type) {
        state.targetType = type;

        const userActive = type === 'USUARIO';
        els.btnUser.className = 'target-type-btn px-3 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition ' +
            (userActive ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-800');

        els.btnGroup.className = 'target-type-btn px-3 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition ' +
            (!userActive ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-800');

        els.search.placeholder = userActive
            ? 'Digite o nome ou ID do usuário...'
            : 'Digite o nome ou ID do grupo...';

        els.search.value = '';
        els.target.value = '';
        els.searchClear.classList.add('hidden');
        els.results.classList.add('hidden');

        syncTargetOptions();
        updateTargetInfo();
        loadSelectedPermissions();
    }

    function selectedTarget() {
        const id = Number(els.target.value || 0);
        return getTargetList().find(item => Number(item.id) === id) || null;
    }

    function updateTargetInfo() {
        const item = selectedTarget();

        if (!item) {
            els.targetInfo.innerHTML = `<p class="text-xs text-slate-500">Selecione um usuário ou grupo.</p>`;
            els.saveAccess.disabled = true;
            els.clearAccess.disabled = true;
            return;
        }

        els.targetInfo.innerHTML = `
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                ${state.targetType === 'USUARIO' ? 'Usuário selecionado' : 'Grupo selecionado'}
            </p>
            <p class="mt-1 text-base font-black text-slate-900">${escapeHtml(item.label)}</p>
            <p class="mt-1 text-xs font-bold text-blue-600">ID ${item.id}</p>
        `;

        els.saveAccess.disabled = false;
        els.clearAccess.disabled = false;
    }

    function permissionsForSelectedTarget() {
        const target = selectedTarget();
        if (!target) return [];

        return state.permissions.filter(p =>
            p.alvo_tipo === state.targetType &&
            Number(p.alvo_id) === Number(target.id) &&
            Number(p.ativo) === 1
        );
    }

    function selectScopeMode(mode) {
        document.querySelectorAll('input[name="scope-mode"]').forEach(radio => {
            radio.checked = radio.value === mode;
        });

        document.querySelectorAll('.scope-card').forEach(card => {
            const radio = card.querySelector('input[name="scope-mode"]');
            const active = radio?.value === mode;
            card.classList.toggle('border-blue-400', active);
            card.classList.toggle('bg-blue-50', active);
            card.classList.toggle('border-slate-200', !active);
        });

        document.querySelectorAll('.scope-panel').forEach(panel => panel.classList.add('hidden'));
        const panel = document.getElementById('scope-' + mode);
        if (panel) panel.classList.remove('hidden');
    }

    function clearCustomTree() {
        els.tree.querySelectorAll('.node-check').forEach(check => {
            check.checked = false;
        });
        els.tree.querySelectorAll('.node-desc').forEach(select => {
            select.value = '1';
        });
    }

    function loadSelectedPermissions() {
        updateTargetInfo();
        clearCustomTree();

        const perms = permissionsForSelectedTarget();

        if (!selectedTarget()) {
            els.badge.classList.add('hidden');
            selectScopeMode('subtree');
            return;
        }

        if (!perms.length) {
            els.badge.textContent = 'Sem acesso cadastrado';
            els.badge.className = 'px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-[10px] font-black uppercase tracking-wider text-amber-700';
            els.badge.classList.remove('hidden');
            selectScopeMode('subtree');
            els.accessLevel.value = 'VISUALIZAR';
            return;
        }

        els.badge.textContent = perms.length + (perms.length === 1 ? ' permissão' : ' permissões');
        els.badge.className = 'px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-[10px] font-black uppercase tracking-wider text-blue-700';
        els.badge.classList.remove('hidden');

        els.accessLevel.value = perms.some(p => p.nivel_acesso === 'GERENCIAR') ? 'GERENCIAR' : 'VISUALIZAR';

        if (perms.some(p => p.estrutura_codigo === '*')) {
            selectScopeMode('full');
            return;
        }

        if (perms.length === 1 && Number(perms[0].inclui_descendentes) === 1) {
            selectScopeMode('subtree');
            els.subtree.value = perms[0].estrutura_codigo;
            return;
        }

        selectScopeMode('custom');

        perms.forEach(permission => {
            const code = permission.estrutura_codigo;
            const check = els.tree.querySelector(`.node-check[data-code="${CSS.escape(code)}"]`);
            const desc = els.tree.querySelector(`.node-desc[data-code="${CSS.escape(code)}"]`);

            if (check) check.checked = true;
            if (desc && !desc.disabled) {
                desc.value = Number(permission.inclui_descendentes) === 1 ? '1' : '0';
            }
        });
    }

    function collectPermissions() {
        const mode = document.querySelector('input[name="scope-mode"]:checked')?.value || 'subtree';

        if (mode === 'full') {
            return [{ estrutura_codigo: '*', inclui_descendentes: 1 }];
        }

        if (mode === 'subtree') {
            const code = els.subtree.value;
            return code ? [{ estrutura_codigo: code, inclui_descendentes: 1 }] : [];
        }

        const rows = [];

        els.tree.querySelectorAll('.node-check:checked').forEach(check => {
            const code = check.dataset.code;
            const desc = els.tree.querySelector(`.node-desc[data-code="${CSS.escape(code)}"]`);

            rows.push({
                estrutura_codigo: code,
                inclui_descendentes: desc && !desc.disabled ? Number(desc.value) : 0
            });
        });

        return rows;
    }

    function targetLabel(type, id) {
        const list = type === 'USUARIO' ? state.users : state.groups;
        return list.find(item => Number(item.id) === Number(id))?.label || ('ID ' + id);
    }

    function renderPermissionTable() {
        const rows = state.permissions.filter(p => Number(p.ativo) === 1);
        els.count.textContent = rows.length + (rows.length === 1 ? ' registro' : ' registros');

        if (!rows.length) {
            els.table.innerHTML = `<tr><td colspan="7" class="px-5 py-5 text-sm text-slate-500">Nenhuma permissão cadastrada.</td></tr>`;
            return;
        }

        els.table.innerHTML = rows.map(p => `
            <tr class="text-sm">
                <td class="px-5 py-3 font-bold text-slate-700">${p.alvo_tipo === 'USUARIO' ? 'Usuário' : 'Grupo'}</td>
                <td class="px-5 py-3">
                    <div class="font-bold text-slate-900">${escapeHtml(targetLabel(p.alvo_tipo, p.alvo_id))}</div>
                    <div class="text-[10px] font-bold text-slate-400">ID ${p.alvo_id}</div>
                </td>
                <td class="px-5 py-3">
                    <div class="font-bold text-slate-800">${escapeHtml(structureName(p.estrutura_codigo))}</div>
                    <div class="text-[10px] font-bold text-slate-400">${escapeHtml(p.estrutura_codigo)}</div>
                </td>
                <td class="px-5 py-3">${Number(p.inclui_descendentes) === 1 ? 'Sim' : 'Não'}</td>
                <td class="px-5 py-3">${escapeHtml(p.nivel_acesso)}</td>
                <td class="px-5 py-3">
                    <span class="inline-flex px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase">Ativo</span>
                </td>
                <td class="px-5 py-3">
                    <div class="flex items-center justify-end gap-2">
                        <button type="button"
                                class="btn-edit-permission px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-[10px] font-black uppercase tracking-wider hover:bg-blue-100 transition"
                                data-type="${escapeHtml(p.alvo_tipo)}"
                                data-target="${Number(p.alvo_id)}">
                            Editar
                        </button>

                        <button type="button"
                                class="btn-delete-permission px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 text-red-600 text-[10px] font-black uppercase tracking-wider hover:bg-red-100 transition"
                                data-id="${Number(p.id)}"
                                data-label="${escapeHtml(targetLabel(p.alvo_tipo, p.alvo_id))}"
                                data-structure="${escapeHtml(structureName(p.estrutura_codigo))}">
                            Excluir
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        els.table.querySelectorAll('.btn-edit-permission').forEach(button => {
            button.addEventListener('click', () => {
                editTargetAccess(button.dataset.type, Number(button.dataset.target));
            });
        });

        els.table.querySelectorAll('.btn-delete-permission').forEach(button => {
            button.addEventListener('click', () => {
                deletePermission(
                    Number(button.dataset.id),
                    button.dataset.label || 'este acesso',
                    button.dataset.structure || ''
                );
            });
        });
    }

    function editTargetAccess(type, targetId) {
        setTargetType(type);
        chooseTarget(targetId, { scroll: true });

        const target = selectedTarget();
        if (target) {
            showAlert(
                `Editando os acessos de ${target.label}. Altere o escopo e clique em “Salvar acesso”.`,
                'ok'
            );
        }
    }

    async function deletePermission(permissionId, targetName, structureNameLabel) {
        const label = structureNameLabel
            ? `${targetName} → ${structureNameLabel}`
            : targetName;

        if (!confirm(
            `Excluir esta permissão?\n\n${label}\n\n` +
            'Se for a única permissão desse usuário/grupo, ele ficará sem acesso à Governança.'
        )) {
            return;
        }

        try {
            const payload = await apiPost('delete_permission', {
                id: permissionId
            });

            state.permissions = payload.permissions_all || state.permissions;
            renderPermissionTable();

            if (selectedTarget()) {
                loadSelectedPermissions();
            }

            showAlert('Permissão excluída com sucesso.', 'ok');
        } catch (error) {
            showAlert(error.message, 'err');
        }
    }

    function renderGlobalState() {
        els.enabled.checked = state.enabled;
        els.enabledText.textContent = state.enabled ? 'Ativo' : 'Desativado';
        els.enabledText.className = 'text-xs font-black uppercase tracking-wider ' +
            (state.enabled ? 'text-emerald-600' : 'text-slate-500');
    }

    async function refreshAccessData() {
        const payload = await apiGet('bootstrap');

        state.users = payload.users || [];
        state.groups = payload.groups || [];
        state.permissions = payload.permissions || [];
        state.enabled = Boolean(payload.permissions_enabled);

        renderGlobalState();
        syncTargetOptions();
        renderPermissionTable();
    }

    async function loadStructures() {
        const response = await fetch(API_DATA + '?t=' + Date.now(), {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.error || 'Não foi possível carregar a estrutura da Governança.');
        }

        normalizeStructureRows(payload.data?.estrutura || []);
        renderStructureSelectors();
    }

    async function saveAccess() {
        const target = selectedTarget();
        if (!target) {
            showAlert('Selecione um usuário ou grupo.', 'warn');
            return;
        }

        const permissions = collectPermissions();
        if (!permissions.length) {
            showAlert('Selecione pelo menos uma área ou use “Remover acessos”.', 'warn');
            return;
        }

        els.saveAccess.disabled = true;
        els.saveAccess.textContent = 'Salvando...';

        try {
            const payload = await apiPost('save', {
                alvo_tipo: state.targetType,
                alvo_id: target.id,
                nivel_acesso: els.accessLevel.value,
                permissions
            });

            state.permissions = payload.permissions_all || state.permissions;
            renderPermissionTable();
            loadSelectedPermissions();

            showAlert('Acesso salvo com sucesso.', 'ok');
        } catch (error) {
            showAlert(error.message, 'err');
        } finally {
            els.saveAccess.disabled = false;
            els.saveAccess.textContent = 'Salvar acesso';
        }
    }

    async function clearAccess() {
        const target = selectedTarget();
        if (!target) return;

        if (!confirm(`Remover todos os acessos de Governança de "${target.label}"?`)) {
            return;
        }

        els.clearAccess.disabled = true;

        try {
            const payload = await apiPost('save', {
                alvo_tipo: state.targetType,
                alvo_id: target.id,
                nivel_acesso: 'VISUALIZAR',
                permissions: []
            });

            state.permissions = payload.permissions_all || state.permissions;
            renderPermissionTable();
            loadSelectedPermissions();

            showAlert('Acessos removidos.', 'ok');
        } catch (error) {
            showAlert(error.message, 'err');
        } finally {
            els.clearAccess.disabled = false;
        }
    }

    async function changeGlobalState() {
        const next = els.enabled.checked;

        if (next) {
            const ok = confirm(
                'Ativar permissões por nó?\n\n' +
                'Usuários sem permissão cadastrada deixarão de acessar a Governança.'
            );

            if (!ok) {
                els.enabled.checked = state.enabled;
                return;
            }
        }

        try {
            const payload = await apiPost('set_enabled', { enabled: next });
            state.enabled = Boolean(payload.permissions_enabled);
            renderGlobalState();

            showAlert(
                state.enabled
                    ? 'Permissões por nó ativadas.'
                    : 'Permissões desativadas: usuários autenticados voltam a visualizar tudo.',
                state.enabled ? 'ok' : 'warn'
            );
        } catch (error) {
            els.enabled.checked = state.enabled;
            showAlert(error.message, 'err');
        }
    }

    document.querySelectorAll('input[name="scope-mode"]').forEach(radio => {
        radio.addEventListener('change', () => selectScopeMode(radio.value));
    });

    els.btnUser.addEventListener('click', () => setTargetType('USUARIO'));
    els.btnGroup.addEventListener('click', () => setTargetType('GRUPO'));

    els.search.addEventListener('focus', () => {
        renderTargetResults(els.search.value.trim() !== '');
    });

    els.search.addEventListener('input', () => {
        // Ao digitar novamente, a seleção anterior deixa de valer
        // até que o usuário clique em um novo resultado.
        els.target.value = '';
        updateTargetInfo();
        loadSelectedPermissions();
        renderTargetResults(true);
    });

    els.searchClear.addEventListener('click', clearTargetSearch);

    document.addEventListener('click', (event) => {
        if (!els.search.closest('.relative')?.contains(event.target)) {
            els.results.classList.add('hidden');
        }
    });

    els.clearCustom.addEventListener('click', clearCustomTree);
    els.saveAccess.addEventListener('click', saveAccess);
    els.clearAccess.addEventListener('click', clearAccess);
    els.enabled.addEventListener('change', changeGlobalState);

    (async () => {
        try {
            setTargetType('USUARIO');
            selectScopeMode('subtree');

            await Promise.all([
                loadStructures(),
                refreshAccessData()
            ]);

            // Agora que a estrutura existe, reaplica as permissões corretamente.
            loadSelectedPermissions();
        } catch (error) {
            console.error(error);
            showAlert(error.message || 'Falha ao carregar a gestão de acessos.', 'err');
        }
    })();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
