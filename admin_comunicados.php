<?php 
require_once 'config.php'; 
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// Lógica de Postagem
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['postar_comunicado'])) {
    $titulo = $_POST['titulo'];
    $conteudo = $_POST['conteudo'];
    $categoria = $_POST['categoria'];

    $stmt = $pdo_intra->prepare("INSERT INTO comunicados (titulo, conteudo, categoria) VALUES (?, ?, ?)");
    $stmt->execute([$titulo, $conteudo, $categoria]);
    echo "<script>alert('✅ Comunicado publicado!'); window.location.href='admin_comunicados.php';</script>";
}

$comunicados = $pdo_intra->query("SELECT * FROM comunicados ORDER BY data_postagem DESC")->fetchAll();
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
    <div class="max-w-4xl mx-auto space-y-8">
        <a href="admin_marketing.php" class="text-slate-400 hover:text-navy-900 font-bold text-xs uppercase tracking-widest flex items-center gap-2 transition-all">⬅ Voltar ao Hub</a>
        
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8">
            <h4 class="text-2xl font-black text-navy-900 uppercase tracking-tighter mb-6 italic">📢 Novo Comunicado</h4>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Título do Aviso</label>
                        <input type="text" name="titulo" required class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-corporate-blue transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Categoria</label>
                        <select name="categoria" class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none">
                            <option value="GERAL">GERAL</option>
                            <option value="RH">RECURSOS HUMANOS</option>
                            <option value="TI">T.I.</option>
                            <option value="EVENTO">EVENTOS</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Conteúdo do Texto</label>
                    <textarea name="conteudo" rows="4" required class="w-full p-6 bg-slate-50 border-2 border-slate-100 rounded-3xl outline-none focus:border-corporate-blue transition-all"></textarea>
                </div>
                <button type="submit" name="postar_comunicado" class="w-full bg-navy-900 text-white font-black py-5 rounded-2xl shadow-xl uppercase tracking-widest">Publicar Agora</button>
            </form>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8">
             <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-6">Comunicados Ativos</h4>
             <div class="space-y-4">
                 <?php foreach($comunicados as $c): ?>
                 <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                     <div>
                         <span class="text-[9px] font-black px-2 py-1 bg-white border border-slate-200 rounded text-corporate-blue mr-2"><?php echo $c['categoria']; ?></span>
                         <span class="font-bold text-navy-900"><?php echo $c['titulo']; ?></span>
                         <p class="text-[10px] text-slate-400 ml-14"><?php echo date('d/m/Y', strtotime($c['data_postagem'])); ?></p>
                     </div>
                     <a href="excluir_comunicado.php?id=<?php echo $c['id']; ?>" class="text-red-400 hover:text-red-600 transition-colors">🗑️</a>
                 </div>
                 <?php endforeach; ?>
             </div>
        </div>
    </div>
</main>