<?php
require_once 'config.php';
// Certifique-se de que a sessão já foi iniciada no config.php ou header.php
$user_id = $_SESSION['user_id'];

// 1. LÓGICA DE UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documento'])) {
    $titulo = trim($_POST['titulo']);
    $arquivo = $_FILES['documento'];
    
    // Gera um nome único para o arquivo não dar conflito
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $nome_novo = md5(time() . rand(0,9999)) . '.' . $extensao;
    $caminho_destino = 'uploads_fluxo/' . $nome_novo;

    if (move_uploaded_file($arquivo['tmp_name'], $caminho_destino)) {
        // Salva no banco com o status inicial
        $stmt = $pdo_intra->prepare("INSERT INTO docs_fluxo_simples (usuario_id, titulo, nome_arquivo) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $titulo, $nome_novo]);
        $mensagem = "✅ Documento enviado para análise da T.I!";
    } else {
        $erro = "❌ Erro ao salvar o arquivo.";
    }
}

// 2. BUSCA OS DOCUMENTOS (SÓ OS DO USUÁRIO LOGADO)
$stmt_docs = $pdo_intra->prepare("SELECT * FROM docs_fluxo_simples WHERE usuario_id = ? ORDER BY id DESC");
$stmt_docs->execute([$user_id]);
$meus_documentos = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php'; 
?>

<main class="p-8 bg-slate-50 min-h-screen">
    <div class="max-w-4xl mx-auto space-y-8">
        
        <h2 class="text-2xl font-black text-navy-900 uppercase">📤 Envio de Documentos</h2>

        <?php if(!empty($mensagem)) echo "<p class='p-4 bg-green-100 text-green-700 rounded-xl font-bold'>$mensagem</p>"; ?>
        <?php if(!empty($erro)) echo "<p class='p-4 bg-red-100 text-red-700 rounded-xl font-bold'>$erro</p>"; ?>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <form method="POST" enctype="multipart/form-data" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase">Título/Descrição</label>
                    <input type="text" name="titulo" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl">
                </div>
                <div class="flex-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase">Arquivo (PDF, Imagem)</label>
                    <input type="file" name="documento" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700">Enviar</button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-black text-slate-700 uppercase mb-4">Meus Arquivos no Fluxo</h3>
            
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-[10px] text-slate-400 uppercase">
                        <th class="py-3">Título</th>
                        <th class="py-3">Data</th>
                        <th class="py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($meus_documentos as $doc): 
                        // Cor da tag dependendo do status
                        $cor_status = 'bg-amber-100 text-amber-700'; // Pendente
                        if($doc['status'] == 'Aprovado') $cor_status = 'bg-emerald-100 text-emerald-700';
                        if($doc['status'] == 'Recusado') $cor_status = 'bg-red-100 text-red-700';
                    ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-4 font-bold text-slate-700"><?= htmlspecialchars($doc['titulo']) ?></td>
                        <td class="py-4 text-xs text-slate-500"><?= date('d/m/Y H:i', strtotime($doc['data_envio'])) ?></td>
                        <td class="py-4">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?= $cor_status ?>">
                                <?= $doc['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($meus_documentos)): ?>
                        <tr><td colspan="3" class="py-8 text-center text-slate-400 text-xs uppercase">Nenhum documento enviado ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>