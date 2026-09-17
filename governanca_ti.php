<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 min-w-0 min-h-0 overflow-hidden bg-slate-100 flex flex-col">
    <?php require __DIR__ . '/includes/governanca_nav.php'; ?>

    <section id="gov-structure" class="flex-1 min-h-0 flex flex-col bg-slate-50">
        <header class="gov-page-head">
            <div class="gov-page-title">
                <p>Governança</p>
                <h1>Estrutura</h1>
                <span>Áreas, funções, ocupantes e responsabilidades do seu escopo.</span>
            </div>

            <div class="gov-page-actions">
                <button type="button" id="govOpenTree" class="gov-tree-mobile-btn">
                    ☰ Estruturas
                </button>

                <label class="gov-search">
                    <span>⌕</span>
                    <input
                        id="govStructureSearch"
                        type="search"
                        placeholder="Buscar área, função, pessoa ou atividade..."
                        autocomplete="off"
                    >
                </label>

                <div id="govAccessBadge" class="gov-access-badge">
                    <span class="dot"></span>
                    <b>Carregando</b>
                </div>
            </div>
        </header>

        <div id="govLoadState" class="gov-load-state">
            <div class="gov-spinner"></div>
            <strong>Carregando estrutura...</strong>
            <span>Consultando a Governança no MariaDB.</span>
        </div>

        <div id="govStructureApp" class="gov-structure-app" hidden>
            <!-- Árvore -->
            <aside id="govTreePanel" class="gov-tree-panel">
                <div class="gov-tree-head">
                    <div>
                        <p>Estrutura</p>
                        <strong id="govTreeCount">0 nós</strong>
                    </div>
                    <button type="button" id="govCloseTree" class="gov-tree-close" aria-label="Fechar">×</button>
                </div>

                <div class="gov-tree-filter-wrap">
                    <input
                        id="govTreeFilter"
                        type="search"
                        placeholder="Filtrar estrutura..."
                        autocomplete="off"
                    >
                </div>

                <div id="govTree" class="gov-tree-scroll"></div>
            </aside>

            <div id="govTreeBackdrop" class="gov-tree-backdrop"></div>

            <!-- Conteúdo -->
            <section class="gov-content-panel">
                <div id="govEmptySelection" class="gov-empty">
                    <div class="gov-empty-icon">⌂</div>
                    <strong>Selecione uma estrutura</strong>
                    <span>Escolha um nó na árvore para visualizar funções, pessoas e responsabilidades.</span>
                </div>

                <div id="govSelectedContent" class="gov-selected" hidden>
                    <div class="gov-selected-head">
                        <div class="gov-selected-copy">
                            <div id="govBreadcrumb" class="gov-breadcrumb"></div>
                            <h2 id="govSelectedName">Estrutura</h2>
                            <p id="govSelectedDescription"></p>
                        </div>

                        <div id="govSelectedCode" class="gov-code-badge"></div>
                    </div>

                    <div class="gov-leader-card">
                        <div class="gov-leader-avatar" id="govLeaderAvatar">—</div>
                        <div class="gov-leader-copy">
                            <span>Liderança da área</span>
                            <strong id="govLeaderName">Líder não definido</strong>
                        </div>
                        <div id="govLeaderStatus" class="gov-leader-status">Não definido</div>
                    </div>

                    <div class="gov-stats">
                        <div class="gov-stat">
                            <strong id="govFunctionsCount">0</strong>
                            <span>Funções</span>
                        </div>
                        <div class="gov-stat">
                            <strong id="govPeopleCount">0</strong>
                            <span>Pessoas</span>
                        </div>
                        <div class="gov-stat">
                            <strong id="govResponsibilitiesCount">0</strong>
                            <span>Responsabilidades</span>
                        </div>
                        <div class="gov-stat">
                            <strong id="govChildrenCount">0</strong>
                            <span>Subestruturas</span>
                        </div>
                    </div>

                    <div id="govChildSection" class="gov-section" hidden>
                        <div class="gov-section-head">
                            <div>
                                <p>Organização</p>
                                <h3>Subestruturas</h3>
                            </div>
                        </div>
                        <div id="govChildrenGrid" class="gov-children-grid"></div>
                    </div>

                    <div class="gov-section">
                        <div class="gov-section-head">
                            <div>
                                <p>Funções / Cargos</p>
                                <h3 id="govFunctionsTitle">Funções deste ramo</h3>
                            </div>
                            <span id="govFunctionsScope" class="gov-muted-note"></span>
                        </div>

                        <div id="govFunctionsList" class="gov-functions-list"></div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Drawer de função -->
        <div id="govFunctionBackdrop" class="gov-drawer-backdrop"></div>
        <aside id="govFunctionDrawer" class="gov-drawer" aria-hidden="true">
            <div class="gov-drawer-head">
                <div>
                    <p>Função / Cargo</p>
                    <h2 id="govDrawerTitle">Detalhes</h2>
                </div>
                <button type="button" id="govDrawerClose" aria-label="Fechar">×</button>
            </div>
            <div id="govDrawerBody" class="gov-drawer-body"></div>
        </aside>
    </section>
</main>

<style>
#gov-structure{
    --navy:#0d1b3e;
    --blue:#2563eb;
    --blue-soft:#eff6ff;
    --green:#059669;
    --green-soft:#ecfdf5;
    --amber:#d97706;
    --amber-soft:#fffbeb;
    --purple:#7c3aed;
    --purple-soft:#f5f3ff;
    --slate-950:#0f172a;
    --slate-800:#1e293b;
    --slate-700:#334155;
    --slate-600:#475569;
    --slate-500:#64748b;
    --slate-400:#94a3b8;
    --slate-300:#cbd5e1;
    --slate-200:#e2e8f0;
    --slate-100:#f1f5f9;
    --slate-50:#f8fafc;
    color:var(--slate-950);
}
#gov-structure *{box-sizing:border-box}
#gov-structure button,
#gov-structure input{font:inherit}

/* Header */
#gov-structure .gov-page-head{
    flex:0 0 auto;
    min-height:72px;
    padding:12px 18px 12px 20px;
    display:flex;
    align-items:center;
    gap:18px;
    border-bottom:1px solid var(--slate-200);
    background:#fff;
}
#gov-structure .gov-page-title{margin-right:auto;min-width:210px}
#gov-structure .gov-page-title>p{
    margin:0 0 2px;
    color:var(--blue);
    font-size:9px;
    font-weight:900;
    letter-spacing:.18em;
    text-transform:uppercase;
}
#gov-structure .gov-page-title h1{
    margin:0;
    color:var(--slate-950);
    font-size:20px;
    line-height:1.1;
    font-weight:900;
}
#gov-structure .gov-page-title>span{
    display:block;
    margin-top:4px;
    color:var(--slate-400);
    font-size:10px;
}
#gov-structure .gov-page-actions{
    display:flex;
    align-items:center;
    gap:9px;
    min-width:0;
}
#gov-structure .gov-search{
    width:min(390px,34vw);
    height:36px;
    display:flex;
    align-items:center;
    gap:8px;
    padding:0 11px;
    border:1px solid var(--slate-200);
    border-radius:10px;
    background:#fff;
    color:var(--slate-400);
}
#gov-structure .gov-search:focus-within{
    border-color:#93c5fd;
    box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
#gov-structure .gov-search input{
    width:100%;
    min-width:0;
    border:0;
    outline:0;
    background:transparent;
    color:var(--slate-700);
    font-size:11px;
}
#gov-structure .gov-search input::placeholder{color:var(--slate-400)}
#gov-structure .gov-access-badge{
    height:34px;
    padding:0 11px;
    display:flex;
    align-items:center;
    gap:7px;
    border:1px solid var(--slate-200);
    border-radius:10px;
    background:#fff;
    color:var(--slate-500);
    box-shadow:0 1px 3px rgba(15,23,42,.04);
}
#gov-structure .gov-access-badge .dot{
    width:7px;height:7px;border-radius:50%;
    background:#10b981;
    box-shadow:0 0 0 3px rgba(16,185,129,.1);
}
#gov-structure .gov-access-badge b{
    font-size:9px;font-weight:900;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap
}
#gov-structure .gov-tree-mobile-btn{
    display:none;
    height:36px;
    padding:0 11px;
    border:1px solid #bfdbfe;
    border-radius:10px;
    background:#eff6ff;
    color:#1d4ed8;
    font-size:10px;
    font-weight:900;
}

/* Loading */
#gov-structure .gov-load-state{
    flex:1 1 auto;
    min-height:0;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:7px;
    color:var(--slate-500);
}
#gov-structure .gov-load-state[hidden]{display:none!important}
#gov-structure .gov-load-state strong{font-size:13px;color:var(--slate-700)}
#gov-structure .gov-load-state span{font-size:10px}
#gov-structure .gov-spinner{
    width:26px;height:26px;
    border:3px solid #dbeafe;
    border-top-color:var(--blue);
    border-radius:50%;
    animation:gov-structure-spin .75s linear infinite
}
@keyframes gov-structure-spin{to{transform:rotate(360deg)}}

/* Layout */
#gov-structure .gov-structure-app{
    flex:1 1 auto;
    min-height:0;
    display:grid;
    grid-template-columns:310px minmax(0,1fr);
    gap:0;
    overflow:hidden;
}
#gov-structure .gov-structure-app[hidden]{display:none!important}

/* Tree panel */
#gov-structure .gov-tree-panel{
    min-width:0;
    min-height:0;
    display:flex;
    flex-direction:column;
    border-right:1px solid var(--slate-200);
    background:#fff;
}
#gov-structure .gov-tree-head{
    flex:0 0 auto;
    min-height:58px;
    padding:12px 14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    border-bottom:1px solid var(--slate-100);
}
#gov-structure .gov-tree-head p{
    margin:0 0 3px;
    color:var(--slate-400);
    font-size:8px;
    font-weight:900;
    letter-spacing:.12em;
    text-transform:uppercase;
}
#gov-structure .gov-tree-head strong{
    color:var(--slate-700);
    font-size:12px;
}
#gov-structure .gov-tree-close{
    display:none;
    width:30px;height:30px;
    border:1px solid var(--slate-200);
    border-radius:8px;
    background:#fff;
    color:var(--slate-500);
    font-size:19px;
}
#gov-structure .gov-tree-filter-wrap{
    flex:0 0 auto;
    padding:10px 12px;
    border-bottom:1px solid var(--slate-100);
}
#gov-structure .gov-tree-filter-wrap input{
    width:100%;
    height:34px;
    padding:0 10px;
    border:1px solid var(--slate-200);
    border-radius:9px;
    outline:0;
    background:var(--slate-50);
    color:var(--slate-700);
    font-size:10px;
}
#gov-structure .gov-tree-filter-wrap input:focus{
    border-color:#93c5fd;
    box-shadow:0 0 0 3px rgba(37,99,235,.06)
}
#gov-structure .gov-tree-scroll{
    flex:1 1 auto;
    min-height:0;
    overflow:auto;
    padding:10px 9px 18px;
}
#gov-structure .gov-tree-scroll::-webkit-scrollbar,
#gov-structure .gov-content-panel::-webkit-scrollbar,
#gov-structure .gov-drawer-body::-webkit-scrollbar{
    width:8px;height:8px
}
#gov-structure .gov-tree-scroll::-webkit-scrollbar-thumb,
#gov-structure .gov-content-panel::-webkit-scrollbar-thumb,
#gov-structure .gov-drawer-body::-webkit-scrollbar-thumb{
    background:var(--slate-300);
    border:2px solid transparent;
    background-clip:padding-box;
    border-radius:999px
}
#gov-structure .tree-group{margin:0}
#gov-structure .tree-children{
    margin-left:16px;
    padding-left:8px;
    border-left:1px solid var(--slate-200);
}
#gov-structure .tree-row{
    width:100%;
    min-height:38px;
    margin:2px 0;
    padding:6px 8px;
    display:grid;
    grid-template-columns:22px minmax(0,1fr) auto;
    align-items:center;
    gap:4px;
    border:1px solid transparent;
    border-radius:9px;
    background:transparent;
    color:var(--slate-600);
    text-align:left;
    cursor:pointer;
    transition:.14s ease;
}
#gov-structure .tree-row:hover{
    background:var(--slate-50);
    color:var(--slate-950)
}
#gov-structure .tree-row.selected{
    background:var(--blue-soft);
    border-color:#bfdbfe;
    color:#1d4ed8;
}
#gov-structure .tree-toggle{
    width:22px;height:22px;
    display:grid;place-items:center;
    color:var(--slate-400);
    border:0;
    background:transparent;
    font-size:10px;
    cursor:pointer;
}
#gov-structure .tree-toggle.has-children:hover{color:var(--blue)}
#gov-structure .tree-label{min-width:0}
#gov-structure .tree-label strong{
    display:block;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:inherit;
    font-size:10px;
    font-weight:850;
}
#gov-structure .tree-label small{
    display:block;
    margin-top:2px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:var(--slate-400);
    font-size:8px;
}
#gov-structure .tree-count{
    min-width:23px;
    padding:3px 5px;
    border-radius:999px;
    background:var(--slate-100);
    color:var(--slate-500);
    font-size:8px;
    font-weight:900;
    text-align:center
}
#gov-structure .tree-row.selected .tree-count{
    background:#dbeafe;
    color:#2563eb;
}
#gov-structure .tree-no-results{
    padding:22px 10px;
    text-align:center;
    color:var(--slate-400);
    font-size:10px
}
#gov-structure .gov-tree-backdrop{
    display:none;
}

/* Main content */
#gov-structure .gov-content-panel{
    min-width:0;
    min-height:0;
    overflow:auto;
    background:var(--slate-50);
}
#gov-structure .gov-empty[hidden]{
    display:none !important;
}
#gov-structure .gov-empty{
    min-height:100%;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:7px;
    padding:30px;
    text-align:center;
    color:var(--slate-400);
}
#gov-structure .gov-empty-icon{
    width:46px;height:46px;
    display:grid;place-items:center;
    border-radius:14px;
    background:#eef2ff;
    color:#4f46e5;
    font-size:18px;
}
#gov-structure .gov-empty strong{color:var(--slate-700);font-size:14px}
#gov-structure .gov-empty span{max-width:360px;font-size:10px;line-height:1.5}
#gov-structure .gov-selected{
    width:min(1040px,100%);
    margin:0 auto;
    padding:18px 20px 30px;
}
#gov-structure .gov-selected[hidden]{display:none!important}
#gov-structure .gov-selected-head{
    display:flex;
    align-items:flex-start;
    gap:14px;
}
#gov-structure .gov-selected-copy{
    min-width:0;
    margin-right:auto
}
#gov-structure .gov-breadcrumb{
    margin-bottom:5px;
    color:var(--slate-400);
    font-size:9px;
    line-height:1.4;
}
#gov-structure .gov-breadcrumb button{
    border:0;
    padding:0;
    background:transparent;
    color:#3b82f6;
    font-size:9px;
    cursor:pointer
}
#gov-structure .gov-breadcrumb .sep{padding:0 5px;color:var(--slate-300)}
#gov-structure .gov-selected-head h2{
    margin:0;
    color:var(--slate-950);
    font-size:23px;
    line-height:1.15;
    font-weight:900;
}
#gov-structure .gov-selected-head p{
    max-width:680px;
    margin:5px 0 0;
    color:var(--slate-500);
    font-size:10px;
    line-height:1.5
}
#gov-structure .gov-code-badge{
    flex:0 0 auto;
    padding:6px 8px;
    border:1px solid var(--slate-200);
    border-radius:9px;
    background:#fff;
    color:var(--slate-400);
    font-size:8px;
    font-weight:900;
    letter-spacing:.05em
}

/* Leader + stats */
#gov-structure .gov-leader-card{
    margin-top:15px;
    padding:12px 14px;
    display:flex;
    align-items:center;
    gap:10px;
    border:1px solid var(--slate-200);
    border-radius:14px;
    background:#fff;
    box-shadow:0 2px 7px rgba(15,23,42,.03)
}
#gov-structure .gov-leader-avatar{
    width:38px;height:38px;
    display:grid;place-items:center;
    flex:0 0 auto;
    border-radius:50%;
    background:#eff6ff;
    color:#2563eb;
    font-size:10px;
    font-weight:900
}
#gov-structure .gov-leader-copy{min-width:0;margin-right:auto}
#gov-structure .gov-leader-copy span{
    display:block;
    color:var(--slate-400);
    font-size:8px;
    font-weight:900;
    letter-spacing:.07em;
    text-transform:uppercase
}
#gov-structure .gov-leader-copy strong{
    display:block;
    margin-top:3px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:var(--slate-800);
    font-size:12px
}
#gov-structure .gov-leader-status{
    flex:0 0 auto;
    padding:5px 7px;
    border-radius:999px;
    background:var(--slate-100);
    color:var(--slate-500);
    font-size:8px;
    font-weight:900
}
#gov-structure .gov-leader-status.defined{
    background:var(--green-soft);
    color:var(--green)
}
#gov-structure .gov-stats{
    margin-top:10px;
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:8px
}
#gov-structure .gov-stat{
    min-height:70px;
    padding:12px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    border:1px solid var(--slate-200);
    border-radius:13px;
    background:#fff
}
#gov-structure .gov-stat strong{
    color:var(--slate-950);
    font-size:19px;
    line-height:1;
    font-weight:900
}
#gov-structure .gov-stat span{
    margin-top:5px;
    color:var(--slate-400);
    font-size:8px;
    font-weight:900;
    letter-spacing:.05em;
    text-transform:uppercase
}

/* Sections */
#gov-structure .gov-section{margin-top:18px}
#gov-structure .gov-section[hidden]{display:none!important}
#gov-structure .gov-section-head{
    margin-bottom:9px;
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:12px
}
#gov-structure .gov-section-head p{
    margin:0 0 2px;
    color:var(--slate-400);
    font-size:8px;
    font-weight:900;
    letter-spacing:.10em;
    text-transform:uppercase
}
#gov-structure .gov-section-head h3{
    margin:0;
    color:var(--slate-800);
    font-size:13px;
    font-weight:900
}
#gov-structure .gov-muted-note{color:var(--slate-400);font-size:8px}
#gov-structure .gov-children-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:8px
}
#gov-structure .gov-child-card{
    min-height:66px;
    padding:11px 12px;
    display:flex;
    align-items:center;
    gap:9px;
    border:1px solid var(--slate-200);
    border-radius:12px;
    background:#fff;
    cursor:pointer;
    text-align:left;
    transition:.14s ease
}
#gov-structure .gov-child-card:hover{
    transform:translateY(-1px);
    border-color:#bfdbfe;
    box-shadow:0 4px 12px rgba(15,23,42,.05)
}
#gov-structure .gov-child-icon{
    width:30px;height:30px;
    display:grid;place-items:center;
    flex:0 0 auto;
    border-radius:9px;
    background:#eff6ff;
    color:#2563eb;
    font-size:10px;
    font-weight:900
}
#gov-structure .gov-child-copy{min-width:0;margin-right:auto}
#gov-structure .gov-child-copy strong{
    display:block;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:var(--slate-700);
    font-size:10px
}
#gov-structure .gov-child-copy small{
    display:block;
    margin-top:3px;
    color:var(--slate-400);
    font-size:8px
}
#gov-structure .gov-child-arrow{color:var(--slate-300);font-size:13px}

/* Functions */
#gov-structure .gov-functions-list{
    display:flex;
    flex-direction:column;
    gap:9px
}
#gov-structure .gov-function-card{
    border:1px solid var(--slate-200);
    border-radius:14px;
    background:#fff;
    overflow:hidden;
    box-shadow:0 2px 6px rgba(15,23,42,.025)
}
#gov-structure .gov-function-top{
    min-height:51px;
    padding:11px 13px;
    display:flex;
    align-items:center;
    gap:10px
}
#gov-structure .gov-function-copy{
    min-width:0;
    margin-right:auto
}
#gov-structure .gov-function-copy strong{
    display:block;
    color:var(--slate-800);
    font-size:11px;
    font-weight:900
}
#gov-structure .gov-function-copy small{
    display:block;
    margin-top:3px;
    color:var(--slate-400);
    font-size:8px
}
#gov-structure .gov-function-role-badges{
    display:flex;gap:4px;flex-wrap:wrap;margin-top:6px
}
#gov-structure .role-badge{
    padding:3px 5px;
    border-radius:6px;
    font-size:7px;
    font-weight:900
}
#gov-structure .role-main{background:var(--green-soft);color:var(--green)}
#gov-structure .role-support{background:#eff6ff;color:#2563eb}
#gov-structure .role-backup{background:var(--purple-soft);color:var(--purple)}
#gov-structure .gov-function-detail-btn{
    flex:0 0 auto;
    border:0;
    background:transparent;
    color:#2563eb;
    font-size:9px;
    font-weight:850;
    cursor:pointer
}
#gov-structure .gov-function-middle{
    padding:9px 13px;
    display:flex;
    align-items:center;
    gap:8px;
    border-top:1px solid var(--slate-100);
    border-bottom:1px solid var(--slate-100);
    background:#fcfdff
}
#gov-structure .gov-occupant-avatar{
    width:27px;height:27px;
    display:grid;place-items:center;
    flex:0 0 auto;
    border-radius:50%;
    background:#eff6ff;
    color:#2563eb;
    font-size:8px;
    font-weight:900
}
#gov-structure .gov-occupant-copy{min-width:0}
#gov-structure .gov-occupant-copy span{
    display:block;
    color:var(--slate-400);
    font-size:7px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.05em
}
#gov-structure .gov-occupant-copy strong{
    display:block;
    margin-top:2px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:var(--slate-700);
    font-size:9px
}
#gov-structure .gov-vacant{
    color:var(--amber)!important
}
#gov-structure .gov-processes{
    padding:9px 13px 11px;
    display:flex;
    align-items:center;
    gap:5px;
    flex-wrap:wrap
}
#gov-structure .gov-process-chip{
    max-width:180px;
    padding:4px 7px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    border-radius:999px;
    background:var(--slate-100);
    color:var(--slate-500);
    font-size:7px;
    font-weight:700
}
#gov-structure .gov-process-more{
    background:#eef2ff;
    color:#4f46e5;
    font-weight:900
}
#gov-structure .gov-no-functions{
    padding:26px 18px;
    border:1px dashed var(--slate-300);
    border-radius:14px;
    background:#fff;
    color:var(--slate-400);
    text-align:center;
    font-size:10px
}

/* Drawer */
#gov-structure .gov-drawer-backdrop{
    position:fixed;inset:0;
    opacity:0;pointer-events:none;
    background:rgba(15,23,42,.22);
    transition:.2s ease;
    z-index:79
}
#gov-structure .gov-drawer-backdrop.show{opacity:1;pointer-events:auto}
#gov-structure .gov-drawer{
    position:fixed;
    top:0;right:0;bottom:0;
    width:min(440px,94vw);
    display:flex;
    flex-direction:column;
    border-left:1px solid var(--slate-200);
    background:#fff;
    box-shadow:-15px 0 40px rgba(15,23,42,.18);
    transform:translateX(102%);
    transition:transform .22s ease;
    z-index:80
}
#gov-structure .gov-drawer.open{transform:translateX(0)}
#gov-structure .gov-drawer-head{
    min-height:76px;
    padding:18px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px solid var(--slate-200)
}
#gov-structure .gov-drawer-head p{
    margin:0 0 3px;
    color:var(--blue);
    font-size:8px;
    font-weight:900;
    letter-spacing:.13em;
    text-transform:uppercase
}
#gov-structure .gov-drawer-head h2{
    margin:0;
    color:var(--slate-950);
    font-size:18px;
    line-height:1.2;
    font-weight:900
}
#gov-structure .gov-drawer-head button{
    width:32px;height:32px;
    border:1px solid var(--slate-200);
    border-radius:9px;
    background:#fff;
    color:var(--slate-500);
    font-size:20px;
    cursor:pointer
}
#gov-structure .gov-drawer-body{
    flex:1 1 auto;
    min-height:0;
    overflow:auto;
    padding:16px;
    background:var(--slate-50)
}
#gov-structure .drawer-card{
    margin-bottom:10px;
    padding:13px;
    border:1px solid var(--slate-200);
    border-radius:13px;
    background:#fff
}
#gov-structure .drawer-label{
    margin-bottom:6px;
    color:var(--slate-400);
    font-size:8px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase
}
#gov-structure .drawer-value{
    color:var(--slate-700);
    font-size:11px;
    font-weight:850
}
#gov-structure .drawer-activity{
    padding:10px 0;
    border-top:1px solid var(--slate-100)
}
#gov-structure .drawer-activity:first-child{border-top:0;padding-top:0}
#gov-structure .drawer-activity strong{
    display:block;
    color:var(--slate-700);
    font-size:10px
}
#gov-structure .drawer-activity small{
    display:block;
    margin-top:3px;
    color:var(--slate-400);
    font-size:8px
}

/* Responsive */
@media(max-width:1100px){
    #gov-structure .gov-structure-app{grid-template-columns:270px minmax(0,1fr)}
    #gov-structure .gov-children-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    #gov-structure .gov-search{width:min(320px,30vw)}
}
@media(max-width:960px){
    #gov-structure .gov-page-head{
        min-height:64px;
        padding:9px 10px;
        gap:8px
    }
    #gov-structure .gov-page-title>span{display:none}
    #gov-structure .gov-page-title{min-width:120px}
    #gov-structure .gov-page-title h1{font-size:16px}
    #gov-structure .gov-tree-mobile-btn{display:inline-flex;align-items:center}
    #gov-structure .gov-access-badge{display:none}
    #gov-structure .gov-search{width:min(310px,45vw)}
    #gov-structure .gov-structure-app{display:block;position:relative}
    #gov-structure .gov-tree-panel{
        position:absolute;
        z-index:45;
        top:0;left:0;bottom:0;
        width:min(330px,88vw);
        border-right:1px solid var(--slate-200);
        box-shadow:12px 0 30px rgba(15,23,42,.16);
        transform:translateX(-103%);
        transition:transform .2s ease
    }
    #gov-structure .gov-tree-panel.open{transform:translateX(0)}
    #gov-structure .gov-tree-close{display:grid;place-items:center}
    #gov-structure .gov-tree-backdrop{
        position:absolute;
        z-index:44;
        inset:0;
        display:block;
        background:rgba(15,23,42,.18);
        opacity:0;
        pointer-events:none;
        transition:.2s ease
    }
    #gov-structure .gov-tree-backdrop.show{opacity:1;pointer-events:auto}
    #gov-structure .gov-content-panel{height:100%}
}
@media(max-width:680px){
    #gov-structure .gov-page-head{align-items:flex-start;flex-wrap:wrap}
    #gov-structure .gov-page-actions{width:100%}
    #gov-structure .gov-search{flex:1 1 auto;width:auto}
    #gov-structure .gov-selected{padding:14px 10px 24px}
    #gov-structure .gov-selected-head h2{font-size:19px}
    #gov-structure .gov-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    #gov-structure .gov-children-grid{grid-template-columns:1fr}
    #gov-structure .gov-code-badge{display:none}
}
@media(max-height:650px){
    #gov-structure .gov-page-head{min-height:56px;padding-top:7px;padding-bottom:7px}
    #gov-structure .gov-page-title>span{display:none}
}
</style>

<script>
(() => {
    const API = 'api/governanca_dados.php';

    const state = {
        payload:null,
        data:null,
        selectedCode:null,
        structureById:new Map(),
        childrenById:new Map(),
        parentById:new Map(),
        expanded:new Set(),
        globalSearch:'',
        treeFilter:''
    };

    const el = {
        load:document.getElementById('govLoadState'),
        app:document.getElementById('govStructureApp'),
        treePanel:document.getElementById('govTreePanel'),
        treeBackdrop:document.getElementById('govTreeBackdrop'),
        openTree:document.getElementById('govOpenTree'),
        closeTree:document.getElementById('govCloseTree'),
        tree:document.getElementById('govTree'),
        treeCount:document.getElementById('govTreeCount'),
        treeFilter:document.getElementById('govTreeFilter'),
        search:document.getElementById('govStructureSearch'),
        access:document.getElementById('govAccessBadge'),
        contentPanel:document.querySelector('#gov-structure .gov-content-panel'),
        empty:document.getElementById('govEmptySelection'),
        selected:document.getElementById('govSelectedContent'),
        breadcrumb:document.getElementById('govBreadcrumb'),
        selectedName:document.getElementById('govSelectedName'),
        selectedDescription:document.getElementById('govSelectedDescription'),
        selectedCode:document.getElementById('govSelectedCode'),
        leaderAvatar:document.getElementById('govLeaderAvatar'),
        leaderName:document.getElementById('govLeaderName'),
        leaderStatus:document.getElementById('govLeaderStatus'),
        functionsCount:document.getElementById('govFunctionsCount'),
        peopleCount:document.getElementById('govPeopleCount'),
        responsibilitiesCount:document.getElementById('govResponsibilitiesCount'),
        childrenCount:document.getElementById('govChildrenCount'),
        childSection:document.getElementById('govChildSection'),
        childrenGrid:document.getElementById('govChildrenGrid'),
        functionsTitle:document.getElementById('govFunctionsTitle'),
        functionsScope:document.getElementById('govFunctionsScope'),
        functionsList:document.getElementById('govFunctionsList'),
        drawer:document.getElementById('govFunctionDrawer'),
        drawerBackdrop:document.getElementById('govFunctionBackdrop'),
        drawerClose:document.getElementById('govDrawerClose'),
        drawerTitle:document.getElementById('govDrawerTitle'),
        drawerBody:document.getElementById('govDrawerBody')
    };

    const esc = value => String(value ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');

    const norm = value => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g,'')
        .toLowerCase();

    const initials = name => {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '—';
        if (parts.length === 1) return parts[0].slice(0,2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    };

    function buildMaps(){
        state.structureById.clear();
        state.childrenById.clear();
        state.parentById.clear();

        (state.data?.estrutura || []).forEach(row => {
            const id = String(row.ID || '').trim();
            if (!id) return;

            state.structureById.set(id,row);

            const parent = String(row.ID_PAI || '').trim();
            state.parentById.set(id,parent);

            if (!state.childrenById.has(parent)){
                state.childrenById.set(parent,[]);
            }
            state.childrenById.get(parent).push(row);
        });

        for (const items of state.childrenById.values()){
            items.sort((a,b) => {
                const oa = Number(a.ORDEM || 9999);
                const ob = Number(b.ORDEM || 9999);
                if (oa !== ob) return oa - ob;
                return String(a.NOME || '').localeCompare(String(b.NOME || ''),'pt-BR');
            });
        }
    }

    function descendants(code){
        const out = new Set([code]);

        const walk = id => {
            (state.childrenById.get(id) || []).forEach(child => {
                const cid = String(child.ID || '');
                if (!cid || out.has(cid)) return;
                out.add(cid);
                walk(cid);
            });
        };

        walk(code);
        return out;
    }

    function ancestors(code){
        const out = [];
        let current = code;
        let guard = 0;

        while (current && state.structureById.has(current) && guard < 100){
            const row = state.structureById.get(current);
            out.unshift(row);
            current = state.parentById.get(current) || '';
            guard++;
        }

        return out;
    }

    function leadership(code){
        return (state.data?.liderancas || []).find(
            item => String(item.estrutura_codigo || '') === code
        ) || null;
    }

    function functionsForBranch(code){
        const allowed = descendants(code);

        return (state.data?.funcoes || [])
            .filter(fn => allowed.has(String(fn.estrutura_codigo || '')))
            .sort((a,b) => {
                const sa = state.structureById.get(String(a.estrutura_codigo || ''))?.NOME || '';
                const sb = state.structureById.get(String(b.estrutura_codigo || ''))?.NOME || '';
                const cmp = String(sa).localeCompare(String(sb),'pt-BR');
                if (cmp !== 0) return cmp;
                return String(a.nome || '').localeCompare(String(b.nome || ''),'pt-BR');
            });
    }

    function responsibilitiesForFunction(fn){
        const fid = Number(fn.id || 0);
        const fname = String(fn.nome || '');

        return (state.data?.responsabilidades || []).filter(row => {
            const rowFid = Number(row._FUNCAO_ID || 0);
            if (fid && rowFid === fid) return true;

            return !rowFid
                && String(row['FUNÇÃO (AUTOMÁTICO)'] || '').trim() === fname;
        });
    }

    function responsibilityRoleCounts(rows){
        const c = {Principal:0,Apoio:0,Backup:0};
        rows.forEach(row => {
            const role = String(row.PAPEL || '').trim();
            if (Object.prototype.hasOwnProperty.call(c,role)) c[role]++;
        });
        return c;
    }

    function uniqueProcesses(rows){
        const values = [];
        const seen = new Set();

        rows.forEach(row => {
            const p = String(row.PROCESSO || row.MACROPROCESSO || row.ATIVIDADE || '').trim();
            if (!p || seen.has(p)) return;
            seen.add(p);
            values.push(p);
        });

        return values;
    }

    function directChildren(code){
        return state.childrenById.get(code) || [];
    }

    function branchResponsibilityCount(code){
        const allowed = descendants(code);
        return (state.data?.responsabilidades || [])
            .filter(r => allowed.has(String(r.ID_ESTRUTURA || ''))).length;
    }

    function branchPeopleCount(code){
        const ids = new Set();
        functionsForBranch(code).forEach(fn => {
            (fn.ocupantes || []).forEach(p => ids.add(String(p.id)));
        });
        return ids.size;
    }

    function treeRoots(){
        return [...state.structureById.values()]
            .filter(row => {
                const parent = String(row.ID_PAI || '').trim();
                return !parent || !state.structureById.has(parent);
            })
            .sort((a,b) => Number(a.ORDEM || 9999) - Number(b.ORDEM || 9999));
    }

    function matchesTreeFilter(row){
        if (!state.treeFilter) return true;

        const q = norm(state.treeFilter);
        const code = String(row.ID || '');
        const own = norm(`${row.NOME || ''} ${code}`);

        if (own.includes(q)) return true;

        return [...descendants(code)].some(id => {
            const d = state.structureById.get(id);
            if (!d) return false;
            return norm(`${d.NOME || ''} ${d.ID || ''}`).includes(q);
        });
    }

    function renderTree(){
        const roots = treeRoots();
        const visibleCount = state.structureById.size;
        el.treeCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'nó' : 'nós'}`;

        function renderNode(row,depth=0){
            if (!matchesTreeFilter(row)) return '';

            const code = String(row.ID || '');
            const children = directChildren(code).filter(matchesTreeFilter);
            const hasChildren = children.length > 0;
            const expanded = state.expanded.has(code) || Boolean(state.treeFilter);
            const selected = state.selectedCode === code;
            const resp = branchResponsibilityCount(code);

            return `
                <div class="tree-group">
                    <button type="button"
                            class="tree-row${selected ? ' selected' : ''}"
                            data-select="${esc(code)}">
                        <span class="tree-toggle ${hasChildren ? 'has-children' : ''}"
                              data-toggle="${esc(code)}"
                              title="${hasChildren ? (expanded ? 'Recolher' : 'Expandir') : ''}">
                            ${hasChildren ? (expanded ? '▾' : '▸') : '·'}
                        </span>
                        <span class="tree-label">
                            <strong>${esc(row.NOME || code)}</strong>
                            <small>${esc(code)}</small>
                        </span>
                        <span class="tree-count">${resp}</span>
                    </button>

                    ${hasChildren && expanded ? `
                        <div class="tree-children">
                            ${children.map(child => renderNode(child,depth+1)).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }

        const html = roots.map(root => renderNode(root)).join('');

        el.tree.innerHTML = html || `
            <div class="tree-no-results">Nenhuma estrutura encontrada.</div>
        `;

        el.tree.querySelectorAll('[data-toggle]').forEach(button => {
            button.addEventListener('click',event => {
                event.stopPropagation();

                const code = button.dataset.toggle;
                if (!directChildren(code).length) return;

                if (state.expanded.has(code)) state.expanded.delete(code);
                else state.expanded.add(code);

                renderTree();
            });
        });

        el.tree.querySelectorAll('[data-select]').forEach(button => {
            button.addEventListener('click',() => {
                selectStructure(button.dataset.select);
            });
        });
    }

    function renderBreadcrumb(code){
        const path = ancestors(code);

        el.breadcrumb.innerHTML = path.map((row,index) => {
            const last = index === path.length - 1;

            return `
                ${index ? '<span class="sep">›</span>' : ''}
                ${
                    last
                        ? `<span>${esc(row.NOME || row.ID)}</span>`
                        : `<button type="button" data-breadcrumb="${esc(row.ID)}">${esc(row.NOME || row.ID)}</button>`
                }
            `;
        }).join('');

        el.breadcrumb.querySelectorAll('[data-breadcrumb]').forEach(btn => {
            btn.addEventListener('click',() => selectStructure(btn.dataset.breadcrumb));
        });
    }

    function functionCard(fn){
        const rows = responsibilitiesForFunction(fn);
        const roles = responsibilityRoleCounts(rows);
        const processes = uniqueProcesses(rows);
        const occupants = fn.ocupantes || [];

        const occupantHtml = occupants.length
            ? occupants.map(p => `
                <div style="display:flex;align-items:center;gap:7px;margin-right:12px;">
                    <div class="gov-occupant-avatar">${esc(initials(p.nome))}</div>
                    <div class="gov-occupant-copy">
                        <span>Ocupante atual</span>
                        <strong>${esc(p.nome)}</strong>
                    </div>
                </div>
            `).join('')
            : `
                <div class="gov-occupant-avatar" style="background:#fffbeb;color:#d97706;">!</div>
                <div class="gov-occupant-copy">
                    <span>Ocupante atual</span>
                    <strong class="gov-vacant">Vaga / sem ocupante</strong>
                </div>
            `;

        return `
            <article class="gov-function-card">
                <div class="gov-function-top">
                    <div class="gov-function-copy">
                        <strong>${esc(fn.nome || 'Função')}</strong>
                        <small>${esc(fn.estrutura_nome || fn.estrutura_codigo || '')}</small>

                        <div class="gov-function-role-badges">
                            ${roles.Principal ? `<span class="role-badge role-main">${roles.Principal} Principal</span>` : ''}
                            ${roles.Apoio ? `<span class="role-badge role-support">${roles.Apoio} Apoio</span>` : ''}
                            ${roles.Backup ? `<span class="role-badge role-backup">${roles.Backup} Backup</span>` : ''}
                        </div>
                    </div>

                    <button type="button"
                            class="gov-function-detail-btn"
                            data-function="${Number(fn.id || 0)}">
                        Detalhes →
                    </button>
                </div>

                <div class="gov-function-middle">
                    ${occupantHtml}
                </div>

                <div class="gov-processes">
                    ${
                        processes.length
                            ? processes.slice(0,5).map(p => `<span class="gov-process-chip">${esc(p)}</span>`).join('')
                            : '<span class="gov-process-chip">Nenhuma responsabilidade cadastrada</span>'
                    }
                    ${
                        processes.length > 5
                            ? `<span class="gov-process-chip gov-process-more">+ ${processes.length - 5} processos</span>`
                            : ''
                    }
                </div>
            </article>
        `;
    }

    function selectStructure(code){
        const row = state.structureById.get(code);
        if (!row) return;

        state.selectedCode = code;

        // abre toda a trilha da estrutura selecionada
        ancestors(code).forEach(item => state.expanded.add(String(item.ID || '')));
        renderTree();

        const functions = functionsForBranch(code);
        const people = new Set();
        functions.forEach(fn => (fn.ocupantes || []).forEach(p => people.add(String(p.id))));

        const responsibilities = functions.reduce(
            (sum,fn) => sum + responsibilitiesForFunction(fn).length,
            0
        );

        const children = directChildren(code);
        const leader = leadership(code);

        renderBreadcrumb(code);

        el.selectedName.textContent = row.NOME || code;
        el.selectedDescription.textContent =
            row['DESCRIÇÃO / OBSERVAÇÕES']
            || row['RESPONSÁVEL / EMPRESA']
            || 'Estrutura organizacional da Governança.';

        el.selectedCode.textContent = code;

        el.leaderName.textContent = leader?.pessoa_nome || 'Líder não definido';
        el.leaderAvatar.textContent = leader ? initials(leader.pessoa_nome) : '—';
        el.leaderStatus.textContent = leader ? 'Definido' : 'Não definido';
        el.leaderStatus.classList.toggle('defined',Boolean(leader));

        el.functionsCount.textContent = functions.length;
        el.peopleCount.textContent = people.size;
        el.responsibilitiesCount.textContent = responsibilities;
        el.childrenCount.textContent = children.length;

        if (children.length){
            el.childSection.hidden = false;

            el.childrenGrid.innerHTML = children.map(child => {
                const childCode = String(child.ID || '');
                const fnCount = functionsForBranch(childCode).length;
                const personCount = branchPeopleCount(childCode);

                return `
                    <button type="button" class="gov-child-card" data-child="${esc(childCode)}">
                        <span class="gov-child-icon">▦</span>
                        <span class="gov-child-copy">
                            <strong>${esc(child.NOME || childCode)}</strong>
                            <small>${fnCount} função(ões) · ${personCount} pessoa(s)</small>
                        </span>
                        <span class="gov-child-arrow">›</span>
                    </button>
                `;
            }).join('');

            el.childrenGrid.querySelectorAll('[data-child]').forEach(button => {
                button.addEventListener('click',() => selectStructure(button.dataset.child));
            });
        }else{
            el.childSection.hidden = true;
            el.childrenGrid.innerHTML = '';
        }

        const isBranch = children.length > 0;
        el.functionsTitle.textContent = isBranch ? 'Funções deste ramo' : 'Funções / cargos';
        el.functionsScope.textContent = isBranch
            ? 'Inclui funções das subestruturas visíveis'
            : 'Funções vinculadas diretamente a esta estrutura';

        el.functionsList.innerHTML = functions.length
            ? functions.map(functionCard).join('')
            : `
                <div class="gov-no-functions">
                    Nenhuma função/cargo cadastrada neste ramo.
                </div>
            `;

        el.functionsList.querySelectorAll('[data-function]').forEach(button => {
            button.addEventListener('click',() => {
                openFunction(Number(button.dataset.function));
            });
        });

        el.empty.hidden = true;
        el.selected.hidden = false;

        if (el.contentPanel) {
            el.contentPanel.scrollTop = 0;
        }

        if (window.innerWidth <= 960) closeTree();
    }

    function openFunction(functionId){
        const fn = (state.data?.funcoes || []).find(
            item => Number(item.id || 0) === Number(functionId)
        );
        if (!fn) return;

        const rows = responsibilitiesForFunction(fn);
        const roles = responsibilityRoleCounts(rows);
        const occupants = fn.ocupantes || [];

        el.drawerTitle.textContent = fn.nome || 'Função';

        el.drawerBody.innerHTML = `
            <div class="drawer-card">
                <div class="drawer-label">Área / Estrutura</div>
                <div class="drawer-value">${esc(fn.estrutura_nome || fn.estrutura_codigo || 'Não informada')}</div>
            </div>

            <div class="drawer-card">
                <div class="drawer-label">Ocupante atual</div>
                <div class="drawer-value">
                    ${
                        occupants.length
                            ? occupants.map(p => esc(p.nome)).join(', ')
                            : 'Vaga / sem ocupante'
                    }
                </div>
            </div>

            <div class="drawer-card">
                <div class="drawer-label">Resumo de responsabilidades</div>
                <div class="drawer-value">
                    ${rows.length} total ·
                    ${roles.Principal} Principal ·
                    ${roles.Apoio} Apoio ·
                    ${roles.Backup} Backup
                </div>
            </div>

            <div class="drawer-card">
                <div class="drawer-label">Responsabilidades</div>
                ${
                    rows.length
                        ? rows.map(row => `
                            <div class="drawer-activity">
                                <strong>${esc(row.ATIVIDADE || row.PROCESSO || 'Atividade')}</strong>
                                <small>
                                    ${esc(row.PROCESSO || row.MACROPROCESSO || '')}
                                    ${row.PAPEL ? ' · ' + esc(row.PAPEL) : ''}
                                </small>
                            </div>
                        `).join('')
                        : '<div class="drawer-value" style="color:#94a3b8">Nenhuma responsabilidade cadastrada.</div>'
                }
            </div>
        `;

        el.drawer.classList.add('open');
        el.drawerBackdrop.classList.add('show');
        el.drawer.setAttribute('aria-hidden','false');
    }

    function closeFunction(){
        el.drawer.classList.remove('open');
        el.drawerBackdrop.classList.remove('show');
        el.drawer.setAttribute('aria-hidden','true');
    }

    function openTree(){
        el.treePanel.classList.add('open');
        el.treeBackdrop.classList.add('show');
    }

    function closeTree(){
        el.treePanel.classList.remove('open');
        el.treeBackdrop.classList.remove('show');
    }

    function renderAccess(){
        const access = state.payload?.access || {};

        let label = 'Visualizar';

        if (access.is_admin){
            label = 'Administrador';
        }else if (access.level === 'GERENCIAR'){
            label = 'Gerenciar';
        }else if (access.scope === 'FULL_GRANTED'){
            label = 'Acesso total';
        }

        el.access.querySelector('b').textContent = label;
    }

    function chooseInitialSelection(){
        const roots = treeRoots();

        // Em escopo por nó, prefere a raiz explicitamente permitida.
        const permittedRoot = state.payload?.access?.roots?.find(
            code => state.structureById.has(String(code))
        );

        if (permittedRoot){
            return String(permittedRoot);
        }

        // Em acesso total, prefere Comercial Souza.
        if (state.structureById.has('CS')) return 'CS';

        return roots[0] ? String(roots[0].ID || '') : '';
    }

    function handleGlobalSearch(){
        const q = norm(el.search.value.trim());
        state.globalSearch = q;

        if (!q) return;

        // 1) estrutura
        const structure = [...state.structureById.values()].find(row =>
            norm(`${row.NOME || ''} ${row.ID || ''}`).includes(q)
        );

        if (structure){
            selectStructure(String(structure.ID || ''));
            return;
        }

        // 2) função
        const fn = (state.data?.funcoes || []).find(item =>
            norm(`${item.nome || ''} ${item.estrutura_nome || ''}`).includes(q)
        );

        if (fn){
            const code = String(fn.estrutura_codigo || '');
            if (code) selectStructure(code);
            setTimeout(() => openFunction(Number(fn.id || 0)),80);
            return;
        }

        // 3) pessoa
        const byPerson = (state.data?.funcoes || []).find(item =>
            (item.ocupantes || []).some(p => norm(p.nome || '').includes(q))
        );

        if (byPerson){
            const code = String(byPerson.estrutura_codigo || '');
            if (code) selectStructure(code);
            return;
        }

        // 4) atividade
        const responsibility = (state.data?.responsabilidades || []).find(row =>
            norm(`${row.ATIVIDADE || ''} ${row.PROCESSO || ''} ${row.MACROPROCESSO || ''}`).includes(q)
        );

        if (responsibility){
            const code = String(responsibility.ID_ESTRUTURA || '');
            if (code && state.structureById.has(code)) selectStructure(code);
        }
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
                throw new Error(payload?.error || ('HTTP ' + response.status));
            }

            state.payload = payload;
            state.data = payload.data || {};

            buildMaps();

            // deixa os dois primeiros níveis abertos por padrão
            treeRoots().forEach(root => {
                const code = String(root.ID || '');
                state.expanded.add(code);
                directChildren(code).forEach(child => state.expanded.add(String(child.ID || '')));
            });

            renderAccess();
            renderTree();

            el.load.hidden = true;
            el.app.hidden = false;

            const initial = chooseInitialSelection();
            if (initial) selectStructure(initial);

        }catch(error){
            console.error(error);

            el.load.innerHTML = `
                <strong style="color:#b91c1c">Falha ao carregar a Estrutura</strong>
                <span>${esc(error?.message || 'Erro desconhecido.')}</span>
            `;
        }
    }

    el.treeFilter.addEventListener('input',() => {
        state.treeFilter = el.treeFilter.value.trim();
        renderTree();
    });

    let searchTimer = null;
    el.search.addEventListener('input',() => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(handleGlobalSearch,220);
    });

    el.openTree.addEventListener('click',openTree);
    el.closeTree.addEventListener('click',closeTree);
    el.treeBackdrop.addEventListener('click',closeTree);

    el.drawerClose.addEventListener('click',closeFunction);
    el.drawerBackdrop.addEventListener('click',closeFunction);

    window.addEventListener('keydown',event => {
        if (event.key === 'Escape'){
            closeFunction();
            closeTree();
        }
    });

    load();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
