<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 min-w-0 min-h-0 overflow-hidden bg-slate-100 flex flex-col">
    <?php require __DIR__ . '/includes/governanca_nav.php'; ?>

    <section id="gov-org" class="flex-1 min-h-0 flex flex-col bg-slate-50">

        <!-- Barra superior do módulo -->
        <div class="gov-orgbar">
            <div class="gov-orgtitle">
                <p class="gov-kicker">Governança</p>
                <h1>Organograma</h1>
                <span>Comercial Souza — visão executiva hierárquica</span>
            </div>

            <div class="gov-orgcontrols">
                <div class="gov-segmented" aria-label="Nível de visualização">
                    <button type="button" data-level="areas">Somente Áreas</button>
                    <button type="button" data-level="leaders" class="active">Áreas + Líderes</button>
                    <button type="button" data-level="team">Equipe Completa</button>
                </div>

                <div class="gov-zoom">
                    <button type="button" id="govZoomOut" aria-label="Diminuir zoom">−</button>
                    <span id="govZoomValue">90%</span>
                    <button type="button" id="govZoomIn" aria-label="Aumentar zoom">+</button>
                    <button type="button" id="govFit">⌕ <span>Ajustar</span></button>
                </div>

                <div id="govAccessBadge" class="gov-access">
                    <span></span>
                    <b>Carregando</b>
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div id="govCanvas" class="gov-canvas">
            <div id="govLoading" class="gov-loading">
                <div class="gov-spinner"></div>
                <strong>Carregando organograma...</strong>
                <span>Consultando a Governança no MariaDB.</span>
            </div>

            <div id="govScrollShell" class="gov-scroll-shell" hidden>
                <div id="govStage" class="gov-stage">
                    <svg id="govEdges" class="gov-edges" aria-hidden="true"></svg>
                    <div id="govNodes"></div>
                </div>
            </div>
        </div>

        <!-- Rodapé interno -->
        <div class="gov-footerbar">
            <div id="govLegend" class="gov-legend"></div>
            <div class="gov-footmeta">
                <span id="govSource">Fonte: MariaDB</span>
                <span>•</span>
                <span id="govNodeCount">0 nós</span>
                <span>•</span>
                <span id="govEdgeCount">0 conexões</span>
            </div>
        </div>

        <!-- Drawer de detalhes -->
        <div id="govDrawerBackdrop" class="gov-drawer-backdrop"></div>

        <aside id="govDrawer" class="gov-drawer" aria-hidden="true">
            <div class="gov-drawer-head">
                <div>
                    <p id="govDrawerType">Estrutura</p>
                    <h2 id="govDrawerTitle">Detalhes</h2>
                </div>
                <button type="button" id="govDrawerClose" aria-label="Fechar">×</button>
            </div>

            <div id="govDrawerBody" class="gov-drawer-body"></div>
        </aside>

    </section>
</main>

<style>
#gov-org{
    --gov-navy:#0d1b3e;
    --gov-navy-2:#173260;
    --gov-blue:#2563eb;
    --gov-blue-soft:#eff6ff;
    --gov-border:#dbe4ee;
    --gov-text:#0f172a;
    --gov-muted:#64748b;
    --gov-canvas:#f4f7fb;
    --gov-green:#10b981;
    --gov-purple:#8b5cf6;
    --gov-amber:#f59e0b;
    --gov-cyan:#0ea5e9;
    color:var(--gov-text);
}

#gov-org *{box-sizing:border-box}

#gov-org .gov-orgbar{
    flex:0 0 auto;
    min-height:72px;
    padding:11px 18px 11px 20px;
    display:flex;
    align-items:center;
    gap:18px;
    border-bottom:1px solid var(--gov-border);
    background:#fff;
    box-shadow:0 1px 2px rgba(15,23,42,.03);
    z-index:10;
}

#gov-org .gov-orgtitle{
    min-width:220px;
    margin-right:auto;
}

#gov-org .gov-kicker{
    margin:0 0 2px;
    font-size:9px;
    line-height:1;
    font-weight:900;
    letter-spacing:.18em;
    text-transform:uppercase;
    color:var(--gov-blue);
}

#gov-org .gov-orgtitle h1{
    margin:0;
    font-size:17px;
    line-height:1.15;
    font-weight:800;
    color:#0f172a;
}

#gov-org .gov-orgtitle span{
    display:block;
    margin-top:3px;
    font-size:11px;
    color:#94a3b8;
}

#gov-org .gov-orgcontrols{
    display:flex;
    align-items:center;
    gap:10px;
    min-width:0;
}

#gov-org .gov-segmented{
    display:flex;
    align-items:center;
    gap:2px;
    padding:4px;
    border-radius:11px;
    background:#f1f5f9;
}

#gov-org button{
    font:inherit;
}

#gov-org .gov-segmented button{
    border:0;
    border-radius:8px;
    padding:7px 11px;
    background:transparent;
    color:#64748b;
    font-size:11px;
    font-weight:700;
    white-space:nowrap;
    cursor:pointer;
    transition:.16s ease;
}

#gov-org .gov-segmented button:hover{
    color:#1e293b;
}

#gov-org .gov-segmented button.active{
    background:#fff;
    color:#0f172a;
    box-shadow:0 1px 5px rgba(15,23,42,.12);
}

#gov-org .gov-zoom{
    display:flex;
    align-items:center;
    gap:4px;
}

#gov-org .gov-zoom button{
    height:30px;
    min-width:30px;
    border:1px solid #e2e8f0;
    border-radius:8px;
    background:#fff;
    color:#475569;
    font-size:13px;
    font-weight:800;
    cursor:pointer;
}

#gov-org .gov-zoom button:hover{
    background:#f8fafc;
    border-color:#cbd5e1;
}

#gov-org .gov-zoom #govFit{
    padding:0 9px;
    display:flex;
    align-items:center;
    gap:5px;
    font-size:10px;
}

#gov-org #govZoomValue{
    width:40px;
    text-align:center;
    font-size:10px;
    color:#64748b;
    font-variant-numeric:tabular-nums;
}

#gov-org .gov-access{
    min-width:105px;
    height:31px;
    padding:0 10px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    border:1px solid #dbe4ee;
    border-radius:10px;
    background:#fff;
    color:#64748b;
    box-shadow:0 1px 3px rgba(15,23,42,.04);
}

#gov-org .gov-access span{
    width:7px;
    height:7px;
    border-radius:50%;
    background:#10b981;
    box-shadow:0 0 0 3px rgba(16,185,129,.1);
}

#gov-org .gov-access b{
    font-size:9px;
    font-weight:900;
    letter-spacing:.05em;
    text-transform:uppercase;
    white-space:nowrap;
}

/* Canvas */
#gov-org .gov-canvas{
    position:relative;
    flex:1 1 auto;
    min-height:0;
    overflow:auto;
    background-color:var(--gov-canvas);
    background-image:radial-gradient(#cbd5e1 1px, transparent 1px);
    background-size:26px 26px;
    overscroll-behavior:contain;
    scrollbar-gutter:stable;
}

#gov-org .gov-scroll-shell{
    position:relative;
    min-width:100%;
    min-height:100%;
}

#gov-org .gov-stage{
    position:absolute;
    transform-origin:center center;
}

#gov-org .gov-edges{
    position:absolute;
    inset:0;
    overflow:visible;
    pointer-events:none;
}

#gov-org .gov-loading{
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    gap:8px;
    color:#64748b;
    z-index:2;
}


#gov-org .gov-loading[hidden]{
    display:none !important;
}

#gov-org .gov-loading strong{
    color:#334155;
    font-size:13px;
}

#gov-org .gov-loading span{
    font-size:11px;
}

#gov-org .gov-spinner{
    width:26px;
    height:26px;
    border:3px solid #dbeafe;
    border-top-color:#2563eb;
    border-radius:50%;
    animation:gov-spin .75s linear infinite;
}
@keyframes gov-spin{to{transform:rotate(360deg)}}

/* Cards do organograma */
#gov-org .org-node{
    position:absolute;
    cursor:pointer;
    user-select:none;
    transition:transform .15s ease, box-shadow .15s ease;
}

#gov-org .org-node:hover{
    transform:translateY(-2px);
}

#gov-org .org-card{
    width:100%;
    height:100%;
    overflow:hidden;
    background:#fff;
    background:linear-gradient(
        145deg,
        color-mix(in srgb,var(--node-color,#3b82f6) 10%,white),
        white 72%
    );
    border:1px solid #dbe4ee;
    border:1px solid color-mix(in srgb,var(--node-color,#3b82f6) 28%,#dbe4ee);
    border-radius:14px;
    box-shadow:
        0 5px 18px rgba(15,23,42,.09),
        inset 0 1px 0 rgba(255,255,255,.82);
    transition:border-color .16s ease,box-shadow .16s ease,background .16s ease;
}

#gov-org .org-node:hover .org-card{
    border-color:color-mix(in srgb,var(--node-color,#3b82f6) 55%,#cbd5e1);
    box-shadow:0 9px 24px rgba(15,23,42,.13),inset 0 1px 0 rgba(255,255,255,.9);
}

#gov-org .org-node.company .org-card{
    border:none;
    border-radius:16px;
    background:linear-gradient(135deg,var(--gov-navy),var(--gov-navy-2));
    box-shadow:0 10px 34px rgba(13,27,62,.30),0 2px 8px rgba(15,23,42,.13);
    color:#fff;
}

#gov-org .org-node.company:hover .org-card{
    box-shadow:0 13px 38px rgba(13,27,62,.34),0 3px 10px rgba(15,23,42,.15);
}

#gov-org .org-node.depth-1 .org-card{
    border:0;
    background:#4338ca;
    background:linear-gradient(
        135deg,
        color-mix(in srgb,var(--node-color,#4f46e5) 82%,#111827),
        var(--node-color,#4f46e5)
    );
    box-shadow:0 9px 26px color-mix(in srgb,var(--node-color,#4f46e5) 28%,transparent);
}

#gov-org .org-node.depth-1 .org-stripe{
    background:rgba(255,255,255,.34);
}

#gov-org .org-node.depth-1 .org-area-title,
#gov-org .org-node.depth-1 .org-leader-name{
    color:#fff;
}

#gov-org .org-node.depth-1 .org-leader-caption{
    color:rgba(255,255,255,.64);
}

#gov-org .org-node.depth-1 .org-avatar{
    background:rgba(255,255,255,.18);
    color:#fff;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.2);
}

#gov-org .org-node.depth-2 .org-card{
    border-width:1.5px;
    border-color:#c4b5fd;
    background:#f5f3ff;
    background:linear-gradient(
        145deg,
        color-mix(in srgb,var(--node-color,#7c3aed) 20%,white),
        color-mix(in srgb,var(--node-color,#7c3aed) 7%,white)
    );
    box-shadow:0 7px 22px color-mix(in srgb,var(--node-color,#7c3aed) 18%,transparent);
}

#gov-org .org-company-body{
    height:100%;
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px 17px;
}

#gov-org .org-company-icon{
    width:42px;
    height:42px;
    border-radius:11px;
    display:grid;
    place-items:center;
    flex:0 0 auto;
    background:rgba(59,130,246,.24);
    color:#bfdbfe;
    font-size:18px;
}

#gov-org .org-company-title{
    font-size:13px;
    line-height:1.2;
    font-weight:800;
}

#gov-org .org-company-sub{
    margin-top:3px;
    color:#93c5fd;
    font-size:10px;
}

#gov-org .org-stripe{
    height:5px;
    background:var(--node-color,#3b82f6);
}

#gov-org .org-area-body{
    height:calc(100% - 5px);
    padding:10px 13px 11px;
    display:flex;
    flex-direction:column;
}

#gov-org .org-area-title{
    display:flex;
    align-items:center;
    gap:7px;
    min-width:0;
    font-size:12px;
    line-height:1.25;
    font-weight:900;
    color:#0f172a;
}

#gov-org .org-area-title .dot{
    width:9px;
    height:9px;
    border-radius:50%;
    flex:0 0 auto;
    background:var(--node-color,#3b82f6);
}

#gov-org .org-area-title span:last-child{
    display:-webkit-box;
    white-space:normal;
    overflow:hidden;
    -webkit-box-orient:vertical;
    -webkit-line-clamp:2;
    line-clamp:2;
}

#gov-org .org-leader{
    margin-top:auto;
    padding-top:8px;
    display:flex;
    align-items:center;
    gap:7px;
    color:#475569;
    font-size:10px;
}

#gov-org .org-leader-meta{min-width:0;display:flex;flex-direction:column;gap:1px}

#gov-org .org-leader-caption{
    color:#94a3b8;
    font-size:7px;
    line-height:1;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
}

#gov-org .org-leader-name{
    display:-webkit-box;
    color:#475569;
    font-size:9px;
    line-height:1.25;
    font-weight:750;
    overflow:hidden;
    -webkit-box-orient:vertical;
    -webkit-line-clamp:2;
    line-clamp:2;
}

#gov-org .org-avatar{
    width:23px;
    height:23px;
    border-radius:50%;
    display:grid;
    place-items:center;
    flex:0 0 auto;
    background:color-mix(in srgb,var(--node-color,#3b82f6) 13%,white);
    color:var(--node-color,#3b82f6);
    font-size:8px;
    font-weight:900;
}

#gov-org .org-node.person .org-card{
    border-radius:10px;
}

#gov-org .org-person-body{
    height:100%;
    display:flex;
    align-items:center;
    gap:8px;
    padding:8px 9px;
}

#gov-org .org-person-body .org-avatar{
    width:28px;
    height:28px;
    font-size:9px;
}

#gov-org .org-person-info{
    min-width:0;
}

#gov-org .org-person-name{
    color:#1e293b;
    font-size:10px;
    line-height:1.15;
    font-weight:800;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

#gov-org .org-person-role{
    margin-top:3px;
    color:#94a3b8;
    font-size:8px;
    line-height:1.15;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

/* Rodapé */
#gov-org .gov-footerbar{
    flex:0 0 auto;
    min-height:34px;
    padding:5px 14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    border-top:1px solid var(--gov-border);
    background:#fff;
    color:#94a3b8;
    font-size:9px;
}

#gov-org .gov-legend{
    display:flex;
    align-items:center;
    gap:12px;
    min-width:0;
    overflow:hidden;
}

#gov-org .gov-legend-item{
    display:flex;
    align-items:center;
    gap:5px;
    white-space:nowrap;
}

#gov-org .gov-legend-dot{
    width:7px;
    height:7px;
    border-radius:50%;
}

#gov-org .gov-footmeta{
    display:flex;
    align-items:center;
    gap:6px;
    white-space:nowrap;
}

/* Drawer */
#gov-org .gov-drawer-backdrop{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.18);
    opacity:0;
    pointer-events:none;
    transition:.2s ease;
    z-index:79;
}

#gov-org .gov-drawer-backdrop.show{
    opacity:1;
    pointer-events:auto;
}

#gov-org .gov-drawer{
    position:fixed;
    right:0;
    top:0;
    bottom:0;
    width:min(410px,92vw);
    display:flex;
    flex-direction:column;
    background:#fff;
    border-left:1px solid #dbe4ee;
    box-shadow:-14px 0 40px rgba(15,23,42,.16);
    transform:translateX(102%);
    transition:transform .23s ease;
    z-index:80;
}

#gov-org .gov-drawer.open{
    transform:translateX(0);
}

#gov-org .gov-drawer-head{
    min-height:74px;
    padding:18px 18px 14px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    border-bottom:1px solid #e2e8f0;
}

#gov-org .gov-drawer-head p{
    margin:0 0 3px;
    color:#2563eb;
    font-size:9px;
    font-weight:900;
    letter-spacing:.16em;
    text-transform:uppercase;
}

#gov-org .gov-drawer-head h2{
    margin:0;
    color:#0f172a;
    font-size:19px;
    line-height:1.2;
    font-weight:850;
}

#gov-org .gov-drawer-head button{
    width:32px;
    height:32px;
    border:1px solid #e2e8f0;
    border-radius:9px;
    background:#fff;
    color:#64748b;
    font-size:22px;
    line-height:1;
    cursor:pointer;
}

#gov-org .gov-drawer-body{
    flex:1 1 auto;
    overflow:auto;
    padding:17px;
    background:#f8fafc;
}

#gov-org .drawer-card{
    margin-bottom:12px;
    padding:14px;
    border:1px solid #e2e8f0;
    border-radius:13px;
    background:#fff;
}

#gov-org .drawer-label{
    margin-bottom:7px;
    color:#94a3b8;
    font-size:9px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
}

#gov-org .drawer-value{
    color:#1e293b;
    font-size:13px;
    font-weight:750;
}

#gov-org .drawer-stats{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:7px;
}

#gov-org .drawer-stat{
    padding:10px 7px;
    border-radius:10px;
    background:#f8fafc;
    text-align:center;
}

#gov-org .drawer-stat strong{
    display:block;
    color:#0f172a;
    font-size:17px;
}

#gov-org .drawer-stat span{
    display:block;
    margin-top:2px;
    color:#94a3b8;
    font-size:8px;
    font-weight:700;
    text-transform:uppercase;
}

#gov-org .drawer-list{
    display:flex;
    flex-direction:column;
    gap:7px;
}

#gov-org .drawer-row{
    padding:10px 11px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    border:1px solid #e2e8f0;
    border-radius:10px;
    background:#fff;
}

#gov-org .drawer-row strong{
    color:#334155;
    font-size:11px;
}

#gov-org .drawer-row small{
    display:block;
    margin-top:2px;
    color:#94a3b8;
    font-size:9px;
}

#gov-org .drawer-chip{
    flex:0 0 auto;
    padding:4px 6px;
    border-radius:999px;
    background:#eff6ff;
    color:#2563eb;
    font-size:8px;
    font-weight:900;
}

/* Scroll */
#gov-org .gov-canvas::-webkit-scrollbar,
#gov-org .gov-drawer-body::-webkit-scrollbar{
    width:8px;
    height:8px;
}

#gov-org .gov-canvas::-webkit-scrollbar-thumb,
#gov-org .gov-drawer-body::-webkit-scrollbar-thumb{
    background:#cbd5e1;
    border:2px solid transparent;
    background-clip:padding-box;
    border-radius:999px;
}

/* Notebook / compact */
@media (max-width: 1100px){
    #gov-org .gov-orgbar{
        padding:8px 10px;
        gap:8px;
    }
    #gov-org .gov-orgtitle{
        min-width:120px;
    }
    #gov-org .gov-orgtitle span{
        display:none;
    }
    #gov-org .gov-segmented button{
        padding:6px 8px;
        font-size:9px;
    }
    #gov-org .gov-zoom #govFit span{
        display:none;
    }
    #gov-org .gov-access{
        display:none;
    }
}

@media (max-width: 860px){
    #gov-org .gov-orgbar{
        min-height:58px;
        flex-wrap:wrap;
        align-content:center;
    }
    #gov-org .gov-orgtitle h1{
        font-size:14px;
    }
    #gov-org .gov-orgcontrols{
        gap:5px;
        margin-left:auto;
    }
    #gov-org .gov-segmented button{
        max-width:84px;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    #gov-org .gov-zoom{
        display:none;
    }
    #gov-org .gov-footerbar{
        min-height:30px;
        padding:4px 8px;
    }
    #gov-org .gov-footmeta #govSource,
    #gov-org .gov-footmeta span:nth-child(2){
        display:none;
    }
}

@media (max-height:650px){
    #gov-org .gov-orgbar{
        min-height:54px;
        padding-top:6px;
        padding-bottom:6px;
    }
    #gov-org .gov-orgtitle span{
        display:none;
    }
    #gov-org .gov-footerbar{
        min-height:28px;
    }
}
</style>

<script>
(() => {
    const API = 'api/governanca_dados.php';

    const CARD_W = {
        company: 228,
        area: 204,
        subarea: 190,
        person: 166
    };

    const CARD_H = {
        company: 80,
        area: 88,
        subarea: 92,
        person: 56
    };

    const H_GAP = 24;
    const V_GAP = 50;
    const PAD = 54;

    const COLORS = [
        '#2563eb',
        '#0f766e',
        '#d97706',
        '#0284c7',
        '#db2777',
        '#059669',
        '#7c3aed'
    ];

    const state = {
        payload: null,
        data: null,
        level: 'leaders',
        zoom: 0.90,
        nodes: [],
        edges: [],
        selected: null
    };

    const el = {
        canvas: document.getElementById('govCanvas'),
        shell: document.getElementById('govScrollShell'),
        stage: document.getElementById('govStage'),
        edges: document.getElementById('govEdges'),
        nodes: document.getElementById('govNodes'),
        loading: document.getElementById('govLoading'),
        zoomValue: document.getElementById('govZoomValue'),
        source: document.getElementById('govSource'),
        nodeCount: document.getElementById('govNodeCount'),
        edgeCount: document.getElementById('govEdgeCount'),
        legend: document.getElementById('govLegend'),
        access: document.getElementById('govAccessBadge'),
        drawer: document.getElementById('govDrawer'),
        drawerBackdrop: document.getElementById('govDrawerBackdrop'),
        drawerClose: document.getElementById('govDrawerClose'),
        drawerType: document.getElementById('govDrawerType'),
        drawerTitle: document.getElementById('govDrawerTitle'),
        drawerBody: document.getElementById('govDrawerBody')
    };

    function esc(value){
        return String(value ?? '')
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'",'&#039;');
    }

    function initials(name){
        const parts = String(name || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean);

        if (!parts.length) return '—';
        if (parts.length === 1) return parts[0].slice(0,2).toUpperCase();

        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function normalize(value){
        return String(value ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g,'')
            .toLowerCase()
            .replace(/[^a-z0-9]/g,'');
    }

    function structureMaps(){
        const rows = state.data?.estrutura || [];
        const byId = new Map();
        const children = new Map();

        rows.forEach(row => {
            const id = String(row.ID || '').trim();
            if (!id) return;

            byId.set(id,row);

            const parent = String(row.ID_PAI || '').trim();
            if (!children.has(parent)) children.set(parent,[]);
            children.get(parent).push(row);
        });

        for (const items of children.values()){
            items.sort((a,b) => {
                const oa = Number(a.ORDEM || 9999);
                const ob = Number(b.ORDEM || 9999);
                if (oa !== ob) return oa - ob;
                return String(a.NOME || '').localeCompare(
                    String(b.NOME || ''),
                    'pt-BR'
                );
            });
        }

        return {byId,children};
    }

    function leadershipMap(){
        const grouped = new Map();

        (state.data?.liderancas || []).forEach(item => {
            const code = String(item.estrutura_codigo || '').trim();
            if (!code) return;
            if (!grouped.has(code)) grouped.set(code,[]);

            grouped.get(code).push({
                name: item.pessoa_nome || 'Líder não definido',
                initials: initials(item.pessoa_nome || '')
            });
        });

        const map = new Map();
        grouped.forEach((items,code) => {
            map.set(code,{
                name:items.map(item => item.name).join(' · '),
                initials:items[0]?.initials || '—',
                count:items.length,
                names:items.map(item => item.name)
            });
        });

        return map;
    }

    function functionsByStructure(){
        const map = new Map();

        (state.data?.funcoes || []).forEach(fn => {
            const code = String(fn.estrutura_codigo || '').trim();
            if (!code) return;
            if (!map.has(code)) map.set(code,[]);
            map.get(code).push(fn);
        });

        return map;
    }

    function responsibilitiesForStructure(code){
        return (state.data?.responsabilidades || [])
            .filter(r => String(r.ID_ESTRUTURA || '') === code);
    }

    function peopleForStructure(code, functionsMap){
        const result = [];
        const seen = new Set();

        (functionsMap.get(code) || []).forEach(fn => {
            (fn.ocupantes || []).forEach(person => {
                const key = `${person.id}-${fn.id}`;
                if (seen.has(key)) return;
                seen.add(key);

                result.push({
                    id: String(person.id),
                    name: person.nome,
                    initials: initials(person.nome),
                    role: fn.nome,
                    functionId: fn.id
                });
            });
        });

        return result;
    }

    function detectCompanyRoot(byId,children){
        if (byId.has('CS')) return byId.get('CS');

        const roots = [...byId.values()].filter(
            row => !String(row.ID_PAI || '').trim()
        );

        return roots.find(
            row => normalize(row['TIPO_NÓ']) === 'empresa'
                && !normalize(row['VÍNCULO']).includes('terceiro')
        ) || roots[0] || null;
    }

    function topBranchColor(code,rootCode,byId,colorByTop){
        if (code === rootCode) return '#0d1b3e';

        let current = byId.get(code);
        let guard = 0;

        while (current && guard < 100){
            const parent = String(current.ID_PAI || '').trim();

            if (parent === rootCode){
                return colorByTop.get(current.ID) || COLORS[0];
            }

            if (!parent) break;
            current = byId.get(parent);
            guard++;
        }

        return COLORS[0];
    }

    function hierarchyColor(code,rootCode,colorRootCode,byId,colorByTop){
        if (code === rootCode) return '#0d1b3e';
        if (code === 'CS-DIR') return '#4f46e5';
        if (code === 'CS-GG') return '#7c3aed';

        let current = byId.get(code);
        let guard = 0;

        while (current && guard < 100){
            const parent = String(current.ID_PAI || '').trim();
            if (parent === colorRootCode){
                return colorByTop.get(String(current.ID)) || COLORS[0];
            }
            if (!parent) break;
            current = byId.get(parent);
            guard++;
        }

        return topBranchColor(code,rootCode,byId,colorByTop);
    }

    function buildOrgTree(){
        const {byId,children} = structureMaps();
        const leaders = leadershipMap();
        const fnMap = functionsByStructure();
        const root = detectCompanyRoot(byId,children);

        if (!root){
            throw new Error('Nenhuma estrutura raiz foi encontrada.');
        }

        const colorRootCode = byId.has('CS-GG')
            ? 'CS-GG'
            : String(root.ID);
        const colorChildren = children.get(colorRootCode) || [];
        const colorByTop = new Map();

        colorChildren.forEach((row,index) => {
            colorByTop.set(row.ID,COLORS[index % COLORS.length]);
        });

        function buildStructure(row,depth){
            const code = String(row.ID || '');
            const color = hierarchyColor(
                code,
                String(root.ID),
                colorRootCode,
                byId,
                colorByTop
            );

            const leader = leaders.get(code) || {
                name:'Líder não definido',
                initials:'—',
                count:0,
                names:[]
            };

            const childrenNodes = (children.get(code) || [])
                .map(child => buildStructure(child,depth + 1));

            if (state.level === 'team'){
                peopleForStructure(code,fnMap).forEach(person => {
                    childrenNodes.push({
                        id:`person-${code}-${person.id}-${person.functionId}`,
                        refCode:code,
                        personId:person.id,
                        functionId:person.functionId,
                        type:'person',
                        label:person.name,
                        sublabel:person.role,
                        initials:person.initials,
                        color,
                        depth:depth + 1,
                        children:[]
                    });
                });
            }

            return {
                id:`struct-${code}`,
                refCode:code,
                type: depth === 0
                    ? 'company'
                    : depth === 1
                        ? 'area'
                        : 'subarea',
                label:String(row.NOME || code),
                sublabel: depth === 0
                    ? 'Estrutura principal'
                    : state.level === 'areas'
                        ? ''
                        : leader.name,
                initials: depth === 0
                    ? initials(row.NOME || 'CS')
                    : leader.initials,
                leaderCount:depth === 0 ? 0 : leader.count,
                leaderNames:depth === 0 ? [] : leader.names,
                color,
                depth,
                children: state.level === 'areas'
                    ? (
                        depth === 0
                            ? childrenNodes.map(n => ({...n,children:[]}))
                            : []
                    )
                    : childrenNodes
            };
        }

        return {
            root:buildStructure(root,0),
            rootCode:String(root.ID),
            byId,
            children,
            leaders,
            fnMap,
            colorByTop,
            colorRootCode,
            externalRoots:[...byId.values()].filter(row => {
                const parent = String(row.ID_PAI || '').trim();
                return !parent && String(row.ID || '') !== String(root.ID);
            })
        };
    }

    function cardWidth(type){
        return CARD_W[type] || CARD_W.person;
    }

    function cardHeight(type){
        return CARD_H[type] || CARD_H.person;
    }

    function computeLayout(node){
        const cw = cardWidth(node.type);
        const ch = cardHeight(node.type);

        if (!node.children.length){
            return {
                ...node,
                cx:cw/2,
                w:cw,
                h:ch,
                layoutChildren:[]
            };
        }

        const childLayouts = node.children.map(computeLayout);
        const childrenW =
            childLayouts.reduce((sum,child) => sum + child.w,0)
            + H_GAP * Math.max(0,childLayouts.length - 1);

        const subtreeW = Math.max(cw,childrenW);
        const startX = (subtreeW - childrenW) / 2;
        let cursor = startX;

        const positioned = childLayouts.map(layout => {
            const leftOffset = cursor;
            cursor += layout.w + H_GAP;
            return {layout,leftOffset};
        });

        return {
            ...node,
            cx:subtreeW/2,
            w:subtreeW,
            h:ch,
            layoutChildren:positioned
        };
    }

    function flattenLayout(layout,absLeft=0,absTop=0,parentCX=null,parentBY=null){
        const absCX = absLeft + layout.cx;
        const absBY = absTop + layout.h;

        const nodes = [{
            ...layout,
            cx:absCX,
            top:absTop
        }];

        const edges = [];

        if (parentCX !== null && parentBY !== null){
            edges.push({
                x1:parentCX,
                y1:parentBY,
                x2:absCX,
                y2:absTop
            });
        }

        const childTop = absBY + V_GAP;

        layout.layoutChildren.forEach(({layout:child,leftOffset}) => {
            const result = flattenLayout(
                child,
                absLeft + leftOffset,
                childTop,
                absCX,
                absBY
            );

            nodes.push(...result.nodes);
            edges.push(...result.edges);
        });

        return {nodes,edges};
    }

    function chartHeight(layout){
        if (!layout.layoutChildren.length) return layout.h;

        return layout.h + V_GAP + Math.max(
            ...layout.layoutChildren.map(item => chartHeight(item.layout))
        );
    }

    function nodeCard(node){
        const w = cardWidth(node.type);
        const h = cardHeight(node.type);

        if (node.type === 'company'){
            return `
                <button type="button"
                        class="org-node company depth-0"
                        data-id="${esc(node.id)}"
                        title="${esc(node.label)}"
                        style="left:${node.cx - w/2}px;top:${node.top}px;width:${w}px;height:${h}px;--node-color:${esc(node.color)}">
                    <div class="org-card">
                        <div class="org-company-body">
                            <div class="org-company-icon">⌂</div>
                            <div>
                                <div class="org-company-title">${esc(node.label)}</div>
                                <div class="org-company-sub">${esc(node.sublabel || 'Estrutura principal')}</div>
                            </div>
                        </div>
                    </div>
                </button>
            `;
        }

        if (node.type === 'person'){
            return `
                <button type="button"
                        class="org-node person depth-${Number(node.depth || 0)}"
                        data-id="${esc(node.id)}"
                        title="${esc(node.label)} · ${esc(node.sublabel || '')}"
                        style="left:${node.cx - w/2}px;top:${node.top}px;width:${w}px;height:${h}px;--node-color:${esc(node.color)}">
                    <div class="org-card">
                        <div class="org-person-body">
                            <div class="org-avatar">${esc(node.initials)}</div>
                            <div class="org-person-info">
                                <div class="org-person-name">${esc(node.label)}</div>
                                <div class="org-person-role">${esc(node.sublabel || '')}</div>
                            </div>
                        </div>
                    </div>
                </button>
            `;
        }

        return `
            <button type="button"
                    class="org-node ${esc(node.type)} depth-${Number(node.depth || 0)}"
                    data-id="${esc(node.id)}"
                    title="${esc(node.label)}${node.sublabel ? ' · ' + esc(node.sublabel) : ''}"
                    style="left:${node.cx - w/2}px;top:${node.top}px;width:${w}px;height:${h}px;--node-color:${esc(node.color)}">
                <div class="org-card">
                    <div class="org-stripe"></div>
                    <div class="org-area-body">
                        <div class="org-area-title">
                            <span class="dot"></span>
                            <span>${esc(node.label)}</span>
                        </div>
                        ${
                            state.level !== 'areas'
                                ? `
                                <div class="org-leader">
                                    <div class="org-avatar">${esc(node.initials || '—')}</div>
                                    <div class="org-leader-meta">
                                        <span class="org-leader-caption">${
                                            Number(node.leaderCount || 0) > 1
                                                ? `${Number(node.leaderCount)} lideranças`
                                                : 'Liderança'
                                        }</span>
                                        <span class="org-leader-name">${esc(node.sublabel || 'Líder não definido')}</span>
                                    </div>
                                </div>
                                `
                                : ''
                        }
                    </div>
                </div>
            </button>
        `;
    }

    function renderEdges(width,height,edges){
        el.edges.setAttribute('width',String(width));
        el.edges.setAttribute('height',String(height));
        el.edges.setAttribute('viewBox',`0 0 ${width} ${height}`);

        el.edges.innerHTML = edges.map(edge => {
            const x1 = edge.x1 + PAD;
            const y1 = edge.y1 + PAD;
            const x2 = edge.x2 + PAD;
            const y2 = edge.y2 + PAD;
            const midY = (y1 + y2) / 2;

            return `
                <path
                    d="M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}"
                    fill="none"
                    stroke="#94a3b8"
                    stroke-width="1.35"
                    stroke-linecap="round"
                    opacity=".72"
                />
                <circle cx="${x1}" cy="${y1}" r="2.2" fill="#cbd5e1"/>
            `;
        }).join('');
    }

    function renderLegend(model){
        const items = [{
            name:'Empresa',
            color:'#0d1b3e'
        }];

        if (model.byId.has('CS-DIR')) {
            items.push({name:'Diretoria',color:'#4f46e5'});
        }

        if (model.byId.has('CS-GG')) {
            items.push({name:'Gerência Geral',color:'#7c3aed'});
        }

        const rootChildren = model.children.get(model.colorRootCode) || [];

        rootChildren.forEach((row,index) => {
            items.push({
                name:String(row.NOME || row.ID),
                color:COLORS[index % COLORS.length]
            });
        });

        el.legend.innerHTML = items.map(item => `
            <span class="gov-legend-item">
                <span class="gov-legend-dot" style="background:${esc(item.color)}"></span>
                <span>${esc(item.name)}</span>
            </span>
        `).join('');
    }

    function applyZoom(center=true){
        state.zoom = Math.max(.25,Math.min(1.45,state.zoom));
        el.zoomValue.textContent = Math.round(state.zoom * 100) + '%';

        const rawW = Number(el.stage.dataset.rawWidth || 0);
        const rawH = Number(el.stage.dataset.rawHeight || 0);

        const scaledW = Math.max(
            rawW * state.zoom,
            el.canvas.clientWidth
        );

        const scaledH = Math.max(
            rawH * state.zoom,
            el.canvas.clientHeight
        );

        el.shell.style.width = scaledW + 'px';
        el.shell.style.height = scaledH + 'px';

        el.stage.style.width = rawW + 'px';
        el.stage.style.height = rawH + 'px';
        el.stage.style.left = (scaledW / 2) + 'px';
        el.stage.style.top = (scaledH / 2) + 'px';
        el.stage.style.transform =
            `translate(-50%,-50%) scale(${state.zoom})`;

        if (center){
            requestAnimationFrame(() => {
                el.canvas.scrollLeft =
                    Math.max(0,(el.canvas.scrollWidth - el.canvas.clientWidth)/2);
                el.canvas.scrollTop =
                    Math.max(0,(el.canvas.scrollHeight - el.canvas.clientHeight)/2);
            });
        }
    }

    function fitChart(){
        const rawW = Number(el.stage.dataset.rawWidth || 0);
        const rawH = Number(el.stage.dataset.rawHeight || 0);

        if (!rawW || !rawH) return;

        const availableW = Math.max(240,el.canvas.clientWidth - 36);
        const availableH = Math.max(180,el.canvas.clientHeight - 36);

        const z = Math.min(
            availableW/rawW,
            availableH/rawH
        ) * .94;

        state.zoom = Math.max(.25,Math.min(1.15,z));
        applyZoom(true);
    }

    function renderChart(autoFit=false){
        const model = buildOrgTree();
        const layout = computeLayout(model.root);
        const flat = flattenLayout(layout);

        state.nodes = flat.nodes;
        state.edges = flat.edges;
        state.model = model;

        const rawW = layout.w + PAD*2;
        const rawH = chartHeight(layout) + PAD*2;

        el.stage.dataset.rawWidth = String(rawW);
        el.stage.dataset.rawHeight = String(rawH);

        el.nodes.innerHTML = flat.nodes.map(node => {
            const shifted = {
                ...node,
                cx:node.cx + PAD,
                top:node.top + PAD
            };
            return nodeCard(shifted);
        }).join('');

        renderEdges(rawW,rawH,flat.edges);
        renderLegend(model);

        el.nodeCount.textContent =
            `${flat.nodes.length} ${flat.nodes.length === 1 ? 'nó' : 'nós'}`;

        el.edgeCount.textContent =
            `${flat.edges.length} ${flat.edges.length === 1 ? 'conexão' : 'conexões'}`;

        el.nodes.querySelectorAll('.org-node').forEach(button => {
            button.addEventListener('click',() => {
                openNodeDetails(button.dataset.id);
            });
        });

        applyZoom(false);

        if (autoFit){
            setTimeout(fitChart,60);
        }
    }

    function findStructureNode(nodeId){
        const node = state.nodes.find(n => n.id === nodeId);
        return node || null;
    }

    function structureDetail(code){
        const structure = (state.data?.estrutura || [])
            .find(row => String(row.ID || '') === code);

        if (!structure) return null;

        const functions = (state.data?.funcoes || [])
            .filter(fn => String(fn.estrutura_codigo || '') === code);

        const peopleMap = new Map();

        functions.forEach(fn => {
            (fn.ocupantes || []).forEach(person => {
                peopleMap.set(person.id,{
                    ...person,
                    funcao:fn.nome
                });
            });
        });

        const responsibilities = responsibilitiesForStructure(code);
        const leaderships = (state.data?.liderancas || [])
            .filter(item => String(item.estrutura_codigo || '') === code);

        const childrenCount = (state.model?.children.get(code) || []).length;

        return {
            structure,
            functions,
            people:[...peopleMap.values()],
            responsibilities,
            leaderships,
            childrenCount
        };
    }

    function roleCounts(rows){
        const counts = {Principal:0,Apoio:0,Backup:0};

        rows.forEach(row => {
            const role = String(row.PAPEL || '').trim();
            if (role in counts) counts[role]++;
        });

        return counts;
    }

    function openNodeDetails(nodeId){
        const node = findStructureNode(nodeId);
        if (!node) return;

        if (node.type === 'person'){
            const fn = (state.data?.funcoes || [])
                .find(item => Number(item.id) === Number(node.functionId));

            el.drawerType.textContent = 'Pessoa';
            el.drawerTitle.textContent = node.label;

            el.drawerBody.innerHTML = `
                <div class="drawer-card">
                    <div class="drawer-label">Função / Cargo</div>
                    <div class="drawer-value">${esc(node.sublabel || 'Não informado')}</div>
                </div>

                <div class="drawer-card">
                    <div class="drawer-label">Área</div>
                    <div class="drawer-value">${esc(fn?.estrutura_nome || 'Não informada')}</div>
                </div>

                <div class="drawer-card">
                    <div class="drawer-label">Responsabilidades da função</div>
                    <div class="drawer-value">${Number(fn?.responsabilidades || 0)}</div>
                </div>
            `;

            openDrawer();
            return;
        }

        const code = String(node.refCode || '');
        const detail = structureDetail(code);
        if (!detail) return;

        const roles = roleCounts(detail.responsibilities);

        el.drawerType.textContent =
            node.type === 'company' ? 'Empresa' : 'Estrutura';

        el.drawerTitle.textContent =
            detail.structure.NOME || code;

        const leaderName = detail.leaderships.length
            ? detail.leaderships.map(item => item.pessoa_nome).join(' · ')
            : 'Líder ainda não definido';

        el.drawerBody.innerHTML = `
            <div class="drawer-card">
                <div class="drawer-label">Liderança</div>
                <div class="drawer-value">${esc(leaderName)}</div>
            </div>

            <div class="drawer-card">
                <div class="drawer-label">Resumo</div>
                <div class="drawer-stats">
                    <div class="drawer-stat">
                        <strong>${detail.functions.length}</strong>
                        <span>Funções</span>
                    </div>
                    <div class="drawer-stat">
                        <strong>${detail.people.length}</strong>
                        <span>Pessoas</span>
                    </div>
                    <div class="drawer-stat">
                        <strong>${detail.responsibilities.length}</strong>
                        <span>Resp.</span>
                    </div>
                </div>
            </div>

            ${
                detail.functions.length
                    ? `
                    <div class="drawer-card">
                        <div class="drawer-label">Funções / Cargos</div>
                        <div class="drawer-list">
                            ${detail.functions.map(fn => `
                                <div class="drawer-row">
                                    <div>
                                        <strong>${esc(fn.nome)}</strong>
                                        <small>${
                                            fn.ocupantes?.length
                                                ? esc(fn.ocupantes.map(p => p.nome).join(', '))
                                                : 'Sem ocupante'
                                        }</small>
                                    </div>
                                    <span class="drawer-chip">${Number(fn.responsabilidades || 0)} resp.</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    `
                    : ''
            }

            ${
                detail.responsibilities.length
                    ? `
                    <div class="drawer-card">
                        <div class="drawer-label">Papéis nesta estrutura</div>
                        <div class="drawer-list">
                            <div class="drawer-row"><strong>Principal</strong><span class="drawer-chip">${roles.Principal}</span></div>
                            <div class="drawer-row"><strong>Apoio</strong><span class="drawer-chip">${roles.Apoio}</span></div>
                            <div class="drawer-row"><strong>Backup</strong><span class="drawer-chip">${roles.Backup}</span></div>
                        </div>
                    </div>
                    `
                    : ''
            }
        `;

        openDrawer();
    }

    function openDrawer(){
        el.drawer.classList.add('open');
        el.drawerBackdrop.classList.add('show');
        el.drawer.setAttribute('aria-hidden','false');
    }

    function closeDrawer(){
        el.drawer.classList.remove('open');
        el.drawerBackdrop.classList.remove('show');
        el.drawer.setAttribute('aria-hidden','true');
    }

    function renderAccess(){
        const access = state.payload?.access || {};

        let label = 'Consulta';

        if (access.is_admin){
            label = 'Administrador';
        }else if (access.level === 'GERENCIAR'){
            label = 'Gerenciar';
        }else if (access.scope === 'FULL_GRANTED'){
            label = 'Acesso total';
        }

        el.access.querySelector('b').textContent = label;
    }

    async function load(){
        try{
            const response = await fetch(
                API + '?t=' + Date.now(),
                {
                    cache:'no-store',
                    credentials:'same-origin',
                    headers:{Accept:'application/json'}
                }
            );

            const payload = await response.json().catch(() => null);

            if (!response.ok || !payload?.ok){
                throw new Error(
                    payload?.error || ('HTTP ' + response.status)
                );
            }

            state.payload = payload;
            state.data = payload.data || {};

            renderAccess();

            el.source.textContent =
                'Fonte: ' + (payload.source?.label || 'MariaDB');

            el.loading.hidden = true;
            el.loading.style.display = 'none';
            el.shell.hidden = false;

            renderChart(true);

        }catch(error){
            console.error(error);

            el.loading.hidden = false;
            el.loading.style.display = 'flex';
            el.loading.innerHTML = `
                <strong style="color:#b91c1c">Falha ao carregar a Governança</strong>
                <span>${esc(error?.message || 'Erro desconhecido.')}</span>
            `;
        }
    }

    document.querySelectorAll('[data-level]').forEach(button => {
        button.addEventListener('click',() => {
            document.querySelectorAll('[data-level]')
                .forEach(item => item.classList.remove('active'));

            button.classList.add('active');
            state.level = button.dataset.level || 'leaders';
            renderChart(true);
        });
    });

    document.getElementById('govZoomOut')
        .addEventListener('click',() => {
            state.zoom -= .10;
            applyZoom(false);
        });

    document.getElementById('govZoomIn')
        .addEventListener('click',() => {
            state.zoom += .10;
            applyZoom(false);
        });

    document.getElementById('govFit')
        .addEventListener('click',fitChart);

    el.drawerClose.addEventListener('click',closeDrawer);
    el.drawerBackdrop.addEventListener('click',closeDrawer);

    window.addEventListener('keydown',event => {
        if (event.key === 'Escape') closeDrawer();
    });

    let resizeTimer = null;

    window.addEventListener('resize',() => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(fitChart,150);
    });

    load();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
