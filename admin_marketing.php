<?php 
require_once 'config.php'; 
include 'includes/header.php'; 
include 'includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10 text-navy-900">
    <div class="max-w-5xl mx-auto space-y-10">
        
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-3xl font-black uppercase tracking-tighter italic">Central de Marketing</h3>
                <p class="text-slate-500 font-medium italic">Selecione o módulo que deseja gerenciar.</p>
            </div>
            <a href="index.php" class="bg-white border border-slate-200 px-6 py-3 rounded-2xl text-xs font-black hover:bg-slate-100 transition-all shadow-sm">VISUALIZAR HOME 🏠</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <a href="admin_aniversariantes.php" class="group bg-white p-8 rounded-[3rem] border border-slate-200 shadow-sm hover:shadow-xl hover:-translate-y-2 transition-all">
                <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:bg-amber-500 transition-colors">🎂</div>
                <h4 class="text-xl font-black uppercase tracking-tighter mb-2">Aniversariantes</h4>
                <p class="text-slate-500 text-sm leading-relaxed">Gerencie a lista do letreiro e a arte do modal mensal.</p>
                <div class="mt-6 text-amber-600 font-black text-xs uppercase tracking-widest flex items-center gap-2">Acessar Painel ➔</div>
            </a>

            <a href="admin_boas_vindas.php" class="group bg-white p-8 rounded-[3rem] border border-slate-200 shadow-sm hover:shadow-xl hover:-translate-y-2 transition-all">
                <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:bg-corporate-blue transition-colors">👋</div>
                <h4 class="text-xl font-black uppercase tracking-tighter mb-2">Boas-Vindas</h4>
                <p class="text-slate-500 text-sm leading-relaxed">Atualize o banner principal de saudação do portal.</p>
                <div class="mt-6 text-corporate-blue font-black text-xs uppercase tracking-widest flex items-center gap-2">Acessar Painel ➔</div>
            </a>

            <a href="admin_campanhas.php" class="group bg-white p-8 rounded-[3rem] border border-slate-200 shadow-sm hover:shadow-xl hover:-translate-y-2 transition-all">
                <div class="w-16 h-16 bg-purple-50 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:bg-purple-600 transition-colors">📢</div>
                <h4 class="text-xl font-black uppercase tracking-tighter mb-2">Campanhas</h4>
                <p class="text-slate-500 text-sm leading-relaxed">Agende banners rotativos com tempo de exibição.</p>
                <div class="mt-6 text-purple-600 font-black text-xs uppercase tracking-widest flex items-center gap-2">Acessar Painel ➔</div>
            </a>

        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>