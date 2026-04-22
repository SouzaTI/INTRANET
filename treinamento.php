<?php 
require_once 'config.php';
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// Organizando os dados para o PHP processar
$setores = [
    'TI' => [
        ['n' => '535', 'p' => '1', 'url' => '8FCBhmB4m7w'],
        ['n' => '528', 'p' => '1', 'url' => '3uj56rG2YTk'],
        ['n' => '528', 'p' => '2', 'url' => '1fYnrnq0hC0'],
        ['n' => '528', 'p' => '3', 'url' => 'gDA4mjp93pU'],
        ['n' => '530', 'p' => '1', 'url' => 'mr1ePm1ehMM'],
        ['n' => '530', 'p' => '2', 'url' => 'evIuvjfzCvo'],
        // ... adicione os outros de TI seguindo esse padrão
    ],
    'CADASTRO' => [
        ['n' => '513', 'p' => '1', 'url' => 'NBWxqDiLImE'],
        ['n' => '571', 'p' => '1', 'url' => 'MxaP2UsFytw'],
        // ... adicione os outros de Cadastro
    ],
    'FISCAL' => [
        ['n' => '543', 'p' => '1', 'url' => 'YK-bEnJ1hzA'],
        ['n' => '580', 'p' => '1', 'url' => 'BpCgFKTEEK8'],
        // ... adicione os outros de Fiscal
    ]
];
?>

<main class="flex-1 bg-slate-50 p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto mb-8">
        <h1 id="main-title" class="text-3xl font-black text-navy-900 italic uppercase tracking-tighter">Academia Winthor</h1>
        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Selecione um setor para iniciar as rotinas</p>
        
        <div class="flex gap-2 mt-6 overflow-x-auto pb-2 custom-scrollbar">
            <?php foreach(array_keys($setores) as $setor): ?>
                <button onclick="filtrarSetor('<?= $setor ?>')" class="btn-setor px-6 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition-all shadow-sm whitespace-nowrap">
                    <?= $setor ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-black rounded-[2.5rem] shadow-2xl overflow-hidden border-4 border-white aspect-video relative">
                <iframe id="youtubePlayer" class="w-full h-full" src="https://www.youtube.com/embed/<?= $setores['TI'][0]['url'] ?>?rel=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-200">
                <h3 id="video-title" class="font-bold text-lg text-navy-900 mb-2">Rotina <?= $setores['TI'][0]['n'] ?> - Parte <?= $setores['TI'][0]['p'] ?></h3>
                <p class="text-slate-600 text-sm italic">Treinamento oficial de rotinas operacionais.</p>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-200">
                <h3 class="text-xs font-black text-slate-400 uppercase mb-4 tracking-widest">Playlist do Setor</h3>
                <div id="playlist-container" class="space-y-2 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                    </div>
            </div>
        </div>
    </div>
</main>

<script>
    const dadosCursos = <?= json_encode($setores) ?>;

    function filtrarSetor(setor) {
        const container = document.getElementById('playlist-container');
        container.innerHTML = ''; // Limpa a lista atual

        dadosCursos[setor].forEach((aula, index) => {
            const item = document.createElement('div');
            item.className = "p-4 bg-slate-50 border border-transparent rounded-2xl flex items-center gap-3 hover:bg-blue-50 hover:border-blue-100 cursor-pointer transition-all group";
            item.onclick = () => carregarVideo(aula.url, aula.n, aula.p);
            
            item.innerHTML = `
                <span class="w-8 h-8 rounded-lg bg-white text-slate-400 group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center text-xs font-black shadow-sm transition-all">${aula.p}</span>
                <div>
                    <p class="text-[10px] font-black text-navy-900 uppercase">Rotina ${aula.n}</p>
                    <p class="text-[9px] text-slate-400 font-bold group-hover:text-blue-600">PARTE ${aula.p}</p>
                </div>
            `;
            container.appendChild(item);
        });

        // Carrega o primeiro vídeo do setor automaticamente
        const primeira = dadosCursos[setor][0];
        carregarVideo(primeira.url, primeira.n, primeira.p);
    }

    function carregarVideo(url, rotina, parte) {
        document.getElementById('youtubePlayer').src = `https://www.youtube.com/embed/${url}?autoplay=1&rel=0`;
        document.getElementById('video-title').innerText = `Rotina ${rotina} - Parte ${parte}`;
    }

    // Inicia com TI por padrão
    window.onload = () => filtrarSetor('TI');
</script>