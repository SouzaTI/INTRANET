<?php 
require_once 'config.php'; 
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// Busca todos os banners para a tabela
$todos_banners = $pdo_intra->query("SELECT * FROM banners_marketing ORDER BY id DESC")->fetchAll();
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
    <div class="max-w-5xl mx-auto space-y-8">
        
        <a href="admin_marketing.php" class="text-slate-400 hover:text-navy-900 font-bold text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
            ⬅ Voltar ao Hub
        </a>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8">
            <h4 class="text-2xl font-black text-navy-900 uppercase tracking-tighter mb-6">📢 Nova Campanha / Destaque</h4>
            
            <form action="api/processa_banner.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Título do Banner</label>
                        <input type="text" name="titulo" placeholder="Ex: Treinamento TOTVS" class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-purple-400 transition-all">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Data Início</label>
                            <input type="date" name="data_inicio" required class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Data Fim</label>
                            <input type="date" name="data_fim" required class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2 mb-2">Arquivo da Arte (1200x400px)</label>
                    <div class="flex-1 border-2 border-dashed border-slate-200 rounded-3xl flex flex-col items-center justify-center p-6 bg-slate-50 hover:bg-slate-100 transition-all cursor-pointer relative">
                        <input type="file" name="banner_arquivo" accept="image/*" required class="absolute inset-0 opacity-0 cursor-pointer">
                        <span class="text-3xl mb-2">🖼️</span>
                        <span class="text-xs font-bold text-slate-400 uppercase">Clique para selecionar</span>
                    </div>
                    <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white font-black py-5 rounded-2xl shadow-lg transition-all uppercase tracking-widest">
                        🚀 Agendar Publicação
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8">
            <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-6 px-2">Campanhas na Fila</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-slate-400 uppercase text-[10px] tracking-widest">
                            <th class="px-4 pb-4">Miniatura</th>
                            <th class="px-4 pb-4">Título / Período</th>
                            <th class="px-4 pb-4">Status</th>
                            <th class="px-4 pb-4 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todos_banners)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-10 text-slate-400 italic">Nenhum banner agendado ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($todos_banners as $b): 
                                $hoje = strtotime(date('Y-m-d'));
                                $inicio = strtotime($b['data_inicio']);
                                $fim = strtotime($b['data_fim']);
                                
                                // Lógica de Status
                                if ($hoje > $fim) {
                                    $status_label = "Expirado";
                                    $status_class = "bg-red-50 text-red-600";
                                } elseif ($hoje < $inicio) {
                                    $status_label = "Agendado";
                                    $status_class = "bg-amber-50 text-amber-600";
                                } else {
                                    $status_label = "No Ar";
                                    $status_class = "bg-green-50 text-green-600";
                                }
                            ?>
                            <tr class="bg-slate-50/50 group">
                                <td class="px-4 py-3 rounded-l-2xl">
                                    <img src="<?php echo $b['imagem_path']; ?>" class="w-20 h-12 object-cover rounded-xl border border-white shadow-sm">
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-black text-navy-900 leading-none mb-1"><?php echo htmlspecialchars($b['titulo']); ?></p>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">
                                        📅 <?php echo date('d/m', $inicio); ?> até <?php echo date('d/m', $fim); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest <?php echo $status_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 rounded-r-2xl text-right flex justify-end gap-2">
                                    <!-- Botão de Editar -->
                                    <button type="button" 
                                        onclick="abrirModalEdicaoBanner(<?php echo $b['id']; ?>, '<?php echo htmlspecialchars($b['titulo'], ENT_QUOTES); ?>', '<?php echo $b['data_inicio']; ?>', '<?php echo $b['data_fim']; ?>')"
                                        class="bg-white p-2.5 rounded-xl border border-slate-200 text-blue-500 hover:bg-blue-500 hover:text-white transition-all shadow-sm" title="Editar Datas">
                                        ✏️
                                    </button>

                                    <!-- Botão de Excluir (O seu original) -->
                                    <a href="api/excluir_banner.php?id=<?php echo $b['id']; ?>" 
                                    onclick="return confirm('Deseja realmente remover esta campanha?')"
                                    class="bg-white p-2.5 rounded-xl border border-slate-200 text-red-500 hover:bg-red-500 hover:text-white transition-all shadow-sm" title="Excluir">
                                        🗑️
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- MODAL DE EDICÃO DE BANNER -->
<div id="modal-edita-banner" class="hidden fixed inset-0 bg-navy-900/60 backdrop-blur-sm z-[1000] flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in zoom-in-95 duration-300">
        <form action="api/editar_banner.php" method="POST">
            <!-- ID oculto para o banco saber quem atualizar -->
            <input type="hidden" name="id" id="edit_banner_id">
            
            <div class="px-8 py-6 bg-purple-600 text-white flex justify-between items-center">
                <h3 class="text-xl font-black italic uppercase">Editar Campanha</h3>
                <button type="button" onclick="fecharModalEdicaoBanner()" class="text-white/50 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            
            <div class="p-8 space-y-4">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Título do Banner</label>
                    <input type="text" name="titulo" id="edit_banner_titulo" required class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-purple-400 transition-all font-bold text-navy-900">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Data Início</label>
                        <input type="date" name="data_inicio" id="edit_banner_inicio" required class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none text-slate-700">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Data Fim</label>
                        <input type="date" name="data_fim" id="edit_banner_fim" required class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none text-slate-700">
                    </div>
                </div>
            </div>
            
            <div class="p-6 bg-slate-50 border-t border-slate-200 flex gap-4">
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-4 rounded-2xl font-black uppercase tracking-widest shadow-md transition-all active:scale-95">
                    💾 Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalEdicaoBanner(id, titulo, inicio, fim) {
        document.getElementById('edit_banner_id').value = id;
        document.getElementById('edit_banner_titulo').value = titulo;
        document.getElementById('edit_banner_inicio').value = inicio;
        document.getElementById('edit_banner_fim').value = fim;
        
        const modal = document.getElementById('modal-edita-banner');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function fecharModalEdicaoBanner() {
        const modal = document.getElementById('modal-edita-banner');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
</script>

<?php include 'includes/footer.php'; ?>