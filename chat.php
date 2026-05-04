<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

$meu_id = $_SESSION['user_id'];

$destino_ativo = isset($_GET['destino']) ? (int)$_GET['destino'] : 1; 

if ($destino_ativo == 1) {
    $nome_destino = "GRUPO GERAL";
} else {
    $stmt_dest = $pdo_glpi->prepare("SELECT firstname, realname FROM glpi_users WHERE id = ?");
    $stmt_dest->execute([$destino_ativo]);
    $dest = $stmt_dest->fetch(PDO::FETCH_ASSOC);
    $nome_destino = $dest ? $dest['firstname'] . ' ' . $dest['realname'] : "Usuário Desconhecido";
}

$sql_contatos = "
    SELECT u.id, u.firstname, u.realname,
           (SELECT MAX(data_hora) 
            FROM chat_mensagens 
            WHERE (remetente_id = u.id AND destinatario_id = :meu_id) 
               OR (remetente_id = :meu_id AND destinatario_id = u.id)
           ) as ultima_msg
    FROM glpi_users u 
    WHERE u.is_deleted = 0 AND u.is_active = 1 AND u.id != :meu_id
    ORDER BY ultima_msg DESC, u.firstname ASC
";
$stmt_contatos = $pdo_glpi->prepare($sql_contatos);
$stmt_contatos->execute(['meu_id' => $meu_id]);
$contatos = $stmt_contatos->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- AJUSTE: h-screen removido do main para evitar bugs de scroll no mobile -->
<main class="flex-1 bg-slate-50 p-2 md:p-4 h-[calc(100vh-64px)] flex flex-col overflow-hidden">
    <div class="max-w-7xl mx-auto w-full flex h-full bg-white rounded-[2rem] md:rounded-[3rem] shadow-2xl border border-slate-200 overflow-hidden">
        
        <!-- COLUNA DE CONTATOS: Escondida no mobile se um destino estiver ativo -->
        <div class="<?php echo isset($_GET['destino']) ? 'hidden' : 'flex'; ?> md:flex w-full md:w-1/3 bg-slate-50 border-r border-slate-200 flex-col">
            <div class="p-6 bg-navy-900 text-white">
                <h2 class="text-xl font-black uppercase italic tracking-tighter">Mensagens Rápidas</h2>
            </div>
            
            <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-2">
                <a href="chat.php?destino=1" class="flex items-center gap-3 p-3 rounded-2xl transition-all <?= ($destino_ativo == 1) ? 'bg-blue-100 border border-blue-200' : 'hover:bg-slate-100 border border-transparent' ?>">
                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white font-bold">🌍</div>
                    <div class="flex-1">
                        <h4 class="text-xs font-black text-navy-900 uppercase">Grupo Geral</h4>
                        <p class="text-[9px] text-slate-400 font-bold">Chat de toda a empresa</p>
                    </div>
                </a>

                <hr class="border-slate-200 my-4">

                <?php foreach($contatos as $c): ?>
                <a href="chat.php?destino=<?= $c['id'] ?>" class="flex items-center gap-3 p-3 rounded-2xl transition-all <?= ($destino_ativo == $c['id']) ? 'bg-blue-100 border border-blue-200' : 'hover:bg-slate-100 border border-transparent' ?>">
                    <div class="w-10 h-10 bg-slate-200 text-slate-600 rounded-xl flex items-center justify-center font-black">
                        <?= substr($c['firstname'], 0, 1) ?>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <h4 class="text-xs font-black text-navy-900 uppercase truncate"><?= $c['firstname'] . ' ' . $c['realname'] ?></h4>
                        <?php if($c['ultima_msg']): ?>
                            <p class="text-[9px] text-blue-500 font-bold truncate">Última msg: <?= date('d/m H:i', strtotime($c['ultima_msg'])) ?></p>
                        <?php else: ?>
                            <p class="text-[9px] text-slate-300 font-medium italic">Iniciar conversa</p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- COLUNA DE MENSAGENS: Ocupa tudo no mobile se houver destino -->
        <div class="<?php echo isset($_GET['destino']) ? 'flex' : 'hidden'; ?> md:flex w-full md:w-2/3 flex-col bg-white">
            <div class="bg-white border-b border-slate-100 p-4 md:p-6 flex justify-between items-center shadow-sm z-10">
                <div class="flex items-center gap-4">
                    <!-- Botão Voltar: Só aparece no Mobile para voltar à lista de contatos -->
                    <a href="chat.php" class="md:hidden p-2 text-slate-400 hover:text-navy-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-600 rounded-xl md:rounded-2xl flex items-center justify-center text-xl md:text-2xl shadow-lg shadow-blue-600/20">💬</div>
                    <div>
                        <h2 class="text-sm md:text-xl font-black uppercase italic tracking-tighter text-navy-900 truncate max-w-[150px] md:max-w-none"><?= $nome_destino ?></h2>
                        <p class="hidden md:block text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-1">Conectado como: <?= $_SESSION['user_name'] ?></p>
                    </div>
                </div>
            </div>

            <div id="chat-container" class="flex-1 p-4 md:p-6 overflow-y-auto space-y-4 custom-scrollbar bg-slate-50/50">
                <div class="flex justify-center py-10">
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.3em]">Carregando histórico...</p>
                </div>
            </div>

            <div class="p-4 md:p-6 bg-white border-t border-slate-100">
                <form id="form-chat" class="flex gap-2 md:gap-3 items-center relative">
                    
                    <div class="relative">
                        <button type="button" onclick="document.getElementById('emoji-picker').classList.toggle('hidden')" class="p-2 md:p-3 text-slate-400 hover:text-amber-500 transition-all text-xl md:text-2xl hover:scale-110">
                            😀
                        </button>
                        <div id="emoji-picker" class="hidden absolute bottom-14 left-0 bg-white border border-slate-200 shadow-2xl rounded-2xl p-3 grid grid-cols-6 gap-2 z-50 w-64">
                            <?php 
                            $emojis = ['😀','😂','🥰','😎','🤔','👍','🙌','🔥','🎉','👀','🚨','✅','💼','💡','🚀','🎯','📝','📅'];
                            foreach($emojis as $e): 
                            ?>
                                <button type="button" onclick="addEmoji('<?= $e ?>')" class="hover:bg-slate-100 p-2 rounded-xl text-xl transition-colors"><?= $e ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <input type="text" id="input-mensagem" autocomplete="off" placeholder="Mensagem..." 
                        class="flex-1 bg-slate-50 border border-slate-200 p-3 md:p-4 rounded-2xl text-xs md:text-sm font-medium outline-none focus:ring-4 focus:ring-blue-600/10 transition-all">
                    
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white w-12 h-12 md:w-14 md:h-14 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-600/20 transition-all transform hover:scale-105 shrink-0">
                        <svg class="w-5 h-5 md:w-6 md:h-6 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </form>
            </div>
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

    function addEmoji(emoji) {
        inputMsg.value += emoji;
        inputMsg.focus();
        document.getElementById('emoji-picker').classList.add('hidden');
    }

    function buscarMensagens() {
        fetch(`api/chat_engine.php?acao=buscar&destino=${destinoAtivo}&ultimo_id=${ultimoIdRecebido}`)
        .then(res => res.json())
        .then(mensagens => {
            if (mensagens.length > 0) {
                if(ultimoIdRecebido === 0) chatContainer.innerHTML = '';

                mensagens.forEach(m => {
                    const souEu = (m.remetente_id == meuId);
                    const bubble = document.createElement('div');
                    
                    const dataMsg = new Date(m.data_hora);
                    const strData = dataMsg.toLocaleDateString('pt-BR') + ' ' + dataMsg.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});

                    bubble.className = `flex ${souEu ? 'justify-end' : 'justify-start'} w-full animate-in fade-in slide-in-from-bottom-2 duration-300`;
                    
                    bubble.innerHTML = `
                        <div class="max-w-[85%] md:max-w-[70%]">
                            <div class="flex items-center gap-2 mb-1 ${souEu ? 'justify-end' : 'justify-start'}">
                                <span class="text-[9px] font-black uppercase text-slate-400">${m.nome}</span>
                            </div>
                            <div class="p-3 md:p-4 rounded-[1.2rem] md:rounded-[1.5rem] text-xs md:text-sm shadow-sm font-medium ${souEu ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-white text-slate-700 border border-slate-200 rounded-tl-none'} relative group">
                                ${m.mensagem}
                                <span class="block mt-2 text-[8px] md:text-[9px] ${souEu ? 'text-blue-200' : 'text-slate-400'} text-right font-bold">
                                    ${strData}
                                </span>
                            </div>
                        </div>
                    `;
                    
                    chatContainer.appendChild(bubble);
                    ultimoIdRecebido = m.id;
                });
                
                chatContainer.scrollTop = chatContainer.scrollHeight;
            } else if (ultimoIdRecebido === 0) {
                chatContainer.innerHTML = '<div class="flex justify-center py-10"><p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.3em]">Nenhuma mensagem ainda.</p></div>';
            }
        })
        .catch(err => console.error("Erro ao buscar:", err));
    }

    formChat.onsubmit = (e) => {
        e.preventDefault();
        const texto = inputMsg.value.trim();
        if(!texto) return;

        const formData = new FormData();
        formData.append('mensagem', texto);
        formData.append('destino', destinoAtivo);

        inputMsg.value = ''; 

        fetch('api/chat_engine.php?acao=enviar', {
            method: 'POST',
            body: formData
        })
        .then(() => {
            buscarMensagens(); 
        });
    };

    buscarMensagens();
    setInterval(buscarMensagens, 2000);
</script>

<?php include 'includes/footer.php'; ?>