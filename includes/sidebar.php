<?php
require_once __DIR__ . '/../api/ContratoAuth.php';

$usuarioIdSidebar = (int) ($_SESSION['user_id'] ?? 0);
$ehAdminSidebar = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$setorPrincipalSidebar = $_SESSION['setor_principal'] ?? '';
$podeGerenciarDocsSidebar = !empty($_SESSION['pode_gerenciar_docs']);
$podeGerenciarAcessosSidebar = !empty($_SESSION['pode_gerenciar_acessos']);

$contratoAuthSidebar = new ContratoAuth(
    $pdo_intra,
    $usuarioIdSidebar,
    $ehAdminSidebar
);

$mostrarGestaoContratos = $contratoAuthSidebar->pode('acessar_modulo');

$podeVisualizarWinthorSidebar = $ehAdminSidebar || $podeGerenciarAcessosSidebar;

if (!$podeVisualizarWinthorSidebar && $usuarioIdSidebar > 0) {
    $stmtWinthorSidebar = $pdo_intra->prepare(
        "SELECT 1
           FROM usuarios_grupos UG
           JOIN grupos_intranet G ON G.id = UG.grupo_id
          WHERE UG.usuario_id = ?
            AND G.pode_visualizar_winthor = 1
          LIMIT 1"
    );
    $stmtWinthorSidebar->execute([$usuarioIdSidebar]);
    $podeVisualizarWinthorSidebar = (bool) $stmtWinthorSidebar->fetchColumn();
}

// Página atual
$current_page = basename($_SERVER['PHP_SELF']);
$is_docs_active = ($current_page === 'view.php' || isset($_GET['path']));

// Documentação: escaneamento dinâmico
$diretorio_docs = __DIR__ . '/../docs/';
$setores_disponiveis = [];

if (is_dir($diretorio_docs)) {
    $pastas = scandir($diretorio_docs);
    foreach ($pastas as $pasta) {
        if ($pasta !== '.' && $pasta !== '..' && is_dir($diretorio_docs . $pasta)) {
            $setores_disponiveis[] = strtoupper($pasta);
        }
    }
}
sort($setores_disponiveis);

$pasta_url_atual = '';
if (isset($_GET['path'])) {
    $partes_path = explode('/', urldecode($_GET['path']));
    $pasta_url_atual = strtoupper($partes_path[0]);
}

// Processos homologados
$stmt_aprovados = $pdo_intra->query("
    SELECT id, titulo, versao_atual, setor_origem
    FROM docs_fluxo_simples
    WHERE status = 'Aprovado'
    ORDER BY setor_origem ASC, titulo ASC
");

$aprovados_por_setor = [];
while ($row = $stmt_aprovados->fetch(PDO::FETCH_ASSOC)) {
    $s = !empty($row['setor_origem']) ? strtoupper($row['setor_origem']) : 'GERAL';
    $aprovados_por_setor[$s][] = $row;
}

$is_proc_active = ($current_page === 'visualizar_processo.php');
$setor_atual_sidebar = isset($_GET['setor_origem']) ? urldecode($_GET['setor_origem']) : '';

// Permissões já existentes na sidebar original
$tem_permissao_feed = $ehAdminSidebar
    || !empty($_SESSION['pode_postar_feed'])
    || $setorPrincipalSidebar === 'MARKETING';

$podeVisualizarBaseErrosSidebar = $ehAdminSidebar || $podeGerenciarAcessosSidebar;
$podeVisualizarAdministracaoSidebar = $ehAdminSidebar || $podeGerenciarDocsSidebar;

// Estado dos grupos principais
$is_sistemas_active = in_array($current_page, [
    'governanca_organograma.php',
    'governanca_ti.php',
    'governanca_pessoas.php',
    'governanca_minha_area.php',
    'ti_base_erros.php',
    'acompanhamento_winthor.php',
    'acompanhamento_implantacao.php'
], true) || isset($_GET['abrir_sistemas']);

$is_assinaturas_active = in_array($current_page, [
    'minhas_assinaturas.php',
    'criar_envelope.php',
    'detalhe_envelope.php',
    'configuracoes_assinaturas.php'
], true);

$is_comunicacao_active = in_array($current_page, [
    'matriz.php',
    'admin_marketing.php',
    'admin_feed.php'
], true);

$is_documentacao_dados_active = in_array($current_page, [
    'treinamento.php',
    'meus_documentos.php',
    'view.php',
    'visualizar_processo.php'
], true) || $is_docs_active || $is_proc_active;

$is_administracao_active = in_array($current_page, [
    'admin_docs.php',
    'admin_gestao.php',
    'admin_logs.php',
    'gestao_fluxo.php',
    'governanca_acessos.php'
], true);

// Helpdesk / GLPI
$helpdeskUrlSidebar = 'http://192.168.0.63:8080/glpi17/index.php';

function sidebarRootState(bool $active): string {
    return $active ? ' is-active' : '';
}

function sidebarGroupState(bool $open): string {
    return $open ? ' is-open' : '';
}
?>

<style>
    /* =========================================================
       SIDEBAR CORPORATIVA - Comercial Souza
       Foco: hierarquia, legibilidade e crescimento do menu.
       ========================================================= */
    #sidebar-menu {
        width: 270px;
        min-width: 270px;
    }

    #sidebar-menu .sidebar-brand {
        flex: 0 0 auto;
        padding: 14px 14px 12px;
        border-bottom: 1px solid rgba(71, 85, 105, .32);
        background: rgba(6, 17, 35, .36);
    }

    #sidebar-menu .sidebar-brand-inner {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #sidebar-menu .sidebar-brand-badge {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
        background: #2563eb;
        color: #fff;
        font-size: 12px;
        font-weight: 900;
        box-shadow: 0 8px 22px rgba(37, 99, 235, .22);
    }

    #sidebar-menu .sidebar-brand-title {
        color: #fff;
        font-size: 12px;
        line-height: 1.1;
        font-weight: 900;
        letter-spacing: .01em;
    }

    #sidebar-menu .sidebar-brand-subtitle {
        margin-top: 4px;
        color: #60708b;
        font-size: 8px;
        font-weight: 800;
        letter-spacing: .19em;
        text-transform: uppercase;
    }

    #sidebar-menu .sidebar-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 12px 10px 18px;
        scrollbar-width: thin;
        scrollbar-color: #64748b transparent;
    }

    #sidebar-menu .sidebar-scroll::-webkit-scrollbar {
        width: 5px;
    }

    #sidebar-menu .sidebar-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    #sidebar-menu .sidebar-scroll::-webkit-scrollbar-thumb {
        background: #475569;
        border-radius: 999px;
    }

    #sidebar-menu .sidebar-main-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    #sidebar-menu .sidebar-root-link,
    #sidebar-menu .sidebar-parent {
        width: 100%;
        min-height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 11px;
        color: #cbd5e1;
        font-size: 13px;
        line-height: 1.25;
        font-weight: 750;
        text-align: left;
        transition: background-color .18s ease, color .18s ease, box-shadow .18s ease, transform .18s ease;
        border: 1px solid transparent;
    }

    #sidebar-menu .sidebar-root-link:hover,
    #sidebar-menu .sidebar-parent:hover {
        background: rgba(30, 41, 59, .82);
        color: #fff;
        border-color: rgba(71, 85, 105, .45);
    }

    #sidebar-menu .sidebar-root-link.is-active {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 8px 22px rgba(37, 99, 235, .20);
    }

    /* Destaque muito mais forte do grupo expandido */
    #sidebar-menu .sidebar-parent.is-open {
        background: linear-gradient(90deg, rgba(37, 99, 235, .23), rgba(30, 41, 59, .90));
        color: #fff;
        border-color: rgba(59, 130, 246, .55);
        box-shadow: inset 4px 0 0 #3b82f6, 0 5px 14px rgba(0, 0, 0, .12);
    }

    #sidebar-menu .sidebar-icon {
        width: 22px;
        flex: 0 0 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        line-height: 1;
    }

    #sidebar-menu .sidebar-label {
        flex: 1 1 auto;
        min-width: 0;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    #sidebar-menu .sidebar-chevron {
        width: 18px;
        flex: 0 0 18px;
        color: #94a3b8;
        font-size: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform .2s ease, color .2s ease;
    }

    #sidebar-menu .sidebar-parent.is-open .sidebar-chevron {
        transform: rotate(90deg);
        color: #93c5fd;
    }

    #sidebar-menu .sidebar-submenu {
        margin: 4px 0 7px 15px;
        padding: 6px 0 6px 12px;
        border-left: 2px solid #334155;
        border-radius: 0 0 0 8px;
        background: rgba(2, 8, 23, .20);
    }

    #sidebar-menu .sidebar-submenu.hidden {
        display: none !important;
    }

    #sidebar-menu .sidebar-sub-link,
    #sidebar-menu .sidebar-sub-button {
        width: 100%;
        min-height: 38px;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 7px 10px;
        margin: 2px 0;
        border-radius: 8px;
        color: #cbd5e1;
        font-size: 12px;
        line-height: 1.3;
        font-weight: 700;
        text-align: left;
        transition: background-color .16s ease, color .16s ease, padding-left .16s ease;
    }

    #sidebar-menu .sidebar-sub-link:hover,
    #sidebar-menu .sidebar-sub-button:hover {
        background: rgba(51, 65, 85, .72);
        color: #fff;
        padding-left: 13px;
    }

    #sidebar-menu .sidebar-sub-link.is-active,
    #sidebar-menu .sidebar-sub-button.is-active {
        background: rgba(37, 99, 235, .24);
        color: #fff;
        box-shadow: inset 3px 0 0 #60a5fa;
    }

    #sidebar-menu .sidebar-sub-icon {
        width: 20px;
        flex: 0 0 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    #sidebar-menu .sidebar-mini-chevron {
        margin-left: auto;
        color: #94a3b8;
        font-size: 9px;
        transition: transform .2s ease;
    }

    #sidebar-menu .sidebar-mini-chevron.is-open {
        transform: rotate(90deg);
        color: #93c5fd;
    }

    #sidebar-menu .sidebar-third-level {
        margin: 3px 0 7px 18px;
        padding: 5px 0 5px 9px;
        border-left: 1px solid #475569;
    }

    #sidebar-menu .sidebar-third-link {
        display: flex;
        align-items: center;
        gap: 7px;
        min-height: 32px;
        padding: 6px 7px;
        border-radius: 7px;
        color: #aebdd0;
        font-size: 11px;
        font-weight: 650;
        line-height: 1.25;
        transition: background-color .16s ease, color .16s ease;
    }

    #sidebar-menu .sidebar-third-link:hover,
    #sidebar-menu .sidebar-third-link.is-active {
        background: rgba(30, 64, 175, .22);
        color: #fff;
    }

    #sidebar-menu .sidebar-empty {
        padding: 7px 8px;
        color: #64748b;
        font-size: 10px;
        font-style: italic;
    }

    /* Central de ajuda: não é mais empurrada para o fim da tela.
       Fica logo depois do menu e continua visível no scroll por sticky. */
    #sidebar-menu .sidebar-help-zone {
        position: sticky;
        bottom: 0;
        z-index: 5;
        margin-top: 14px;
        padding-top: 10px;
        padding-bottom: 2px;
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0), #0f172a 24%, #0f172a 100%);
    }

    #sidebar-menu .sidebar-help-card {
        border: 1px solid #334155;
        border-left: 3px solid #2563eb;
        background: #111c31;
        border-radius: 12px;
        padding: 11px 11px 10px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, .20);
    }

    #sidebar-menu .sidebar-help-kicker {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #60a5fa;
        font-size: 8px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    #sidebar-menu .sidebar-help-kicker::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 999px;
        background: #3b82f6;
        box-shadow: 0 0 10px rgba(59, 130, 246, .8);
    }

    #sidebar-menu .sidebar-help-title {
        margin-top: 6px;
        color: #fff;
        font-size: 11px;
        font-weight: 850;
        line-height: 1.3;
    }

    #sidebar-menu .sidebar-help-row {
        margin-top: 9px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 10px;
    }

    #sidebar-menu .sidebar-help-label {
        color: #ffffffff;
        font-size: 10px;
        font-weight: 850;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    #sidebar-menu .sidebar-help-ramal {
        margin-top: 2px;
        color: #fff;
        font-size: 20px;
        line-height: 1;
        font-weight: 900;
    }

    #sidebar-menu .sidebar-help-button {
        background-color: #2563eb; /* Tom de azul aproximado da sua imagem */
        color: #ffffff;
        text-decoration: none;
        font-weight: bold;
        font-size: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        box-shadow: 0px 4px 10px rgba(37, 99, 235, 0.3);
        transition: background 0.2s ease;
        
        /* Ajustes para o novo formato com texto */
        padding: 10px 16px;       /* Dá espaçamento interno nas laterais e altura */
        white-space: nowrap;      /* Garante que o texto fique em uma única linha */
        width: auto;              /* Permite que o botão estique para caber o texto */
        height: auto;             /* Remove restrições de altura fixa se houver */
    }

    #sidebar-menu .sidebar-help-button:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    #sidebar-menu .sidebar-version {
        margin-top: 8px;
        color: #475569;
        font-size: 8px;
        text-align: center;
        font-weight: 700;
    }

    @media (max-width: 1023px) {
        #sidebar-menu {
            width: 282px;
            min-width: 282px;
        }
    }
</style>

<div id="mobile-overlay" onclick="toggleMobileMenu()" class="fixed inset-0 bg-black/60 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity opacity-0"></div>

<aside id="sidebar-menu" class="fixed inset-y-0 left-0 z-50 bg-navy-900 flex flex-col h-full border-r border-navy-700 transition-transform duration-300 transform -translate-x-full lg:relative lg:translate-x-0 lg:flex shrink-0 shadow-2xl lg:shadow-none">

    <div class="flex justify-end px-3 pt-3 lg:hidden">
        <button onclick="toggleMobileMenu()" class="text-slate-300 hover:text-white p-2" aria-label="Fechar menu">
            ✕
        </button>
    </div>
    
    <nav class="sidebar-scroll" aria-label="Menu principal da intranet">
        <div class="sidebar-main-list">

            <!-- INÍCIO -->
            <a href="index.php" class="sidebar-root-link<?= sidebarRootState($current_page === 'index.php' && !isset($_GET['abrir_sistemas'])) ?>">
                <span class="sidebar-icon">🏠</span>
                <span class="sidebar-label">Início</span>
            </a>

            <!-- SISTEMAS INTERNOS -->
            <button type="button"
                    id="sidebar-btn-sistemas"
                    class="sidebar-parent<?= sidebarGroupState($is_sistemas_active) ?>"
                    onclick="toggleSidebarGroup('sidebar-group-sistemas', 'sidebar-btn-sistemas')"
                    aria-expanded="<?= $is_sistemas_active ? 'true' : 'false' ?>">
                <span class="sidebar-icon">🚀</span>
                <span class="sidebar-label">Sistemas Internos</span>
                <span class="sidebar-chevron">▶</span>
            </button>

            <div id="sidebar-group-sistemas" class="sidebar-submenu<?= $is_sistemas_active ? '' : ' hidden' ?>" data-level="root">
                <a href="#"
                   onclick="if (typeof abrirModalSistemas === 'function') { abrirModalSistemas(); } else { window.location.href='index.php?abrir_sistemas=1'; } return false;"
                   class="sidebar-sub-link<?= isset($_GET['abrir_sistemas']) ? ' is-active' : '' ?>">
                    <span class="sidebar-sub-icon">🚀</span>
                    <span>Botões navegação</span>
                </a>


                <!-- Governança: entrada oficial pelo Organograma -->
                <?php
                $govPagesSidebar = [
                    'governanca_organograma.php',
                    'governanca_ti.php',
                    'governanca_pessoas.php',
                    'governanca_minha_area.php'
                ];
                $govModuloAtivoSidebar = in_array($current_page, $govPagesSidebar, true);
                ?>
                <a href="governanca_organograma.php" class="sidebar-sub-link<?= $govModuloAtivoSidebar ? ' is-active' : '' ?>">
                    <span class="sidebar-sub-icon">🧭</span>
                    <span>Governança</span>
                </a>

                <?php if ($podeVisualizarBaseErrosSidebar): ?>
                    <a href="ti_base_erros.php" class="sidebar-sub-link<?= $current_page === 'ti_base_erros.php' ? ' is-active' : '' ?>">
                        <span class="sidebar-sub-icon">🛠️</span>
                        <span>Base de Erros</span>
                    </a>
                <?php endif; ?>

                <?php if ($podeVisualizarWinthorSidebar): ?>
                    <a href="acompanhamento_winthor.php" class="sidebar-sub-link<?= $current_page === 'acompanhamento_winthor.php' ? ' is-active' : '' ?>">
                        <span class="sidebar-sub-icon">📊</span>
                        <span>Acompanhamento WinThor</span>
                    </a>

                    <a href="acompanhamento_implantacao.php" class="sidebar-sub-link<?= $current_page === 'acompanhamento_implantacao.php' ? ' is-active' : '' ?>">
                        <span class="sidebar-sub-icon">🗓️</span>
                        <span>Acompanhamento Implantação</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- ASSINATURAS DIGITAIS: apenas Minhas Assinaturas -->
            <button type="button"
                    id="sidebar-btn-assinaturas"
                    class="sidebar-parent<?= sidebarGroupState($is_assinaturas_active) ?>"
                    onclick="toggleSidebarGroup('sidebar-group-assinaturas', 'sidebar-btn-assinaturas')"
                    aria-expanded="<?= $is_assinaturas_active ? 'true' : 'false' ?>">
                <span class="sidebar-icon">✍️</span>
                <span class="sidebar-label">Assinaturas Digitais</span>
                <span class="sidebar-chevron">▶</span>
            </button>

            <div id="sidebar-group-assinaturas" class="sidebar-submenu<?= $is_assinaturas_active ? '' : ' hidden' ?>" data-level="root">
                <a href="minhas_assinaturas.php" class="sidebar-sub-link<?= $current_page === 'minhas_assinaturas.php' ? ' is-active' : '' ?>">
                    <span class="sidebar-sub-icon">✍️</span>
                    <span>Minhas Assinaturas</span>
                </a>
            </div>

            <!-- GESTÃO DE CONTRATOS -->
            <?php if ($mostrarGestaoContratos): ?>
                <a href="contratos.php" class="sidebar-root-link<?= sidebarRootState($current_page === 'contratos.php') ?>">
                    <span class="sidebar-icon">📑</span>
                    <span class="sidebar-label">Gestão de Contratos</span>
                </a>
            <?php endif; ?>

            <!-- COMUNICAÇÃO -->
            <button type="button"
                    id="sidebar-btn-comunicacao"
                    class="sidebar-parent<?= sidebarGroupState($is_comunicacao_active) ?>"
                    onclick="toggleSidebarGroup('sidebar-group-comunicacao', 'sidebar-btn-comunicacao')"
                    aria-expanded="<?= $is_comunicacao_active ? 'true' : 'false' ?>">
                <span class="sidebar-icon">📞</span>
                <span class="sidebar-label">Comunicação</span>
                <span class="sidebar-chevron">▶</span>
            </button>

            <div id="sidebar-group-comunicacao" class="sidebar-submenu<?= $is_comunicacao_active ? '' : ' hidden' ?>" data-level="root">
                <a href="matriz.php" class="sidebar-sub-link<?= $current_page === 'matriz.php' ? ' is-active' : '' ?>">
                    <span class="sidebar-sub-icon">📞</span>
                    <span>Matriz de Comunicação</span>
                </a>

                <?php if ($tem_permissao_feed): ?>
                    <a href="admin_marketing.php" class="sidebar-sub-link<?= $current_page === 'admin_marketing.php' ? ' is-active' : '' ?>">
                        <span class="sidebar-sub-icon">🎨</span>
                        <span>Gestão Marketing</span>
                    </a>

                    <a href="admin_feed.php" class="sidebar-sub-link<?= $current_page === 'admin_feed.php' ? ' is-active' : '' ?>">
                        <span class="sidebar-sub-icon">📢</span>
                        <span>Gestão do Feed</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- DOCUMENTAÇÃO & DADOS -->
            <button type="button"
                    id="sidebar-btn-documentacao"
                    class="sidebar-parent<?= sidebarGroupState($is_documentacao_dados_active) ?>"
                    onclick="toggleSidebarGroup('sidebar-group-documentacao', 'sidebar-btn-documentacao')"
                    aria-expanded="<?= $is_documentacao_dados_active ? 'true' : 'false' ?>">
                <span class="sidebar-icon">📂</span>
                <span class="sidebar-label">Documentação &amp; Dados</span>
                <span class="sidebar-chevron">▶</span>
            </button>

            <div id="sidebar-group-documentacao" class="sidebar-submenu<?= $is_documentacao_dados_active ? '' : ' hidden' ?>" data-level="root">
                <a href="treinamento.php" class="sidebar-sub-link<?= $current_page === 'treinamento.php' ? ' is-active' : '' ?>">
                    <span class="sidebar-sub-icon">🎓</span>
                    <span>Cursos &amp; Treinamentos</span>
                </a>

                <a href="meus_documentos.php" class="sidebar-sub-link<?= $current_page === 'meus_documentos.php' ? ' is-active' : '' ?>">
                    <span class="sidebar-sub-icon">📤</span>
                    <span>Envio de Processos</span>
                </a>

                <!-- Documentação dinâmica -->
                <button type="button"
                        id="docs-button"
                        class="sidebar-sub-button<?= $is_docs_active ? ' is-active' : '' ?>"
                        onclick="toggleNestedMenu('docs-menu', 'docs-arrow')">
                    <span class="sidebar-sub-icon">📂</span>
                    <span>Documentação</span>
                    <span id="docs-arrow" class="sidebar-mini-chevron<?= $is_docs_active ? ' is-open' : '' ?>">▶</span>
                </button>

                <div id="docs-menu" class="sidebar-third-level<?= $is_docs_active ? '' : ' hidden' ?>">
                    <a href="view.php" class="sidebar-third-link<?= $current_page === 'view.php' && !isset($_GET['path']) ? ' is-active' : '' ?>">
                        <span>👁️</span>
                        <span>Visão Geral</span>
                    </a>

                    <?php foreach ($setores_disponiveis as $setor):
                        $tem_acesso = $ehAdminSidebar
                            || $setorPrincipalSidebar === $setor
                            || (isset($_SESSION['pastas_extras']) && in_array($setor, $_SESSION['pastas_extras']));

                        if ($tem_acesso):
                            $diretorio_base = $_SERVER['DOCUMENT_ROOT'] . '/intranet/docs/';
                            $diretorio_setor = $diretorio_base . $setor;
                            $arquivos_docs = glob($diretorio_setor . '/*.{md,pdf}', GLOB_BRACE);
                            $id_setor_limpo = preg_replace('/[^a-zA-Z0-9_]/', '_', $setor);
                            $is_this_open = ($pasta_url_atual === $setor);
                    ?>
                        <div>
                            <div style="display:flex; align-items:center; gap:4px;">
                                <a href="view.php?path=<?= urlencode($setor) ?>"
                                   class="sidebar-third-link<?= $is_this_open ? ' is-active' : '' ?>"
                                   style="flex:1; min-width:0;">
                                    <span>📁</span>
                                    <span style="overflow-wrap:anywhere;"><?= htmlspecialchars($setor) ?></span>
                                </a>
                                <button type="button"
                                        onclick="event.preventDefault(); event.stopPropagation(); toggleSetor('sub_<?= $id_setor_limpo ?>', 'arrow_<?= $id_setor_limpo ?>')"
                                        style="width:28px;height:28px;border-radius:7px;color:#94a3b8;flex:0 0 28px;">
                                    <span id="arrow_<?= $id_setor_limpo ?>" style="font-size:9px;display:block;transform:<?= $is_this_open ? 'rotate(90deg)' : 'rotate(0deg)' ?>;transition:transform .2s ease;">▶</span>
                                </button>
                            </div>

                            <div id="sub_<?= $id_setor_limpo ?>" class="sidebar-third-level<?= $is_this_open ? '' : ' hidden' ?>" style="margin-left:15px;">
                                <?php if (!$arquivos_docs || empty($arquivos_docs)): ?>
                                    <div class="sidebar-empty">Nenhum documento</div>
                                <?php else: ?>
                                    <?php foreach ($arquivos_docs as $arq):
                                        $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
                                        $nome_doc = str_replace(['.md', '.pdf'], '', basename($arq));
                                        $icone = ($ext === 'pdf') ? '📕' : '📄';
                                    ?>
                                        <a href="view.php?path=<?= urlencode($setor . '/' . basename($arq)) ?>" class="sidebar-third-link">
                                            <span><?= $icone ?></span>
                                            <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(str_replace('_', ' ', $nome_doc)) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                </div>

                <!-- Processos homologados -->
                <button type="button"
                        id="processos-button"
                        class="sidebar-sub-button<?= $is_proc_active ? ' is-active' : '' ?>"
                        onclick="toggleNestedMenu('processos-menu', 'processos-arrow')">
                    <span class="sidebar-sub-icon">✅</span>
                    <span>Processos Homologados</span>
                    <span id="processos-arrow" class="sidebar-mini-chevron<?= $is_proc_active ? ' is-open' : '' ?>">▶</span>
                </button>

                <div id="processos-menu" class="sidebar-third-level<?= $is_proc_active ? '' : ' hidden' ?>">
                    <a href="visualizar_processo.php" class="sidebar-third-link<?= $current_page === 'visualizar_processo.php' && !$setor_atual_sidebar ? ' is-active' : '' ?>">
                        <span>👁️</span>
                        <span>Visão Geral</span>
                    </a>

                    <?php if (empty($aprovados_por_setor)): ?>
                        <div class="sidebar-empty">Nenhum processo oficial.</div>
                    <?php else: ?>
                        <?php foreach ($aprovados_por_setor as $nome_setor => $docs_setor):
                            $id_setor_clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $nome_setor);
                            $is_this_proc_open = ($setor_atual_sidebar === $nome_setor);
                        ?>
                            <div>
                                <div style="display:flex;align-items:center;gap:4px;">
                                    <a href="visualizar_processo.php?setor_origem=<?= urlencode($nome_setor) ?>"
                                       class="sidebar-third-link<?= $is_this_proc_open ? ' is-active' : '' ?>"
                                       style="flex:1;min-width:0;">
                                        <span>📁</span>
                                        <span style="overflow-wrap:anywhere;"><?= htmlspecialchars($nome_setor) ?></span>
                                    </a>
                                    <button type="button"
                                            onclick="event.preventDefault(); event.stopPropagation(); toggleSetor('proc_sub_<?= $id_setor_clean ?>', 'proc_arr_<?= $id_setor_clean ?>')"
                                            style="width:28px;height:28px;border-radius:7px;color:#94a3b8;flex:0 0 28px;">
                                        <span id="proc_arr_<?= $id_setor_clean ?>" style="font-size:9px;display:block;transform:<?= $is_this_proc_open ? 'rotate(90deg)' : 'rotate(0deg)' ?>;transition:transform .2s ease;">▶</span>
                                    </button>
                                </div>

                                <div id="proc_sub_<?= $id_setor_clean ?>" class="sidebar-third-level<?= $is_this_proc_open ? '' : ' hidden' ?>" style="margin-left:15px;">
                                    <?php foreach ($docs_setor as $doc_ap): ?>
                                        <a href="visualizar_processo.php?setor_origem=<?= urlencode($nome_setor) ?>&open_doc=<?= (int) $doc_ap['id'] ?>&title=<?= urlencode($doc_ap['titulo']) ?>" class="sidebar-third-link">
                                            <span>📋</span>
                                            <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($doc_ap['titulo']) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ADMINISTRAÇÃO -->
            <?php if ($podeVisualizarAdministracaoSidebar): ?>
                <button type="button"
                        id="sidebar-btn-administracao"
                        class="sidebar-parent<?= sidebarGroupState($is_administracao_active) ?>"
                        onclick="toggleSidebarGroup('sidebar-group-administracao', 'sidebar-btn-administracao')"
                        aria-expanded="<?= $is_administracao_active ? 'true' : 'false' ?>">
                    <span class="sidebar-icon">🛡️</span>
                    <span class="sidebar-label">Administração</span>
                    <span class="sidebar-chevron">▶</span>
                </button>

                <div id="sidebar-group-administracao" class="sidebar-submenu<?= $is_administracao_active ? '' : ' hidden' ?>" data-level="root">
                    <?php if ($podeGerenciarDocsSidebar): ?>
                        <a href="admin_docs.php" class="sidebar-sub-link<?= $current_page === 'admin_docs.php' ? ' is-active' : '' ?>">
                            <span class="sidebar-sub-icon">📝</span>
                            <span>Gestão de Manuais</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($ehAdminSidebar): ?>
                        <a href="admin_gestao.php" class="sidebar-sub-link<?= $current_page === 'admin_gestao.php' ? ' is-active' : '' ?>">
                            <span class="sidebar-sub-icon">🛡️</span>
                            <span>Gestão de Acessos</span>
                        </a>

                        <a href="admin_logs.php" class="sidebar-sub-link<?= $current_page === 'admin_logs.php' ? ' is-active' : '' ?>">
                            <span class="sidebar-sub-icon">📋</span>
                            <span>Logs de Auditoria</span>
                        </a>

                        <a href="gestao_fluxo.php" class="sidebar-sub-link<?= $current_page === 'gestao_fluxo.php' ? ' is-active' : '' ?>">
                            <span class="sidebar-sub-icon">🛠️</span>
                            <span>Aprovações de Processos</span>
                        </a>

                        <a href="governanca_acessos.php" class="sidebar-sub-link<?= $current_page === 'governanca_acessos.php' ? ' is-active' : '' ?>">
                            <span class="sidebar-sub-icon">🧭</span>
                            <span>Acessos da Governança</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- CENTRAL DE AJUDA: logo após os módulos, sem aquele vazio gigante -->
        <div class="sidebar-help-zone">
            <div class="sidebar-help-card">
                <div class="sidebar-help-kicker">Central de Ajuda</div>
                <div class="sidebar-help-title">Dúvidas ou suporte técnico?</div>

                <div class="sidebar-help-row">
                    <div>
                        <div class="sidebar-help-label">Ramal interno</div>
                        <div class="sidebar-help-ramal">3171 ☎️</div>
                    </div>

                    <a href="<?= htmlspecialchars($helpdeskUrlSidebar) ?>"
                    target="_blank" rel="noopener noreferrer"
                    class="sidebar-help-button text-button"
                    title="Abrir Help Chamados"
                    aria-label="Abrir HelpDesk Chamados"
                    >
                        Abrir Chamado
                    </a>

                </div>
            </div>

            <div class="sidebar-version">v2.4.1 · Comercial Souza © 2026</div>
        </div>
    </nav>
</aside>

<script>
function toggleSidebarGroup(menuId, buttonId) {
    const menu = document.getElementById(menuId);
    const button = document.getElementById(buttonId);
    if (!menu || !button) return;

    const willOpen = menu.classList.contains('hidden');

    // Fecha os outros grupos principais para a sidebar não virar um "paredão".
    document.querySelectorAll('#sidebar-menu .sidebar-submenu[data-level="root"]').forEach((otherMenu) => {
        if (otherMenu.id === menuId) return;
        otherMenu.classList.add('hidden');

        const otherButton = document.querySelector(`#sidebar-menu [onclick*="${otherMenu.id}"]`);
        if (otherButton) {
            otherButton.classList.remove('is-open');
            otherButton.setAttribute('aria-expanded', 'false');
        }
    });

    if (willOpen) {
        menu.classList.remove('hidden');
        button.classList.add('is-open');
        button.setAttribute('aria-expanded', 'true');
    } else {
        menu.classList.add('hidden');
        button.classList.remove('is-open');
        button.setAttribute('aria-expanded', 'false');
    }
}

function toggleNestedMenu(menuId, arrowId) {
    const menu = document.getElementById(menuId);
    const arrow = document.getElementById(arrowId);
    if (!menu || !arrow) return;

    const opening = menu.classList.contains('hidden');
    menu.classList.toggle('hidden');
    arrow.classList.toggle('is-open', opening);
}

// Compatibilidade com chamadas antigas da sidebar
function toggleDocs() {
    toggleNestedMenu('docs-menu', 'docs-arrow');
}

function toggleProcessos() {
    toggleNestedMenu('processos-menu', 'processos-arrow');
}

function toggleSetor(id, arrowId) {
    const submenu = document.getElementById(id);
    const arrow = document.getElementById(arrowId);
    if (!submenu || !arrow) return;

    submenu.classList.toggle('hidden');
    arrow.style.transform = submenu.classList.contains('hidden')
        ? 'rotate(0deg)'
        : 'rotate(90deg)';
}
</script>
