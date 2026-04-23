<?php
require_once 'config.php';

// Segurança: Admin ou permissão de Feed
if (!$_SESSION['is_admin'] && !($_SESSION['pode_postar_feed'] ?? false)) {
    header("Location: index.php");
    exit;
}

$admin_id = $_SESSION['user_id'] ?? 0;
$msg_sucesso = "";

// --- LÓGICA DE PROCESSAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'salvar_comunicado') {
        $titulo = trim($_POST['com_titulo']);
        $resumo = trim($_POST['com_resumo']);
        $categoria = $_POST['com_categoria'];
        $cid = $_POST['comunicado_id'] ?? '';

        if(empty($cid)){
            $sql = "INSERT INTO comunicados (titulo, resumo, categoria, autor_id) VALUES (?, ?, ?, ?)";
            $pdo_intra->prepare($sql)->execute([$titulo, $resumo, $categoria, $admin_id]);
            registrarLog($pdo_intra, 'POSTOU NOTÍCIA', "Publicou: $titulo");
        } else {
            $sql = "UPDATE comunicados SET titulo=?, resumo=?, categoria=? WHERE id=?";
            $pdo_intra->prepare($sql)->execute([$titulo, $resumo, $categoria, $cid]);
            registrarLog($pdo_intra, 'EDITOU NOTÍCIA', "Alterou: $titulo");
        }
        $msg_sucesso = "📢 Feed atualizado com sucesso!";
    }

    if ($_POST['acao'] === 'excluir_comunicado') {
        $cid = $_POST['comunicado_id'];
        $pdo_intra->prepare("DELETE FROM comunicados WHERE id = ?")->execute([$cid]);
        registrarLog($pdo_intra, 'EXCLUIU NOTÍCIA', "ID: $cid");
        $msg_sucesso = "🗑️ Comunicado removido!";
    }
}

include 'includes/header.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="max-w-6xl mx-auto">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-navy-900 italic uppercase tracking-tighter">Gestão de Marketing</h1>
                <p class="text-slate-500 font-medium">Controle as notícias e comunicados do portal.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="index.php" class="bg-white text-navy-900 border border-slate-200 px-5 py-3 rounded-2xl font-bold text-xs uppercase hover:bg-slate-100 transition-all flex items-center gap-2 shadow-sm">
                    <span>🏠</span> Voltar ao Início
                </a>

                <button onclick="abrirModalComunicado(0)" class="bg-amber-500 text-white px-6 py-3 rounded-2xl font-black uppercase text-xs shadow-lg shadow-amber-500/30 hover:scale-105 transition-all">
                    + Novo Comunicado
                </button>
            </div>
        </div>

        <?php if($msg_sucesso): ?>
            <div class="mb-6 p-4 bg-emerald-500 text-white rounded-2xl font-bold animate-bounce text-center shadow-lg">
                <?php echo $msg_sucesso; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Post / Setor</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Conteúdo</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Data</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php 
                    $coms = $pdo_intra->query("SELECT * FROM comunicados ORDER BY data_postagem DESC")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($coms as $c): 
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-5">
                            <div class="font-bold text-navy-900"><?php echo $c['titulo']; ?></div>
                            <span class="text-[9px] px-2 py-0.5 bg-slate-100 rounded-full font-black uppercase text-slate-500"><?php echo $c['categoria']; ?></span>
                        </td>
                        <td class="px-6 py-5">
                            <p class="text-xs text-slate-500 line-clamp-1 max-w-sm"><?php echo $c['resumo']; ?></p>
                        </td>
                        <td class="px-6 py-5 text-[10px] font-bold text-slate-400">
                            <?php echo date('d/m/Y H:i', strtotime($c['data_postagem'])); ?>
                        </td>
                        <td class="px-6 py-5">
                            <div class="flex justify-center gap-2">
                                <button onclick="abrirModalComunicado(<?php echo htmlspecialchars(json_encode($c)); ?>)" class="p-2 bg-slate-100 rounded-xl hover:bg-navy-900 hover:text-white transition-all text-sm">✏️</button>
                                <form method="POST" onsubmit="return confirm('Excluir este post permanentemente?');">
                                    <input type="hidden" name="acao" value="excluir_comunicado">
                                    <input type="hidden" name="comunicado_id" value="<?php echo $c['id']; ?>">
                                    <button class="p-2 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-all text-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="modalComunicado" class="fixed inset-0 bg-navy-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in zoom-in-95 duration-300">
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_comunicado">
            <input type="hidden" name="comunicado_id" id="mcom_id">
            
            <div class="px-8 py-6 bg-amber-500 text-white flex justify-between items-center">
                <h3 class="text-xl font-black italic uppercase italic">Postar no Feed 📢</h3>
                <button type="button" onclick="fecharModal()" class="text-white/50 hover:text-white text-3xl font-light">&times;</button>
            </div>
            
            <div class="p-8 space-y-4">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3">Título</label>
                    <input type="text" name="com_titulo" id="mcom_titulo" required class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm font-bold uppercase focus:ring-2 ring-amber-500 outline-none transition-all">
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3">Categoria</label>
                    <select name="com_categoria" id="mcom_categoria" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm font-bold uppercase outline-none">
                        <option value="MARKETING">🎨 Marketing</option>
                        <option value="RH">👥 RH</option>
                        <option value="TI">💻 TI</option>
                        <option value="AVISO GERAL">📣 Aviso Geral</option>
                        <option value="IMPORTANTE">🚨 Importante</option>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3">Conteúdo</label>
                    <textarea name="com_resumo" id="mcom_resumo" required rows="5" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm focus:ring-2 ring-amber-500 outline-none transition-all"></textarea>
                </div>
            </div>
            
            <div class="p-6 bg-slate-50 border-t border-slate-200">
                <button type="submit" class="w-full bg-amber-500 text-white py-4 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-amber-600 shadow-lg shadow-amber-500/30 transition-all">
                    Publicar Agora 🚀
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalComunicado(dados) {
    document.getElementById('mcom_id').value = '';
    document.getElementById('mcom_titulo').value = '';
    document.getElementById('mcom_resumo').value = '';
    
    if(dados !== 0) {
        document.getElementById('mcom_id').value = dados.id;
        document.getElementById('mcom_titulo').value = dados.titulo;
        document.getElementById('mcom_resumo').value = dados.resumo;
        document.getElementById('mcom_categoria').value = dados.categoria;
    }
    document.getElementById('modalComunicado').classList.replace('hidden', 'flex');
}

function fecharModal() {
    document.getElementById('modalComunicado').classList.replace('flex', 'hidden');
}
</script>

<?php include 'includes/footer.php'; ?>