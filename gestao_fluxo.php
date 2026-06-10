<?php
require_once 'config.php';
require_once 'includes/DocumentoFluxo.php';

$user_id = $_SESSION['user_id'];
$fluxo = new DocumentoFluxo($pdo_intra);

// 1. BARREIRA DE SEGURANÇA REVISADA
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

// 2. PROCESSAMENTO DO FORMULÁRIO SEGURO (POST) COM CHECKLIST JSON[cite: 126]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_acao'])) {
    $id_doc = (int)$_POST['doc_id'];
    $acao = $_POST['_acao'];
    $mensagem = trim($_POST['mensagem'] ?? '');
    
    $dados_extras = [];
    
    // Constrói o histórico do checklist baseado no mapeamento do Claude[cite: 126, 127]
    if ($acao === 'aprovar' || $acao === 'devolver') {
        $checklist_post = $_POST['checklist'] ?? [];
        $criterios_def = [
            'norma_abnt'     => 'Formatação no padrão ABNT',
            'metodo_5w2h'    => 'Utiliza a metodologia 5W2H',
            'iso_9000'       => 'Conformidade com a ISO 9000',
            'lean_office'    => 'Aplica conceitos de Lean Office',
            'documentacao'   => 'Devidamente documentado e claro para entendimento',
            'fluxograma'     => 'Fluxograma do processo desenhado e anexado'
        ];

        $itens = [];
        foreach ($criterios_def as $key => $label) {
            $itens[] = [
                'id'       => $key,
                'label'    => $label,
                'aprovado' => isset($checklist_post[$key]),
            ];
        }

        $pontuacao = count(array_filter($itens, fn($i) => $i['aprovado']));
        $dados_extras = [
            'checklist_versao' => '1.0',
            'itens'            => $itens,
            'pontuacao'        => $pontuacao,
            'total'            => count($criterios_def),
        ];
    }

    $sucesso = $fluxo->transitar($id_doc, $acao, $user_id, $mensagem, $dados_extras);

    if ($sucesso) {
        header("Location: gestao_fluxo.php?msg=" . urlencode("Ação '{$acao}' aplicada com sucesso!"));
    } else {
        header("Location: gestao_fluxo.php?erro=" . urlencode("Erro ao transitar fluxo de estado. Verifique limites de revisões."));
    }
    exit;
}

// 3. BUSCA OS DOCUMENTOS PRINCIPAIS
$stmt_docs = $pdo_intra->query("
    SELECT d.*, u.firstname, u.realname 
    FROM docs_fluxo_simples d
    LEFT JOIN " . DB_GLPI . ".glpi_users u ON d.usuario_id = u.id
    ORDER BY d.id DESC
");
$todos_docs = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

// 4. 🧠 ESTRATÉGIA ANTI-LENTIDÃO DO CLAUDE: Carrega todo o histórico de uma vez só! (Evita consultas N+1 no loop)[cite: 131, 135]
$ids_docs = array_column($todos_docs, 'id');
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
    <div class="max-w-6xl mx-auto space-y-8">
        
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-black text-navy-900 uppercase">🛠️ Gestão de Processos <h2>
        </div>

        <?php if(isset($_GET['msg'])) echo "<p class='p-4 bg-blue-100 text-blue-700 rounded-xl font-bold'>".htmlspecialchars($_GET['msg'])."</p>"; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-y-auto max-h-[70vh] relative">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 z-10 shadow-sm">
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] text-slate-500 uppercase tracking-widest font-black">
                        <th class="py-4 px-4">Data Envio</th>
                        <th class="py-4 px-4">Colaborador</th>
                        <th class="py-4 px-4">Título do Processo / Escopo</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4 text-right">Análise</th>
                    </tr>
                </thead>

                <?php foreach($todos_docs as $doc): 
                    $badges = [
                        'Pendente T.I'       => 'bg-amber-100 text-amber-700',
                        'Em Análise'         => 'bg-purple-100 text-purple-700',
                        'Aguardando Ajustes' => 'bg-orange-100 text-orange-700',
                        'Aprovado'           => 'bg-emerald-100 text-emerald-700',
                        'Recusado'           => 'bg-red-100 text-red-700',
                    ];
                    $cor_status = $badges[$doc['status']] ?? 'bg-slate-100 text-slate-500';
                    $historico_doc = $historico_agrupado[$doc['id']] ?? [];

                    // Lógica Matemática do SLA (Calcula dias parados)
                    $data_ref = new DateTime($doc['data_envio']); 
                    $hoje = new DateTime();
                    $dias_parados = $hoje->diff($data_ref)->days;

                    // Configuração do Semáforo Visual
                    $sla_cor = 'text-emerald-500';
                    $sla_icone = '🟢 No Prazo';

                    // O SLA só corre se a bola estiver com a T.I
                    if ($doc['status'] === 'Pendente T.I' || $doc['status'] === 'Em Análise') {
                        if ($dias_parados == 2) {
                            $sla_cor = 'text-amber-500';
                            $sla_icone = '🟡 Atenção';
                        } elseif ($dias_parados >= 3) {
                            $sla_cor = 'text-red-500 font-black animate-pulse';
                            $sla_icone = '🔴 Estourado';
                        }
                    } else {
                        // Se estiver Aprovado, Recusado ou Aguardando o Usuário, o SLA da T.I congela
                        $sla_icone = '⚪ Congelado'; 
                        $sla_cor = 'text-slate-300';
                    }
                ?>

                <tbody x-data="{ aberto: false }">
                    
                    <tr class="border-b border-slate-50 hover:bg-slate-50/80 transition-colors cursor-pointer group"
                        @click="aberto = !aberto">

                        <td class="py-5 px-4 text-xs font-bold text-slate-500">
                            <?= date('d/m H:i', strtotime($doc['data_envio'])) ?>
                            <div class="text-[9px] font-bold uppercase mt-1 <?= $sla_cor ?>">
                                <?= $sla_icone ?> (<?= $dias_parados ?> dias)
                            </div>
                        </td>
                        <td class="py-5 px-4 text-xs font-black uppercase text-slate-800">
                            <?= htmlspecialchars($doc['firstname'] . ' ' . $doc['realname']) ?>
                        </td>
                        <td class="py-5 px-4">
                            <div>
                                <p class="font-bold text-navy-900 text-sm group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($doc['titulo']) ?></p>
                                <p class="text-[10px] text-slate-400 font-medium mt-0.5">
                                    Versão Atual: <b>V<?= $doc['versao_atual'] ?></b> • Ciclo: <?= $doc['ciclos_revisao'] ?>/3
                                </p>
                            </div>
                        </td>
                        <td class="py-5 px-4">
                            <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider <?= $cor_status ?>">
                                <?= $doc['status'] ?>
                            </span>
                        </td>
                        <td class="py-5 px-4 text-right">
                            <svg class="w-4 h-4 text-slate-400 ml-auto transition-transform duration-300" :class="{ 'rotate-180': aberto }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </td>
                    </tr>

                    <tr x-show="aberto" x-transition class="bg-slate-50/50">
                        <td colspan="5" class="p-0 border-b border-slate-200">
                            <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6 animate-in fade-in duration-200">
                                
                                <div class="space-y-4">
                                    <?php
                                    $ext_arquivo = strtolower(pathinfo($doc['nome_arquivo'], PATHINFO_EXTENSION));
                                    
                                    // Separamos os arquivos em categorias que a web entende
                                    $eh_video = in_array($ext_arquivo, ['mp4', 'webm', 'ogg']);
                                    $eh_pdf = ($ext_arquivo === 'pdf');
                                    $eh_imagem = in_array($ext_arquivo, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    
                                    $url_arquivo = 'uploads_fluxo/' . htmlspecialchars($doc['nome_arquivo']);
                                    ?>

                                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                                <?= $eh_video ? '🎥 PLAYER DE VÍDEO' : '📄 VISUALIZADOR SEGURO' ?>
                                            </span>
                                            
                                            <!-- Link passando pelo guardião -->
                                            <a href="serve_documento.php?id=<?= $doc['id'] ?>&modo=visualizar" 
                                            target="_blank" 
                                            class="text-xs font-bold text-blue-600 hover:text-blue-700">
                                            Abrir no Visualizador ↗
                                            </a>
                                        </div>

                                        <template x-if="aberto">
                                            <div @click.stop class="bg-slate-100 flex justify-center w-full">
                                                
                                                <?php if($eh_video): ?>
                                                    <video controls class="w-full max-h-72 bg-black">
                                                        <source src="<?= $url_arquivo ?>" type="video/<?= $ext_arquivo ?>">
                                                    </video>

                                                <?php elseif($eh_pdf): ?>
                                                    <iframe src="serve_documento.php?id=<?= $doc['id'] ?>&modo=visualizar" class="w-full h-80" frameborder="0"></iframe>

                                                <?php elseif($eh_imagem): ?>
                                                    <img src="<?= $url_arquivo ?>" class="max-w-full max-h-80 object-contain p-2">

                                                <?php else: ?>
                                                    <div class="py-12 px-6 text-center flex flex-col items-center justify-center w-full">
                                                        <span class="text-5xl mb-4">📁</span>
                                                        <h3 class="text-sm font-black text-slate-700 mb-1">Formato de arquivo incompatível com visualização direta</h3>
                                                        <p class="text-[11px] font-medium text-slate-500 mb-5">O navegador não suporta exibir arquivos <b>.<?= $ext_arquivo ?></b> nativamente.</p>
                                                        
                                                        <?php 
                                                        // 🧠 MÁGICA: Pega o título do doc, troca espaços por '_' e remove caracteres estranhos
                                                        $nome_limpo = preg_replace('/[^A-Za-z0-9\-]/', '_', $doc['titulo']);
                                                        $nome_download = $nome_limpo . '_V' . $doc['versao_atual'] . '.' . $ext_arquivo;
                                                        ?>

                                                        <a href="<?= $url_arquivo ?>" download="<?= $nome_download ?>" class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm mt-4">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                                            Baixar: <?= htmlspecialchars($nome_download) ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                        </template>
                                    </div>

                                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm" @click.stop>
                                    <?php if($doc['status'] === 'Em Análise'): ?>
                                        <form method="POST" class="space-y-4" x-data="{ acao_escolhida: '' }">
                                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                            <input type="hidden" name="_acao" :value="acao_escolhida">

                                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">📋 Itens Obrigatórios de Auditoria</p>
                                            
                                            <div class="space-y-2">
                                                <label class="flex items-center gap-3 p-2.5 bg-slate-50/60 hover:bg-slate-50 rounded-xl border border-slate-100 cursor-pointer transition-colors">
                                                    <input type="checkbox" name="checklist[norma_abnt]" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-xs font-semibold text-slate-700">Formatação no padrão ABNT</span>
                                                </label>
                                                <label class="flex items-center gap-3 p-2.5 bg-slate-50/60 hover:bg-slate-50 rounded-xl border border-slate-100 cursor-pointer transition-colors">
                                                    <input type="checkbox" name="checklist[metodo_5w2h]" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-xs font-semibold text-slate-700">Utiliza a metodologia 5W2H</span>
                                                </label>
                                                <label class="flex items-center gap-3 p-2.5 bg-slate-50/60 hover:bg-slate-50 rounded-xl border border-slate-100 cursor-pointer transition-colors">
                                                    <input type="checkbox" name="checklist[iso_9000]" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-xs font-semibold text-slate-700">Conformidade com a ISO 9000</span>
                                                </label>
                                                <label class="flex items-center gap-3 p-2.5 bg-slate-50/60 hover:bg-slate-50 rounded-xl border border-slate-100 cursor-pointer transition-colors">
                                                    <input type="checkbox" name="checklist[lean_office]" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-xs font-semibold text-slate-700">Aplica conceitos de Lean Office</span>
                                                </label>
                                                <label class="flex items-center gap-3 p-2.5 bg-slate-50/60 hover:bg-slate-50 rounded-xl border border-slate-100 cursor-pointer transition-colors">
                                                    <input type="checkbox" name="checklist[documentacao]" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-xs font-semibold text-slate-700">Devidamente documentado e claro para entendimento</span>
                                                </label>
                                                <label class="flex items-center gap-3 p-2.5 bg-slate-50/60 hover:bg-slate-50 rounded-xl border border-slate-100 cursor-pointer transition-colors">
                                                    <input type="checkbox" name="checklist[fluxograma]" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-xs font-semibold text-slate-700">Fluxograma do processo desenhado e anexado</span>
                                                </label>
                                            </div>

                                            <div>
                                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Parecer Técnico Técnico</label>
                                                <textarea name="mensagem" rows="3" required placeholder="Digite os ajustes necessários ou notas de homologação..." class="w-full p-3 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 outline-none resize-none"></textarea>
                                            </div>

                                            <div class="grid grid-cols-3 gap-2">
                                                <button type="submit" @click="acao_escolhida = 'devolver'" class="p-3 bg-orange-50 hover:bg-orange-100 border border-orange-200 text-orange-700 text-center rounded-xl flex flex-col items-center justify-center transition-all">
                                                    <span class="text-lg">↩️</span><span class="text-[10px] font-black uppercase">Devolver</span>
                                                </button>
                                                <button type="submit" @click="acao_escolhida = 'recusar'" onclick="return confirm('Recusar em definitivo?')" class="p-3 bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 text-center rounded-xl flex flex-col items-center justify-center transition-all">
                                                    <span class="text-lg">✖️</span><span class="text-[10px] font-black uppercase">Recusar</span>
                                                </button>
                                                <button type="submit" @click="acao_escolhida = 'aprovar'" onclick="return confirm('Aprovar e homologar o processo?')" class="p-3 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-center rounded-xl flex flex-col items-center justify-center transition-all">
                                                    <span class="text-lg">✔️</span><span class="text-[10px] font-black uppercase">Aprovar</span>
                                                </button>
                                            </div>
                                        </form>
                                    <?php elseif($doc['status'] === 'Pendente T.I'): ?>
                                        <form method="POST" class="h-full flex flex-col items-center justify-center p-10 space-y-3">
                                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                            <input type="hidden" name="_acao" value="assumir">
                                            <p class="text-xs text-slate-400 font-bold text-center">Este processo ainda não foi avaliado por nenhum auditor da equipe.</p>
                                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-black px-6 py-3.5 rounded-xl text-xs uppercase tracking-widest shadow-md transition-all">👁️ Assumir Auditoria</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="h-full flex flex-col items-center justify-center p-10 text-slate-400 font-bold uppercase text-xs">
                                            🏁 Fluxo Concluído com Status: <span class="ml-1 text-navy-900"><?= $doc['status'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody> 
                <?php endforeach; ?>
            </table>
        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>