<?php
$currentGovPage = basename($_SERVER['PHP_SELF']);
$govEhAdmin = !empty($_SESSION['is_admin']);

$govTabs = [
    [
        'file' => 'governanca_organograma.php',
        'label' => 'Organograma',
        'icon' => '⌘',
    ],
    [
        'file' => 'governanca_ti.php',
        'label' => 'Estrutura',
        'icon' => '▦',
    ],
    [
        'file' => 'governanca_pessoas.php',
        'label' => 'Pessoas',
        'icon' => '♙',
    ],
    [
        'file' => 'governanca_minha_area.php',
        'label' => 'Minha Área',
        'icon' => '⌂',
    ],
];

if ($govEhAdmin) {
    $govTabs[] = [
        'file' => 'governanca_liderancas.php',
        'label' => 'Gestores',
        'icon' => '♛',
    ];
    $govTabs[] = [
        'file' => 'governanca_acessos.php',
        'label' => 'Gestão de Acessos',
        'icon' => '⊙',
    ];
}
?>

<nav class="gov-module-nav" aria-label="Navegação da Governança">
    <div class="gov-module-nav-inner">
        <div class="gov-module-brand">
            <span class="gov-module-brand-icon">G</span>
            <div>
                <strong>Governança</strong>
                <small>Comercial Souza</small>
            </div>
        </div>

        <div class="gov-module-tabs">
            <?php foreach ($govTabs as $tab): ?>
                <a
                    href="<?= htmlspecialchars($tab['file']) ?>"
                    class="gov-module-tab<?= $currentGovPage === $tab['file'] ? ' is-active' : '' ?>"
                >
                    <span class="gov-module-tab-icon"><?= htmlspecialchars($tab['icon']) ?></span>
                    <span><?= htmlspecialchars($tab['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<style>
.gov-module-nav{
    flex:0 0 auto;
    background:#fff;
    border-bottom:1px solid #dbe4ee;
    box-shadow:0 1px 2px rgba(15,23,42,.03);
    position:relative;
    z-index:25;
}
.gov-module-nav-inner{
    min-height:48px;
    display:flex;
    align-items:center;
    gap:14px;
    padding:0 14px;
}
.gov-module-brand{
    flex:0 0 auto;
    display:flex;
    align-items:center;
    gap:8px;
    padding-right:13px;
    border-right:1px solid #e2e8f0;
}
.gov-module-brand-icon{
    width:27px;
    height:27px;
    border-radius:8px;
    display:grid;
    place-items:center;
    background:#0d1b3e;
    color:#fff;
    font-size:10px;
    font-weight:900;
}
.gov-module-brand strong{
    display:block;
    color:#0f172a;
    font-size:11px;
    line-height:1.05;
    font-weight:900;
}
.gov-module-brand small{
    display:block;
    margin-top:2px;
    color:#94a3b8;
    font-size:8px;
    line-height:1;
}
.gov-module-tabs{
    min-width:0;
    display:flex;
    align-items:center;
    gap:4px;
    overflow-x:auto;
    scrollbar-width:none;
}
.gov-module-tabs::-webkit-scrollbar{display:none}
.gov-module-tab{
    min-height:34px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:0 10px;
    border:1px solid transparent;
    border-radius:8px;
    color:#64748b;
    background:transparent;
    font-size:10px;
    font-weight:800;
    white-space:nowrap;
    transition:.15s ease;
}
.gov-module-tab:hover{
    color:#1e293b;
    background:#f8fafc;
    border-color:#e2e8f0;
}
.gov-module-tab.is-active{
    color:#1d4ed8;
    background:#eff6ff;
    border-color:#bfdbfe;
}
.gov-module-tab-icon{
    width:16px;
    text-align:center;
    font-size:12px;
}
@media (max-width:900px){
    .gov-module-nav-inner{
        padding:0 8px;
        gap:7px;
    }
    .gov-module-brand{
        padding-right:7px;
    }
    .gov-module-brand div{
        display:none;
    }
    .gov-module-tab{
        padding:0 8px;
    }
}
@media (max-width:640px){
    .gov-module-brand{display:none}
    .gov-module-tabs{width:100%}
}
</style>
