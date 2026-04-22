<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Definimos o grupo padrão (ID 1 - GERAL que criamos no SQL)
$destino_ativo = 1; 
$meu_id = $_SESSION['user_id'];
?>

<main class="flex-1 bg-slate-50 p-4 h-screen flex flex-col overflow-hidden">
    <div class="max-w-6xl mx-auto w-full flex flex-col h-full bg-white rounded-[3rem] shadow-2xl border border-slate-200 overflow-hidden">
        
        <div class="bg-navy-900 p-6 text-white flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-2xl shadow-lg">💬</div>
                <div>
                    <h2 class="text-xl font-black uppercase italic tracking-tighter leading-none">Chat Interno - GERAL</h2>
                    <p class="text-blue-400 text-[10px] font-bold uppercase tracking-widest mt-1">Conectado como: <?= $_SESSION['user_name'] ?></p>
                </div>
            </div>
            <div id="status-conexao" class="px-4 py-1 bg-emerald-500/20 text-emerald-400 rounded-full text-[9px] font-black uppercase border border-emerald-500/30 animate-pulse">Online</div>
        </div>

        <div id="chat-container" class="flex-1 p-6 overflow-y-auto space-y-4 custom-scrollbar bg-slate-50/50">
            <div class="flex justify-center py-10">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.3em]">Carregando histórico...</p>
            </div>
        </div>

        <div class="p-6 bg-white border-t border-slate-100">
            <form id="form-chat" class="flex gap-3 items-center">
                <input type="text" id="input-mensagem" autocomplete="off" placeholder="Digite sua mensagem aqui..." 
                    class="flex-1 bg-slate-100 border-none p-4 rounded-2xl text-sm font-medium outline-none focus:ring-4 focus:ring-blue-600/10 transition-all">
                
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-600/20 transition-all transform hover:scale-105">
                    <svg class="w-6 h-6 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </form>
        </div>
    </div>
</main>

<script>
    const destinoAtivo = <?= $destino_ativo ?>;
    const meuId = <?= $meu_id ?>;
    let ultimoIdRecebido = 0;
    const chatContainer = document.getElementById('chat-container');
    const formChat = document.getElementById('form-chat');
    const inputMsg = document.getElementById('input-mensagem');

    // 1. FUNÇÃO PARA BUSCAR MENSAGENS
    function buscarMensagens() {
        fetch(`api/chat_engine.php?acao=buscar&destino=${destinoAtivo}&ultimo_id=${ultimoIdRecebido}`)
        .then(res => res.json())
        .then(mensagens => {
            if (mensagens.length > 0) {
                // Se é a primeira carga, limpa o "Carregando..."
                if(ultimoIdRecebido === 0) chatContainer.innerHTML = '';

                mensagens.forEach(m => {
                    const souEu = (m.remetente_id == meuId);
                    const bubble = document.createElement('div');
                    
                    bubble.className = `flex ${souEu ? 'justify-end' : 'justify-start'} w-full animate-in fade-in slide-in-from-bottom-2 duration-300`;
                    
                    bubble.innerHTML = `
                        <div class="max-w-[70%]">
                            <div class="flex items-center gap-2 mb-1 ${souEu ? 'justify-end' : 'justify-start'}">
                                <span class="text-[9px] font-black uppercase text-slate-400">${m.nome}</span>
                                <span class="text-[8px] text-slate-300 font-bold">${new Date(m.data_hora).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                            </div>
                            <div class="p-4 rounded-[1.5rem] text-sm shadow-sm font-medium ${souEu ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-white text-slate-700 border border-slate-100 rounded-tl-none'}">
                                ${m.mensagem}
                            </div>
                        </div>
                    `;
                    
                    chatContainer.appendChild(bubble);
                    ultimoIdRecebido = m.id;
                });
                
                // Rola para o fim
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        })
        .catch(err => console.error("Erro ao buscar:", err));
    }

    // 2. FUNÇÃO PARA ENVIAR MENSAGEM
    formChat.onsubmit = (e) => {
        e.preventDefault();
        const texto = inputMsg.value.trim();
        if(!texto) return;

        const formData = new FormData();
        formData.append('mensagem', texto);
        formData.append('destino', destinoAtivo);

        inputMsg.value = ''; // Limpa o campo na hora (Fluidez!)

        fetch('api/chat_engine.php?acao=enviar', {
            method: 'POST',
            body: formData
        })
        .then(() => {
            buscarMensagens(); // Busca imediatamente após enviar
        });
    };

    // 3. CICLO DE ATUALIZAÇÃO (Real-time básico)
    buscarMensagens(); // Primeira carga
    setInterval(buscarMensagens, 2000); // Checa novas mensagens a cada 2 segundos
</script>

<?php include 'includes/footer.php'; ?>