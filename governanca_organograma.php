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
                    <button type="button" data-level="leaders" class="active">Áreas + Gestores</button>
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
    --gov-blue:#60a5fa;
    --gov-blue-soft:#172554;
    --gov-border:#29364b;
    --gov-text:#e5edf8;
    --gov-muted:#94a3b8;
    --gov-canvas:#0b1220;
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
    background:#111827;
    box-shadow:0 1px 0 rgba(148,163,184,.08),0 8px 26px rgba(2,6,23,.18);
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
    color:#f8fafc;
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
    background:#1e293b;
    border:1px solid #334155;
}

#gov-org button{
    font:inherit;
}

#gov-org .gov-segmented button{
    border:0;
    border-radius:8px;
    padding:7px 11px;
    background:transparent;
    color:#a8b5c7;
    font-size:12px;
    font-weight:700;
    white-space:nowrap;
    cursor:pointer;
    transition:.16s ease;
}

#gov-org .gov-segmented button:hover{
    color:#f8fafc;
}

#gov-org .gov-segmented button.active{
    background:#334155;
    color:#fff;
    box-shadow:0 1px 6px rgba(2,6,23,.32),inset 0 0 0 1px rgba(148,163,184,.12);
}

#gov-org .gov-zoom{
    display:flex;
    align-items:center;
    gap:4px;
}

#gov-org .gov-zoom button{
    height:30px;
    min-width:30px;
    border:1px solid #334155;
    border-radius:8px;
    background:#182235;
    color:#dbeafe;
    font-size:13px;
    font-weight:800;
    cursor:pointer;
}

#gov-org .gov-zoom button:hover{
    background:#25334a;
    border-color:#4b6382;
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
    color:#a8b5c7;
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
    border:1px solid #334155;
    border-radius:10px;
    background:#182235;
    color:#cbd5e1;
    box-shadow:0 1px 4px rgba(2,6,23,.24);
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
    background-image:
        radial-gradient(rgba(148,163,184,.26) 1px, transparent 1px),
        linear-gradient(145deg,#0b1220 0%,#101a2d 58%,#0c1525 100%);
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
    color:#8fa0b8;
    z-index:2;
}


#gov-org .gov-loading[hidden]{
    display:none !important;
}

#gov-org .gov-loading strong{
    color:#e2e8f0;
    font-size:14px;
}

#gov-org .gov-loading span{
    font-size:12px;
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
    position:relative;
    width:100%;
    height:100%;
    overflow:hidden;
    background:#fff;
    background:linear-gradient(
        145deg,
        color-mix(in srgb,var(--node-color,#3b82f6) 11%,white),
        #fff 76%
    );
    border:1px solid #d8e2ef;
    border:1px solid color-mix(in srgb,var(--node-color,#3b82f6) 38%,#d8e2ef);
    border-radius:14px;
    box-shadow:
        0 9px 24px rgba(2,6,23,.26),
        inset 0 1px 0 rgba(255,255,255,.9);
    transition:border-color .16s ease,box-shadow .16s ease,background .16s ease;
}

#gov-org .org-node:hover .org-card{
    border-color:color-mix(in srgb,var(--node-color,#3b82f6) 68%,#94a3b8);
    box-shadow:0 14px 34px rgba(2,6,23,.38),inset 0 1px 0 rgba(255,255,255,.95);
}

#gov-org .org-node.company .org-card{
    border:none;
    border-radius:16px;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    box-shadow:0 12px 34px rgba(29,78,216,.32),0 3px 10px rgba(2,6,23,.2);
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
        color-mix(in srgb,var(--node-color,#4f46e5) 84%,white),
        color-mix(in srgb,var(--node-color,#4f46e5) 94%,#312e81)
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
    border-color:#a78bfa;
    background:#f5f3ff;
    background:linear-gradient(
        145deg,
        color-mix(in srgb,var(--node-color,#7c3aed) 18%,white),
        color-mix(in srgb,var(--node-color,#7c3aed) 7%,white)
    );
    box-shadow:0 9px 26px rgba(2,6,23,.24),0 0 22px color-mix(in srgb,var(--node-color,#7c3aed) 13%,transparent);
}

#gov-org .org-node.depth-2 .org-area-title{
    color:#3b1d72;
}

#gov-org .org-node.depth-2 .org-leader-name{
    color:#4c1d95;
}

#gov-org .org-node.depth-2 .org-leader-caption{
    color:#7c3aed;
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
    font-size:17px;
    line-height:1.2;
    font-weight:800;
}

#gov-org .org-company-sub{
    margin-top:3px;
    color:#93c5fd;
    font-size:12px;
}

#gov-org .org-stripe{
    height:5px;
    background:var(--node-color,#3b82f6);
}

#gov-org .org-area-body{
    height:calc(100% - 5px);
    padding:11px 13px 12px;
    display:flex;
    flex-direction:column;
}

#gov-org .org-area-title{
    display:flex;
    align-items:center;
    gap:7px;
    min-width:0;
    font-size:16px;
    line-height:1.25;
    font-weight:900;
    color:#172033;
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
    margin-top:9px;
    padding-top:0;
    display:flex;
    align-items:center;
    gap:7px;
    color:#475569;
    font-size:12px;
}

#gov-org .org-leader-meta{min-width:0;display:flex;flex-direction:column;gap:1px}

#gov-org .org-leader-caption{
    color:#64748b;
    font-size:9px;
    line-height:1;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
}

#gov-org .org-leader-name{
    display:block;
    color:#334155;
    font-size:13px;
    line-height:1.25;
    font-weight:800;
    overflow:hidden;
}

#gov-org .org-leader-person{display:block}

#gov-org .org-avatar{
    width:27px;
    height:27px;
    border-radius:50%;
    display:grid;
    place-items:center;
    flex:0 0 auto;
    background:color-mix(in srgb,var(--node-color,#3b82f6) 15%,white);
    color:color-mix(in srgb,var(--node-color,#3b82f6) 82%,#0f172a);
    font-size:10px;
    font-weight:900;
}

#gov-org .org-node.person .org-card{
    border-radius:10px;
}

#gov-org .org-node.manager .org-card{
    border-width:2px;
    background:linear-gradient(
        135deg,
        color-mix(in srgb,var(--node-color,#3b82f6) 17%,white),
        #fff 76%
    );
    box-shadow:0 11px 28px rgba(2,6,23,.3),0 0 20px color-mix(in srgb,var(--node-color,#3b82f6) 10%,transparent);
}

#gov-org .org-person-body{
    height:100%;
    display:flex;
    align-items:center;
    gap:10px;
    padding:9px 11px;
}

#gov-org .org-person-body .org-avatar{
    width:34px;
    height:34px;
    font-size:11px;
}

#gov-org .org-person-info{
    min-width:0;
}

#gov-org .org-person-name{
    color:#172033;
    font-size:15px;
    line-height:1.15;
    font-weight:800;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

#gov-org .org-person-role{
    margin-top:3px;
    color:#64748b;
    font-size:12px;
    line-height:1.15;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

#gov-org .org-node.external .org-card{
    border-color:#fbbf24;
    background:linear-gradient(145deg,#fffbeb,#fff 78%);
    box-shadow:0 9px 24px rgba(2,6,23,.25),0 0 0 1px rgba(251,191,36,.12);
}

#gov-org .org-node.external .org-stripe{
    background:#f59e0b;
}

#gov-org .org-node.external .org-area-title{
    color:#78350f;
}

#gov-org .org-external-badge{
    width:max-content;
    margin-top:10px;
    padding:4px 8px;
    border-radius:999px;
    background:#fef3c7;
    color:#92400e;
    font-size:10px;
    line-height:1;
    font-weight:900;
    letter-spacing:.05em;
    text-transform:uppercase;
}

/* Leitura rápida para painéis e TVs no modo Somente Áreas */
#gov-org[data-level="areas"] .org-area-body{
    justify-content:center;
    padding:10px 12px 13px;
}

#gov-org[data-level="areas"] .org-area-title{
    gap:8px;
    font-size:24px;
    line-height:1.08;
    letter-spacing:-.025em;
}

#gov-org[data-level="areas"] .org-area-title .dot{
    width:11px;
    height:11px;
}

#gov-org[data-level="areas"] .org-company-title{
    font-size:24px;
    letter-spacing:-.025em;
}

#gov-org[data-level="areas"] .org-company-sub{
    font-size:15px;
}

#gov-org[data-level="areas"] .org-external-badge{
    display:none;
}

#gov-org .org-expand-indicator{
    position:absolute;
    right:6px;
    bottom:6px;
    width:24px;
    height:24px;
    display:grid;
    place-items:center;
    border:1px solid color-mix(in srgb,var(--node-color,#3b82f6) 38%,#cbd5e1);
    border-radius:7px;
    background:color-mix(in srgb,var(--node-color,#3b82f6) 14%,white);
    color:color-mix(in srgb,var(--node-color,#3b82f6) 78%,#0f172a);
    font-size:18px;
    line-height:1;
    font-weight:900;
    box-shadow:0 2px 7px rgba(15,23,42,.12);
}

#gov-org[data-level="areas"] .org-expand-indicator{
    right:8px;
    bottom:8px;
    width:30px;
    height:30px;
    border-radius:9px;
    font-size:22px;
}

#gov-org:not([data-level="areas"]) .org-node.has-children .org-area-body,
#gov-org:not([data-level="areas"]) .org-node.has-children .org-person-body,
#gov-org:not([data-level="areas"]) .org-node.has-children .org-company-body{
    padding-right:38px;
}

#gov-org .org-node.company .org-expand-indicator,
#gov-org .org-node.depth-1 .org-expand-indicator{
    border-color:rgba(255,255,255,.24);
    background:rgba(255,255,255,.16);
    color:#fff;
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
    background:#111827;
    color:#a8b5c7;
    font-size:10px;
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
    width:8px;
    height:8px;
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
    background:rgba(2,6,23,.62);
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
    background:#111827;
    border-left:1px solid #334155;
    box-shadow:-14px 0 40px rgba(2,6,23,.48);
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
    border-bottom:1px solid #334155;
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
    color:#f8fafc;
    font-size:19px;
    line-height:1.2;
    font-weight:850;
}

#gov-org .gov-drawer-head button{
    width:32px;
    height:32px;
    border:1px solid #334155;
    border-radius:9px;
    background:#182235;
    color:#cbd5e1;
    font-size:22px;
    line-height:1;
    cursor:pointer;
}

#gov-org .gov-drawer-body{
    flex:1 1 auto;
    overflow:auto;
    padding:17px;
    background:#0b1220;
}

#gov-org .drawer-card{
    margin-bottom:12px;
    padding:14px;
    border:1px solid #334155;
    border-radius:13px;
    background:#172033;
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
    color:#e2e8f0;
    font-size:14px;
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
    background:#111827;
    text-align:center;
}

#gov-org .drawer-stat strong{
    display:block;
    color:#f8fafc;
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
    border:1px solid #334155;
    border-radius:10px;
    background:#172033;
}

#gov-org .drawer-row strong{
    color:#e2e8f0;
    font-size:12px;
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
    background:#172554;
    color:#93c5fd;
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
    background:#475569;
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
        subarea: 184,
        external: 184,
        manager: 220,
        person: 166
    };

    const CARD_H = {
        company: 88,
        area: 124,
        subarea: 124,
        external: 96,
        manager: 72,
        person: 64
    };

    const AREA_CARD_W = {
        company: 240,
        area: 210,
        subarea: 200,
        external: 180
    };

    const AREA_CARD_H = {
        company: 96,
        area: 110,
        subarea: 110,
        external: 100
    };

    const H_GAP = 20;
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
        selected: null,
        collapsedByLevel: {
            areas: new Set(),
            leaders: new Set(),
            team: new Set()
        },
        collapseReady: {
            areas: false,
            leaders: false,
            team: false
        }
    };

    const el = {
        root: document.getElementById('gov-org'),
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

    function isManagementLeadership(item){
        const role = normalize(item?.tipo || '');
        return role === 'gestor'
            || role === 'diretor'
            || role === 'responsavel'
            || role.includes('gerente');
    }

    function leadershipMap(){
        const grouped = new Map();

        (state.data?.liderancas || []).forEach(item => {
            if (!isManagementLeadership(item)) return;

            const code = String(item.estrutura_codigo || '').trim();
            if (!code) return;
            if (!grouped.has(code)) grouped.set(code,[]);

            grouped.get(code).push({
                name: item.pessoa_nome || 'Gestor não definido',
                initials: initials(item.pessoa_nome || ''),
                role: item.tipo || 'Gestor'
            });
        });

        const map = new Map();
        grouped.forEach((items,code) => {
            map.set(code,{
                name:items.map(item => item.name).join(' · '),
                initials:items[0]?.initials || '—',
                count:items.length,
                names:items.map(item => item.name),
                roles:items.map(item => item.role)
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
            const isManager = normalize(row['TIPO_NÓ']) === 'gestor';
            const isExternal = normalize(row['VÍNCULO']).includes('terceiro');
            const color = isExternal
                ? '#f59e0b'
                : hierarchyColor(
                    code,
                    String(root.ID),
                    colorRootCode,
                    byId,
                    colorByTop
                );

            const leader = leaders.get(code) || {
                name:'Gestor não definido',
                initials:'—',
                count:0,
                names:[],
                roles:[]
            };

            const childrenNodes = (children.get(code) || [])
                .flatMap(child => {
                    const childCode = String(child.ID || '');
                    const childIsManager =
                        normalize(child['TIPO_NÓ']) === 'gestor';

                    if (state.level === 'areas' && childIsManager) {
                        return (children.get(childCode) || [])
                            .map(grandchild => buildStructure(grandchild,depth + 1));
                    }

                    return [buildStructure(child,depth + 1)];
                });

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

            const hasChildren = childrenNodes.length > 0;
            const collapsedNodes = state.collapsedByLevel[state.level];

            if (
                !state.collapseReady[state.level]
                && depth >= 3
                && hasChildren
            ) {
                collapsedNodes.add(code);
            }

            const isCollapsed =
                hasChildren
                && collapsedNodes.has(code);

            return {
                id:`struct-${code}`,
                refCode:code,
                type: isManager
                    ? 'manager'
                    : isExternal
                        ? 'external'
                    : depth === 0
                        ? 'company'
                        : depth === 1
                            ? 'area'
                            : 'subarea',
                label:isManager && leader.names.length
                    ? leader.names[0]
                    : String(row.NOME || code),
                sublabel:isManager
                    ? (leader.roles[0] || 'Gerente Geral')
                    : isExternal
                        ? 'Empresa terceira'
                    : depth === 0
                        ? 'Estrutura principal'
                        : state.level === 'areas'
                            ? ''
                            : leader.name,
                initials: depth === 0
                    ? initials(row.NOME || 'CS')
                    : isExternal
                        ? initials(row.NOME || '')
                        : leader.initials,
                leaderCount:depth === 0 ? 0 : leader.count,
                leaderNames:depth === 0 ? [] : leader.names,
                color,
                depth,
                hasChildren,
                isCollapsed,
                childCount:childrenNodes.length,
                children:isCollapsed ? [] : childrenNodes
            };
        }

        const rootNode = buildStructure(root,0);

        if (!state.collapseReady[state.level]) {
            state.collapseReady[state.level] = true;
        }

        return {
            root:rootNode,
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
        if (state.level === 'areas' && AREA_CARD_W[type]) {
            return AREA_CARD_W[type];
        }

        return CARD_W[type] || CARD_W.person;
    }

    function cardHeight(nodeOrType){
        const node = typeof nodeOrType === 'string'
            ? {type:nodeOrType,leaderCount:0}
            : nodeOrType;
        const type = node?.type || 'person';
        const baseHeight = CARD_H[type] || CARD_H.person;

        if (state.level === 'areas' && AREA_CARD_H[type]) {
            return AREA_CARD_H[type];
        }

        if (state.level !== 'areas' && (type === 'area' || type === 'subarea')) {
            const leaderCount = Math.max(1,Number(node?.leaderCount || 0));
            return baseHeight + Math.max(0,leaderCount - 1) * 16;
        }

        return baseHeight;
    }

    function computeLayout(node){
        const cw = cardWidth(node.type);
        const ch = cardHeight(node);
        const horizontalGap = state.level === 'areas' ? 10 : H_GAP;

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
            + horizontalGap * Math.max(0,childLayouts.length - 1);

        const subtreeW = Math.max(cw,childrenW);
        const startX = (subtreeW - childrenW) / 2;
        let cursor = startX;

        const positioned = childLayouts.map(layout => {
            const leftOffset = cursor;
            cursor += layout.w + horizontalGap;
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

        const verticalGap = state.level === 'areas' ? 38 : V_GAP;
        const childTop = absBY + verticalGap;

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

        const verticalGap = state.level === 'areas' ? 38 : V_GAP;

        return layout.h + verticalGap + Math.max(
            ...layout.layoutChildren.map(item => chartHeight(item.layout))
        );
    }

    function nodeCard(node){
        const w = cardWidth(node.type);
        const h = cardHeight(node);
        const leaderNames = Array.isArray(node.leaderNames) && node.leaderNames.length
            ? node.leaderNames
            : ['Gestor não definido'];
        const childClass = node.hasChildren ? ' has-children' : '';
        const expandIndicator = node.hasChildren
            ? `<span class="org-expand-indicator" aria-hidden="true">${node.isCollapsed ? '+' : '−'}</span>`
            : '';

        if (node.type === 'company'){
            return `
                <button type="button"
                        class="org-node company depth-0${childClass}"
                        data-id="${esc(node.id)}"
                        title="${esc(node.label)}"
                        style="left:${node.cx - w/2}px;top:${node.top}px;width:${w}px;height:${h}px;--node-color:${esc(node.color)}">
                    <div class="org-card">
                        ${expandIndicator}
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

        if (node.type === 'person' || node.type === 'manager'){
            return `
                <button type="button"
                        class="org-node person ${esc(node.type)} depth-${Number(node.depth || 0)}${childClass}"
                        data-id="${esc(node.id)}"
                        title="${esc(node.label)} · ${esc(node.sublabel || '')}"
                        style="left:${node.cx - w/2}px;top:${node.top}px;width:${w}px;height:${h}px;--node-color:${esc(node.color)}">
                    <div class="org-card">
                        ${expandIndicator}
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

        if (node.type === 'external'){
            return `
                <button type="button"
                        class="org-node external depth-${Number(node.depth || 0)}${childClass}"
                        data-id="${esc(node.id)}"
                        title="${esc(node.label)} · Empresa terceira"
                        style="left:${node.cx - w/2}px;top:${node.top}px;width:${w}px;height:${h}px;--node-color:${esc(node.color)}">
                    <div class="org-card">
                        ${expandIndicator}
                        <div class="org-stripe"></div>
                        <div class="org-area-body">
                            <div class="org-area-title">
                                <span class="dot"></span>
                                <span>${esc(node.label)}</span>
                            </div>
                            <div class="org-external-badge">Empresa terceira</div>
                        </div>
                    </div>
                </button>
            `;
        }

        return `
            <button type="button"
                    class="org-node ${esc(node.type)} depth-${Number(node.depth || 0)}${childClass}"
                    data-id="${esc(node.id)}"
                    title="${esc(node.label)}${node.sublabel ? ' · ' + esc(node.sublabel) : ''}"
                    style="left:${node.cx - w/2}px;top:${node.top}px;width:${w}px;height:${h}px;--node-color:${esc(node.color)}">
                <div class="org-card">
                    ${expandIndicator}
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
                                                ? `${Number(node.leaderCount)} gestores`
                                                : 'Gestor'
                                        }</span>
                                        <span class="org-leader-name">${leaderNames
                                            .map(name => `<span class="org-leader-person">${esc(name)}</span>`)
                                            .join('')}</span>
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
                    stroke="#64748b"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    opacity=".9"
                />
                <circle cx="${x1}" cy="${y1}" r="2.6" fill="#94a3b8"/>
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

        const hasThirdParties = [...model.byId.values()].some(
            row => normalize(row['VÍNCULO']).includes('terceiro')
        );

        if (hasThirdParties) {
            items.push({name:'Empresa terceira',color:'#f59e0b'});
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
        el.root.dataset.level = state.level;

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
                const node = findStructureNode(button.dataset.id);

                if (node?.hasChildren) {
                    const code = String(node.refCode || '');
                    const collapsedNodes = state.collapsedByLevel[state.level];

                    if (collapsedNodes.has(code)) {
                        collapsedNodes.delete(code);
                    } else {
                        collapsedNodes.add(code);
                    }

                    renderChart(true);
                    return;
                }

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

        const managers = detail.leaderships.filter(isManagementLeadership);
        const operationalLeaders = detail.leaderships.filter(item => !isManagementLeadership(item));
        const managerName = managers.length
            ? managers.map(item => item.pessoa_nome).join(' · ')
            : 'Gestor ainda não definido';

        el.drawerBody.innerHTML = `
            <div class="drawer-card">
                <div class="drawer-label">Gestor da área</div>
                <div class="drawer-value">${esc(managerName)}</div>
            </div>

            ${operationalLeaders.length ? `
                <div class="drawer-card">
                    <div class="drawer-label">Lideranças operacionais</div>
                    <div class="drawer-value">${esc(operationalLeaders.map(item => item.pessoa_nome).join(' · '))}</div>
                </div>
            ` : ''}

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

            Object.values(state.collapsedByLevel)
                .forEach(collapsedNodes => collapsedNodes.clear());

            Object.keys(state.collapseReady)
                .forEach(level => {
                    state.collapseReady[level] = false;
                });

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
