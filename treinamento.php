<?php 
require_once 'config.php';
include 'includes/header.php'; 
include 'includes/sidebar.php'; 
?>

<main class="flex-1 bg-slate-50 p-8 overflow-y-auto">
    <div class="max-w-5xl mx-auto mb-8">
        <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-blue-600 mb-2">
            <span>Academia Totvs</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-400">Treinamento de Sistema</span>
        </nav>
        <h1 class="text-3xl font-black text-navy-900 italic uppercase tracking-tighter">Introdução ao Módulo Winthor</h1>
    </div>

    <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-black rounded-[2.5rem] shadow-2xl overflow-hidden border-4 border-white aspect-video relative">
                <video id="videoPlayer" class="w-full h-full" controls controlsList="nodownload">
                    <source src="https://educacao.totvs.com/common/app0000013/content/video/video18876-1733167351041.mp4" type="video/mp4">
                    Seu navegador não suporta vídeos.
                </video>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-200">
                <h3 class="font-bold text-lg text-navy-900 mb-4">Sobre esta aula</h3>
                <p class="text-slate-600 text-sm leading-relaxed">
                    Neste treinamento fundamental, exploramos as rotinas operacionais do sistema. 
                    Certifique-se de anotar os pontos principais para a avaliação final.
                </p>
                <div class="mt-6 flex gap-4">
                    <button class="px-6 py-3 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition-all">📥 Baixar Material PDF</button>
                    <button class="px-6 py-3 bg-navy-900 text-white rounded-xl text-xs font-bold hover:bg-blue-600 transition-all">✅ Marcar como Concluída</button>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-200">
                <h3 class="text-xs font-black text-slate-400 uppercase mb-4 tracking-widest">Conteúdo do Curso</h3>
                
                <div class="space-y-2">
                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-2xl flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center text-xs font-bold">01</span>
                        <div>
                            <p class="text-xs font-black text-navy-900 uppercase">Introdução</p>
                            <p class="text-[10px] text-blue-600 font-bold">Assistindo agora...</p>
                        </div>
                    </div>

                    <?php for($i=2; $i<=5; $i++): ?>
                    <div class="p-4 bg-slate-50 border border-transparent rounded-2xl flex items-center gap-3 hover:bg-slate-100 cursor-not-allowed opacity-60">
                        <span class="w-8 h-8 rounded-lg bg-slate-200 text-slate-500 flex items-center justify-center text-xs font-bold">0<?= $i ?></span>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase">Módulo Avançado 0<?= $i ?></p>
                            <p class="text-[10px] text-slate-400 font-bold">Bloqueado</p>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="bg-gradient-to-br from-navy-900 to-blue-900 p-6 rounded-[2.5rem] text-white shadow-xl">
                <p class="text-[10px] font-black uppercase opacity-60 mb-1">Seu Progresso</p>
                <h4 class="text-xl font-black italic mb-4">20% CONCLUÍDO</h4>
                <div class="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                    <div class="bg-blue-500 h-full w-[20%]"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>