<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Parsedown.php';

function documentoMarkdownNormalizarMidia(string $caminho): ?string
{
    $caminho = rawurldecode(trim($caminho));
    $caminho = preg_replace('/[?#].*$/', '', $caminho) ?? '';
    $caminho = ltrim(str_replace('\\', '/', $caminho), '/');
    if (!str_starts_with($caminho, 'img/') || str_contains($caminho, '..')) return null;
    return $caminho;
}

function documentoMarkdownExtrairMidias(string $markdown): array
{
    preg_match_all('/!\[[^\]]*\]\(([^)\s]+)(?:\s+["\'][^)]*["\'])?\)/u', $markdown, $matches);
    $midias = [];
    foreach ($matches[1] ?? [] as $caminho) {
        $normalizado = documentoMarkdownNormalizarMidia((string) $caminho);
        if ($normalizado !== null) $midias[] = $normalizado;
    }
    return array_values(array_unique($midias));
}

function documentoMarkdownRenderizar(string $markdown, int $documentoId): string
{
    $parser = new Parsedown();
    $parser->setSafeMode(true);
    $parser->setBreaksEnabled(true);
    $blocos = [];

    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    $markdown = preg_replace_callback(
        '/^:::(info|tip|warning|danger|note)\s*([^\n]*)\n(.*?)^:::\s*$/msu',
        static function (array $match) use ($parser, &$blocos): string {
            $tipo = strtolower($match[1]);
            $titulosPadrao = [
                'info' => 'Informação', 'tip' => 'Dica', 'warning' => 'Atenção',
                'danger' => 'Importante', 'note' => 'Observação',
            ];
            $titulo = trim($match[2]) ?: $titulosPadrao[$tipo];
            $indice = count($blocos);
            $token = 'DOCBLOCO' . $indice . 'TOKEN';
            $blocos[$token] = '<aside class="admonition admonition-' . $tipo . '">'
                . '<div class="admonition-icon" aria-hidden="true">i</div>'
                . '<div><p class="admonition-title">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<div class="admonition-content">' . $parser->text(trim($match[3])) . '</div></div></aside>';
            return "\n\n" . $token . "\n\n";
        },
        $markdown
    ) ?? $markdown;

    $markdown = preg_replace_callback(
        '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+["\'][^)]*["\'])?\)/u',
        static function (array $match) use ($documentoId): string {
            $caminho = documentoMarkdownNormalizarMidia($match[2]);
            if ($caminho === null) return $match[0];
            $url = 'DocumentoCentralMidia.php?id=' . $documentoId . '&arquivo=' . rawurlencode($caminho);
            return '![' . $match[1] . '](' . $url . ')';
        },
        $markdown
    ) ?? $markdown;

    // Os manuais legados usam "1 – instrução" em vez da sintaxe CommonMark "1. instrução".
    // Convertemos isso em seções de procedimento, preservando imagens e parágrafos associados.
    $markdown = preg_replace(
        '/^(\d+)\h*[\x{2013}\x{2014}-]\h*(.+)$/mu',
        "## Passo $1\n\n$2",
        $markdown
    ) ?? $markdown;

    $html = $parser->text($markdown);
    foreach ($blocos as $token => $blocoHtml) {
        $html = str_replace(['<p>' . $token . '</p>', $token], $blocoHtml, $html);
    }

    $html = preg_replace(
        '/<h2>Passo\s+(\d+)<\/h2>/u',
        '<h2 class="procedure-heading"><span>Passo</span><strong>$1</strong></h2>',
        $html
    ) ?? $html;

    $html = preg_replace_callback(
        '/<img src="([^"]+)" alt="([^"]*)"(?: title="([^"]*)")?\s*\/>/u',
        static function (array $match): string {
            $src = $match[1];
            $alt = $match[2];
            $titulo = $match[3] ?? '';
            $legenda = trim(html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $mostrarLegenda = $legenda !== '' && mb_strtoupper($legenda, 'UTF-8') !== 'IA';
            return '<figure class="document-image"><a href="' . $src . '" target="_blank" rel="noopener">'
                . '<img src="' . $src . '" alt="' . $alt . '" loading="lazy"'
                . ($titulo !== '' ? ' title="' . $titulo . '"' : '') . '></a>'
                . ($mostrarLegenda ? '<figcaption>' . $alt . '</figcaption>' : '') . '</figure>';
        },
        $html
    ) ?? $html;

    return $html;
}

function documentoMarkdownCss(): string
{
    return <<<'CSS'
:root{color-scheme:light;--ink:#172033;--muted:#64748b;--line:#dbe3ee;--brand:#1d4ed8;--paper:#fff;--canvas:#eef2f7}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--canvas);color:var(--ink);font:15px/1.72 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;-webkit-font-smoothing:antialiased}
.document-shell{max-width:1080px;min-height:100vh;margin:0 auto;background:var(--paper);box-shadow:0 0 0 1px rgba(148,163,184,.14);padding:48px 64px 80px}
.document-header{margin:-48px -64px 42px;padding:34px 64px 30px;background:linear-gradient(135deg,#0f172a,#172554);color:#fff;border-bottom:4px solid #2563eb}
.document-eyebrow{margin:0 0 9px;color:#93c5fd;font-size:10px;font-weight:900;letter-spacing:.2em;text-transform:uppercase}.document-header h1{max-width:850px;margin:0;font-size:clamp(24px,3vw,36px);line-height:1.17;letter-spacing:-.025em}.document-meta{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:15px;color:#cbd5e1;font-size:12px;font-weight:650}.document-meta span{display:inline-flex;align-items:center;gap:6px}.document-meta span:before{content:"";width:5px;height:5px;border-radius:50%;background:#60a5fa}
.markdown-body{max-width:900px;margin:0 auto}.markdown-body>:first-child{margin-top:0}.markdown-body>:last-child{margin-bottom:0}.markdown-body p{margin:0 0 1.1em}.markdown-body h1,.markdown-body h2,.markdown-body h3,.markdown-body h4{color:#0f172a;line-height:1.3;letter-spacing:-.015em}.markdown-body h1{margin:1.8em 0 .75em;padding-bottom:.42em;border-bottom:2px solid var(--line);font-size:2em}.markdown-body h2{margin:2em 0 .8em;padding-bottom:.35em;border-bottom:1px solid var(--line);font-size:1.45em}.markdown-body h3{margin:1.7em 0 .65em;font-size:1.18em}.markdown-body strong{color:#0f172a;font-weight:800}.markdown-body a{color:var(--brand);text-decoration:none}.markdown-body a:hover{text-decoration:underline}
.admonition{display:grid;grid-template-columns:34px 1fr;gap:14px;margin:0 0 32px;padding:18px 20px;border:1px solid #bfdbfe;border-left:5px solid #2563eb;border-radius:12px;background:#eff6ff}.admonition-icon{display:flex;width:28px;height:28px;align-items:center;justify-content:center;border-radius:50%;background:#2563eb;color:#fff;font-family:Georgia,serif;font-size:17px;font-weight:900}.admonition-title{margin:0 0 5px!important;color:#1e3a8a;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}.admonition-content p{margin:0 0 4px}.admonition-content p:last-child{margin-bottom:0}.admonition-warning{border-color:#fde68a;border-left-color:#d97706;background:#fffbeb}.admonition-warning .admonition-icon{background:#d97706}.admonition-warning .admonition-title{color:#92400e}.admonition-danger{border-color:#fecaca;border-left-color:#dc2626;background:#fef2f2}.admonition-danger .admonition-icon{background:#dc2626}.admonition-danger .admonition-title{color:#991b1b}
.procedure-heading{display:flex!important;align-items:center;gap:10px;margin:42px 0 12px!important;padding:0!important;border:0!important;font-size:13px!important;text-transform:uppercase;letter-spacing:.12em;color:#2563eb!important}.procedure-heading strong{display:inline-flex;width:34px;height:34px;align-items:center;justify-content:center;border-radius:10px;background:#2563eb;color:#fff;font-size:16px;letter-spacing:0;box-shadow:0 5px 12px rgba(37,99,235,.22)}.procedure-heading+ p{font-size:16px;font-weight:650;color:#334155}
.document-image{margin:20px 0 34px;padding:10px;border:1px solid #dbe3ee;border-radius:14px;background:#f8fafc;box-shadow:0 8px 24px rgba(15,23,42,.07)}.document-image a{display:block;line-height:0}.document-image img{display:block;width:auto;max-width:100%;height:auto;max-height:720px;margin:auto;border-radius:8px;background:#fff;object-fit:contain}.document-image figcaption{padding:9px 6px 1px;color:var(--muted);font-size:12px;text-align:center}
.markdown-body ul,.markdown-body ol{margin:1em 0 1.25em;padding-left:1.75em}.markdown-body li{margin:.38em 0}.markdown-body blockquote{margin:1.4em 0;padding:10px 18px;border-left:4px solid #94a3b8;background:#f8fafc;color:#475569}.markdown-body code{padding:.16em .38em;border-radius:5px;background:#e2e8f0;font:13px Consolas,"Liberation Mono",monospace}.markdown-body pre{overflow:auto;margin:1.4em 0;padding:18px;border-radius:12px;background:#0f172a;color:#e2e8f0}.markdown-body pre code{padding:0;background:transparent;color:inherit}.markdown-body table{display:block;width:100%;overflow:auto;margin:1.5em 0;border-collapse:collapse}.markdown-body th,.markdown-body td{padding:9px 12px;border:1px solid #cbd5e1;text-align:left}.markdown-body th{background:#f1f5f9;font-weight:800}.markdown-body hr{height:1px;margin:2em 0;border:0;background:#cbd5e1}
@media(max-width:720px){.document-shell{padding:28px 20px 55px}.document-header{margin:-28px -20px 30px;padding:26px 20px 23px}.document-image{margin-left:-8px;margin-right:-8px}.procedure-heading{margin-top:34px!important}}
@media print{body{background:#fff}.document-shell{max-width:none;padding:20px;box-shadow:none}.document-header{margin:-20px -20px 30px;-webkit-print-color-adjust:exact;print-color-adjust:exact}.document-image{break-inside:avoid;box-shadow:none}.procedure-heading{break-after:avoid}}
CSS;
}
