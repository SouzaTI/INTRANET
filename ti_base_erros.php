<?php
require_once 'config.php';

// 🛡️ TRAVA DE SEGURANÇA (Apenas TI / Admin)
if (!isset($_SESSION['is_admin']) && empty($_SESSION['pode_gerenciar_acessos'])) {
    header("Location: index.php");
    exit;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10 min-h-screen">
    <div class="max-w-5xl mx-auto space-y-8">
        
        <!-- CABEÇALHO -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 mb-1">Engenharia de Conhecimento</p>
                <h1 class="text-3xl font-black text-navy-900 uppercase tracking-tighter italic leading-none">
                    Base de Soluções TOTVS
                </h1>
            </div>
            <button class="bg-navy-900 hover:bg-corporate-blue text-white font-black text-xs uppercase tracking-widest px-6 py-3 rounded-2xl shadow-lg transition-all flex items-center gap-2 opacity-50 cursor-not-allowed" title="Em breve">
                + Novo Erro
            </button>
        </div>

        <!-- BARRA DE BUSCA (MOCK) -->
        <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-2">
            <div class="pl-4 text-xl">🔍</div>
            <input type="text" disabled placeholder="Buscar por rotina, setor ou erro... (Protótipo)" 
                   class="w-full bg-transparent p-3 outline-none text-sm font-bold text-slate-700 placeholder:text-slate-300 cursor-not-allowed">
            <button disabled class="bg-slate-100 text-slate-400 font-black px-6 py-3 rounded-xl text-xs uppercase transition-all cursor-not-allowed">
                Buscar
            </button>
        </div>

        <!-- LISTAGEM DE ERROS (HARDCODED PARA VALIDAÇÃO) -->
        <div class="space-y-4">
            
            <!-- ERRO 1 (Baseado na imagem da rotina 530) -->
            <div x-data="{ aberto: false }" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all hover:border-corporate-blue">
                <div @click="aberto = !aberto" class="p-5 flex flex-col md:flex-row justify-between md:items-center gap-4 cursor-pointer hover:bg-slate-50">
                    <div class="flex gap-4 items-center">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-black text-lg shrink-0">
                            530
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-slate-100 text-slate-500 mb-2 inline-block">Distribuição / TI</span>
                            <h3 class="font-bold text-navy-900 text-sm">Ocorrendo erro ao incluir usuário em grupo (DDFINAN-37636)</h3>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-bold text-slate-400">12/10/2025</span>
                        <svg class="w-5 h-5 text-slate-400 transition-transform duration-300" :class="{ 'rotate-180': aberto }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
                
                <div x-show="aberto" x-transition class="p-6 bg-slate-50 border-t border-slate-100 text-sm text-slate-600 space-y-4">
                    <div>
                        <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Situação / Requisito</h4>
                        <p class="font-medium bg-white p-4 rounded-xl border border-slate-200">
                            Inconsistência durante o gerenciamento de grupos. O sistema permitia incluir o mesmo usuário em múltiplos grupos gerando inconsistências na tabela <b>PCEMPRPERFIL</b>.
                        </p>
                    </div>
                    <div>
                        <h4 class="text-[10px] font-black uppercase text-emerald-500 tracking-widest mb-1">Solução Aplicada</h4>
                        <p class="font-medium bg-emerald-50 p-4 rounded-xl border border-emerald-100 text-emerald-800">
                            Ajuste na rotina 530 para interromper o processo de gravação ao identificar duplicidade, preservando a integridade. Versão mínima exigida: 37.0.5.496.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ERRO 2 -->
            <div x-data="{ aberto: false }" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all hover:border-corporate-blue">
                <div @click="aberto = !aberto" class="p-5 flex flex-col md:flex-row justify-between md:items-center gap-4 cursor-pointer hover:bg-slate-50">
                    <div class="flex gap-4 items-center">
                        <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center text-orange-600 font-black text-lg shrink-0">
                            1301
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-slate-100 text-slate-500 mb-2 inline-block">Recebimento / Fiscal</span>
                            <h3 class="font-bold text-navy-900 text-sm">Nota Fiscal travada na importação de XML</h3>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-bold text-slate-400">05/11/2025</span>
                        <svg class="w-5 h-5 text-slate-400 transition-transform duration-300" :class="{ 'rotate-180': aberto }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
                
                <div x-show="aberto" x-transition class="p-6 bg-slate-50 border-t border-slate-100 text-sm text-slate-600 space-y-4">
                    <div>
                        <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Situação / Requisito</h4>
                        <p class="font-medium bg-white p-4 rounded-xl border border-slate-200">
                            Ao tentar importar o XML do fornecedor, a rotina apresentava rejeição na Sefaz e não deixava fechar o espelho da nota.
                        </p>
                    </div>
                    <div>
                        <h4 class="text-[10px] font-black uppercase text-emerald-500 tracking-widest mb-1">Solução Aplicada</h4>
                        <p class="font-medium bg-emerald-50 p-4 rounded-xl border border-emerald-100 text-emerald-800">
                            Inconsistência de tributação. Foi necessário acessar a rotina 203 (Cadastro de Produto) e atualizar o CST e NCM do item conforme orientação da contabilidade. Após ajuste, reprocessado o XML.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ERRO 3 -->
            <div x-data="{ aberto: false }" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all hover:border-corporate-blue">
                <div @click="aberto = !aberto" class="p-5 flex flex-col md:flex-row justify-between md:items-center gap-4 cursor-pointer hover:bg-slate-50">
                    <div class="flex gap-4 items-center">
                        <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600 font-black text-lg shrink-0">
                            316
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-slate-100 text-slate-500 mb-2 inline-block">Comercial / Televendas</span>
                            <h3 class="font-bold text-navy-900 text-sm">Cliente bloqueado indevidamente no crédito</h3>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-bold text-slate-400">18/01/2026</span>
                        <svg class="w-5 h-5 text-slate-400 transition-transform duration-300" :class="{ 'rotate-180': aberto }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
                
                <div x-show="aberto" x-transition class="p-6 bg-slate-50 border-t border-slate-100 text-sm text-slate-600 space-y-4">
                    <div>
                        <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Situação / Requisito</h4>
                        <p class="font-medium bg-white p-4 rounded-xl border border-slate-200">
                            Operador tentava faturar o pedido e o sistema barrou indicando falta de limite, porém o cliente havia feito um PIX de antecipação.
                        </p>
                    </div>
                    <div>
                        <h4 class="text-[10px] font-black uppercase text-emerald-500 tracking-widest mb-1">Solução Aplicada</h4>
                        <p class="font-medium bg-emerald-50 p-4 rounded-xl border border-emerald-100 text-emerald-800">
                            Título PIX constava em aberto na rotina 1206. O financeiro baixou o título, em seguida rodamos o recálculo de limite do cliente na rotina 302, liberando o crédito para faturamento imediato.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>