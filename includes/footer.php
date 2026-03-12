</div> <div id="toast-container" class="fixed top-20 right-6 z-[3000] flex flex-col gap-3 pointer-events-none"></div>

<div id="wrapper-chat-navi" class="fixed bottom-6 right-6 z-[1000] flex flex-col items-end font-sans">
    
    <div id="janela-chat" class="hidden w-[90vw] md:w-[850px] h-[600px] bg-white rounded-[2rem] shadow-[0_25px_60px_rgba(15,23,42,0.3)] border border-slate-200 overflow-hidden flex mb-4 animate-in slide-in-from-bottom-10 duration-500">
        
        <aside class="w-80 bg-slate-50 border-r border-slate-200 flex flex-col h-full shrink-0">
            <header class="p-5 border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between mb-2">
                    <h1 class="text-xl font-black text-navy-900 italic uppercase tracking-tighter">Navi Messenger</h1>
                    <button onclick="abrirModalCriarGrupo()" class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center hover:bg-navy-900 transition-all shadow-md">
                        <span class="text-xl font-bold">+</span>
                    </button>
                </div>
                <div class="relative mt-2">
                    <input type="text" id="search-chat" placeholder="Buscar contato..." class="w-full pl-8 pr-4 py-2 bg-slate-100 border-none rounded-xl text-xs outline-none focus:ring-2 focus:ring-blue-500/20">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </header>
            <nav id="lista-chat-usuarios" class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1"></nav>
        </aside>

        <main class="flex-1 flex flex-col bg-white h-full relative">
            <header class="px-6 py-4 bg-navy-900 flex items-center justify-between shrink-0 shadow-lg">
                <div class="flex items-center gap-3">
                    <div id="chat-header-avatar" class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center text-white font-bold shadow-md">G</div>
                    <div>
                        <h2 id="chat-header-nome" class="font-bold text-white text-sm">Chat Global</h2>
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                            <p id="chat-header-status" class="text-[9px] text-blue-400 font-black uppercase tracking-widest">Equipe Online</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <button onclick="confirmarLimparChat()" class="w-8 h-8 rounded-full hover:bg-white/10 flex items-center justify-center text-white/50 hover:text-red-400 transition-all" title="Limpar conversa">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                    <button onclick="toggleChatNavi()" class="w-8 h-8 rounded-full hover:bg-white/10 flex items-center justify-center text-white/50 hover:text-white text-2xl transition-all">&times;</button>
                </div>
            </header>

            <section id="chat-feed" class="flex-1 overflow-y-auto p-6 space-y-4 bg-slate-50/50 custom-scrollbar scroll-smooth"></section>

            <footer class="px-6 py-4 bg-white border-t border-slate-100">
                <form id="form-chat-navi" class="flex items-center gap-3">
                    <input type="text" id="input-chat-msg" autocomplete="off" placeholder="Digite uma mensagem..." class="flex-1 px-5 py-3 bg-slate-100 rounded-2xl text-sm border-none outline-none focus:ring-2 focus:ring-blue-500/20">
                    <button type="submit" class="w-12 h-12 rounded-xl bg-navy-900 text-white flex items-center justify-center shadow-lg hover:bg-blue-600 transition-all active:scale-95">
                        <svg class="w-5 h-5 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                    </button>
                </form>
            </footer>
        </main>
    </div>

    <button onclick="toggleChatNavi()" class="hover-float group relative">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center relative shadow-lg bg-navy-900 border border-white/10 transition-transform group-hover:scale-105">
            <svg class="w-8 h-8 text-white" viewbox="0 0 24 24" fill="currentColor">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.2L4 17.2V4h16v12z" />
            </svg>
        </div>
        <span id="chat-notif-badge" class="hidden absolute -top-1 -right-1 px-2 h-5 bg-blue-500 border-2 border-white rounded-full text-[10px] text-white font-black flex items-center justify-center animate-bounce shadow-lg">Nova!</span>
    </button>
</div>

<div id="modal-criar-grupo" class="hidden fixed inset-0 bg-navy-900/50 backdrop-blur-sm z-[2000] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="bg-navy-900 p-6 text-white flex justify-between items-center">
            <h3 class="font-black uppercase italic tracking-tighter">Criar Novo Grupo</h3>
            <button onclick="fecharModalGrupo()" class="text-2xl opacity-50 hover:opacity-100">&times;</button>
        </div>
        <form id="form-criar-grupo" class="p-6 space-y-4">
            <input type="text" id="nome-grupo-input" placeholder="Nome do Grupo" required class="w-full bg-slate-100 border-none rounded-2xl px-5 py-3 text-sm outline-none">
            <div id="lista-membros-selecao" class="mt-2 space-y-2 max-h-48 overflow-y-auto custom-scrollbar p-2 border rounded-xl"></div>
            <button type="submit" class="w-full bg-navy-900 text-white py-4 rounded-2xl font-bold hover:bg-blue-600 transition-all">CRIAR GRUPO 🚀</button>
        </form>
    </div>
</div>

<style>
    .msg-sent { background: #0f172a; border-radius: 20px 20px 4px 20px; color: white; padding: 12px 16px; align-self: flex-end; max-width: 80%; }
    .msg-received { background: white; border: 1px solid #e2e8f0; border-radius: 20px 20px 20px 4px; color: #1e293b; padding: 12px 16px; align-self: flex-start; max-width: 80%; }
    .chat-user-item { transition: all 0.2s; border-left: 3px solid transparent; cursor: pointer; display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; position: relative; }
    .chat-user-item:hover { background: #f1f5f9; }
    .chat-user-item.active { background: #eff6ff; border-left: 3px solid #2563eb; }
    .badge-sidebar { position: absolute; right: 12px; top: 12px; background: #2563eb; color: white; font-size: 9px; font-weight: 900; width: 18px; height: 18px; border-radius: 50%; display: flex; items-center; justify-content: center; }
    .navi-toast { background: #0f172a; color: white; padding: 16px 20px; border-radius: 20px; border-left: 5px solid #2563eb; box-shadow: 0 10px 30px rgba(0,0,0,0.3); pointer-events: auto; min-width: 250px; animation: slideInRight 0.4s ease-out; }
    @keyframes slideInRight { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<script>
let chatAberto = false, destinoId = 'global', meuId = <?= $_SESSION['user_id'] ?>, processadosIds = new Set();
let tituloOriginal = document.title;
let intervaloTitulo = null;
const somAlerta = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');

document.addEventListener('DOMContentLoaded', () => {
    carregarContatos(); // Carrega uma vez ao abrir
    carregarMensagens(); 
    setInterval(monitorarNotificacoesGlobais, 3000); // Monitora notificações a cada 3s
    setInterval(carregarContatos, 20000);
    
    document.addEventListener('click', () => { if (Notification.permission !== "granted") Notification.requestPermission(); }, { once: true });

    // BUSCA DINÂMICA
    document.getElementById('search-chat').addEventListener('input', function(e) {
        const termo = e.target.value.toLowerCase();
        document.querySelectorAll('.chat-user-item').forEach(item => {
            const nome = item.innerText.toLowerCase();
            item.style.display = nome.includes(termo) ? 'flex' : 'none';
        });
    });
});

function monitorarNotificacoesGlobais() {
    fetch('api/chat_engine.php?acao=verificar_notificacoes')
        .then(r => r.json())
        .then(notificacoes => {
            notificacoes.forEach(n => {
                const idBusca = n.id_grupo ? `group_${n.id_grupo}` : n.id_remetente;
                
                if (!processadosIds.has(n.ultimo_id)) {
                    processadosIds.add(n.ultimo_id);
                    
                    if (destinoId !== idBusca) {
                        somAlerta.play().catch(()=>{});
                        mostrarToasty(n.nome_usuario || "Novo no Grupo", n.ultima_msg);
                        document.getElementById('chat-notif-badge').classList.remove('hidden');
                        
                        if (!chatAberto) piscarTitulo();
                        
                        // Busca o item na sidebar pelo atributo data-chat-id
                        const item = document.querySelector(`[data-chat-id="${idBusca}"]`);
                        if(item) {
                            let badge = item.querySelector('.badge-sidebar');
                            if(!badge) {
                                badge = document.createElement('span');
                                badge.className = 'badge-sidebar';
                                item.appendChild(badge);
                            }
                            badge.innerText = n.total;
                        }
                    } else { 
                        fetch(`api/chat_engine.php?acao=marcar_como_lida&destino=${destinoId}`);
                        carregarMensagens(); 
                    }
                }
            });
        });
}

function piscarTitulo() {
    if (intervaloTitulo) return;
    intervaloTitulo = setInterval(() => {
        document.title = (document.title === tituloOriginal) ? "🔔 Nova Mensagem!" : tituloOriginal;
    }, 1000);
}

function pararDePiscar() {
    clearInterval(intervaloTitulo);
    intervaloTitulo = null;
    document.title = tituloOriginal;
}

function carregarMensagens() {
    const url = `api/chat_engine.php?acao=listar_mensagens&destino=${destinoId || 'global'}`;
    fetch(url).then(r => r.json()).then(mensagens => {
        if (mensagens.length > 0) {
            mensagens.forEach(m => processadosIds.add(m.id));
        }
        if(chatAberto) renderizarFeed(mensagens);
    });
}

function renderizarFeed(mensagens) {
    const feed = document.getElementById('chat-feed');
    feed.innerHTML = mensagens.map(m => {
        const souEu = m.id_remetente == meuId;
        return `<div class="flex flex-col ${souEu ? 'items-end' : 'items-start'} w-full mb-2">
            ${!souEu ? `<span class="text-[9px] font-black text-slate-500 mb-1 ml-2 uppercase">${m.nome_usuario}</span>` : ''}
            <div class="${souEu ? 'msg-sent' : 'msg-received'} max-w-[85%] shadow-sm">
                <p class="text-xs">${m.mensagem}</p>
                <span class="text-[8px] opacity-60 block text-right mt-1">${new Date(m.data_envio).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</span>
            </div>
        </div>`;
    }).join('');
    feed.scrollTop = feed.scrollHeight;
}

function selecionarChat(id, nome, el) {
    destinoId = id;
    document.getElementById('chat-header-nome').innerText = nome;
    
    const avatarHeader = document.getElementById('chat-header-avatar');
    const statusHeader = document.getElementById('chat-header-status');
    
    // Define estilo especial se for o Bot (ID 999)
    if(id == 999) {
        avatarHeader.innerText = "🤖";
        statusHeader.innerText = "Assistente Virtual Online";
    } else {
        avatarHeader.innerText = nome.substring(0,1).toUpperCase();
        statusHeader.innerText = "Colaborador Online";
    }

    document.querySelectorAll('.chat-user-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    
    const badge = el.querySelector('.badge-sidebar');
    if(badge) badge.remove();
    
    fetch(`api/chat_engine.php?acao=marcar_como_lida&destino=${id}`);
    document.getElementById('chat-feed').innerHTML = '<div class="text-center py-10 animate-pulse text-xs">A ligar ao Navi...</div>';
    
    // Carrega mensagens e injeta boas-vindas se estiver vazio
    fetch(`api/chat_engine.php?acao=listar_mensagens&destino=${id}`)
        .then(r => r.json())
        .then(mensagens => {
            if (mensagens.length === 0 && id == 999) {
                document.getElementById('chat-feed').innerHTML = `
                    <div class="flex flex-col items-start w-full mb-2">
                        <span class="text-[9px] font-black text-blue-600 mb-1 ml-2 uppercase">Navi Bot</span>
                        <div class="msg-received shadow-sm bg-blue-50/50">
                            <p class="text-xs">Olá! Eu sou o Navi, a tua IA de suporte. 🚀<br>Como posso ajudar hoje?</p>
                        </div>
                    </div>`;
            } else {
                renderizarFeed(mensagens);
            }
        });
}

// Nova lógica para o Navi se apresentar
function carregarMensagensComBoasVindas(id) {
    const url = `api/chat_engine.php?acao=listar_mensagens&destino=${id}`;
    fetch(url).then(r => r.json()).then(mensagens => {
        if (mensagens.length === 0 && id == 999) {
            // Se for a primeira vez do utilizador com o Bot, simula uma entrada
            const feed = document.getElementById('chat-feed');
            feed.innerHTML = `
                <div class="flex flex-col items-center my-4 opacity-50">
                    <span class="text-[10px] bg-slate-200 px-3 py-1 rounded-full font-bold uppercase">Chat de Suporte Iniciado</span>
                </div>
                <div class="flex flex-col items-start w-full mb-2">
                    <span class="text-[9px] font-black text-blue-600 mb-1 ml-2 uppercase">Navi Bot</span>
                    <div class="msg-received shadow-sm border-blue-100 bg-blue-50/50">
                        <p class="text-xs font-medium">Olá! Eu sou o Navi, a tua Inteligência Artificial. 🚀<br><br>Podes perguntar-me sobre o GLPI, ramais, procedimentos internos ou apenas dizer "Olá". Como posso ajudar?</p>
                    </div>
                </div>`;
        } else {
            renderizarFeed(mensagens);
        }
    });
}

function toggleChatNavi() {
    const janela = document.getElementById('janela-chat');
    janela.classList.toggle('hidden');
    chatAberto = !janela.classList.contains('hidden');
    if(chatAberto) { 
        document.getElementById('chat-notif-badge').classList.add('hidden'); 
        pararDePiscar();
        carregarContatos(); 
        carregarMensagens(); 
    }
}

function carregarContatos() {
    fetch('api/chat_engine.php?acao=listar_contatos').then(r => r.json()).then(data => {
        const lista = document.getElementById('lista-chat-usuarios');
        let html = `<div onclick="selecionarChat('global', 'Chat Global', this)" data-chat-id="global" class="chat-user-item ${destinoId === 'global' ? 'active' : ''}">
                <div class="w-10 h-10 rounded-full bg-navy-900 flex items-center justify-center text-white">🌍</div>
                <div class="flex-1"><h3 class="font-bold text-xs">Chat Global</h3></div>
            </div>`;
        data.grupos?.forEach(g => {
            html += `<div onclick="selecionarChat('group_${g.id}', '${g.nome_grupo}', this)" data-chat-id="group_${g.id}" class="chat-user-item ${destinoId === 'group_'+g.id ? 'active' : ''}">
                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-xs">GP</div>
                <div class="flex-1"><h3 class="font-bold text-xs uppercase">${g.nome_grupo}</h3></div>
            </div>`;
        });
        data.usuarios?.forEach(u => {
            html += `<div onclick="selecionarChat(${u.usuario_id}, '${u.nome_usuario}', this)" data-chat-id="${u.usuario_id}" class="chat-user-item ${destinoId == u.usuario_id ? 'active' : ''}">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-xs">${u.nome_usuario.substring(0,2).toUpperCase()}</div>
                <div class="flex-1"><h3 class="font-bold text-xs uppercase">${u.nome_usuario}</h3><span class="text-[9px] text-emerald-500 font-bold uppercase">Online</span></div>
            </div>`;
        });
        lista.innerHTML = html;
    });
}

function mostrarToasty(autor, msg) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'navi-toast';
    toast.innerHTML = `<p class="text-[10px] font-black text-blue-400 uppercase mb-1">${autor} diz:</p><p class="text-xs">${msg}</p>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 500); }, 5000);
}

function confirmarLimparChat() {
    if (confirm("Apagar histórico desta conversa?")) {
        fetch(`api/chat_engine.php?acao=limpar_chat&destino=${destinoId || 'global'}`)
            .then(r => r.json()).then(() => {
                document.getElementById('chat-feed').innerHTML = '';
                mostrarToasty("Sistema", "Conversa limpa!");
            });
    }
}

document.getElementById('form-chat-navi').onsubmit = function(e) {
    e.preventDefault();
    const input = document.getElementById('input-chat-msg'), msg = input.value.trim();
    if(!msg) return;
    const fd = new FormData(); fd.append('mensagem', msg); fd.append('id_destinatario', destinoId);
    fetch('api/chat_engine.php?acao=enviar', { method: 'POST', body: fd }).then(() => { input.value = ''; carregarMensagens(); });
};

function abrirModalCriarGrupo() {
    document.getElementById('modal-criar-grupo').classList.remove('hidden');
    // Busca todos os usuários, independentemente do status online
    fetch('api/chat_engine.php?acao=listar_todos_usuarios').then(r => r.json()).then(data => {
        document.getElementById('lista-membros-selecao').innerHTML = data.map(u => `
            <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-200 transition-all">
                <input type="checkbox" name="membros[]" value="${u.usuario_id}" class="w-4 h-4 text-blue-600">
                <span class="text-xs font-bold uppercase text-slate-700">${u.nome_usuario}</span>
            </label>`).join('');
    });
}

function fecharModalGrupo() { document.getElementById('modal-criar-grupo').classList.add('hidden'); }

document.getElementById('form-criar-grupo').onsubmit = function(e) {
    e.preventDefault();
    const membros = Array.from(document.querySelectorAll('input[name="membros[]"]:checked')).map(cb => cb.value);
    const fd = new FormData(); fd.append('nome_grupo', document.getElementById('nome-grupo-input').value); fd.append('membros', JSON.stringify(membros));
    fetch('api/chat_engine.php?acao=criar_grupo', { method: 'POST', body: fd }).then(() => { fecharModalGrupo(); carregarContatos(); });
};
</script>
</body>
</html>