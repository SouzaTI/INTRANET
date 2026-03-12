<?php 
require_once 'config.php'; 
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

$diretorio_marketing = 'img/comunicacao/';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_FILES['img_welcome']['name'])) {
    move_uploaded_file($_FILES['img_welcome']['tmp_name'], $diretorio_marketing . "banner-boas-vindas.png");
    echo "<script>alert('Banner de Boas-Vindas Atualizado!'); window.location.href='admin_boas_vindas.php';</script>";
}
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
    <div class="max-w-4xl mx-auto space-y-6 text-center">
        <div class="flex justify-start">
            <a href="admin_marketing.php" class="text-slate-400 hover:text-navy-900 font-bold text-xs uppercase tracking-widest flex items-center gap-2 transition-all">⬅ Voltar ao Hub</a>
        </div>

        <div class="bg-white rounded-[3rem] shadow-sm border border-slate-200 p-10 space-y-8">
            <div>
                <h4 class="text-3xl font-black text-navy-900 uppercase tracking-tighter italic mb-2">Banner de Boas-Vindas</h4>
                <p class="text-slate-500 font-medium italic">Esta é a primeira imagem que o colaborador vê ao entrar no portal.</p>
            </div>

            <div class="relative rounded-[2rem] overflow-hidden border-4 border-slate-50 shadow-inner group bg-slate-100">
                <img src="<?php echo $diretorio_marketing; ?>banner-boas-vindas.png?t=<?php echo time(); ?>" class="w-full h-64 object-cover transition-transform group-hover:scale-105 duration-700">
                <div class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <span class="text-white font-black text-xs uppercase tracking-widest bg-black/40 px-6 py-3 rounded-full backdrop-blur-md">Visualização Atual</span>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="max-w-md mx-auto space-y-6">
                <div class="p-6 bg-slate-50 border-2 border-dashed border-slate-200 rounded-3xl">
                    <input type="file" name="img_welcome" accept="image/png" required class="text-xs font-bold text-slate-500 cursor-pointer">
                    <p class="mt-4 text-[10px] font-black text-amber-600 uppercase">Tamanho Recomendado: 1200 x 400px (PNG)</p>
                </div>

                <button type="submit" class="w-full bg-navy-900 hover:bg-corporate-blue text-white font-black py-5 rounded-2xl shadow-xl transition-all uppercase tracking-widest flex items-center justify-center gap-3">
                    🚀 Publicar Nova Imagem
                </button>
            </form>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>