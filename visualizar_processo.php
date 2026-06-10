<?php 
require_once 'config.php';
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// Busca documentos aprovados (Substituindo a listagem de arquivos físicos pela busca no DB)
// Nota: Se quiser separar por setores, adicione a coluna 'setor_origem' no banco e agrupe aqui
$stmt = $pdo_intra->query("SELECT * FROM docs_fluxo_simples WHERE status = 'Aprovado' ORDER BY titulo ASC");
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-12 document-content">
    
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <nav class="flex text-sm text-slate-400 font-medium italic mb-2">
                    <span>Gestão de Processos</span>
                </nav>
                <h2 class="text-3xl font-black text-navy-900 tracking-tight">Processos Homologados</h2>
            </div>
            <input type="text" id="searchInput" placeholder="Pesquisar procedimentos..." 
                   class="px-5 py-3 rounded-xl border border-slate-200 text-sm w-72 focus:ring-2 focus:ring-corporate-blue outline-none shadow-sm">
        </div>

        <div id="gridProcessos" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($documentos)): ?>
                <div class="col-span-full text-center py-20 bg-white/50 rounded-3xl border-2 border-dashed border-slate-200">
                    <p class="text-slate-400 font-medium italic">Nenhum processo homologado disponível no momento.</p>
                </div>
            <?php else: foreach ($documentos as $doc): 
                // Define ícone baseado na extensão
                $ext = strtolower(pathinfo($doc['nome_arquivo'], PATHINFO_EXTENSION));
                $icone = ($ext == 'pdf') ? '📕' : '📄';
            ?>
                <div class="doc-card group relative bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-corporate-blue transition-all transform hover:-translate-y-1 flex flex-col cursor-pointer"
                     onclick="abrirViewer(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['titulo']) ?>')">
                    
                    <span class="absolute top-4 right-4 text-[9px] font-black bg-emerald-100 text-emerald-600 px-2 py-1 rounded uppercase tracking-widest">Aprovado</span>
                    
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 transition-colors bg-blue-50 text-corporate-blue group-hover:bg-corporate-blue group-hover:text-white">
                        <span class="text-2xl"><?= $icone ?></span>
                    </div>
                    
                    <h3 class="font-bold text-navy-900 mb-2 group-hover:text-corporate-blue"><?= htmlspecialchars($doc['titulo']) ?></h3>
                    <p class="text-xs text-slate-400 mt-auto">Versão: <b>V<?= $doc['versao_atual'] ?></b></p>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</main>

<div id="viewerModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-navy-900/40 backdrop-blur-sm p-4">
    <div class="bg-white w-full max-w-5xl h-[85vh] rounded-3xl flex flex-col overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 id="modalTitulo" class="font-black text-navy-900 text-sm">Visualizando Processo...</h3>
            <button onclick="fecharViewer()" class="text-slate-400 hover:text-red-500 font-black text-xl px-2">&times;</button>
        </div>
        <iframe id="iframeViewer" src="" class="w-full flex-1 border-0"></iframe>
    </div>
</div>

<script>
// Filtro de busca simples
document.getElementById('searchInput').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.doc-card').forEach(card => {
        const title = card.querySelector('h3').textContent.toLowerCase();
        card.style.display = title.includes(term) ? 'flex' : 'none';
    });
});

// Abertura do Modal (Exibe inline)
function abrirViewer(id, titulo) {
    document.getElementById('modalTitulo').textContent = titulo;
    // O modo=visualizar garante o Content-Disposition: inline
    document.getElementById('iframeViewer').src = 'serve_documento.php?id=' + id + '&modo=visualizar';
    document.getElementById('viewerModal').classList.remove('hidden');
}

// Fechamento
function fecharViewer() {
    document.getElementById('viewerModal').classList.add('hidden');
    document.getElementById('iframeViewer').src = '';
}
</script>

<?php include 'includes/footer.php'; ?>