<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 min-w-0 min-h-0 overflow-hidden bg-slate-100 flex flex-col">
    <?php require __DIR__ . '/includes/governanca_nav.php'; ?>

    <section class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-5 lg:px-6 py-5">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-blue-600">Governança</p>
                    <h1 class="text-2xl font-black text-slate-900">Pessoas</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Ocupantes atuais das funções/cargos dentro do seu escopo de acesso.
                    </p>
                </div>

                <div class="w-full sm:w-auto sm:min-w-[320px]">
                    <input id="govPeopleSearch" type="search"
                           placeholder="Buscar pessoa, função ou área..."
                           class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/10">
                </div>
            </div>

            <div id="govPeopleStats" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5"></div>
            <div id="govPeopleGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <div class="col-span-full rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                    Carregando pessoas...
                </div>
            </div>
        </div>
    </section>
</main>

<style>
.gov-person-card{
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    padding:16px;
    box-shadow:0 2px 7px rgba(15,23,42,.04);
}
.gov-person-head{display:flex;align-items:center;gap:11px}
.gov-person-avatar{
    width:42px;height:42px;border-radius:50%;
    display:grid;place-items:center;flex:0 0 auto;
    background:#eff6ff;color:#2563eb;font-size:12px;font-weight:900
}
.gov-person-name{font-size:14px;font-weight:900;color:#0f172a}
.gov-person-role{margin-top:2px;font-size:11px;color:#64748b}
.gov-person-area{
    margin-top:13px;padding-top:12px;border-top:1px solid #f1f5f9;
    display:flex;align-items:center;justify-content:space-between;gap:8px;
    color:#64748b;font-size:10px
}
.gov-person-badge{
    padding:4px 7px;border-radius:999px;background:#f1f5f9;
    color:#475569;font-size:8px;font-weight:900;text-transform:uppercase
}
</style>

<script>
(() => {
    const grid = document.getElementById('govPeopleGrid');
    const stats = document.getElementById('govPeopleStats');
    const search = document.getElementById('govPeopleSearch');

    let rows = [];

    const esc = v => String(v ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;')
        .replaceAll('>','&gt;').replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');

    const initials = name => {
        const p = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!p.length) return '—';
        if (p.length === 1) return p[0].slice(0,2).toUpperCase();
        return (p[0][0] + p[p.length-1][0]).toUpperCase();
    };

    function buildRows(data){
        const result = [];
        (data.funcoes || []).forEach(fn => {
            (fn.ocupantes || []).forEach(person => {
                result.push({
                    id: person.id,
                    nome: person.nome,
                    funcao: fn.nome,
                    area: fn.estrutura_nome || fn.estrutura_codigo,
                    responsabilidades: Number(fn.responsabilidades || 0),
                    principal: Boolean(person.principal)
                });
            });
        });
        return result;
    }

    function renderStats(data){
        const uniquePeople = new Set(rows.map(r => r.id)).size;
        const totalFunctions = (data.funcoes || []).length;
        const totalStructures = (data.estrutura || []).length;
        const totalResponsibilities = (data.responsabilidades || []).length;

        const items = [
            [uniquePeople, 'Pessoas'],
            [totalFunctions, 'Funções'],
            [totalStructures, 'Estruturas visíveis'],
            [totalResponsibilities, 'Responsabilidades']
        ];

        stats.innerHTML = items.map(([n,label]) => `
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <strong class="text-2xl font-black text-slate-900">${n}</strong>
                <span class="block mt-1 text-[10px] font-black uppercase tracking-wider text-slate-400">${esc(label)}</span>
            </div>
        `).join('');
    }

    function render(){
        const term = search.value.trim().toLowerCase();
        const filtered = rows.filter(row => {
            const hay = `${row.nome} ${row.funcao} ${row.area}`.toLowerCase();
            return !term || hay.includes(term);
        });

        if (!filtered.length){
            grid.innerHTML = `
                <div class="col-span-full rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                    Nenhuma pessoa encontrada neste escopo.
                </div>
            `;
            return;
        }

        grid.innerHTML = filtered.map(row => `
            <article class="gov-person-card">
                <div class="gov-person-head">
                    <div class="gov-person-avatar">${esc(initials(row.nome))}</div>
                    <div class="min-w-0">
                        <div class="gov-person-name">${esc(row.nome)}</div>
                        <div class="gov-person-role">${esc(row.funcao)}</div>
                    </div>
                </div>
                <div class="gov-person-area">
                    <span>${esc(row.area)}</span>
                    <span class="gov-person-badge">${row.responsabilidades} resp.</span>
                </div>
            </article>
        `).join('');
    }

    async function load(){
        try{
            const response = await fetch('api/governanca_dados.php?t=' + Date.now(), {
                cache:'no-store',
                credentials:'same-origin',
                headers:{Accept:'application/json'}
            });
            const payload = await response.json();
            if (!response.ok || !payload?.ok) throw new Error(payload?.error || 'Falha ao carregar.');
            rows = buildRows(payload.data || {});
            renderStats(payload.data || {});
            render();
        }catch(err){
            grid.innerHTML = `
                <div class="col-span-full rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    ${esc(err.message || 'Falha ao carregar as pessoas.')}
                </div>
            `;
        }
    }

    search.addEventListener('input',render);
    load();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
