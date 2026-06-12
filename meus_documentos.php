<?php
require_once 'config.php';
require_once 'includes/validar_upload.php';
require_once 'includes/DocumentoFluxo.php';

$user_id = $_SESSION['user_id'];
$fluxo = new DocumentoFluxo($pdo_intra);

// ==========================================
// 1. PROCESSAMENTO DE UPLOADS (V1 E V2)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    
    // Validação de arquivo (Segurança)
    if (!isset($_FILES['documento']) || $_FILES['documento']['error'] === UPLOAD_ERR_NO_FILE) {
        $erro = "Por favor, selecione um arquivo para enviar.";
    } else {
        $validacao = validarArquivoFluxo($_FILES['documento']);
        
        if (!$validacao['ok']) {
            $erro = $validacao['erro'];
        } else {
            $nome_novo = $validacao['nome'];
            $caminho_destino = 'uploads_fluxo/' . $nome_novo;

            if (move_uploaded_file($_FILES['documento']['tmp_name'], $caminho_destino)) {
                
                // CENA A: Novo Documento (V1)
                if ($acao === 'novo_envio') {
                    $titulo = trim($_POST['titulo']);
                    $setor = $_POST['setor_origem'] ?? 'GERAL'; // Captura o setor_origem
                    
                    // Atualiza o INSERT para salvar na coluna setor_origem
                    $stmt = $pdo_intra->prepare("INSERT INTO docs_fluxo_simples (usuario_id, titulo, nome_arquivo, setor_origem) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $titulo, $nome_novo, $setor]);
                    
                    // Registra o V1 no Histórico Imutável
                    $novo_doc_id = $pdo_intra->lastInsertId();
                    $fluxo->transitar($novo_doc_id, 'assumir', $user_id, 'Documento original (V1) enviado para análise.', [], $nome_novo);
                    // Como acabamos de criar, voltamos pro status Pendente T.I forçado no banco para iniciar limpo
                    $pdo_intra->prepare("UPDATE docs_fluxo_simples SET status = 'Pendente T.I' WHERE id = ?")->execute([$novo_doc_id]);
                    
                    $mensagem = "✅ Documento enviado com sucesso para análise!";
                
                // CENA B: Reenvio de Documento (V2, V3...)
                } elseif ($acao === 'reenviar_v2') {
                    $doc_id = (int)$_POST['doc_id'];
                    $msg_autor = trim($_POST['mensagem_ajuste']);
                    
                    // Transita o estado de "Aguardando Ajustes" para "Pendente T.I", atualiza versão e anexa o arquivo novo
                    $sucesso = $fluxo->transitar($doc_id, 'reenviar', $user_id, $msg_autor, [], $nome_novo);
                    
                    if ($sucesso) {
                        $mensagem = "✅ Nova versão do documento enviada para análise!";
                    } else {
                        $erro = "❌ Falha ao processar o reenvio. Limite de revisões pode ter sido atingido.";
                    }
                }
            } else {
                $erro = "❌ Falha ao mover o arquivo para o servidor.";
            }
        }
    }
}

// ==========================================
// 2. BUSCA DE DADOS (DOCS + HISTÓRICO)
// ==========================================
// Busca os documentos APENAS do usuário logado
$stmt_docs = $pdo_intra->prepare("SELECT * FROM docs_fluxo_simples WHERE usuario_id = ? ORDER BY id DESC");
$stmt_docs->execute([$user_id]);
$meus_documentos = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

// Carrega o histórico para a Timeline
$ids_docs = array_column($meus_documentos, 'id');
$historico_agrupado = [];
if (!empty($ids_docs)) {
    $placeholders = implode(',', array_fill(0, count($ids_docs), '?'));
    $stmt_hist = $pdo_intra->prepare("
        SELECT h.*, COALESCE(CONCAT(u.firstname,' ',u.realname),'Sistema') AS autor_nome
        FROM docs_fluxo_historico h
        LEFT JOIN " . DB_GLPI . ".glpi_users u ON h.usuario_id = u.id
        WHERE h.doc_id IN ($placeholders)
        ORDER BY h.criado_em ASC
    ");
    $stmt_hist->execute($ids_docs);
    foreach ($stmt_hist->fetchAll(PDO::FETCH_ASSOC) as $evento) {
        $historico_agrupado[$evento['doc_id']][] = $evento;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="p-8 bg-slate-50 min-h-screen">
    <div class="max-w-6xl mx-auto space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-black text-navy-900 tracking-tight">Meus Documentos</h1>
                <p class="text-slate-500 font-medium mt-1">Acompanhe e envie seus fluxos e procedimentos para aprovação.</p>
            </div>
            <button onclick="document.getElementById('modal-novo').classList.remove('hidden')" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-900/20 transition-all flex items-center gap-2">
                <span>➕</span> Novo Processo
            </button>
        </div>

        <?php if(isset($mensagem)): ?>
            <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl border border-emerald-200 font-bold"><?= $mensagem ?></div>
        <?php endif; ?>
        <?php if(isset($erro)): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-2xl border border-red-200 font-bold"><?= $erro ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-y-auto max-h-[75vh] relative">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 z-10 shadow-sm">
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] text-slate-500 uppercase tracking-widest font-black">
                        <th class="py-4 px-6">Título do Processo</th>
                        <th class="py-4 px-4">Última Atualização</th>
                        <th class="py-4 px-4 text-center">Versão</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4 text-right">Detalhes</th>
                    </tr>
                </thead>
                
                <?php foreach($meus_documentos as $doc): 
                    $badges = [
                        'Pendente T.I'       => 'bg-amber-100 text-amber-700',
                        'Em Análise'         => 'bg-purple-100 text-purple-700',
                        'Aguardando Ajustes' => 'bg-orange-100 text-orange-700 animate-pulse',
                        'Aprovado'           => 'bg-emerald-100 text-emerald-700',
                        'Recusado'           => 'bg-red-100 text-red-700',
                    ];
                    $cor_status = $badges[$doc['status']] ?? 'bg-slate-100 text-slate-500';
                    $historico_doc = $historico_agrupado[$doc['id']] ?? [];

                    // ==========================================
                    // 🧠 LÓGICA DE RASTREIO (SLA DO USUÁRIO)
                    // ==========================================
                    $data_ref = new DateTime($doc['data_atualizacao'] ?? $doc['data_envio']);
                    $hoje = new DateTime();
                    $dias_parados = $hoje->diff($data_ref)->days;

                    $sla_cor = 'text-slate-400';
                    $sla_icone = '⚪ Concluído';
                    $sla_texto = '';

                    // Se a bola estiver com a T.I
                    if (in_array($doc['status'], ['Pendente T.I', 'Em Análise'])) {
                        $sla_cor = 'text-blue-500';
                        $sla_icone = '⏳ Na fila da T.I';
                        $sla_texto = "há $dias_parados dias";
                    } 
                    // Se a bola estiver com o Usuário (Aguardando Ajustes)
                    elseif ($doc['status'] === 'Aguardando Ajustes') {
                        // Se ele demorar mais de 3 dias, fica vermelho pra alertar o risco de expirar
                        $sla_cor = $dias_parados > 3 ? 'text-red-500 font-black animate-pulse' : 'text-orange-500';
                        $sla_icone = '⚠️ Ação Necessária';
                        $sla_texto = "parado com você há $dias_parados dias";
                    }
                ?>
                
                <tbody x-data="{ aberto: false }">
                    <tr class="border-b border-slate-50 hover:bg-slate-50/80 transition-colors cursor-pointer group" @click="aberto = !aberto">
                        <td class="py-5 px-6 font-bold text-navy-900"><?= htmlspecialchars($doc['titulo']) ?></td>
                        <td class="py-5 px-4">
                            <div class="text-xs font-bold text-slate-500">
                                <?= date('d/m/Y H:i', strtotime($doc['data_atualizacao'] ?? $doc['data_envio'])) ?>
                            </div>
                            <div class="text-[9px] font-bold uppercase mt-1 <?= $sla_cor ?>">
                                <?= $sla_icone ?> <?= $sla_texto ?>
                            </div>
                        </td>
                        <td class="py-5 px-4 text-center">
                            <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded-md text-xs font-black">V<?= $doc['versao_atual'] ?></span>
                        </td>
                        <td class="py-5 px-4">
                            <span class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider <?= $cor_status ?>">
                                <?= $doc['status'] ?>
                            </span>
                        </td>
                        <td class="py-5 px-4 text-right">
                            <svg class="w-5 h-5 text-slate-400 ml-auto transition-transform duration-300" :class="{ 'rotate-180': aberto }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </td>
                    </tr>

                    <tr x-show="aberto" x-transition class="bg-slate-50/50">
                        <td colspan="5" class="p-0 border-b border-slate-200">
                            <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6" @click.stop>
                                
                                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm h-fit">
                                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 border-b border-slate-100 pb-2">📋 Histórico de Interações</h3>
                                    <div class="space-y-4 pl-2">
                                        <?php foreach($historico_doc as $evento): 
                                            // Cores da timeline baseadas na ação
                                            $cor_linha = 'border-slate-200';
                                            if($evento['tipo_acao'] === 'DEVOLVER') $cor_linha = 'border-orange-400';
                                            if($evento['tipo_acao'] === 'APROVAR') $cor_linha = 'border-emerald-400';
                                            if($evento['tipo_acao'] === 'RECUSAR') $cor_linha = 'border-red-400';
                                        ?>
                                            <div class="text-xs border-l-2 <?= $cor_linha ?> pl-4 py-1 relative">
                                                <div class="absolute -left-[5px] top-1.5 w-2 h-2 rounded-full bg-white border-2 <?= str_replace('border-', 'border-', $cor_linha) ?>"></div>
                                                
                                                <span class="font-black text-slate-700 uppercase"><?= htmlspecialchars($evento['tipo_acao']) ?></span>
                                                <span class="text-slate-400 text-[10px] ml-1 flex flex-col mt-0.5"><?= date('d/m/Y H:i', strtotime($evento['criado_em'])) ?> por <?= htmlspecialchars($evento['autor_nome']) ?></span>
                                                
                                                <?php if(!empty($evento['mensagem'])): ?>
                                                    <div class="mt-2 p-3 bg-slate-50 rounded-xl border border-slate-100 text-slate-600 italic">
                                                        "<?= nl2br(htmlspecialchars($evento['mensagem'])) ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="space-y-4 h-fit">
                                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm text-center">
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Arquivo Atual</p>
                                        
                                        <?php if ($doc['status'] === 'Aprovado'): ?>
                                            <!-- Documento homologado: apenas visualização segura -->
                                            <a href="serve_documento.php?id=<?= $doc['id'] ?>&modo=visualizar" 
                                            target="_blank" 
                                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-50 hover:bg-emerald-100 
                                                    text-emerald-700 text-xs font-bold rounded-xl transition-colors border border-emerald-200">
                                                <span>👁️</span> Visualizar V<?= $doc['versao_atual'] ?> (Aprovado)
                                            </a>
                                            <p class="text-[10px] text-slate-400 mt-2 italic">
                                                🔒 Documento homologado — download desabilitado
                                            </p>
                                        <?php else: ?>
                                            <!-- Em fluxo: download liberado para o dono -->
                                            <a href="serve_documento.php?id=<?= $doc['id'] ?>&modo=baixar" 
                                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-100 hover:bg-slate-200 
                                                    text-slate-700 text-xs font-bold rounded-xl transition-colors">
                                                <span>📥</span> Baixar Versão V<?= $doc['versao_atual'] ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <?php if($doc['status'] === 'Aguardando Ajustes'): ?>
                                        <div class="bg-orange-50 rounded-2xl border border-orange-200 p-5 shadow-sm">
                                            <div class="flex items-center gap-2 mb-3">
                                                <span class="text-xl">⚠️</span>
                                                <h3 class="text-xs font-black text-orange-800 uppercase tracking-widest">Ajustes Solicitados</h3>
                                            </div>
                                            <p class="text-xs text-orange-700 mb-4 font-medium">A equipe técnica solicitou alterações. Leia o parecer na timeline e envie o documento corrigido abaixo.</p>
                                            
                                            <form action="meus_documentos.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                                                <input type="hidden" name="acao" value="reenviar_v2">
                                                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                                
                                                <textarea name="mensagem_ajuste" rows="2" placeholder="Descreva brevemente o que foi ajustado..." required class="w-full p-3 text-xs bg-white border border-orange-200 rounded-xl focus:ring-2 focus:ring-orange-500/20 outline-none resize-none"></textarea>
                                                
                                                <input type="file" name="documento" required class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200 cursor-pointer">
                                                
                                                <button type="submit" class="w-full py-3 bg-orange-600 hover:bg-orange-700 text-white font-black rounded-xl text-xs uppercase tracking-widest transition-all">
                                                    🚀 Enviar Versão V<?= $doc['versao_atual'] + 1 ?>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </td>
                    </tr>
                </tbody>
                <?php endforeach; ?>

                <?php if(empty($meus_documentos)): ?>
                    <tbody><tr><td colspan="5" class="py-12 text-center text-slate-400 text-xs uppercase font-bold">Nenhum processo enviado ainda.</td></tr></tbody>
                <?php endif; ?>
            </table>
        </div>

    </div>
</main>

<div id="modal-novo" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-black text-navy-900">Novo Processo / Documento</h3>
            <button onclick="document.getElementById('modal-novo').classList.add('hidden')" class="text-slate-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        <form action="meus_documentos.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            <input type="hidden" name="acao" value="novo_envio">
            
            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Título do Procedimento</label>
                <input type="text" name="titulo" required placeholder="Ex: POP_Financeiro_Contas_Pagar" class="w-full p-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Setor</label>
                <select name="setor_origem" required class="w-full p-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 outline-none">
                    <option value="GERAL">Geral</option>
                    <option value="COMERCIAL">Comercial</option>
                    <option value="FINANCEIRO">Financeiro</option>
                    <option value="FACILITIES & TI">Facilities & TI</option>
                    <option value="LOGÍSTICA">Logística</option>
                    <option value="RH">Recursos Humanos</option>
                    <option value="TRANSPORTE">Transporte</option>
                    <option value="CADASTRO">Cadastro</option>
                    <option value="COMPRAS">Compras</option>
                    <option value="FISCAL">Fiscal</option>
                    <option value="TELEVENDAS">Televendas</option>
                    <option value="MARKETING">Marketing</option>
                </select>
            </div>

            <!-- 🔥 POKA-YOKE: Área de Download dos Modelos Oficiais -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-lg">💡</span>
                    <h4 class="text-[10px] font-black text-blue-800 uppercase tracking-widest">Modelos Oficiais (Evite Retrabalho)</h4>
                </div>
                <p class="text-xs text-blue-600 mb-3 font-medium">Garanta a aprovação rápida da T.I utilizando nossos templates já formatados nas normas:</p>
                <div class="flex flex-wrap gap-2">
                    
                    <a href="templates/modelo_pop_padrao.docx" download class="px-3 py-2 bg-white border border-blue-200 text-blue-700 text-[10px] font-bold rounded-lg hover:bg-blue-600 hover:border-blue-600 hover:text-white transition-colors flex items-center gap-1 shadow-sm">
                        📄 POP Padrão (5W2H + ABNT + ISO 9000 + LEAN OFFICE)
                    </a>
                    
                    <a href="templates/COMO UTILIZAR O BIZAGI MODELER.docx" download class="px-3 py-2 bg-white border border-blue-200 text-blue-700 text-[10px] font-bold rounded-lg hover:bg-blue-600 hover:border-blue-600 hover:text-white transition-colors flex items-center gap-1 shadow-sm">
                        🔀 Fluxograma Padrão
                    </a>
                    
                </div>
            </div>
            
            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Arquivo Base (V1)</label>
                <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:bg-slate-50 transition-colors">
                    <span class="text-3xl mb-2 block">📁</span>
                    <input type="file" name="documento" required class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    <p class="text-[10px] text-slate-400 font-medium mt-3">Formatos: PDF, Word, Excel, Imagens ou Vídeos MP4.</p>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3.5 bg-navy-800 hover:bg-navy-900 text-white font-black rounded-xl shadow-lg transition-all">Enviar para Análise da T.I</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>