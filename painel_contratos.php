<?php
require_once 'config.php';

  // Processamento das ações do modal de contratos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['id_contrato'])) {
    $id_contrato = intval($_POST['id_contrato']);
    $acao = $_POST['acao'];

    if ($acao === 'cancelar') {
        $stmt = $pdo_intra->prepare("UPDATE intranet.contratos SET status = 'ENCERRADO' WHERE id = ?");
        $stmt->execute([$id_contrato]);
    } 
    elseif ($acao === 'substituir') {
        $stmt = $pdo_intra->prepare("UPDATE intranet.contratos SET status = 'SUBSTITUIDO' WHERE id = ?");
        $stmt->execute([$id_contrato]);
    }
    elseif ($acao === 'renovar') {
        $stmt = $pdo_intra->prepare("UPDATE intranet.contratos SET status = 'ATIVO' WHERE id = ?");
        $stmt->execute([$id_contrato]);
    }
    
    // Redireciona para evitar reenvio do formulário ao atualizar a página
    header("Location: painel_contratos.php");
    exit;
}

include 'includes/header.php';
include 'includes/sidebar.php';

    // 1. Busca os contratos direto do banco (já estável)
    $stmt_contratos = $pdo->prepare("SELECT * FROM intranet.contratos ORDER BY id DESC");
    $stmt_contratos->execute();
    $lista_contratos = $stmt_contratos->fetchAll(PDO::FETCH_ASSOC);

    $regra_alerta = [
        'rh'        => 60,
        'fiscal'    => 60,
        'facilities'=> 90,
        'marketing' => 60,
    ];

   $setores_label = [
    'rh'         => ['nome' => 'RH',         'icone' => '🧑‍💼', 'cor' => 'emerald'],
    'fiscal'     => ['nome' => 'Fiscal',     'icone' => '🧾',  'cor' => 'amber'],
    'facilities' => ['nome' => 'Facilities', 'icone' => '🛠️',  'cor' => 'blue'],
    'marketing'  => ['nome' => 'Marketing',  'icone' => '📣',  'cor' => 'violet'],
    'logistica'  => ['nome' => 'Logística',  'icone' => '🚚',  'cor' => 'slate'], // Adiciona essa linha
];

    $empresas_grupo = ['Souza', 'Mixkar', 'CSA', 'Autoweb', 'Compremix'];

    /* ─── HELPERS ────────────────────────────────────────────── */
    function calcularDiasRestantes(array $c): int {
        $hoje = new DateTime('today');
        if (($c['tipo_vencimento'] ?? '') === 'recorrente') return 9999;
        if (!empty($c['data_final'])) {
            $venc = new DateTime($c['data_final']);
            return (int)$hoje->diff($venc)->format('%r%a');
        }
        return 9999;
    }

    function getCorSetor(string $cor): string {
        return [
            'emerald' => 'background:#d1fae5;color:#065f46',
            'amber'   => 'background:#fef3c7;color:#92400e',
            'blue'    => 'background:#dbeafe;color:#1e40af',
            'violet'  => 'background:#ede9fe;color:#5b21b6',
        ][$cor] ?? 'background:#f1f5f9;color:#475569';
    }

    /* ─── FILTROS (Agora usando $lista_contratos) ───────────── */
    $filtro_setor   = $_GET['setor']   ?? 'todos';
    $filtro_empresa = $_GET['empresa'] ?? 'todas';
    $filtro_status  = $_GET['status']  ?? 'todos';
    $busca          = trim($_GET['q']  ?? '');

    $contratos_filtrados = array_filter($lista_contratos, function($c) use ($filtro_setor, $filtro_empresa, $filtro_status, $busca) {
        if ($filtro_setor !== 'todos' && strcasecmp($c['setor'] ?? '', $filtro_setor) !== 0) return false;
        if ($filtro_empresa !== 'todas' && ($c['empresa'] ?? '') !== $filtro_empresa) return false;
        if ($filtro_status  !== 'todos' && ($c['status']  ?? '') !== $filtro_status) return false;
        if ($busca && stripos(($c['razao_social'] ?? '').($c['servico'] ?? '').($c['cnpj'] ?? ''), $busca) === false) return false;
        return true;
    });

    /* ─── KPIs ───────────────────────────────────────────────── */
    $total_ativos  = count(array_filter($lista_contratos, fn($c) => ($c['status'] ?? '') === 'ATIVO'));
    $total_alertas = 0;
    foreach ($lista_contratos as $c) {
        if (($c['status'] ?? '') === 'ENCERRADO' || ($c['tipo_vencimento'] ?? '') === 'recorrente') continue;
        $dias   = calcularDiasRestantes($c);
        $limite = $regra_alerta[$c['setor']] ?? 60;
        if ($dias <= $limite) $total_alertas++;
    }

    $status_estilo = [
        'EM ANÁLISE'       => ['bg'=>'bg-slate-500/10',   'text'=>'text-slate-500'],
        'ATIVO'            => ['bg'=>'bg-emerald-500/10',  'text'=>'text-emerald-600'],
        'REVISÃO DE VALOR' => ['bg'=>'bg-amber-500/10',    'text'=>'text-amber-600'],
        'ENCERRADO'        => ['bg'=>'bg-slate-200/60',    'text'=>'text-slate-400'],
    ];


?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
<div class="max-w-7xl mx-auto space-y-6">

    <!-- CABEÇALHO -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Gestão de Contratos</p>
            <h1 class="text-2xl md:text-3xl font-black text-navy-900 uppercase tracking-tighter italic">Painel de Acompanhamento</h1>
        </div>
        <a href="gestao_contratos.php"
           class="bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 px-5 rounded-2xl shadow-lg shadow-emerald-900/20 transition-all uppercase tracking-widest text-[11px] inline-flex items-center gap-2 w-fit">
            ➕ Novo Contrato
        </a>
    </div>

    <!-- KPIs (3 cards — sem valor total) -->
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 px-5 py-4">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Contratos Ativos</p>
            <p class="text-3xl font-black text-navy-900"><?php echo $total_ativos; ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 px-5 py-4">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total de Contratos</p>
            <p class="text-3xl font-black text-navy-900"><?php echo count($lista_contratos); ?></p>
        </div>
        <div class="rounded-2xl border px-5 py-4 <?php echo $total_alertas > 0 ? 'bg-amber-50 border-amber-200' : 'bg-white border-slate-200'; ?>">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Alertas de Vencimento</p>
            <p class="text-3xl font-black <?php echo $total_alertas > 0 ? 'text-amber-600' : 'text-navy-900'; ?>"><?php echo $total_alertas; ?></p>
        </div>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="bg-white rounded-2xl border border-slate-200 px-6 py-4 flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[160px]">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Buscar</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($busca); ?>"
                placeholder="Fornecedor, serviço, CNPJ…"
                class="w-full p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-navy-900">
        </div>
        <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Setor</label>
            <select name="setor" class="p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-slate-600 cursor-pointer">
                <option value="todos" <?php echo $filtro_setor==='todos'?'selected':''; ?>>Todos</option>
                <?php foreach ($setores_label as $k=>$s): ?>
                <option value="<?php echo $k; ?>" <?php echo $filtro_setor===$k?'selected':''; ?>><?php echo $s['nome']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Empresa</label>
            <select name="empresa" class="p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-slate-600 cursor-pointer">
                <option value="todas" <?php echo $filtro_empresa==='todas'?'selected':''; ?>>Todas</option>
                <?php foreach ($empresas_grupo as $emp): ?>
                <option value="<?php echo $emp; ?>" <?php echo $filtro_empresa===$emp?'selected':''; ?>><?php echo $emp; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Status</label>
            <select name="status" class="p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-slate-600 cursor-pointer">
                <option value="todos">Todos</option>
                <?php foreach (array_keys($status_estilo) as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $filtro_status===$st?'selected':''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" style="background:#1e293b;color:#fff" class="font-black py-2.5 px-5 rounded-xl text-[11px] uppercase tracking-widest transition-all hover:opacity-90">
            Filtrar
        </button>
        <?php if ($busca || $filtro_setor!=='todos' || $filtro_empresa!=='todas' || $filtro_status!=='todos'): ?>
        <a href="painel_contratos.php" class="text-[11px] font-black text-slate-400 hover:text-navy-900 uppercase tracking-widest py-2.5">✕ Limpar</a>
        <?php endif; ?>
    </form>

    <!-- TABELA -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
        <div style="overflow-y:auto;max-height:520px;">
        <table class="w-full text-left text-sm" style="min-width:860px;">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200" style="position:sticky;top:0;z-index:10;">
                    <th class="px-5 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50">ID</th>
                    <th class="px-5 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50">Fornecedor / Serviço</th>
                    <th class="px-5 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50">Empresa</th>
                    <th class="px-5 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50">Setor</th>
                    <th class="px-5 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50">Status</th>
                    <th class="px-5 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50">Vencimento</th>
                    <th class="px-5 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50">Alerta</th>
                    <th class="px-5 py-4 bg-slate-50"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contratos_filtrados)): ?>
                <tr><td colspan="8" class="px-6 py-12 text-center text-slate-400 text-sm font-semibold">
                    Nenhum contrato encontrado.
                </td></tr>
                <?php endif; ?>

                <?php foreach ($contratos_filtrados as $c):
                   $chave_setor = strtolower(trim($c['setor'] ?? ''));
                   $setor = $setores_label[$chave_setor] ?? ['nome' => ucfirst($c['setor'] ?: 'Geral'), 'icone' => '📁', 'cor' => 'slate'];
                    $is_rec    = $c['tipo_vencimento'] === 'recorrente';
                    $encerrado = $c['status'] === 'ENCERRADO';
                    $dias      = calcularDiasRestantes($c);
                    $limite    = $regra_alerta[$c['setor']] ?? 60;
                    $tem_alerta= !$encerrado && !$is_rec && $dias <= $limite;
                    $critico   = $tem_alerta && $dias <= 15;
                    $est       = $status_estilo[$c['status']] ?? ['bg'=>'','text'=>''];
                ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">

                    <!-- ID -->
                    <td class="px-5 py-4 font-mono text-xs text-slate-400"><?php echo $c['id']; ?></td>

                    <!-- Fornecedor -->
                    <td class="px-5 py-4 max-w-[200px]">
                        <div class="font-bold text-navy-900 truncate"><?php echo htmlspecialchars($c['razao_social']); ?></div>
                        <div class="text-[11px] text-slate-500 mt-0.5"><?php echo htmlspecialchars($c['servico']); ?></div>
                        <div class="text-[10px] text-slate-400">R$ <?php echo number_format($c['valor'],2,',','.'); ?></div>
                    </td>

                    <!-- Empresa -->
                    <td class="px-5 py-4 text-xs font-black text-slate-600 whitespace-nowrap"><?php echo $c['empresa']; ?></td>

                    <!-- Setor -->
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wide"
                              style="<?php echo getCorSetor($setor['cor']); ?>">
                            <?php echo $setor['icone'].' '.$setor['nome']; ?>
                        </span>
                    </td>

                    <!-- Status -->
                    <td class="px-5 py-4">
                        <span class="inline-block px-3 py-1 rounded-full text-[10.5px] font-black uppercase tracking-wide <?php echo $est['bg'].' '.$est['text']; ?>">
                            <?php echo $c['status']; ?>
                        </span>
                    </td>

                    <!-- Vencimento -->
                    <td class="px-5 py-4 whitespace-nowrap">
                        <?php if ($is_rec): ?>
                            <span class="text-xs font-bold" style="color:#1d4ed8">🔁 Recorrente</span>
                        <?php elseif ($c['data_final']): ?>
                            <div class="font-mono text-xs text-slate-600"><?php echo (new DateTime($c['data_final']))->format('d/m/Y'); ?></div>
                            <?php if ($c['prazo']): ?><div class="text-[10px] text-slate-400"><?php echo $c['prazo']; ?></div><?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Alerta -->
                    <td class="px-5 py-4 whitespace-nowrap">
                        <?php if ($encerrado || $is_rec): ?>
                            <span class="text-slate-300 text-xs">—</span>
                        <?php elseif ($critico): ?>
                            <span class="text-[12px] font-black" style="color:#dc2626">🔴 <?php echo $dias>0?"Vence em {$dias}d":'Vencido'; ?></span>
                        <?php elseif ($tem_alerta): ?>
                            <span class="text-[12px] font-black" style="color:#d97706">⚠️ <?php echo $dias; ?>d</span>
                        <?php else: ?>
                            <span class="text-slate-300 text-xs">OK</span>
                        <?php endif; ?>
                    </td>

                    <!-- Ações -->
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="abrirDetalhe(<?php echo htmlspecialchars(json_encode($c),ENT_QUOTES); ?>)"
                                class="border border-slate-200 text-slate-500 hover:bg-slate-100 font-black text-[10px] uppercase tracking-widest px-3 py-1.5 rounded-lg transition-all">
                                Detalhes
                            </button>
                            <?php if (!$encerrado): ?>
                            <button onclick="abrirDecisao('<?php echo $c['id']; ?>','<?php echo htmlspecialchars($c['razao_social'],ENT_QUOTES); ?>','<?php echo $setor['nome']; ?>')"
                                style="border:2px solid #1e293b;color:#1e293b"
                                class="hover:bg-slate-900 hover:text-white font-black text-[10px] uppercase tracking-widest px-3 py-1.5 rounded-lg transition-all">
                                Ação
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div><!-- /scroll -->
    </div>

    <p class="text-[11px] text-slate-400">
        <?php echo count($contratos_filtrados); ?> contrato(s) exibido(s) ·
        Alerta: <strong class="text-slate-500">Facilities</strong> 90 dias ·
        <strong class="text-slate-500">Demais setores</strong> 60 dias
    </p>

</div>
</main>

<!-- MODAL DETALHES -->
<div id="modal-detalhe" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[200] hidden items-center justify-center px-4" onclick="fecharDetalhe(event)">
    <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-200 max-w-lg w-full overflow-hidden max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="px-7 py-5 border-b border-slate-100 flex items-start justify-between shrink-0">
            <div>
                <p id="det-id" class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5"></p>
                <h3 id="det-fornecedor" class="text-lg font-black text-navy-900 uppercase italic tracking-tighter leading-tight"></h3>
                <p id="det-servico-sub" class="text-xs text-slate-400 mt-0.5"></p>
            </div>
            <button onclick="fecharDetalhe()" class="text-slate-300 hover:text-navy-900 text-2xl leading-none mt-1">&times;</button>
        </div>
        <div class="overflow-y-auto p-6 space-y-5 flex-1">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Identificação</p>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <div><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">CNPJ</dt><dd id="det-cnpj" class="font-mono font-bold text-navy-900 text-xs"></dd></div>
                    <div><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Empresa</dt><dd id="det-empresa" class="font-bold text-navy-900 text-xs"></dd></div>
                    <div class="col-span-2"><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Contato</dt><dd id="det-contato" class="font-bold text-navy-900 text-xs break-words"></dd></div>
                </dl>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Contrato</p>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <div><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Valor</dt><dd id="det-valor" class="font-bold text-navy-900 text-xs"></dd></div>
                    <div><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Prazo</dt><dd id="det-prazo" class="font-bold text-navy-900 text-xs"></dd></div>
                    <div><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Qtd. Pagamentos</dt><dd id="det-qtdpag" class="font-bold text-navy-900 text-xs"></dd></div>
                    <div><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Início</dt><dd id="det-inicio" class="font-mono font-bold text-navy-900 text-xs"></dd></div>
                </dl>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Vencimento</p>
                <div id="det-venc-bloco" class="bg-slate-50 rounded-xl px-4 py-3 border border-slate-200 text-sm font-bold text-navy-900"></div>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Cláusulas</p>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <div><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Renov. Automática</dt><dd id="det-renov" class="font-bold text-xs"></dd></div>
                    <div><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Aviso Prévio</dt><dd id="det-aviso" class="font-bold text-navy-900 text-xs"></dd></div>
                    <div class="col-span-2"><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Multa</dt><dd id="det-multa" class="font-bold text-navy-900 text-xs"></dd></div>
                    <div class="col-span-2"><dt class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Cláusula Técnica</dt><dd id="det-clausula" class="font-bold text-navy-900 text-xs"></dd></div>
                </dl>
            </div>
        </div>
        <div class="px-7 py-3 border-t border-slate-100 text-[11px] text-slate-400 shrink-0 flex justify-between items-center">
            <span>Setor: <span id="det-setor" class="font-black text-navy-900"></span></span>
            <button onclick="fecharDetalhe()" class="border border-slate-200 text-slate-500 hover:bg-slate-50 font-black text-[10px] uppercase tracking-widest px-4 py-1.5 rounded-lg transition-all">Fechar</button>
        </div>
    </div>
</div>

<!-- MODAL DECISÃO -->
<div id="modal-decisao" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[200] hidden items-center justify-center px-4" onclick="fecharDecisao(event)">
    <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-200 max-w-md w-full overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-7 py-6 border-b border-slate-100 flex items-start justify-between">
            <div>
                <p id="decisao-id" class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1"></p>
                <h3 id="decisao-fornecedor" class="text-lg font-black text-navy-900 uppercase italic tracking-tighter"></h3>
            </div>
            <button onclick="fecharDecisao()" class="text-slate-300 hover:text-navy-900 text-xl leading-none">&times;</button>
        </div>

        <!-- FORMULÁRIO QUE ENVIA OS DADOS PARA O BACKEND -->
        <form action="painel_contratos.php" method="POST" class="p-5 space-y-2.5">
            <input type="hidden" name="id_contrato" id="input-id-contrato" value="">

            <button type="submit" name="acao" value="cancelar" class="w-full flex items-start gap-3 border border-slate-200 hover:bg-rose-50 hover:border-rose-200 rounded-2xl px-4 py-3 text-left transition-all">
                <span class="text-rose-600 text-lg">🚫</span>
                <span><span class="block text-sm font-black text-navy-900">Cancelar Contrato</span><span class="block text-xs text-slate-400">Encerra o vínculo e move para Encerrado.</span></span>
            </button>

            <button type="submit" name="acao" value="substituir" class="w-full flex items-start gap-3 border border-slate-200 hover:bg-blue-50 hover:border-blue-200 rounded-2xl px-4 py-3 text-left transition-all">
                <span class="text-blue-600 text-lg">🔄</span>
                <span><span class="block text-sm font-black text-navy-900">Substituir Fornecedor</span><span class="block text-xs text-slate-400">Abre um novo cadastro vinculado a este histórico.</span></span>
            </button>

            <button type="submit" name="acao" value="renovar" class="w-full flex items-start gap-3 border border-slate-200 hover:bg-emerald-50 hover:border-emerald-200 rounded-2xl px-4 py-3 text-left transition-all">
                <span class="text-emerald-600 text-lg">📈</span>
                <span><span class="block text-sm font-black text-navy-900">Renovar com Reajuste</span><span class="block text-xs text-slate-400">Mantém o fornecedor e atualiza valor/vigência.</span></span>
            </button>
        </form>

        <div class="px-7 py-3 border-t border-slate-100 text-[11px] text-slate-400">
            Setor: <span id="decisao-setor" class="font-black text-navy-900"></span>
        </div>
    </div>
</div>

<script>
function abrirDetalhe(c) {
    const fmt = v => (v !== null && v !== '' && v !== undefined) ? v : '—';
    const fmtData = iso => { if (!iso) return '—'; const [y,m,d]=iso.split('-'); return `${d}/${m}/${y}`; };
    const fmtValor = v => 'R$ ' + parseFloat(v).toLocaleString('pt-BR',{minimumFractionDigits:2});

    document.getElementById('det-id').textContent         = 'Detalhes · ' + c.id;
    document.getElementById('det-fornecedor').textContent = c.razao_social;
    document.getElementById('det-servico-sub').textContent= c.servico;
    document.getElementById('det-cnpj').textContent       = fmt(c.cnpj);
    document.getElementById('det-empresa').textContent    = fmt(c.empresa);
    document.getElementById('det-contato').textContent    = c.contato || '—';
    document.getElementById('det-valor').textContent      = fmtValor(c.valor);
    document.getElementById('det-prazo').textContent      = fmt(c.prazo);
    document.getElementById('det-qtdpag').textContent     = c.qtd_pagamentos ? c.qtd_pagamentos + 'x' : '—';
    document.getElementById('det-inicio').textContent     = fmtData(c.data_inicio);
    document.getElementById('det-setor').textContent      = c.setor.toUpperCase();
    document.getElementById('det-renov').innerHTML        = c.renovacao_automatica
        ? '<span style="color:#059669">Sim</span>' : '<span style="color:#94a3b8">Não</span>';
    document.getElementById('det-aviso').textContent  = c.aviso_previo ? c.aviso_previo + ' dias' : '—';
    document.getElementById('det-multa').textContent  = fmt(c.multa);
    document.getElementById('det-clausula').textContent = fmt(c.clausula_tecnica);

    const vencEl = document.getElementById('det-venc-bloco');
    if (c.tipo_vencimento === 'recorrente') {
        vencEl.innerHTML = '<span style="color:#1d4ed8">🔁 Recorrente Mensal</span><span style="color:#94a3b8;font-weight:400"> — sem data de término definida</span>';
    } else {
        const dias = calcDias(c.data_final);
        const cor  = dias < 0 ? '#dc2626' : dias <= 15 ? '#dc2626' : dias <= 60 ? '#d97706' : '#475569';
        vencEl.innerHTML = `<span>📅 ${fmtData(c.data_final)}</span>
            <span style="color:${cor};margin-left:8px">(${dias >= 0 ? dias + 'd restantes' : 'Vencido'})</span>`;
    }

    const m = document.getElementById('modal-detalhe');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function fecharDetalhe(e) {
    const m = document.getElementById('modal-detalhe');
    m.classList.add('hidden'); m.classList.remove('flex');
}
function abrirDecisao(id, fornecedor, setor) {
    document.getElementById('decisao-id').textContent        = 'Portal de Decisão · ' + id;
    document.getElementById('decisao-fornecedor').textContent = fornecedor;
    document.getElementById('decisao-setor').textContent      = setor;
    const m = document.getElementById('modal-decisao');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function fecharDecisao(e) {
    if (e && e.target !== document.getElementById('modal-decisao')) return;
    const m = document.getElementById('modal-decisao');
    m.classList.add('hidden'); m.classList.remove('flex');
}
function calcDias(iso) {
    if (!iso) return 9999;
    const hoje = new Date(); hoje.setHours(0,0,0,0);
    return Math.round((new Date(iso+'T00:00:00') - hoje) / 86400000);
}

function abrirDecisao(id, fornecedor, setor) {
    document.getElementById('decisao-id').innerText = id;
    document.getElementById('decisao-fornecedor').innerText = fornecedor;
    document.getElementById('decisao-setor').innerText = setor;
    document.getElementById('input-id-contrato').value = id; // Joga o ID no input do form
    
    document.getElementById('modal-decisao').classList.remove('hidden');
    document.getElementById('modal-decisao').classList.add('flex');
}
</script>

<?php include 'includes/footer.php'; ?>