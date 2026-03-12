<?php 
require_once 'config.php'; 
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

$diretorio_marketing = 'img/comunicacao/';
$caminho_lista = $diretorio_marketing . 'aniversariantes_lista.txt';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lista_nomes'])) {
    $texto_bruto = $_POST['lista_nomes'];
    $texto_limpo = preg_replace('/\t+/', ';', $texto_bruto);
    $texto_limpo = preg_replace('/\s*;\s*/', ';', $texto_limpo);
    $texto_limpo = trim($texto_limpo);
    file_put_contents($caminho_lista, $texto_limpo);

    if (!empty($_FILES['img_mini']['name'])) {
        move_uploaded_file($_FILES['img_mini']['tmp_name'], $diretorio_marketing . "aniversariantes_mini.png");
    }
    if (!empty($_FILES['img_modal']['name'])) {
        move_uploaded_file($_FILES['img_modal']['tmp_name'], $diretorio_marketing . "aniversariantes_modal.png");
    }
    echo "<script>alert('Aniversariantes Atualizados!'); window.location.href='admin_aniversariantes.php';</script>";
}

$conteudo_atual = file_exists($caminho_lista) ? file_get_contents($caminho_lista) : "";
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
    <div class="max-w-5xl mx-auto space-y-6">
        <a href="admin_marketing.php" class="text-slate-400 hover:text-navy-900 font-bold text-xs uppercase tracking-widest flex items-center gap-2 transition-all mb-4">⬅ Voltar ao Hub</a>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8">
                <h4 class="text-xl font-black text-navy-900 uppercase tracking-tighter mb-4 italic">Editor da Lista</h4>
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <textarea name="lista_nomes" rows="15" class="w-full p-6 bg-slate-50 border-2 border-slate-100 rounded-3xl font-mono text-sm focus:border-amber-400 outline-none transition-all" placeholder="NOME;DIA/MES"><?php echo htmlspecialchars($conteudo_atual); ?></textarea>
                    
                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-black py-5 rounded-2xl shadow-lg transition-all uppercase tracking-widest">
                        💾 Salvar Lista e Imagens
                    </button>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-6">
                    <h5 class="text-[10px] font-black uppercase text-slate-400 mb-3 tracking-widest">Miniatura Card</h5>
                    <div class="aspect-video mb-4 rounded-2xl overflow-hidden bg-slate-100 border border-slate-100">
                        <img src="<?php echo $diretorio_marketing; ?>aniversariantes_mini.png?t=<?php echo time(); ?>" class="w-full h-full object-cover">
                    </div>
                    <input type="file" name="img_mini" accept="image/png" class="text-[10px] w-full file:bg-amber-50 file:text-amber-700 file:border-0 file:rounded-full file:px-4 file:py-2">
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-6">
                    <h5 class="text-[10px] font-black uppercase text-slate-400 mb-3 tracking-widest">Arte Modal (Lista)</h5>
                    <div class="aspect-[3/4] mb-4 rounded-2xl overflow-hidden bg-slate-100 border border-slate-100 flex items-center justify-center p-2">
                        <img src="<?php echo $diretorio_marketing; ?>aniversariantes_modal.png?t=<?php echo time(); ?>" class="max-w-full max-h-full object-contain">
                    </div>
                    <input type="file" name="img_modal" accept="image/png" class="text-[10px] w-full file:bg-amber-50 file:text-amber-700 file:border-0 file:rounded-full file:px-4 file:py-2">
                </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>