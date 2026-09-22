<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/auth_check.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
$stmtDev = $pdo_intra->prepare(
    "SELECT MAX(is_admin) FROM (
        SELECT COALESCE(is_admin,0) AS is_admin
          FROM usuarios_permissoes
         WHERE usuario_id=?
        UNION ALL
        SELECT COALESCE(g.is_admin,0)
          FROM usuarios_grupos ug
          JOIN grupos_intranet g ON g.id=ug.grupo_id
         WHERE ug.usuario_id=?
    ) permissoes"
);
$stmtDev->execute([$usuarioId, $usuarioId]);
$isDashboardAdmin = (bool) $stmtDev->fetchColumn();

if (!$isDashboardAdmin) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Acesso restrito</title>'
        . '<body style="margin:0;display:grid;place-items:center;min-height:100vh;background:#071326;color:#e5eefc;font-family:Arial,sans-serif">'
        . '<div style="max-width:520px;padding:36px;text-align:center"><h1>Prévia em desenvolvimento</h1>'
        . '<p style="color:#9db0cc;line-height:1.6">Esta nova página inicial está disponível somente para administradores durante a fase de validação.</p>'
        . '<a href="index.php" style="display:inline-block;margin-top:14px;padding:12px 18px;border-radius:10px;background:#2563eb;color:white;text-decoration:none;font-weight:700">Voltar ao início atual</a>'
        . '</div></body></html>';
    exit;
}

if (empty($_SESSION['dashboard_csrf'])) {
    $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(32));
}
$dashboardCsrf = (string) $_SESSION['dashboard_csrf'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$nomeCompleto = trim((string) ($_SESSION['user_name'] ?? 'Colaborador'));
$partesNome = preg_split('/\s+/u', $nomeCompleto, -1, PREG_SPLIT_NO_EMPTY) ?: ['Colaborador'];
$primeiroNome = mb_convert_case((string) $partesNome[0], MB_CASE_TITLE, 'UTF-8');
?>

<main id="dashboard-home" class="flex-1 min-w-0 overflow-y-auto">
    <div class="dashboard-wrap">
        <header class="dashboard-hero">
            <div>
                <div class="dashboard-kicker"><span></span> Central de trabalho</div>
                <h1>Olá, <?= htmlspecialchars($primeiroNome, ENT_QUOTES, 'UTF-8') ?>.</h1>
                <p>Suas prioridades, documentos e compromissos em uma única visão.</p>
            </div>
            <div class="dashboard-hero-actions">
                <span class="dashboard-preview">Prévia administrativa</span>
                <button type="button" id="dashboardRefresh" class="dashboard-refresh" aria-label="Atualizar painel">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 4v5h5M4 13a8.1 8.1 0 0 0 15.5 2M20 20v-5h-5"/></svg>
                    Atualizar
                </button>
            </div>
        </header>

        <section class="dashboard-kpis" aria-label="Resumo do trabalho">
            <a href="minhas_assinaturas.php" class="dashboard-kpi dashboard-kpi-amber">
                <span class="dashboard-kpi-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5V4.8A1.8 1.8 0 0 1 5.8 3H15l5 5v11.2a1.8 1.8 0 0 1-1.8 1.8H5.5M14 3v6h6M8 14h8M8 17h5"/></svg>
                </span>
                <span><b id="kpiAssinaturas">—</b><small>Para assinar</small></span>
                <em>Ação necessária</em>
            </a>
            <a href="documentos.php?aba=pendencias" class="dashboard-kpi dashboard-kpi-blue">
                <span class="dashboard-kpi-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </span>
                <span><b id="kpiPendencias">—</b><small>Pendências documentais</small></span>
                <em>Minha fila</em>
            </a>
            <a href="documentos.php?aba=biblioteca" class="dashboard-kpi dashboard-kpi-green">
                <span class="dashboard-kpi-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4L19 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z"/></svg>
                </span>
                <span><b id="kpiPublicados">—</b><small>Publicados em 30 dias</small></span>
                <em>Aprovados</em>
            </a>
            <a href="minhas_assinaturas.php" class="dashboard-kpi dashboard-kpi-violet">
                <span class="dashboard-kpi-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
                </span>
                <span><b id="kpiEnvios">—</b><small>Envios em andamento</small></span>
                <em>Acompanhamento</em>
            </a>
        </section>

        <div id="dashboardWarning" class="dashboard-warning" hidden></div>

        <section class="dashboard-feature-grid">
            <article class="dashboard-panel dashboard-banner-panel" aria-label="Campanhas e destaques">
                <div id="dashboardBanner" class="dashboard-banner">
                    <div class="dashboard-loading banner"><span></span></div>
                </div>
                <div class="dashboard-banner-bar">
                    <div>
                        <span class="dashboard-eyebrow">Destaque</span>
                        <strong id="dashboardBannerTitle">Campanhas da empresa</strong>
                    </div>
                    <div class="dashboard-banner-controls">
                        <button type="button" id="bannerPrevious" aria-label="Banner anterior">‹</button>
                        <span id="bannerPosition">—</span>
                        <button type="button" id="bannerNext" aria-label="Próximo banner">›</button>
                    </div>
                </div>
            </article>

            <article class="dashboard-panel dashboard-calendar-panel">
                <div class="dashboard-panel-head compact">
                    <div><span class="dashboard-eyebrow">Agenda</span><h2 id="calendarMonth">Calendário</h2></div>
                    <button type="button" id="openFullAgenda" class="dashboard-head-action">Agenda completa</button>
                </div>
                <div class="dashboard-calendar-weekdays" aria-hidden="true">
                    <span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span>
                </div>
                <div id="dashboardCalendarGrid" class="dashboard-calendar-grid" aria-label="Calendário mensal"></div>
                <div id="dashboardAgenda" class="dashboard-upcoming">
                    <div class="dashboard-loading small"><span></span></div>
                </div>
            </article>
        </section>

        <section class="dashboard-panel dashboard-shortcuts-panel dashboard-shortcuts-wide">
            <div class="dashboard-panel-head compact">
                <div><span class="dashboard-eyebrow">Navegação</span><h2>Acesso rápido</h2></div>
            </div>
            <nav id="dashboardShortcuts" class="dashboard-shortcuts" aria-label="Acessos rápidos">
                <a href="minhas_assinaturas.php"><span>✍</span> Assinaturas</a>
                <a href="documentos.php"><span>▤</span> Documentos</a>
                <a href="governanca_minha_area.php"><span>⌂</span> Minha Área</a>
                <a href="governanca_organograma.php"><span>⌘</span> Organograma</a>
                <a href="contratos.php" id="shortcutContratos"><span>▣</span> Contratos <b id="badgeContratos" hidden></b></a>
                <a href="index.php"><span>◫</span> Início atual</a>
            </nav>
        </section>

        <section class="dashboard-bottom-grid">
            <article class="dashboard-panel">
                <div class="dashboard-panel-head">
                    <div><span class="dashboard-eyebrow">Biblioteca</span><h2>Documentos aprovados recentemente</h2></div>
                    <a href="documentos.php?aba=biblioteca">Abrir biblioteca</a>
                </div>
                <div id="dashboardPublished" class="dashboard-doc-list">
                    <div class="dashboard-loading"><span></span><span></span></div>
                </div>
            </article>

            <article class="dashboard-panel">
                <div class="dashboard-panel-head">
                    <div><span class="dashboard-eyebrow">Histórico pessoal</span><h2>Assinaturas concluídas</h2></div>
                    <a href="minhas_assinaturas.php">Ver histórico</a>
                </div>
                <div id="dashboardSigned" class="dashboard-doc-list">
                    <div class="dashboard-loading"><span></span><span></span></div>
                </div>
            </article>

            <article class="dashboard-panel dashboard-news-panel">
                <div class="dashboard-panel-head">
                    <div><span class="dashboard-eyebrow">Empresa</span><h2>Comunicados</h2></div>
                    <a href="index.php#bloco-feed">Ver mural</a>
                </div>
                <div id="dashboardNews" class="dashboard-news">
                    <div class="dashboard-loading"><span></span><span></span></div>
                </div>
            </article>
        </section>
    </div>
</main>

<div id="dashboardAgendaModal" class="dashboard-modal" hidden role="dialog" aria-modal="true" aria-labelledby="dashboardAgendaModalTitle">
    <div class="dashboard-modal-card">
        <header class="dashboard-modal-head">
            <div>
                <span class="dashboard-eyebrow">Próximos 30 dias</span>
                <h2 id="dashboardAgendaModalTitle">Agenda completa</h2>
            </div>
            <div class="dashboard-modal-actions">
                <button type="button" id="newAgendaEvent" class="dashboard-modal-primary">+ Novo compromisso</button>
                <button type="button" id="closeFullAgenda" aria-label="Fechar agenda">×</button>
            </div>
        </header>
        <div class="dashboard-modal-body">
            <section class="dashboard-full-calendar">
                <h3 id="fullCalendarMonth"></h3>
                <div class="dashboard-calendar-weekdays" aria-hidden="true">
                    <span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span>
                </div>
                <div id="fullCalendarGrid" class="dashboard-calendar-grid large" aria-label="Calendário completo"></div>
                <div class="dashboard-calendar-legend"><span></span> Dia com compromisso</div>
            </section>
            <section>
                <h3>Próximos compromissos</h3>
                <div id="fullAgendaList" class="dashboard-full-agenda-list"></div>
            </section>
        </div>
    </div>
</div>

<div id="dashboardEventModal" class="dashboard-modal dashboard-event-modal" hidden role="dialog" aria-modal="true" aria-labelledby="dashboardEventModalTitle">
    <div class="dashboard-modal-card dashboard-event-card">
        <header class="dashboard-modal-head">
            <div>
                <span class="dashboard-eyebrow">Agenda</span>
                <h2 id="dashboardEventModalTitle">Novo compromisso</h2>
            </div>
            <button type="button" id="closeEventModal" aria-label="Fechar formulário">×</button>
        </header>
        <form id="dashboardEventForm" class="dashboard-event-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($dashboardCsrf, ENT_QUOTES, 'UTF-8') ?>">
            <label class="dashboard-field dashboard-field-wide">
                <span>Título</span>
                <input type="text" name="titulo" minlength="3" maxlength="100" required autocomplete="off" placeholder="Ex.: Reunião de alinhamento">
            </label>
            <label class="dashboard-field">
                <span>Data</span>
                <input type="date" name="data_evento" id="dashboardEventDate" required>
            </label>
            <label class="dashboard-field">
                <span>Local</span>
                <select name="local_sala">
                    <option value="GERAL">Sem reserva de sala</option>
                    <option value="SALA_01">Sala de Reunião P1</option>
                    <option value="SALA_02">Sala de Reunião P2</option>
                    <option value="SALA_03">Auditório P1</option>
                </select>
            </label>
            <label class="dashboard-check dashboard-field-wide">
                <input type="checkbox" name="dia_inteiro" id="dashboardAllDay" value="1">
                <span>Compromisso de dia inteiro</span>
            </label>
            <label class="dashboard-field dashboard-time-field">
                <span>Início</span>
                <input type="time" name="hora_inicio" value="09:00" required>
            </label>
            <label class="dashboard-field dashboard-time-field">
                <span>Fim</span>
                <input type="time" name="hora_fim" value="10:00" required>
            </label>
            <label class="dashboard-field dashboard-field-wide" id="dashboardVisibilityField"<?= $isDashboardAdmin ? '' : ' hidden' ?>>
                <span>Quem poderá ver</span>
                <select name="visibilidade">
                    <option value="PESSOAL">Somente eu — compromisso pessoal</option>
                    <?php if ($isDashboardAdmin): ?>
                    <option value="GERAL">Todos — publicar na agenda da empresa</option>
                    <?php endif; ?>
                </select>
            </label>
            <div id="dashboardEventFeedback" class="dashboard-event-feedback dashboard-field-wide" hidden></div>
            <div class="dashboard-event-actions dashboard-field-wide">
                <button type="button" id="cancelEventModal">Cancelar</button>
                <button type="submit" id="saveAgendaEvent">Salvar compromisso</button>
            </div>
        </form>
    </div>
</div>

<div id="dashboardToast" class="dashboard-toast" hidden role="status" aria-live="polite"></div>

<style>
#dashboard-home{
    --dash-bg:#071326;--dash-surface:#0d1d33;--dash-surface-2:#10243e;--dash-line:#233b59;
    --dash-text:#f7fbff;--dash-muted:#8fa6c3;--dash-blue:#3b82f6;--dash-cyan:#22d3ee;
    min-height:0;background:
        radial-gradient(circle at 9% 4%,rgba(37,99,235,.18),transparent 30%),
        radial-gradient(circle at 92% 0,rgba(14,165,233,.1),transparent 25%),#071326;
    color:var(--dash-text);font-family:Inter,"Segoe UI",sans-serif;
}
#dashboard-home *{box-sizing:border-box}
.dashboard-wrap{width:min(1540px,100%);margin:0 auto;padding:24px clamp(18px,2.3vw,38px) 42px}
.dashboard-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:18px}
.dashboard-kicker,.dashboard-eyebrow{color:#67a4ff;font-size:9px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
.dashboard-kicker{display:flex;align-items:center;gap:8px;margin-bottom:5px}.dashboard-kicker span{width:7px;height:7px;border-radius:99px;background:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.1)}
.dashboard-hero h1{margin:0;font-size:clamp(25px,2.5vw,38px);line-height:1.08;letter-spacing:-.035em}.dashboard-hero p{margin:7px 0 0;color:var(--dash-muted);font-size:13px}
.dashboard-hero-actions{display:flex;align-items:center;gap:10px}.dashboard-preview{padding:9px 12px;border:1px solid rgba(245,158,11,.28);border-radius:10px;background:rgba(245,158,11,.08);color:#fbbf24;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.09em}
.dashboard-refresh{display:flex;align-items:center;gap:7px;padding:9px 13px;border:1px solid var(--dash-line);border-radius:10px;background:var(--dash-surface);color:#dbeafe;font-size:10px;font-weight:850;cursor:pointer}.dashboard-refresh:hover{border-color:#3b82f6;background:#132a49}.dashboard-refresh svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.dashboard-refresh.is-loading svg{animation:dash-spin .8s linear infinite}
.dashboard-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}.dashboard-kpi{position:relative;display:grid;grid-template-columns:42px 1fr;align-items:center;gap:12px;min-height:88px;padding:15px;border:1px solid var(--dash-line);border-radius:16px;background:linear-gradient(145deg,rgba(17,38,65,.96),rgba(10,26,47,.96));color:inherit;text-decoration:none;overflow:hidden;transition:.18s ease}.dashboard-kpi:hover{transform:translateY(-2px);border-color:#3b82f6;box-shadow:0 12px 30px rgba(0,0,0,.17)}.dashboard-kpi:after{content:"";position:absolute;inset:0 0 auto;height:2px;background:var(--accent)}.dashboard-kpi-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:rgba(59,130,246,.13);color:var(--accent)}.dashboard-kpi-amber .dashboard-kpi-icon{background:rgba(245,158,11,.13)}.dashboard-kpi-green .dashboard-kpi-icon{background:rgba(34,197,94,.12)}.dashboard-kpi-violet .dashboard-kpi-icon{background:rgba(167,139,250,.13)}.dashboard-kpi-icon svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.dashboard-kpi b{display:block;font-size:25px;line-height:1;font-weight:900}.dashboard-kpi small{display:block;margin-top:5px;color:#b9c8dc;font-size:10px;font-weight:800}.dashboard-kpi em{position:absolute;right:13px;top:12px;color:var(--accent);font-size:8px;font-style:normal;font-weight:900;text-transform:uppercase;letter-spacing:.08em}.dashboard-kpi-amber{--accent:#f59e0b}.dashboard-kpi-blue{--accent:#3b82f6}.dashboard-kpi-green{--accent:#22c55e}.dashboard-kpi-violet{--accent:#a78bfa}
.dashboard-warning{margin:0 0 14px;padding:11px 14px;border:1px solid rgba(245,158,11,.32);border-radius:12px;background:rgba(245,158,11,.08);color:#fcd34d;font-size:11px;font-weight:700}
.dashboard-feature-grid{display:grid;grid-template-columns:minmax(0,1.72fr) minmax(320px,.72fr);gap:14px;margin-bottom:14px;align-items:stretch}.dashboard-panel{min-width:0;border:1px solid var(--dash-line);border-radius:18px;background:linear-gradient(150deg,rgba(14,32,55,.98),rgba(9,25,45,.98));box-shadow:0 13px 35px rgba(0,0,0,.11);overflow:hidden}.dashboard-panel-head{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:16px 18px;border-bottom:1px solid rgba(49,73,101,.62)}.dashboard-panel-head.compact{padding:12px 15px}.dashboard-panel-head h2{margin:3px 0 0;font-size:14px;line-height:1.2;letter-spacing:-.01em;text-transform:capitalize}.dashboard-panel-head a,.dashboard-head-action{border:0;background:transparent;color:#7eb0ff;font:inherit;font-size:9px;font-weight:900;text-decoration:none;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;cursor:pointer}.dashboard-panel-head a:hover,.dashboard-head-action:hover{color:#fff}
.dashboard-banner-panel{position:relative;display:grid;grid-template-rows:minmax(0,1fr) auto;min-height:260px}.dashboard-banner{position:relative;height:clamp(210px,21vw,300px);background:#091a2f;overflow:hidden}.dashboard-banner-slide{position:absolute;inset:0;opacity:0;transition:opacity .5s ease}.dashboard-banner-slide.active{opacity:1}.dashboard-banner-slide img{display:block;width:100%;height:100%;object-fit:contain;background:#091a2f}.dashboard-banner-slide:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(5,15,29,.12),transparent 45%,rgba(5,15,29,.05))}.dashboard-banner-empty{display:grid;place-items:center;height:100%;min-height:210px;color:#6f89a8;font-size:11px;font-weight:800}.dashboard-banner-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 14px;border-top:1px solid var(--dash-line);background:#0b1c31}.dashboard-banner-bar strong{display:block;margin-top:2px;overflow:hidden;max-width:720px;color:#edf5ff;font-size:10.5px;text-overflow:ellipsis;white-space:nowrap}.dashboard-banner-controls{display:flex;align-items:center;gap:7px}.dashboard-banner-controls button{display:grid;place-items:center;width:27px;height:27px;border:1px solid #294969;border-radius:8px;background:#102640;color:#bcd3ef;font-size:18px;cursor:pointer}.dashboard-banner-controls button:hover{border-color:#3b82f6;color:#fff}.dashboard-banner-controls button:disabled{cursor:default;opacity:.35}.dashboard-banner-controls span{min-width:34px;color:#7f98b7;font-size:8px;font-weight:850;text-align:center}
.dashboard-calendar-panel{min-height:248px}.dashboard-calendar-weekdays,.dashboard-calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;padding:0 12px}.dashboard-calendar-weekdays{padding-top:9px;color:#66809f;font-size:7px;font-weight:900;text-align:center}.dashboard-calendar-grid{padding-top:5px}.dashboard-calendar-day{position:relative;display:grid;place-items:center;height:23px;padding:0;border:0;border-radius:7px;background:transparent;color:#aebed2;font:inherit;font-size:8.5px;font-weight:750}.dashboard-calendar-day:not(.muted):not(:disabled){cursor:pointer}.dashboard-calendar-day:not(.muted):not(:disabled):hover{outline:1px solid #4d83c4;background:#18385d;color:#fff}.dashboard-calendar-day.muted,.dashboard-calendar-day:disabled{color:#344d6a;cursor:default}.dashboard-calendar-day.today{background:#2563eb;color:#fff;box-shadow:0 5px 14px rgba(37,99,235,.32)}.dashboard-calendar-day.has-event:not(.today){color:#fff;background:#153254}.dashboard-calendar-day.has-event:after{content:"";position:absolute;bottom:2px;width:3px;height:3px;border-radius:50%;background:#f59e0b}.dashboard-upcoming{margin:8px 12px 10px;padding-top:7px;border-top:1px solid rgba(35,59,89,.62)}.dashboard-upcoming-item{display:flex;align-items:center;gap:8px;min-width:0;padding:4px 0}.dashboard-upcoming-item+.dashboard-upcoming-item{border-top:1px solid rgba(35,59,89,.42)}.dashboard-upcoming-date{min-width:34px;color:#60a5fa;font-size:8px;font-weight:900;text-transform:uppercase}.dashboard-upcoming-copy{min-width:0}.dashboard-upcoming-copy strong{display:block;overflow:hidden;font-size:8.5px;text-overflow:ellipsis;white-space:nowrap}.dashboard-upcoming-copy small{display:block;margin-top:2px;color:#7690af;font-size:7.5px}.dashboard-upcoming-empty{padding:7px 0;color:#718aa8;font-size:8px;text-align:center}
.dashboard-empty{display:grid;place-items:center;min-height:180px;padding:25px;text-align:center}.dashboard-empty i{display:grid;place-items:center;width:48px;height:48px;margin-bottom:10px;border-radius:15px;background:rgba(34,197,94,.1);color:#4ade80;font-size:22px;font-style:normal}.dashboard-empty strong{font-size:13px}.dashboard-empty p{margin:5px 0 0;color:var(--dash-muted);font-size:10px}
.dashboard-shortcuts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;padding:12px}.dashboard-shortcuts-wide .dashboard-shortcuts{grid-template-columns:repeat(6,minmax(0,1fr))}.dashboard-shortcuts a{position:relative;display:flex;min-width:0;align-items:center;gap:7px;padding:9px 8px;border:1px solid #223d5c;border-radius:10px;background:#0b1b30;color:#bfd0e4;font-size:8.5px;font-weight:850;text-decoration:none;white-space:nowrap}.dashboard-shortcuts a:hover{border-color:#3b82f6;color:#fff;background:#102746}.dashboard-shortcuts span{color:#60a5fa;font-size:12px}.dashboard-shortcuts b{position:absolute;right:4px;top:4px;min-width:16px;padding:2px 4px;border-radius:99px;background:#ef4444;color:#fff;font-size:7px;text-align:center}
.dashboard-bottom-grid{display:grid;grid-template-columns:1fr 1fr 1.05fr;gap:14px;margin-top:14px}.dashboard-doc-list,.dashboard-news{padding:7px 12px 11px;min-height:210px}.dashboard-doc-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:10px 5px;color:inherit;text-decoration:none}.dashboard-doc-item+.dashboard-doc-item{border-top:1px solid rgba(35,59,89,.55)}.dashboard-doc-item:hover strong{color:#7eb0ff}.dashboard-doc-item strong{display:block;overflow:hidden;font-size:10.5px;text-overflow:ellipsis;white-space:nowrap}.dashboard-doc-item p{margin:4px 0 0;color:var(--dash-muted);font-size:8.5px}.dashboard-doc-status{padding:5px 7px;border-radius:7px;background:rgba(34,197,94,.1);color:#4ade80;font-size:7.5px;font-weight:900;text-transform:uppercase}.dashboard-doc-status.refused{background:rgba(244,63,94,.1);color:#fb7185}.dashboard-news-item{display:block;padding:10px 5px}.dashboard-news-item+.dashboard-news-item{border-top:1px solid rgba(35,59,89,.55)}.dashboard-news-item div{display:flex;align-items:center;gap:7px}.dashboard-news-item span{padding:3px 6px;border-radius:6px;background:rgba(59,130,246,.12);color:#79adff;font-size:7px;font-weight:900;text-transform:uppercase}.dashboard-news-item time{margin-left:auto;color:#647f9f;font-size:8px}.dashboard-news-item strong{display:block;margin-top:5px;font-size:10.5px}.dashboard-news-item p{display:-webkit-box;overflow:hidden;margin:4px 0 0;color:var(--dash-muted);font-size:9px;line-height:1.45;-webkit-box-orient:vertical;-webkit-line-clamp:2}
.dashboard-modal{position:fixed;inset:0;z-index:5000;display:grid;place-items:center;padding:22px;background:rgba(2,8,20,.82);backdrop-filter:blur(8px)}.dashboard-modal[hidden]{display:none}.dashboard-modal-card{width:min(900px,100%);max-height:min(720px,92vh);overflow:hidden;border:1px solid #2b4a70;border-radius:22px;background:#0a1a2e;color:#f7fbff;box-shadow:0 30px 90px rgba(0,0,0,.5)}.dashboard-modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid #213b5b;background:#0d2039}.dashboard-modal-head h2{margin:4px 0 0;font-size:20px}.dashboard-modal-actions{display:flex;align-items:center;gap:9px}.dashboard-modal-head #closeFullAgenda,.dashboard-modal-head #closeEventModal{display:grid;place-items:center;width:35px;height:35px;padding:0;border:1px solid #315071;border-radius:10px;background:#102640;color:#d9e8fa;font-size:24px;cursor:pointer}.dashboard-modal-primary{height:35px;padding:0 13px;border:1px solid #3779d4;border-radius:10px;background:#2563eb;color:#fff;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;cursor:pointer}.dashboard-modal-primary:hover{background:#3475eb}.dashboard-modal-body{display:grid;grid-template-columns:minmax(300px,.8fr) minmax(0,1.2fr);gap:20px;max-height:calc(92vh - 76px);overflow:auto;padding:20px}.dashboard-modal-body section{min-width:0}.dashboard-modal-body h3{margin:0 0 12px;font-size:13px}.dashboard-full-calendar{padding:16px;border:1px solid #203d60;border-radius:16px;background:#0c2038}.dashboard-full-calendar .dashboard-calendar-weekdays,.dashboard-full-calendar .dashboard-calendar-grid{padding-left:0;padding-right:0}.dashboard-calendar-grid.large{gap:5px}.dashboard-calendar-grid.large .dashboard-calendar-day{height:36px;font-size:10px}.dashboard-calendar-legend{display:flex;align-items:center;gap:7px;margin-top:13px;color:#7892b1;font-size:8px}.dashboard-calendar-legend span{width:7px;height:7px;border-radius:50%;background:#f59e0b}.dashboard-full-agenda-list{display:grid;gap:8px}.dashboard-full-agenda-item{display:grid;grid-template-columns:52px minmax(0,1fr);gap:11px;padding:12px;border:1px solid #203b5c;border-radius:13px;background:#0c2038}.dashboard-full-agenda-date{display:grid;place-items:center;border-radius:10px;background:#15365b;color:#fff}.dashboard-full-agenda-date b{font-size:16px}.dashboard-full-agenda-date small{color:#87b7f8;font-size:7px;font-weight:900;text-transform:uppercase}.dashboard-full-agenda-copy{min-width:0}.dashboard-full-agenda-copy strong{display:block;font-size:11px}.dashboard-full-agenda-copy p{margin:5px 0 0;color:#8fa6c3;font-size:9px}.dashboard-full-agenda-empty{padding:40px 20px;border:1px dashed #294969;border-radius:14px;color:#7f98b7;font-size:10px;text-align:center}.dashboard-event-modal{z-index:5100}.dashboard-event-card{width:min(600px,100%)}.dashboard-event-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:20px}.dashboard-field{display:grid;gap:6px;min-width:0}.dashboard-field-wide{grid-column:1/-1}.dashboard-field>span{color:#91a8c4;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.09em}.dashboard-field input,.dashboard-field select{width:100%;height:41px;padding:0 12px;border:1px solid #29496d;border-radius:10px;outline:none;background:#091a2e;color:#edf6ff;font:inherit;font-size:11px}.dashboard-field input:focus,.dashboard-field select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.13)}.dashboard-field input:disabled{opacity:.42;cursor:not-allowed}.dashboard-check{display:flex;align-items:center;gap:9px;color:#b8c9dc;font-size:10px;font-weight:750;cursor:pointer}.dashboard-check input{accent-color:#2563eb}.dashboard-event-actions{display:flex;justify-content:flex-end;gap:9px;padding-top:4px}.dashboard-event-actions button{height:39px;padding:0 16px;border:1px solid #2a4a70;border-radius:10px;background:#102640;color:#c9daed;font-size:9px;font-weight:900;text-transform:uppercase;cursor:pointer}.dashboard-event-actions button[type=submit]{border-color:#2563eb;background:#2563eb;color:#fff}.dashboard-event-actions button:disabled{cursor:wait;opacity:.6}.dashboard-event-feedback{padding:10px 12px;border:1px solid rgba(244,63,94,.35);border-radius:10px;background:rgba(244,63,94,.09);color:#fda4af;font-size:10px}.dashboard-event-feedback.success{border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.09);color:#86efac}
.dashboard-toast{position:fixed;right:24px;bottom:24px;z-index:5200;max-width:min(390px,calc(100vw - 32px));padding:13px 16px;border:1px solid rgba(34,197,94,.42);border-radius:12px;background:#0b2b27;color:#9ff3c3;font-size:11px;font-weight:800;box-shadow:0 16px 45px rgba(0,0,0,.38)}.dashboard-toast.error{border-color:rgba(244,63,94,.42);background:#321523;color:#fecdd3}.dashboard-loading{display:grid;gap:8px;padding:13px}.dashboard-loading span{height:48px;border-radius:11px;background:linear-gradient(90deg,#10233c,#173457,#10233c);background-size:200% 100%;animation:dash-shimmer 1.3s infinite}.dashboard-loading.small span{height:38px}
@keyframes dash-shimmer{to{background-position:-200% 0}}@keyframes dash-spin{to{transform:rotate(360deg)}}
@media(max-width:1180px){.dashboard-kpis{grid-template-columns:repeat(2,1fr)}.dashboard-bottom-grid{grid-template-columns:1fr 1fr}.dashboard-news-panel{grid-column:1/-1}.dashboard-feature-grid{grid-template-columns:minmax(0,1.45fr) minmax(300px,.8fr)}.dashboard-shortcuts-wide .dashboard-shortcuts{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:800px){.dashboard-wrap{padding:18px 14px 34px}.dashboard-hero{align-items:flex-start;flex-direction:column}.dashboard-hero-actions{width:100%;justify-content:space-between}.dashboard-feature-grid,.dashboard-bottom-grid{grid-template-columns:1fr}.dashboard-news-panel{grid-column:auto}.dashboard-kpis{gap:8px}.dashboard-kpi{min-height:80px}.dashboard-kpi em{display:none}.dashboard-banner-panel{min-height:220px}.dashboard-banner{min-height:170px}.dashboard-modal-body{grid-template-columns:1fr}.dashboard-modal{padding:10px}.dashboard-modal-card{max-height:96vh}.dashboard-modal-body{max-height:calc(96vh - 72px)}}
@media(max-width:520px){.dashboard-kpis{grid-template-columns:1fr}.dashboard-shortcuts{grid-template-columns:repeat(2,1fr)}.dashboard-modal-actions{gap:5px}.dashboard-modal-primary{padding:0 8px;font-size:7.5px}.dashboard-event-form{grid-template-columns:1fr}.dashboard-field-wide{grid-column:auto}}
</style>

<script>
(() => {
    const $ = (id) => document.getElementById(id);
    const shortDateFormatter = new Intl.DateTimeFormat('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric'});
    const weekdayFormatter = new Intl.DateTimeFormat('pt-BR', {weekday: 'short'});
    let bannerItems = [];
    let bannerIndex = 0;
    let bannerTimer = null;
    let agendaItems = [];
    let toastTimer = null;

    function parseDate(value) {
        if (!value) return null;
        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized.length === 10 ? normalized + 'T12:00:00' : normalized);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function formatDate(value) {
        const date = parseDate(value);
        return date ? shortDateFormatter.format(date) : 'Data não informada';
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function emptyState(title, message) {
        const root = element('div', 'dashboard-empty');
        root.append(element('i', '', '✓'), element('strong', '', title), element('p', '', message));
        return root;
    }

    function localIso(date = new Date()) {
        return date.getFullYear() + '-'
            + String(date.getMonth() + 1).padStart(2, '0') + '-'
            + String(date.getDate()).padStart(2, '0');
    }

    function calendarDay(iso, day, isToday, items) {
        const hasEvent = items.some((item) => item.data === iso);
        const isPast = iso < localIso();
        const cell = element(
            'button',
            'dashboard-calendar-day' + (isToday ? ' today' : '') + (hasEvent ? ' has-event' : ''),
            String(day)
        );
        cell.type = 'button';
        cell.disabled = isPast;
        cell.setAttribute('aria-label', (isPast ? 'Dia ' : 'Agendar compromisso em ') + formatDate(iso));
        if (hasEvent) {
            cell.title = items.filter((item) => item.data === iso).map((item) => item.titulo).join(' · ');
        } else if (!isPast) {
            cell.title = 'Agendar compromisso';
        }
        if (!isPast) cell.addEventListener('click', () => openAgendaComposer(iso));
        return cell;
    }

    function renderCalendar(items) {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth();
        const firstWeekday = new Date(year, month, 1).getDay();
        const totalDays = new Date(year, month + 1, 0).getDate();
        const grid = $('dashboardCalendarGrid');
        grid.replaceChildren();
        $('calendarMonth').textContent = new Intl.DateTimeFormat('pt-BR', {month: 'long', year: 'numeric'}).format(today);

        for (let i = 0; i < firstWeekday; i += 1) {
            grid.append(element('span', 'dashboard-calendar-day muted', ''));
        }
        for (let day = 1; day <= totalDays; day += 1) {
            const iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const isToday = day === today.getDate();
            grid.append(calendarDay(iso, day, isToday, items));
        }

        const upcoming = $('dashboardAgenda');
        upcoming.replaceChildren();
        if (!items.length) {
            upcoming.append(element('div', 'dashboard-upcoming-empty', 'Nenhum compromisso nos próximos 30 dias.'));
            return;
        }
        items.slice(0, 2).forEach((item) => {
            const date = parseDate(item.data);
            const row = element('div', 'dashboard-upcoming-item');
            const dateLabel = date ? String(date.getDate()).padStart(2, '0') + ' ' + weekdayFormatter.format(date).replace('.', '') : '--';
            const copy = element('div', 'dashboard-upcoming-copy');
            const interval = item.hora_inicio ? item.hora_inicio + (item.hora_fim ? '–' + item.hora_fim : '') : 'Dia inteiro';
            copy.append(element('strong', '', item.titulo), element('small', '', interval + ' · ' + item.local));
            row.append(element('span', 'dashboard-upcoming-date', dateLabel), copy);
            upcoming.append(row);
        });
    }

    function renderFullAgenda(items) {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth();
        const firstWeekday = new Date(year, month, 1).getDay();
        const totalDays = new Date(year, month + 1, 0).getDate();
        const grid = $('fullCalendarGrid');
        grid.replaceChildren();
        $('fullCalendarMonth').textContent = new Intl.DateTimeFormat('pt-BR', {month: 'long', year: 'numeric'}).format(today);

        for (let i = 0; i < firstWeekday; i += 1) grid.append(element('span', 'dashboard-calendar-day muted', ''));
        for (let day = 1; day <= totalDays; day += 1) {
            const iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            grid.append(calendarDay(iso, day, day === today.getDate(), items));
        }

        const list = $('fullAgendaList');
        list.replaceChildren();
        if (!items.length) {
            list.append(element('div', 'dashboard-full-agenda-empty', 'Nenhum compromisso nos próximos 30 dias.'));
            return;
        }
        items.forEach((item) => {
            const date = parseDate(item.data);
            const row = element('article', 'dashboard-full-agenda-item');
            const badge = element('div', 'dashboard-full-agenda-date');
            badge.append(
                element('b', '', date ? String(date.getDate()).padStart(2, '0') : '--'),
                element('small', '', date ? weekdayFormatter.format(date).replace('.', '') : '')
            );
            const copy = element('div', 'dashboard-full-agenda-copy');
            const interval = item.hora_inicio ? item.hora_inicio + (item.hora_fim ? '–' + item.hora_fim : '') : 'Dia inteiro';
            const audience = item.visibilidade === 'GERAL' ? 'Para todos' : 'Pessoal';
            copy.append(element('strong', '', item.titulo), element('p', '', interval + ' · ' + item.local + ' · ' + audience));
            row.append(badge, copy);
            list.append(row);
        });
    }

    function syncModalLock() {
        const modalOpen = !$('dashboardAgendaModal').hidden || !$('dashboardEventModal').hidden;
        document.body.style.overflow = modalOpen ? 'hidden' : '';
    }

    function toggleAgendaTimes(allDay) {
        document.querySelectorAll('.dashboard-time-field input').forEach((input) => {
            input.disabled = allDay;
            input.required = !allDay;
        });
    }

    function setEventFeedback(message = '', success = false) {
        const feedback = $('dashboardEventFeedback');
        feedback.hidden = !message;
        feedback.textContent = message;
        feedback.classList.toggle('success', success);
    }

    function openAgendaComposer(dateValue = localIso()) {
        const form = $('dashboardEventForm');
        form.reset();
        const today = localIso();
        const maximum = new Date();
        maximum.setFullYear(maximum.getFullYear() + 2);
        $('dashboardEventDate').min = today;
        $('dashboardEventDate').max = localIso(maximum);
        $('dashboardEventDate').value = dateValue < today ? today : dateValue;
        $('dashboardAllDay').checked = false;
        toggleAgendaTimes(false);
        setEventFeedback();
        $('dashboardEventModal').hidden = false;
        syncModalLock();
        window.setTimeout(() => form.elements.titulo.focus(), 0);
    }

    function closeAgendaComposer() {
        $('dashboardEventModal').hidden = true;
        setEventFeedback();
        syncModalLock();
    }

    function showToast(message, isError = false) {
        const toast = $('dashboardToast');
        if (toastTimer) window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.toggle('error', isError);
        toast.hidden = false;
        toastTimer = window.setTimeout(() => { toast.hidden = true; }, 4200);
    }

    async function saveAgendaEvent(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const button = $('saveAgendaEvent');
        const originalLabel = button.textContent;
        setEventFeedback();
        button.disabled = true;
        button.textContent = 'Salvando...';

        try {
            const formData = new FormData(form);
            const csrfToken = String(formData.get('csrf_token') || '');
            const response = await fetch('api/dashboard_agenda_salvar.php', {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-Token': csrfToken},
                body: formData
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Não foi possível salvar o compromisso.');
            }
            closeAgendaComposer();
            showToast(data.message || 'Compromisso salvo com sucesso.');
            await loadDashboard();
        } catch (error) {
            setEventFeedback(error.message || 'Não foi possível salvar o compromisso.');
        } finally {
            button.disabled = false;
            button.textContent = originalLabel;
        }
    }

    function showBanner(index) {
        if (!bannerItems.length) return;
        bannerIndex = (index + bannerItems.length) % bannerItems.length;
        $('dashboardBanner').querySelectorAll('.dashboard-banner-slide').forEach((slide, position) => {
            slide.classList.toggle('active', position === bannerIndex);
        });
        $('dashboardBannerTitle').textContent = bannerItems[bannerIndex].titulo || 'Campanha da empresa';
        $('bannerPosition').textContent = (bannerIndex + 1) + ' / ' + bannerItems.length;
    }

    function scheduleBanner() {
        if (bannerTimer) window.clearInterval(bannerTimer);
        if (bannerItems.length > 1) bannerTimer = window.setInterval(() => showBanner(bannerIndex + 1), 7000);
    }

    function renderBanners(items) {
        bannerItems = items;
        bannerIndex = 0;
        const root = $('dashboardBanner');
        root.replaceChildren();
        if (!items.length) {
            root.append(element('div', 'dashboard-banner-empty', 'Nenhuma campanha ativa neste período.'));
            $('dashboardBannerTitle').textContent = 'Campanhas da empresa';
            $('bannerPosition').textContent = '0 / 0';
            $('bannerPrevious').disabled = true;
            $('bannerNext').disabled = true;
            scheduleBanner();
            return;
        }
        items.forEach((item, index) => {
            const slide = element('div', 'dashboard-banner-slide' + (index === 0 ? ' active' : ''));
            const image = element('img');
            image.src = item.imagem;
            image.alt = item.titulo || 'Campanha da empresa';
            image.loading = index === 0 ? 'eager' : 'lazy';
            slide.append(image);
            root.append(slide);
        });
        $('bannerPrevious').disabled = items.length < 2;
        $('bannerNext').disabled = items.length < 2;
        showBanner(0);
        scheduleBanner();
    }

    function renderDocuments(rootId, items, signatureHistory = false) {
        const root = $(rootId);
        root.replaceChildren();
        if (!items.length) {
            root.append(emptyState(signatureHistory ? 'Sem histórico' : 'Nenhum documento', signatureHistory ? 'Suas assinaturas concluídas aparecerão aqui.' : 'Não há publicações recentes no seu escopo.'));
            return;
        }
        items.slice(0, 5).forEach((item) => {
            const link = element('a', 'dashboard-doc-item');
            link.href = item.url;
            const copy = element('div');
            copy.append(
                element('strong', '', item.titulo),
                element('p', '', signatureHistory ? formatDate(item.data) : (item.setor + ' · ' + formatDate(item.data)))
            );
            const status = signatureHistory ? item.status : 'Publicado';
            link.append(copy, element('span', 'dashboard-doc-status' + (status === 'RECUSADO' ? ' refused' : ''), status));
            root.append(link);
        });
    }

    function renderNews(items) {
        const root = $('dashboardNews');
        root.replaceChildren();
        if (!items.length) {
            root.append(emptyState('Sem novidades', 'Os comunicados da empresa aparecerão aqui.'));
            return;
        }
        items.slice(0, 4).forEach((item) => {
            const article = element('article', 'dashboard-news-item');
            const meta = element('div');
            meta.append(element('span', '', item.categoria || 'Geral'), element('time', '', formatDate(item.data)));
            article.append(meta, element('strong', '', item.titulo));
            if (item.resumo) article.append(element('p', '', item.resumo));
            root.append(article);
        });
    }

    function applyData(data) {
        $('kpiAssinaturas').textContent = data.resumo.assinaturas_pendentes;
        $('kpiPendencias').textContent = data.resumo.documentos_pendentes;
        $('kpiPublicados').textContent = data.resumo.documentos_publicados;
        $('kpiEnvios').textContent = data.resumo.envios_em_andamento;
        agendaItems = data.agenda || [];
        renderBanners(data.banners || []);
        renderCalendar(agendaItems);
        renderFullAgenda(agendaItems);
        renderDocuments('dashboardPublished', data.publicados || []);
        renderDocuments('dashboardSigned', data.assinaturas_recentes || [], true);
        renderNews(data.comunicados || []);

        const shortcut = $('shortcutContratos');
        shortcut.hidden = !data.permissoes.contratos;
        const badge = $('badgeContratos');
        const alerts = Number(data.resumo.contratos_em_alerta || 0);
        badge.hidden = alerts < 1;
        badge.textContent = alerts;

        const warning = $('dashboardWarning');
        if ((data.indisponiveis || []).length) {
            warning.hidden = false;
            warning.textContent = 'Alguns blocos não puderam ser atualizados agora: ' + data.indisponiveis.join(', ') + '.';
        } else {
            warning.hidden = true;
        }
    }

    async function loadDashboard() {
        const button = $('dashboardRefresh');
        button.classList.add('is-loading');
        button.disabled = true;
        try {
            const response = await fetch('api/dashboard_resumo.php', {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'Não foi possível carregar o painel.');
            applyData(data);
        } catch (error) {
            const warning = $('dashboardWarning');
            warning.hidden = false;
            warning.textContent = 'Não foi possível carregar todos os dados. Atualize a página e tente novamente.';
        } finally {
            button.classList.remove('is-loading');
            button.disabled = false;
        }
    }

    $('bannerPrevious').addEventListener('click', () => { showBanner(bannerIndex - 1); scheduleBanner(); });
    $('bannerNext').addEventListener('click', () => { showBanner(bannerIndex + 1); scheduleBanner(); });
    $('openFullAgenda').addEventListener('click', () => {
        renderFullAgenda(agendaItems);
        $('dashboardAgendaModal').hidden = false;
        syncModalLock();
    });
    $('closeFullAgenda').addEventListener('click', () => {
        $('dashboardAgendaModal').hidden = true;
        syncModalLock();
    });
    $('newAgendaEvent').addEventListener('click', () => openAgendaComposer());
    $('closeEventModal').addEventListener('click', closeAgendaComposer);
    $('cancelEventModal').addEventListener('click', closeAgendaComposer);
    $('dashboardAllDay').addEventListener('change', (event) => toggleAgendaTimes(event.currentTarget.checked));
    $('dashboardEventForm').addEventListener('submit', saveAgendaEvent);
    $('dashboardAgendaModal').addEventListener('click', (event) => {
        if (event.target === $('dashboardAgendaModal')) $('closeFullAgenda').click();
    });
    $('dashboardEventModal').addEventListener('click', (event) => {
        if (event.target === $('dashboardEventModal')) closeAgendaComposer();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (!$('dashboardEventModal').hidden) {
            closeAgendaComposer();
        } else if (!$('dashboardAgendaModal').hidden) {
            $('closeFullAgenda').click();
        }
    });
    $('dashboardRefresh').addEventListener('click', loadDashboard);
    loadDashboard();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
