</div> <div id="toast-container" class="fixed top-20 right-6 z-[3000] flex flex-col gap-3 pointer-events-none"></div>

<div id="wrapper-chat-navi" class="fixed bottom-6 right-6 z-[1000] flex flex-col items-end font-sans">
    
    <div id="janela-chat" class="hidden w-[90vw] md:w-[850px] h-[600px] bg-white rounded-[2rem] shadow-[0_25px_60px_rgba(15,23,42,0.3)] border border-slate-200 overflow-hidden flex mb-4 animate-in slide-in-from-bottom-10 duration-500">
        
        <aside class="w-80 bg-slate-50 border-r border-slate-200 flex flex-col h-full shrink-0">
            <header class="p-5 border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between mb-2">
                    <h1 class="text-xl font-black text-navy-900 italic uppercase tracking-tighter leading-none">Navi Messenger</h1>
                    <button onclick="carregarListaGrupos()" class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center hover:bg-navy-900 transition-all shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Canais e Setores</p>
            </header>

            <div class="p-3 border-b border-slate-200 bg-slate-100">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" id="busca-pessoas-chat" oninput="filtrarPessoasChat()" placeholder="Buscar contato..." autocomplete="off" class="w-full bg-white border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs outline-none focus:border-blue-500 transition-colors shadow-sm text-navy-900 font-medium">
                </div>
            </div>

            <nav id="lista-grupos-chat" class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-2"></nav>
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

                    <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <button id="btn-gerenciar-grupo" onclick="abrirModalGerenciarMembros()" class="hidden w-8 h-8 rounded-full hover:bg-white/10 flex items-center justify-center text-white/50 hover:text-blue-400 transition-all" title="Ver Membros do Grupo">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </button>
                    <?php endif; ?>

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

<div id="modal-gerenciar-membros" class="hidden fixed inset-0 bg-navy-900/50 backdrop-blur-sm z-[2000] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="bg-navy-900 p-6 text-white flex justify-between items-center">
            <div>
                <h3 class="font-black uppercase italic tracking-tighter">Membros do Grupo</h3>
                <p id="nome-grupo-gerenciar" class="text-[10px] font-bold text-blue-400 uppercase tracking-widest"></p>
            </div>
            <button onclick="fecharModalMembros()" class="text-2xl opacity-50 hover:opacity-100">&times;</button>
        </div>
        <form id="form-gerenciar-membros" class="p-6 space-y-4" onsubmit="salvarMembros(event)">
            <div class="relative">
                <input type="text" id="busca-membros" placeholder="Buscar usuário por nome..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500" oninput="filtrarUsuariosLista()">
            </div>
            <div id="lista-usuarios-glpi" class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar p-3 border border-slate-100 rounded-xl bg-slate-50">
                </div>
            <button type="submit" class="w-full bg-navy-900 text-white py-4 rounded-2xl font-bold hover:bg-blue-600 transition-all uppercase text-xs tracking-widest shadow-lg">Salvar Alterações 💾</button>
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
// CONFIGURAÇÕES INICIAIS
let chatAberto = false;
let destinoId = 1; // Começa no GERAL
let tipoDestinoAtual = 'grupo'; // NOVO: Controla se a conversa é grupo ou 1x1
let meuId = <?= $_SESSION['user_id'] ?>;
let ultimoIdRecebido = 0;
const somNotificacao = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');

// 0. O PING SILENCIOSO (Avisa o banco que você leu a sala ou pessoa)
function avisarLeituraBanco(destinoId, tipo = 'grupo') {
    const fd = new FormData();
    fd.append('destino_id', destinoId);
    fd.append('tipo', tipo); // NOVO: Passa o tipo pro banco

    fetch('api/chat_engine.php?acao=marcar_lido', {
        method: 'POST',
        body: fd
    })
    .then(() => carregarListaGrupos()) 
    .catch(err => console.error(err));
}

// FILTRO DE BUSCA LATERAL
function filtrarPessoasChat() {
    const termo = document.getElementById('busca-pessoas-chat').value.toLowerCase();
    document.querySelectorAll('.chat-user-item').forEach(el => {
        // Pega o nome do canal ou da pessoa e compara com o que foi digitado
        const nome = el.querySelector('p.truncate').innerText.toLowerCase();
        el.style.display = nome.includes(termo) ? 'flex' : 'none';
    });
}

// 1. CARREGAR LISTA DE CANAIS E PESSOAS (LATERAL)
function carregarListaGrupos() {
    fetch('api/chat_engine.php?acao=listar_grupos')
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById('lista-grupos-chat'); 
        if(!container) return;
        
        // --- 1. RENDERIZA OS CANAIS ---
        let htmlLateral = '<div class="px-4 py-2 mt-1 text-[10px] font-black text-slate-400 uppercase tracking-widest">Canais</div>';
        
        data.grupos.forEach(g => {
            const ativo = (g.id == destinoId && tipoDestinoAtual === 'grupo');
            const bolinha = g.nao_lidas > 0 
                ? `<span class="absolute top-1/2 -translate-y-1/2 right-3 w-5 h-5 bg-rose-500 text-white text-[9px] font-black rounded-full flex items-center justify-center shadow-md animate-pulse">${g.nao_lidas}</span>` 
                : '';

            htmlLateral += `
                <div onclick="selecionarChat(${g.id}, '${g.nome}', 'grupo')" class="chat-user-item ${ativo ? 'active' : ''}">
                    <div class="w-10 h-10 rounded-xl bg-slate-200 flex items-center justify-center font-bold text-slate-500 shadow-inner shrink-0">${g.nome[0].toUpperCase()}</div>
                    <div class="flex-1 pr-6 relative overflow-hidden">
                        <p class="text-xs font-bold text-navy-900 uppercase truncate">${g.nome}</p>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Setor</p>
                    </div>
                    ${bolinha}
                </div>
            `;
        });

        // --- 2. RENDERIZA AS PESSOAS (1x1) ---
        htmlLateral += '<div class="px-4 py-2 mt-4 border-t border-slate-100/50 text-[10px] font-black text-slate-400 uppercase tracking-widest pt-4">Pessoas</div>';
        
        data.usuarios.forEach(u => {
            const ativo = (u.id == destinoId && tipoDestinoAtual === 'usuario');
            const bolinha = u.nao_lidas > 0 
                ? `<span class="absolute top-1/2 -translate-y-1/2 right-3 w-5 h-5 bg-rose-500 text-white text-[9px] font-black rounded-full flex items-center justify-center shadow-md animate-pulse">${u.nao_lidas}</span>` 
                : '';

            htmlLateral += `
                <div onclick="selecionarChat(${u.id}, '${u.nome}', 'usuario')" class="chat-user-item ${ativo ? 'active' : ''}">
                    <div class="w-10 h-10 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center font-black text-blue-600 shadow-inner text-sm shrink-0">${u.nome[0].toUpperCase()}</div>
                    <div class="flex-1 pr-6 relative overflow-hidden">
                        <p class="text-xs font-bold text-slate-700 capitalize truncate">${u.nome}</p>
                        <p class="text-[9px] text-slate-400 uppercase tracking-widest truncate">Mensagem Direta</p>
                    </div>
                    ${bolinha}
                </div>
            `;
        });

        container.innerHTML = htmlLateral;
        filtrarPessoasChat(); // REAPLICA O FILTRO PARA NÃO PERDER A BUSCA A CADA 5 SEGUNDOS
    }).catch(e => console.error("Erro na lista:", e));
}

// 2. MONITORAR MENSAGENS (O CORAÇÃO BLINDADO DO CHAT)
function monitorarChat() {
    if(!destinoId) return;

    fetch(`api/chat_engine.php?acao=buscar&destino=${destinoId}&ultimo_id=${ultimoIdRecebido}&tipo=${tipoDestinoAtual}`)
    .then(async (res) => {
        const texto = await res.text();
        try { return JSON.parse(texto); } 
        catch (e) { return null; } // Ignora falhas de JSON silenciosamente
    })
    .then(mensagens => {
        if (!mensagens) return; 

        const feed = document.getElementById('chat-feed');
        
        // SACADA: Descobre se é a primeira vez que está carregando as mensagens desta sala
        const primeiraCarga = (ultimoIdRecebido === 0);
        
        if(primeiraCarga) {
            feed.innerHTML = '';
            if(!Array.isArray(mensagens) || mensagens.length === 0) {
                feed.innerHTML = '<div class="flex justify-center p-10"><span class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-center leading-relaxed">Nenhuma mensagem ainda.<br>Seja o primeiro a enviar! 🚀</span></div>';
            }
        }

        if (Array.isArray(mensagens) && mensagens.length > 0) {
            if(primeiraCarga) feed.innerHTML = ''; 

            mensagens.forEach(m => {
                if (m.id > ultimoIdRecebido) {
                    renderizarBolha(m);
                    ultimoIdRecebido = m.id;
                    
                    // A TRAVA: Só toca o som se NÃO for o carregamento do histórico
                    if (!primeiraCarga && deveNotificar(m)) {
                        document.getElementById('chat-notif-badge').classList.remove('hidden');
                        somNotificacao.play().catch(() => {});
                    }
                }
            });
            feed.scrollTo({
                top: feed.scrollHeight,
                behavior: 'smooth'
            });
        }
    })
    .catch(() => {}); 
}

// 3. DESENHAR A BOLHA DE MENSAGEM
function renderizarBolha(m) {
    const feed = document.getElementById('chat-feed');
    const souEu = (m.remetente_id == meuId);
    const bubble = document.createElement('div');
    
    bubble.className = `flex flex-col ${souEu ? 'items-end' : 'items-start'} w-full mb-3 animate-in fade-in slide-in-from-bottom-2 duration-300`;
    bubble.innerHTML = `
        ${!souEu ? `<span class="text-[9px] font-black text-slate-400 mb-1 ml-2 uppercase tracking-widest">${m.nome}</span>` : ''}
        <div class="${souEu ? 'bg-blue-600 text-white rounded-[1.5rem] rounded-tr-none' : 'bg-slate-100 text-slate-700 border border-slate-200 rounded-[1.5rem] rounded-tl-none'} p-4 shadow-sm max-w-[85%]">
            <p class="text-xs font-medium leading-relaxed">${m.mensagem}</p>
            <span class="text-[8px] opacity-50 block text-right mt-1 font-bold">${new Date(m.data_hora).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</span>
        </div>
    `;
    feed.appendChild(bubble);
}

// 4. TROCA DE CANAL DINÂMICA
function selecionarChat(id, nome, tipo = 'grupo') {
    destinoId = id;
    tipoDestinoAtual = tipo; // Guarda se é grupo ou pessoa
    ultimoIdRecebido = 0;
    
    avisarLeituraBanco(id, tipo);
    
    // NOVO: Verifica se o botão existe, e esconde se for o GERAL (1) ou se for um usuário (1x1)
    const btnGerenciar = document.getElementById('btn-gerenciar-grupo');
    if (btnGerenciar) {
        if (id === 1 || tipo === 'usuario') btnGerenciar.classList.add('hidden');
        else btnGerenciar.classList.remove('hidden');
    }

    document.getElementById('chat-header-nome').innerText = nome;
    document.getElementById('chat-feed').innerHTML = '<div class="flex justify-center p-10"><span class="text-[10px] font-black text-slate-400 uppercase animate-pulse">Carregando conversas...</span></div>';
    
    carregarListaGrupos(); 
    monitorarChat();       
}

// 5. ENVIAR MENSAGEM INSTANTÂNEA
document.getElementById('form-chat-navi').onsubmit = function(e) {
    e.preventDefault();
    const input = document.getElementById('input-chat-msg');
    const msg = input.value.trim();
    if (!msg) return;

    const fd = new FormData();
    fd.append('mensagem', msg);
    fd.append('destino', destinoId);
    fd.append('tipo', tipoDestinoAtual); // NOVO: Avisa se vai pro grupo ou pra pessoa

    input.value = ''; 

    fetch('api/chat_engine.php?acao=enviar', { method: 'POST', body: fd })
    .then(() => monitorarChat()); 
};

// 6. CONTROLE DE INTERFACE E NOTIFICAÇÃO
function toggleChatNavi() {
    const janela = document.getElementById('janela-chat');
    janela.classList.toggle('hidden');
    chatAberto = !janela.classList.contains('hidden');
    
    if (chatAberto) {
        document.getElementById('chat-notif-badge').classList.add('hidden');
        document.title = "NAVI Messenger";
        const feed = document.getElementById('chat-feed');
        feed.scrollTop = feed.scrollHeight;
    }
}

function deveNotificar(m) {
    if (m.remetente_id == meuId) return false;
    return (!chatAberto || document.hidden);
}

// --- LÓGICA DE GERENCIAR MEMBROS ---
function abrirModalGerenciarMembros() {
    if(destinoId === 1) return; // Segurança extra
    
    document.getElementById('nome-grupo-gerenciar').innerText = document.getElementById('chat-header-nome').innerText;
    document.getElementById('modal-gerenciar-membros').classList.remove('hidden');
    document.getElementById('lista-usuarios-glpi').innerHTML = '<p class="text-xs text-center text-slate-400 py-4 uppercase font-bold animate-pulse">Carregando equipe...</p>';

    // Pede pro PHP trazer todo mundo do GLPI e quem já está no grupo ao mesmo tempo
    Promise.all([
        fetch('api/chat_engine.php?acao=listar_usuarios_glpi').then(r => r.json()),
        fetch(`api/chat_engine.php?acao=listar_membros_grupo&grupo_id=${destinoId}`).then(r => r.json())
    ]).then(([usuarios, membrosAtuais]) => {
        renderizarListaUsuarios(usuarios, membrosAtuais.map(Number));
    });
}

function fecharModalMembros() {
    document.getElementById('modal-gerenciar-membros').classList.add('hidden');
}

function renderizarListaUsuarios(usuarios, membrosAtuais) {
    const container = document.getElementById('lista-usuarios-glpi');
    container.innerHTML = '';
    
    usuarios.forEach(u => {
        const isChecked = membrosAtuais.includes(Number(u.id)) ? 'checked' : '';
        container.innerHTML += `
            <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-100 cursor-pointer hover:border-blue-300 transition-colors usuario-item-lista">
                <input type="checkbox" name="membros_grupo[]" value="${u.id}" class="w-5 h-5 text-blue-600 rounded border-slate-300" ${isChecked}>
                <span class="text-xs font-bold text-slate-700 uppercase nome-usuario-busca">${u.nome}</span>
            </label>
        `;
    });
}

function filtrarUsuariosLista() {
    const termo = document.getElementById('busca-membros').value.toLowerCase();
    document.querySelectorAll('.usuario-item-lista').forEach(el => {
        const nome = el.querySelector('.nome-usuario-busca').innerText.toLowerCase();
        el.style.display = nome.includes(termo) ? 'flex' : 'none';
    });
}

function salvarMembros(e) {
    e.preventDefault();
    const form = document.getElementById('form-gerenciar-membros');
    const formData = new FormData(form);
    formData.append('grupo_id', destinoId);

    // CORREÇÃO: Colocamos a "acao" direto na URL, igual fizemos nas outras funções!
    fetch('api/chat_engine.php?acao=salvar_membros_grupo', { 
        method: 'POST', 
        body: formData 
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'sucesso') {
            fecharModalMembros();
            alert('✅ Membros atualizados com sucesso!');
        } else if (data.erro) {
            // Se o PHP barrar por não ser Admin, avisa na tela
            alert('Erro: ' + data.erro); 
        }
    })
    .catch(err => console.error("Erro na resposta do servidor:", err));
}

// INICIALIZAÇÃO ÚNICA (Sem loops concorrentes)
document.addEventListener('DOMContentLoaded', function() {
    carregarListaGrupos();
    monitorarChat();
    setInterval(monitorarChat, 2000);
    setInterval(carregarListaGrupos, 5000); // Fica varrendo a lateral a cada 5s para acender as bolinhas
});
</script>


</body>
</html>