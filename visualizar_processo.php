<?php 
require_once 'config.php';

require_once 'includes/validar_upload.php';

// =========================================================================
// LÓGICA DE UPLOAD DIRETO HOMOLOGADO (SOMENTE ADMIN)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'upload_direto' && $_SESSION['is_admin']) {
    $titulo = trim($_POST['titulo']);
    $setor = $_POST['setor_origem'] ?? 'GERAL';
    $user_id = $_SESSION['user_id'];
    
    if (!isset($_FILES['documento']) || $_FILES['documento']['error'] === UPLOAD_ERR_NO_FILE) {
        $erro_upload = "Selecione um arquivo para enviar.";
    } else {
        $validacao = validarArquivoFluxo($_FILES['documento']);
        
        if (!$validacao['ok']) {
            $erro_upload = $validacao['erro'];
        } else {
            $nome_novo = $validacao['nome'];
            $caminho_destino = 'uploads_fluxo/' . $nome_novo;
            
            if (move_uploaded_file($_FILES['documento']['tmp_name'], $caminho_destino)) {
                
                // Pega o Hash do arquivo para auditoria
                $hash = hash_file('sha256', $caminho_destino);
                
                // Insere o documento furando a fila (já entra como Aprovado)
                $sql = "INSERT INTO docs_fluxo_simples 
                        (usuario_id, titulo, nome_arquivo, setor_origem, status, versao_atual, ciclos_revisao, publicado_em, aprovado_por, hash_arquivo) 
                        VALUES (?, ?, ?, ?, 'Aprovado', 1, 0, NOW(), ?, ?)";
                
                $stmt = $pdo_intra->prepare($sql);
                $stmt->execute([$user_id, $titulo, $nome_novo, $setor, $user_id, $hash]);
                
                registrarLog($pdo_intra, 'PROCESSO HOMOLOGADO (DIRETO)', "Admin fez upload direto do processo: $titulo no setor $setor.");
                
                header("Location: visualizar_processo.php?sucesso_upload=1");
                exit;
            } else {
                $erro_upload = "Falha ao mover arquivo para o diretório de uploads.";
            }
        }
    }
}

include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// Pega o setor atual (setor_origem) da URL
$setor_atual = isset($_GET['setor_origem']) ? urldecode($_GET['setor_origem']) : 'TODOS OS SETORES';

$modo_exibicao = 'documentos';
$dados = [];

if ($setor_atual === 'TODOS OS SETORES') {
    // 📂 MODO 1: EXIBIR AS PASTAS (Visão Geral)
    $modo_exibicao = 'pastas';
    // Agrupa pelo setor e conta quantos documentos aprovados tem em cada um
    $stmt = $pdo_intra->query("
        SELECT COALESCE(NULLIF(setor_origem, ''), 'GERAL') as setor, COUNT(id) as total_docs 
        FROM docs_fluxo_simples 
        WHERE status = 'Aprovado' 
        GROUP BY setor 
        ORDER BY setor ASC
    ");
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // 📄 MODO 2: EXIBIR DOCUMENTOS DA PASTA SELECIONADA
    $stmt = $pdo_intra->prepare("
        SELECT * FROM docs_fluxo_simples 
        WHERE status = 'Aprovado' 
          AND COALESCE(NULLIF(setor_origem, ''), 'GERAL') = ? 
        ORDER BY titulo ASC
    ");
    $stmt->execute([$setor_atual]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-12 document-content">
    
    <div class="max-w-6xl mx-auto">
        <?php if(isset($_GET['sucesso_upload'])): ?>
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-2xl font-bold animate-pulse">
                ✅ Processo homologado inserido com sucesso na biblioteca!
            </div>
        <?php endif; ?>
        <?php if(isset($erro_upload)): ?>
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl font-bold">
                ❌ <?= htmlspecialchars($erro_upload) ?>
            </div>
        <?php endif; ?>

        <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <?php if ($modo_exibicao === 'pastas'): ?>
                    <nav class="flex text-sm text-slate-400 font-medium italic mb-2">
                        <span>Processos Homologados</span>
                        <span class="mx-3 text-slate-300">/</span>
                        <span class="text-corporate-blue font-bold truncate">Visão Geral</span>
                    </nav>
                    <h2 class="text-3xl font-black text-navy-900 tracking-tight">Biblioteca de Processos</h2>
                <?php else: ?>
                    <nav class="flex text-sm text-slate-400 font-medium italic mb-2">
                        <a href="visualizar_processo.php" class="hover:text-corporate-blue transition-colors">Processos Homologados</a>
                        <span class="mx-3 text-slate-300">/</span>
                        <span class="text-corporate-blue font-bold truncate"><?= htmlspecialchars($setor_atual) ?></span>
                    </nav>
                    <h2 class="text-3xl font-black text-navy-900 tracking-tight">
                        Pasta: <span class="text-corporate-blue"><?= htmlspecialchars($setor_atual) ?></span>
                    </h2>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-3 w-full md:w-auto">
                <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <button onclick="document.getElementById('modal-upload-direto').classList.remove('hidden'); document.getElementById('modal-upload-direto').classList.add('flex'); document.body.style.overflow='hidden';" 
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-emerald-900/20 transition-all flex items-center gap-2 shrink-0">
                        <span>🚀</span> Upload Direto
                    </button>
                <?php endif; ?>

                <input type="text" id="searchInput" placeholder="<?= $modo_exibicao === 'pastas' ? 'Pesquisar pastas...' : 'Pesquisar procedimentos...' ?>" 
                       class="px-5 py-3 rounded-xl border border-slate-200 text-sm w-full md:w-72 focus:ring-2 focus:ring-corporate-blue outline-none shadow-sm">
            </div>
        </div>

        <div id="gridProcessos" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if (empty($dados)): ?>
                <div class="col-span-full text-center py-20 bg-white/50 rounded-3xl border-2 border-dashed border-slate-200">
                    <p class="text-slate-400 font-medium italic">Nenhum registro encontrado no momento.</p>
                </div>
            <?php else: ?>
                
                <?php if ($modo_exibicao === 'pastas'): ?>
                    <?php foreach ($dados as $pasta): ?>
                        <a href="visualizar_processo.php?setor_origem=<?= urlencode($pasta['setor']) ?>" 
                           class="doc-card group relative bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-corporate-blue transition-all transform hover:-translate-y-1 flex flex-col items-center justify-center text-center min-h-[180px]">
                            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4 transition-colors bg-blue-50 text-corporate-blue group-hover:bg-corporate-blue group-hover:text-white">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                            </div>
                            <h3 class="font-black text-navy-900 mb-1 group-hover:text-corporate-blue uppercase tracking-widest text-sm"><?= htmlspecialchars($pasta['setor']) ?></h3>
                            <p class="text-[10px] font-black text-slate-400 uppercase bg-slate-50 px-3 py-1 rounded-full mt-2"><?= $pasta['total_docs'] ?> Processo(s)</p>
                        </a>
                    <?php endforeach; ?>

                <?php else: ?>
                    <?php foreach ($dados as $doc): 
                        $ext = strtolower(pathinfo($doc['nome_arquivo'], PATHINFO_EXTENSION));
                        $icone = ($ext == 'pdf') 
                            ? '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>'
                            : '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
                    ?>
                        <div class="doc-card group relative bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-corporate-blue transition-all transform hover:-translate-y-1 flex flex-col cursor-pointer"
                             onclick="abrirViewer(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['titulo']) ?>')">
                            
                            <span class="absolute top-4 right-4 text-[9px] font-black bg-emerald-100 text-emerald-600 px-2 py-1 rounded uppercase tracking-widest">Aprovado</span>
                            
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 transition-colors bg-blue-50 text-corporate-blue group-hover:bg-corporate-blue group-hover:text-white">
                                <?= $icone ?>
                            </div>
                            
                            <h3 class="font-bold text-navy-900 mb-2 group-hover:text-corporate-blue"><?= htmlspecialchars($doc['titulo']) ?></h3>
                            <div class="mt-auto flex justify-between items-center pt-4 border-t border-slate-50">
                                <p class="text-[10px] uppercase font-bold text-slate-400 flex items-center gap-1">Visualizar ↗</p>
                                <p class="text-[10px] text-slate-500 font-bold bg-slate-100 px-2 py-1 rounded">V<?= $doc['versao_atual'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</main>

<div id="viewerModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-navy-900/60 backdrop-blur-sm p-4">
    <div class="bg-white w-full max-w-5xl h-[85vh] rounded-3xl flex flex-col overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 id="modalTitulo" class="font-black text-navy-900 text-sm flex items-center gap-2">
                <span class="text-emerald-500">🔒</span> <span id="modalTituloTexto">Visualizando Processo...</span>
            </h3>
            <button onclick="fecharViewer()" class="text-slate-400 hover:text-red-500 font-black text-xl px-2">&times;</button>
        </div>
        <iframe id="iframeViewer" src="" class="w-full flex-1 border-0 bg-slate-100"></iframe>
    </div>
</div>

<script>
// Filtro de busca unificado (Funciona tanto para buscar PASTAS quanto DOCUMENTOS)
document.getElementById('searchInput').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.doc-card').forEach(card => {
        const title = card.querySelector('h3').textContent.toLowerCase();
        card.style.display = title.includes(term) ? 'flex' : 'none';
    });
});

// Abertura do Modal
function abrirViewer(id, titulo) {
    document.getElementById('modalTituloTexto').textContent = titulo;
    document.getElementById('iframeViewer').src = 'serve_documento.php?id=' + id + '&modo=visualizar';
    document.getElementById('viewerModal').classList.remove('hidden');
}

function fecharViewer() {
    document.getElementById('viewerModal').classList.add('hidden');
    document.getElementById('iframeViewer').src = '';
    
    const url = new URL(window.location);
    url.searchParams.delete('open_doc');
    url.searchParams.delete('title');
    window.history.replaceState({}, '', url);
}

// Ouve os cliques da Sidebar
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const openDocId = urlParams.get('open_doc');
    const openDocTitle = urlParams.get('title');
    
    if (openDocId) {
        abrirViewer(openDocId, openDocTitle || 'Documento Oficial');
    }
});
</script>

<?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
<div id="modal-upload-direto" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[2000] items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <div>
                <h3 class="font-black text-navy-900 uppercase italic">Upload Direto (Bypass)</h3>
                <p class="text-[9px] font-bold text-emerald-500 uppercase tracking-widest mt-0.5">Inserção automática de processo aprovado</p>
            </div>
            <button onclick="document.getElementById('modal-upload-direto').classList.add('hidden'); document.getElementById('modal-upload-direto').classList.remove('flex'); document.body.style.overflow='auto';" class="text-slate-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        
        <form action="visualizar_processo.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            <input type="hidden" name="acao" value="upload_direto">
            
            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Título do Procedimento Oficial</label>
                <input type="text" name="titulo" required placeholder="Ex: POP_Logistica_Recebimento_Blindado" class="w-full p-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 outline-none font-bold text-navy-900">
            </div>

            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Setor / Pasta</label>
                <select name="setor_origem" required class="w-full p-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 outline-none font-bold text-slate-600 cursor-pointer">
                    <option value="GERAL">GERAL</option>
                    <option value="COMERCIAL">COMERCIAL</option>
                    <option value="FINANCEIRO">FINANCEIRO</option>
                    <option value="FACILITIES & TI">FACILITIES & TI</option>
                    <option value="LOGÍSTICA">LOGÍSTICA</option>
                    <option value="RH">RECURSOS HUMANOS</option>
                    <option value="TRANSPORTE">TRANSPORTE</option>
                    <option value="CADASTRO">CADASTRO</option>
                    <option value="COMPRAS">COMPRAS</option>
                    <option value="FISCAL">FISCAL</option>
                    <option value="TELEVENDAS">TELEVENDAS</option>
                    <option value="MARKETING">MARKETING</option>
                </select>
            </div>
            
            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Arquivo Final Homologado (PDF/Vídeo)</label>
                <div class="border-2 border-dashed border-emerald-200 rounded-2xl p-6 text-center bg-emerald-50/30 hover:bg-emerald-50 transition-colors">
                    <span class="text-3xl mb-2 block">📄</span>
                    <input type="file" name="documento" required class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-black file:uppercase file:tracking-widest file:bg-emerald-100 file:text-emerald-700 hover:file:bg-emerald-200 cursor-pointer outline-none">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" onclick="this.innerHTML='Salvando... ⏳'; this.classList.add('opacity-75', 'cursor-not-allowed');" class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl shadow-lg shadow-emerald-900/20 transition-all uppercase tracking-widest text-xs">
                    Publicar Imediatamente
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<?php include 'includes/footer.php'; ?>