<?php
require_once 'config.php';
// Certifique-se de que a sessão já foi iniciada
$user_id = $_SESSION['user_id'];

// 1. BARREIRA DE SEGURANÇA: Só passa se for Admin ou do Grupo "FACILITIES & T.I"
// (Ajuste o nome do grupo na query se estiver escrito um pouquinho diferente no seu banco)
$stmt_check = $pdo_intra->prepare("
    SELECT 1 FROM usuarios_grupos ug 
    JOIN grupos_intranet g ON ug.grupo_id = g.id 
    WHERE ug.usuario_id = ? AND g.nome = 'FACILITIES & T.I'
");
$stmt_check->execute([$user_id]);
$is_ti = $stmt_check->fetch();
$is_admin = $_SESSION['is_admin'] ?? false;

if (!$is_ti && !$is_admin) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>❌ Acesso Negado</h2><p>Apenas a equipe de FACILITIES & T.I pode avaliar os documentos.</p></div>");
}

// 2. MOTOR DE APROVAÇÃO E RECUSA
if (isset($_GET['acao']) && isset($_GET['id'])) {
    $id_doc = (int)$_GET['id'];
    $novo_status = ($_GET['acao'] === 'aprovar') ? 'Aprovado' : 'Recusado';
    
    $pdo_intra->prepare("UPDATE docs_fluxo_simples SET status = ? WHERE id = ?")
              ->execute([$novo_status, $id_doc]);
              
    header("Location: gestao_fluxo.php?msg=" . urlencode("Documento $novo_status com sucesso!"));
    exit;
}

// 3. BUSCA TODOS OS DOCUMENTOS ENVIADOS (Juntando com o nome do usuário do GLPI)
$stmt_docs = $pdo_intra->query("
    SELECT d.*, u.firstname, u.realname 
    FROM docs_fluxo_simples d
    LEFT JOIN " . DB_GLPI . ".glpi_users u ON d.usuario_id = u.id
    ORDER BY d.id DESC
");
$todos_docs = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php'; 
?>

<main class="p-8 bg-slate-50 min-h-screen">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-black text-navy-900 uppercase">🛠️ Gestão de Fluxo (T.I / Facilities)</h2>
        </div>

        <?php if(isset($_GET['msg'])) echo "<p class='p-4 bg-blue-100 text-blue-700 rounded-xl font-bold'>".htmlspecialchars($_GET['msg'])."</p>"; ?>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-[10px] text-slate-400 uppercase tracking-widest">
                        <th class="py-3 px-2">Data</th>
                        <th class="py-3 px-2">Colaborador</th>
                        <th class="py-3 px-2">Título do Documento</th>
                        <th class="py-3 px-2">Status</th>
                        <th class="py-3 px-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($todos_docs as $doc): 
                        $cor_status = 'bg-amber-100 text-amber-700'; // Pendente
                        if($doc['status'] == 'Aprovado') $cor_status = 'bg-emerald-100 text-emerald-700';
                        if($doc['status'] == 'Recusado') $cor_status = 'bg-red-100 text-red-700';
                    ?>
                    <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                        <td class="py-4 px-2 text-xs font-bold text-slate-500"><?= date('d/m H:i', strtotime($doc['data_envio'])) ?></td>
                        <td class="py-4 px-2 text-xs font-black uppercase text-navy-900"><?= htmlspecialchars($doc['firstname'] . ' ' . $doc['realname']) ?></td>
                        <td class="py-4 px-2 font-bold text-slate-700"><?= htmlspecialchars($doc['titulo']) ?></td>
                        <td class="py-4 px-2">
                            <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?= $cor_status ?>">
                                <?= $doc['status'] ?>
                            </span>
                        </td>
                        <td class="py-4 px-2 text-right flex justify-end gap-2">
                            
                            <a href="uploads_fluxo/<?= $doc['nome_arquivo'] ?>" target="_blank" class="bg-blue-100 text-blue-600 p-2 rounded-lg hover:bg-blue-600 hover:text-white transition-colors" title="Visualizar/Baixar Documento">
                                📄 Baixar
                            </a>

                            <?php if($doc['status'] === 'Pendente T.I'): ?>
                                <a href="gestao_fluxo.php?acao=aprovar&id=<?= $doc['id'] ?>" onclick="return confirm('Deseja APROVAR este documento?')" class="bg-emerald-100 text-emerald-600 p-2 rounded-lg hover:bg-emerald-600 hover:text-white transition-colors" title="Aprovar">
                                    ✔️
                                </a>
                                
                                <a href="gestao_fluxo.php?acao=recusar&id=<?= $doc['id'] ?>" onclick="return confirm('Deseja RECUSAR este documento?')" class="bg-red-100 text-red-600 p-2 rounded-lg hover:bg-red-600 hover:text-white transition-colors" title="Recusar">
                                    ✖️
                                </a>
                            <?php endif; ?>
                            
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($todos_docs)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-slate-400 text-xs uppercase font-bold">A caixa de entrada está limpa! Nenhum documento pendente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>