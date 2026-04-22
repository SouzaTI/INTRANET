<?php 
require_once 'config.php';
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// 1. INFORMAÇÕES BÁSICAS DO USUÁRIO
$user_id = $_SESSION['user_id'] ?? 0;
$setor_usuario = strtoupper($_SESSION['setor_principal'] ?? '');
$is_admin = $_SESSION['is_admin'] ?? false;

// 2. BUSCA AS PASTAS EXTRAS QUE ELE TEM PERMISSÃO LÁ DA GESTÃO DE ACESSOS
$pastas_extras = [];
if ($user_id > 0) {
    $stmt_perm = $pdo_intra->prepare("SELECT pasta_nome FROM permissoes_pastas WHERE user_id = ?");
    $stmt_perm->execute([$user_id]);
    $pastas_extras = $stmt_perm->fetchAll(PDO::FETCH_COLUMN);
    $pastas_extras = array_map('strtoupper', $pastas_extras); // Padroniza tudo em maiúsculo
}

// 3. OS CURSOS (O "Netflix" do Winthor)
$setores = [
    'TI' => [
        ['n' => '535', 'p' => '1', 'url' => '8FCBhmB4m7w'],
        ['n' => '528', 'p' => '1', 'url' => '3uj56rG2YTk'],
        ['n' => '528', 'p' => '2', 'url' => '1fYnrnq0hC0'],
        ['n' => '528', 'p' => '3', 'url' => 'gDA4mjp93pU'],
        ['n' => '530', 'p' => '1', 'url' => 'mr1ePm1ehMM'],
        ['n' => '530', 'p' => '2', 'url' => 'evIuvjfzCvo'],
    ],
    'CADASTRO' => [
        ['n' => '513', 'p' => '1', 'url' => 'NBWxqDiLImE'],
        ['n' => '571', 'p' => '1', 'url' => 'MxaP2UsFytw'],
    ],
    'FISCAL' => [
        ['n' => '543', 'p' => '1', 'url' => 'YK-bEnJ1hzA'],
        ['n' => '580', 'p' => '1', 'url' => 'BpCgFKTEEK8'],
    ]
];

// 4. MÁGICA: DESCOBRE O QUE ELE PODE ASSISTIR (Admin OU Setor Principal OU Permissão Extra)
$setores_permitidos = [];
foreach(array_keys($setores) as $s) {
    $s_upper = strtoupper($s);
    // Para bater com o banco, se o botão for "TI", a pasta é "FACILITIES & TI", então validamos:
    if( $is_admin || 
        $setor_usuario === $s_upper || 
        in_array($s_upper, $pastas_extras) || 
        ($s_upper === 'TI' && in_array('FACILITIES & TI', $pastas_extras))
    ) {
        $setores_permitidos[] = $s;
    }
}
$setor_inicial = !empty($setores_permitidos) ? $setores_permitidos[0] : '';
?>

<main class="flex-1 bg-slate-50 p-8 overflow-y-auto">
    <div class="max-w-[95%] 2xl:max-w-[1600px] mx-auto mb-8">
        <h1 id="main-title" class="text-3xl font-black text-navy-900 italic uppercase tracking-tighter">Academia Winthor</h1>
        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Selecione um setor para iniciar as rotinas</p>
        
        <div class="flex gap-2 mt-6 overflow-x-auto pb-2 custom-scrollbar">
            <?php foreach($setores_permitidos as $setor): ?>
                <button onclick="filtrarSetor('<?= $setor ?>')" class="btn-setor px-6 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition-all shadow-sm whitespace-nowrap">
                    <?= $setor ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if(!empty($setor_inicial)): ?>
    <div class="max-w-[95%] 2xl:max-w-[1600px] mx-auto grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-black rounded-[2.5rem] shadow-2xl overflow-hidden border-4 border-white aspect-video relative">
                <iframe id="youtubePlayer" class="w-full h-full" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-200">
                <h3 id="video-title" class="font-bold text-lg text-navy-900 mb-2">Carregando...</h3>
                <p class="text-slate-600 text-sm italic">Treinamento oficial de rotinas operacionais.</p>
            </div>
        </div>

        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-200">
                <h3 class="text-xs font-black text-slate-400 uppercase mb-4 tracking-widest">Playlist do Setor</h3>
                <div id="playlist-container" class="space-y-2 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                    </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="max-w-[95%] 2xl:max-w-[1600px] mx-auto text-center py-20 bg-white rounded-[2.5rem] border border-slate-200 shadow-sm mt-8">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">🎓</div>
        <h2 class="text-xl font-black text-navy-900 uppercase">Nenhum treinamento disponível</h2>
        <p class="text-slate-500 mt-2 font-medium">Os treinamentos do seu setor ainda não foram cadastrados na Academia Winthor ou você não possui permissão de acesso.</p>
    </div>
    <?php endif; ?>

</main>

<script>
    const dadosCursos = <?= json_encode($setores) ?>;
    const setorInicial = '<?= $setor_inicial ?>'; 

    function filtrarSetor(setor) {
        if(!setor || !dadosCursos[setor]) return;

        const container = document.getElementById('playlist-container');
        container.innerHTML = ''; 

        dadosCursos[setor].forEach((aula, index) => {
            const item = document.createElement('div');
            item.className = "p-4 bg-slate-50 border border-transparent rounded-2xl flex items-center gap-3 hover:bg-blue-50 hover:border-blue-100 cursor-pointer transition-all group";
            item.onclick = () => carregarVideo(aula.url, aula.n, aula.p);
            
            item.innerHTML = `
                <span class="w-8 h-8 rounded-lg bg-white text-slate-400 group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center text-xs font-black shadow-sm transition-all shrink-0">${aula.p}</span>
                <div class="overflow-hidden">
                    <p class="text-[10px] font-black text-navy-900 uppercase truncate">Rotina ${aula.n}</p>
                    <p class="text-[9px] text-slate-400 font-bold group-hover:text-blue-600">PARTE ${aula.p}</p>
                </div>
            `;
            container.appendChild(item);
        });

        // Carrega o primeiro vídeo da playlist selecionada
        if(dadosCursos[setor].length > 0) {
            const primeira = dadosCursos[setor][0];
            carregarVideo(primeira.url, primeira.n, primeira.p);
        }
    }

    function carregarVideo(url, rotina, parte) {
        document.getElementById('youtubePlayer').src = `https://www.youtube.com/embed/${url}?autoplay=1&rel=0`;
        document.getElementById('video-title').innerText = `Rotina ${rotina} - Parte ${parte}`;
    }

    // Inicia a tela automaticamente
    window.onload = () => {
        if(setorInicial) filtrarSetor(setorInicial);
    };
</script>