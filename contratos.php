<?php
require_once 'config.php';

// =====================================================================
// 0. MAPA DAS 7 ETAPAS DO FLUXO (POP - Compartilhamento de Informações
//    de Contratos com o Contas a Pagar)
// =====================================================================
$ETAPAS = [
    1 => 'Demanda de Informação',
    2 => 'Análise da Necessidade (5W2H)',
    3 => 'Preparação das Informações',
    4 => 'Validação e Aprovação',
    5 => 'Compartilhamento Controlado',
    6 => 'Uso e Execução (Contas a Pagar)',
    7 => 'Comunicação e Controle',
];

// =====================================================================
// 1. CONTEXTO DO USUÁRIO LOGADO (usado tanto no POST quanto na tela)
// =====================================================================
$user_id_sessao = $_SESSION['user_id'] ?? 0;
$admin_ip       = $_SERVER['REMOTE_ADDR'];
$eh_admin       = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

$setor_usuario  = mb_strtoupper(trim($_SESSION['setor'] ?? ''), 'UTF-8');
$eh_financeiro  = in_array($setor_usuario, ['FINANCEIRO', 'CONTAS A PAGAR']);

// RECUPERA MENSAGENS DA URL
$msg_sucesso = $_GET['sucesso'] ?? '';
$msg_erro    = $_GET['erro'] ?? '';

include 'includes/header.php';
include 'includes/sidebar.php';

// =====================================================================
// 3. TRAVA DE SEGURANÇA DA PÁGINA — permissão de SISTEMA (RBAC)
// =====================================================================
$sistema_id_contratos = $pdo_intra->query("SELECT id FROM sistemas_lista WHERE nome = 'Gestão de Contratos'")->fetchColumn();

$tem_acesso_sistema = $eh_admin;
if (!$tem_acesso_sistema && $sistema_id_contratos) {
    $stmt = $pdo_intra->prepare("SELECT 1 FROM permissoes_sistemas WHERE user_id = ? AND sistema_id = ?");
    $stmt->execute([$user_id_sessao, $sistema_id_contratos]);
    $tem_acesso_sistema = (bool) $stmt->fetch();

    if (!$tem_acesso_sistema) {
        $stmt_g = $pdo_intra->prepare("
            SELECT 1 FROM usuarios_grupos ug
            INNER JOIN grupos_sistemas gs ON gs.grupo_id = ug.grupo_id
            WHERE ug.usuario_id = ? AND gs.sistema_id = ?
        ");
        $stmt_g->execute([$user_id_sessao, $sistema_id_contratos]);
        $tem_acesso_sistema = (bool) $stmt_g->fetch();
    }
}

if (!$tem_acesso_sistema) {
    die("<script>window.location.href='index.php';</script>");
}

// =====================================================================
// 4. BUSCA DE DADOS
// =====================================================================
function calcularAlerta(string $data_vencimento): array {
    $hoje  = new DateTime('today');
    $venc  = new DateTime($data_vencimento);
    $dias  = (int) $hoje->diff($venc)->days;
    if ($venc < $hoje) return ['texto' => 'Vencido', 'cor' => 'rose'];
    if ($dias <= 15)   return ['texto' => "Vence em {$dias}d", 'cor' => 'rose'];
    if ($dias <= 30)   return ['texto' => "{$dias}d", 'cor' => 'amber'];
    return ['texto' => "{$dias}d", 'cor' => 'slate'];
}

if ($eh_admin) {
    $contratos = $pdo_intra->query("SELECT * FROM contratos ORDER BY data_vencimento ASC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($eh_financeiro) {
    $contratos = $pdo_intra->query("SELECT * FROM contratos WHERE etapa_atual >= 5 ORDER BY data_vencimento ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo_intra->prepare("SELECT * FROM contratos WHERE setor = ? ORDER BY data_vencimento ASC");
    $stmt->execute([$setor_usuario]);
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$divergencias_abertas = $pdo_intra->query("SELECT contrato_id, COUNT(*) as qtd FROM contratos_divergencias WHERE status = 'ABERTA' GROUP BY contrato_id")
                                   ->fetchAll(PDO::FETCH_KEY_PAIR);

// KPIs
$total_contratos  = count($contratos);
$total_ativos     = count(array_filter($contratos, fn($c) => $c['status'] === 'ATIVO'));
$total_alertas    = count(array_filter($contratos, fn($c) => calcularAlerta($c['data_vencimento'])['cor'] !== 'slate'));

$setores_distintos = [];
if ($eh_admin) {
    $setores_distintos = $pdo_intra->query("SELECT DISTINCT setor FROM contratos ORDER BY setor")->fetchAll(PDO::FETCH_COLUMN);
}
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-8">
    <div class="max-w-7xl mx-auto">

        <?php if (!empty($msg_sucesso)): ?>
            <div class="mb-6 bg-emerald-50 text-emerald-700 p-4 rounded-2xl font-bold border border-emerald-100 shadow-sm"><?php echo htmlspecialchars($msg_sucesso); ?></div>
        <?php endif; ?>
        <?php if (!empty($msg_erro)): ?>
            <div class="mb-6 bg-red-50 text-red-700 p-4 rounded-2xl font-bold border border-red-100 shadow-sm"><?php echo htmlspecialchars($msg_erro); ?></div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h2 class="text-3xl font-black text-navy-900 tracking-tight uppercase italic">Gestão de Contratos</h2>
                <p class="text-slate-500 font-medium mt-1">
                    <?php if ($eh_financeiro && !$eh_admin): ?>
                        Contratos compartilhados com o Contas a Pagar.
                    <?php elseif (!$eh_admin): ?>
                        Contratos do setor <?php echo htmlspecialchars($setor_usuario); ?>.
                    <?php else: ?>
                        Painel de acompanhamento — todos os setores.
                    <?php endif; ?>
                </p>
            </div>

            <?php if (!$eh_financeiro || $eh_admin): ?>
            <button onclick="abrirWizard()" class="bg-navy-900 hover:bg-navy-800 text-white font-bold px-5 py-3 rounded-2xl shadow-md transition-all">
                + Novo Contrato
            </button>
            <?php endif; ?>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Contratos Ativos</p>
                <p class="text-3xl font-black text-navy-900 mt-1"><?php echo $total_ativos; ?></p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Total de Contratos</p>
                <p class="text-3xl font-black text-navy-900 mt-1"><?php echo $total_contratos; ?></p>
            </div>
            <div class="bg-amber-50 rounded-2xl border border-amber-100 p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wider text-amber-600">Alertas de Vencimento</p>
                <p class="text-3xl font-black text-amber-700 mt-1"><?php echo $total_alertas; ?></p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 mb-4 flex flex-col md:flex-row gap-3 shadow-sm">
            <input type="text" id="busca-contrato" placeholder="🔍 Fornecedor, serviço, CNPJ..."
                   class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium outline-none focus:border-corporate-blue">
            <?php if ($eh_admin): ?>
            <select id="filtro-setor" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600">
                <option value="">Todos os setores</option>
                <?php foreach ($setores_distintos as $s): ?>
                    <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select id="filtro-etapa" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600">
                <option value="">Todas as etapas</option>
                <?php foreach ($ETAPAS as $num => $label): ?>
                    <option value="<?php echo $num; ?>"><?php echo $num; ?>. <?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Tabela -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-400 text-xs font-black uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-6 py-3">Fornecedor / Serviço</th>
                        <?php if ($eh_admin): ?><th class="text-left px-4 py-3">Setor</th><?php endif; ?>
                        <th class="text-left px-4 py-3">Etapa</th>
                        <th class="text-left px-4 py-3">Vencimento</th>
                        <th class="text-left px-4 py-3">Alerta</th>
                        <th class="text-right px-6 py-3">Ações</th>
                    </tr>
                </thead>
                <tbody id="corpo-tabela-contratos">
                    <?php if (empty($contratos)): ?>
                        <tr><td colspan="6" class="text-center px-6 py-10 text-slate-400 font-medium">Nenhum contrato encontrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($contratos as $c):
                        $alerta   = calcularAlerta($c['data_vencimento']);
                        $etapa    = (int) $c['etapa_atual'];
                        $tem_div  = isset($divergencias_abertas[$c['id']]);
                        $eh_dono  = $eh_admin || $c['setor'] === $setor_usuario;
                    ?>
                    <tr class="linha-contrato border-t border-slate-100 hover:bg-slate-50/60"
                        data-nome="<?php echo htmlspecialchars(mb_strtolower($c['fornecedor'] . ' ' . $c['servico_objeto'] . ' ' . $c['cnpj'])); ?>"
                        data-setor="<?php echo htmlspecialchars($c['setor']); ?>"
                        data-etapa="<?php echo $etapa; ?>">
                        <td class="px-6 py-4">
                            <p class="font-bold text-navy-900"><?php echo htmlspecialchars($c['fornecedor']); ?></p>
                            <p class="text-slate-400 text-xs"><?php echo htmlspecialchars($c['servico_objeto']); ?></p>
                            <?php if ($tem_div): ?>
                                <span class="inline-block mt-1 bg-rose-100 text-rose-700 text-[10px] font-black uppercase px-2 py-0.5 rounded-full">⚠ Divergência aberta</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($eh_admin): ?>
                        <td class="px-4 py-4"><span class="bg-slate-100 text-slate-700 text-[11px] font-black uppercase px-2 py-1 rounded-full"><?php echo htmlspecialchars($c['setor']); ?></span></td>
                        <?php endif; ?>
                        <td class="px-4 py-4">
                            <span class="text-xs font-bold text-navy-900"><?php echo $etapa; ?>/7</span>
                            <p class="text-[11px] text-slate-400"><?php echo htmlspecialchars($ETAPAS[$etapa] ?? ''); ?></p>
                        </td>
                        <td class="px-4 py-4 font-medium text-slate-600"><?php echo (new DateTime($c['data_vencimento']))->format('d/m/Y'); ?></td>
                        <td class="px-4 py-4">
                            <span class="bg-<?php echo $alerta['cor']; ?>-100 text-<?php echo $alerta['cor']; ?>-700 text-xs font-bold px-2 py-1 rounded-full"><?php echo $alerta['texto']; ?></span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <button onclick='abrirDetalhes(<?php echo json_encode($c); ?>, <?php echo $eh_dono ? "true" : "false"; ?>)'
                                    class="text-xs font-bold text-navy-900 border border-slate-200 px-3 py-2 rounded-xl hover:bg-slate-50">Detalhes</button>

                            <?php if ($eh_dono && $etapa < 5): ?>
                                <button onclick="compartilharContrato(<?php echo $c['id']; ?>)" class="text-xs font-bold text-blue-700 border border-blue-200 px-3 py-2 rounded-xl hover:bg-blue-50 ml-1">Compartilhar</button>
                            <?php endif; ?>

                            <?php if (($eh_financeiro || $eh_admin) && $etapa === 5): ?>
                                <button onclick="confirmarUso(<?php echo $c['id']; ?>)" class="text-xs font-bold text-emerald-700 border border-emerald-200 px-3 py-2 rounded-xl hover:bg-emerald-50 ml-1">Confirmar Uso</button>
                            <?php endif; ?>

                            <?php if (($eh_financeiro || $eh_admin) && $etapa >= 5): ?>
                                <button onclick="abrirDivergencia(<?php echo $c['id']; ?>)" class="text-xs font-bold text-rose-700 border border-rose-200 px-3 py-2 rounded-xl hover:bg-rose-50 ml-1">Divergência</button>
                            <?php endif; ?>

                            <?php if ($eh_dono): ?>
                                <button onclick='editarContrato(<?php echo json_encode($c); ?>)' class="text-xs font-bold text-slate-600 border border-slate-200 px-3 py-2 rounded-xl hover:bg-slate-50 ml-1">Editar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- SLIDE-OVER DETALHES -->
<div id="slideover-detalhes" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/30" onclick="fecharDetalhes()"></div>
    <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl overflow-y-auto p-6">
        <div class="flex justify-between items-start mb-4">
            <h3 id="det-titulo" class="text-xl font-black text-navy-900"></h3>
            <button onclick="fecharDetalhes()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
        </div>
        <div id="det-conteudo" class="space-y-4 text-sm"></div>
    </div>
</div>

<!-- MODAL DIVERGÊNCIA -->
<div id="modal-divergencia" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-lg font-black text-navy-900 mb-1">Comunicar Divergência de Valores</h3>
        <p class="text-xs text-slate-400 mb-4">Conforme o POP, o Contas a Pagar só comunica divergências de valores do contrato.</p>
        <input type="hidden" id="div-contrato-id">
        <textarea id="div-descricao" rows="4" placeholder="Descreva a divergência encontrada..."
                  class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-corporate-blue"></textarea>
        <div class="flex justify-end gap-2 mt-4">
            <button onclick="fecharModalDivergencia()" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-50">Cancelar</button>
            <button onclick="enviarDivergencia()" class="px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-rose-600 hover:bg-rose-500">Enviar</button>
        </div>
    </div>
</div>

<!-- WIZARD NOVO / EDITAR CONTRATO -->
<div id="modal-wizard" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <form id="form-contrato" method="POST" action="api/ContratoController.php" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="salvar_contrato">
            <input type="hidden" name="contrato_id" id="w-contrato-id">
            <input type="hidden" name="arquivo_atual" id="w-arquivo-atual">

            <div class="p-6 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white z-10">
                <div>
                    <h3 id="w-titulo" class="text-lg font-black text-navy-900">Novo Contrato</h3>
                    <p id="w-passo-label" class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-0.5">Passo 1 de 3 — Dados Gerais</p>
                </div>
                <button type="button" onclick="fecharWizard()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>

            <!-- PASSO 1: DADOS GERAIS + CAMPOS NOVOS -->
            <div class="w-passo p-6 space-y-4" data-passo="1">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Fornecedor</label>
                        <input required name="fornecedor" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">CNPJ</label>
                        <input name="cnpj" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Nº do Contrato</label>
                        <input name="numero_contrato" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Código do Sistema (Opcional)</label>
                        <input name="codigo_sistema" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Valor Contratado (R$)</label>
                        <input name="valor" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Objeto / Serviço</label>
                        <input required name="servico_objeto" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Setor</label>
                        <select required name="setor" id="w-setor" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                            <?php if ($eh_admin): ?>
                                <option value="RH">RH</option>
                                <option value="FISCAL">FISCAL</option>
                                <option value="FACILITIES">FACILITIES</option>
                                <option value="TI">TI</option>
                                <option value="COMERCIAL">COMERCIAL</option>
                                <option value="LOGISTICA">LOGÍSTICA</option>
                            <?php else: ?>
                                <option value="<?php echo htmlspecialchars($setor_usuario); ?>"><?php echo htmlspecialchars($setor_usuario); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Empresa</label>
                        <input name="empresa" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Início da Vigência</label>
                        <input type="date" name="data_inicio" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Vencimento</label>
                        <input type="date" name="data_vencimento" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Cláusula Técnica / Observações Restritas</label>
                        <textarea name="clausula_tecnica" rows="2" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue"></textarea>
                    </div>
                    <div class="flex items-center gap-2 mt-2 col-span-2">
                        <input type="checkbox" name="recorrente" id="w-recorrente" checked class="w-4 h-4">
                        <label for="w-recorrente" class="text-sm font-bold text-slate-600">Vencimento recorrente (renovação periódica / indeterminado)</label>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Anexo do Contrato (PDF)</label>
                        <input type="file" name="arquivo_contrato" accept=".pdf" class="mt-1 w-full text-sm">
                    </div>
                </div>
            </div>

            <!-- PASSO 2: RESUMO FINANCEIRO + QTD PARCELAS E MULTA -->
            <div class="w-passo p-6 space-y-4 hidden" data-passo="2">
                <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 text-xs text-emerald-800 font-medium">
                    ✅ Estes campos compõem o <strong>Resumo Financeiro</strong> visível ao Contas a Pagar.
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Forma de Pagamento</label>
                        <input name="forma_pagamento" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Quantidade de Parcelas</label>
                        <input type="number" name="quantidade_parcelas" placeholder="Ex: 12" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Periodicidade</label>
                        <input name="periodicidade" placeholder="Mensal, quinzenal..." class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Índices de Reajuste</label>
                        <input name="indices_reajuste" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Centro de Custo</label>
                        <input name="centro_custo" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Multa / Carência / Aviso Prévio</label>
                        <input name="multa_carencia" placeholder="Ex: Multa 10% / Aviso 30d" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Dados Bancários do Fornecedor</label>
                        <input name="dados_bancarios_fornecedor" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Retenções Tributárias</label>
                        <input name="retencoes_tributarias" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Condições de Pagamento</label>
                        <input name="condicoes_pagamento" placeholder="Medição, aceite, NF..." class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Responsável pela Aprovação do Serviço</label>
                        <input name="responsavel_aprovacao_servico" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Contato Financeiro — Nome</label>
                        <input name="contato_financeiro_nome" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Contato Financeiro — Telefone</label>
                        <input name="contato_financeiro_telefone" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Contato Financeiro — E-mail</label>
                        <input type="email" name="contato_financeiro_email" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                </div>
            </div>

            <!-- PASSO 3: REVISÃO -->
            <div class="w-passo p-6 space-y-4 hidden" data-passo="3">
                <div class="bg-red-50 border border-red-100 rounded-xl p-4 text-xs text-red-700">
                    <p class="font-black uppercase tracking-wider mb-2">⚠ Informações restritas — NÃO compartilhar</p>
                    <p class="leading-relaxed">Estratégias comerciais, histórico de negociações e cláusulas de confidencialidade não devem ir para campos públicos.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input required type="checkbox" id="w-confirma" class="w-4 h-4">
                    <label for="w-confirma" class="text-sm font-bold text-slate-600">Confirmo que as informações restritas foram tratadas corretamente.</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="compartilhar_agora" id="w-compartilhar-agora" class="w-4 h-4">
                    <label for="w-compartilhar-agora" class="text-sm font-bold text-slate-600">Compartilhar com o Contas a Pagar agora (Avança para etapa 5).</label>
                </div>
            </div>

            <div class="p-6 border-t border-slate-100 flex justify-between sticky bottom-0 bg-white">
                <button type="button" id="w-btn-voltar" onclick="wizardVoltar()" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-50 invisible">Voltar</button>
                <div class="flex gap-2">
                    <button type="button" onclick="fecharWizard()" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-50">Cancelar</button>
                    <button type="button" id="w-btn-avancar" onclick="wizardAvancar()" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-navy-900 hover:bg-navy-800">Avançar</button>
                    <button type="submit" id="w-btn-salvar" class="hidden px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-navy-900 hover:bg-navy-800">Salvar Contrato</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const ETAPAS_LABEL = <?php echo json_encode($ETAPAS, JSON_UNESCAPED_UNICODE); ?>;
const EH_ADMIN      = <?php echo $eh_admin ? 'true' : 'false'; ?>;
const EH_FINANCEIRO = <?php echo $eh_financeiro ? 'true' : 'false'; ?>;
const SETOR_USUARIO = <?php echo json_encode($setor_usuario, JSON_UNESCAPED_UNICODE); ?>;

let wizardPassoAtual = 1;

function abrirWizard() {
    document.getElementById('form-contrato').reset();
    document.getElementById('w-contrato-id').value = '';
    document.getElementById('w-arquivo-atual').value = '';
    document.getElementById('w-titulo').innerText = 'Novo Contrato';
    wizardPassoAtual = 1;
    renderPassoWizard();
    document.getElementById('modal-wizard').classList.remove('hidden');
    document.getElementById('modal-wizard').classList.add('flex');
}

function editarContrato(c) {
    document.getElementById('form-contrato').reset();
    document.getElementById('w-contrato-id').value = c.id;
    document.getElementById('w-arquivo-atual').value = c.arquivo_path || '';
    document.getElementById('w-titulo').innerText = 'Editar Contrato';

    const form = document.getElementById('form-contrato');
    for (const campo in c) {
        const el = form.querySelector(`[name="${campo}"]`);
        if (el && el.type !== 'file' && el.type !== 'checkbox') el.value = c[campo] ?? '';
    }
    form.querySelector('[name="recorrente"]').checked = !!Number(c.recorrente);

    wizardPassoAtual = 1;
    renderPassoWizard();
    document.getElementById('modal-wizard').classList.remove('hidden');
    document.getElementById('modal-wizard').classList.add('flex');
}

function fecharWizard() {
    document.getElementById('modal-wizard').classList.add('hidden');
    document.getElementById('modal-wizard').classList.remove('flex');
}

function renderPassoWizard() {
    document.querySelectorAll('.w-passo').forEach(p => p.classList.add('hidden'));
    document.querySelector(`.w-passo[data-passo="${wizardPassoAtual}"]`).classList.remove('hidden');

    const labels = { 1: 'Dados Gerais', 2: 'Resumo Financeiro', 3: 'Revisão e Compartilhamento' };
    document.getElementById('w-passo-label').innerText = `Passo ${wizardPassoAtual} de 3 — ${labels[wizardPassoAtual]}`;

    document.getElementById('w-btn-voltar').classList.toggle('invisible', wizardPassoAtual === 1);
    document.getElementById('w-btn-avancar').classList.toggle('hidden', wizardPassoAtual === 3);
    document.getElementById('w-btn-salvar').classList.toggle('hidden', wizardPassoAtual !== 3);
}

function wizardAvancar() {
    if (wizardPassoAtual === 1) {
        const form = document.getElementById('form-contrato');
        if (!form.fornecedor.value || !form.servico_objeto.value) {
            alert('Preencha fornecedor e objeto antes de avançar.');
            return;
        }
    }
    wizardPassoAtual = Math.min(3, wizardPassoAtual + 1);
    renderPassoWizard();
}

function wizardVoltar() {
    wizardPassoAtual = Math.max(1, wizardPassoAtual - 1);
    renderPassoWizard();
}

document.getElementById('form-contrato').addEventListener('submit', function (e) {
    if (!document.getElementById('w-confirma').checked) {
        e.preventDefault();
        alert('Confirme que nenhuma informação restrita foi incluída antes de salvar.');
    }
});

// =====================================================================
// SLIDE-OVER DE DETALHES (COM AS REGRAS DE VISIBILIDADE)
// =====================================================================
function abrirDetalhes(c, ehDono) {
    document.getElementById('det-titulo').innerText = c.fornecedor;
    const etapaLabel = ETAPAS_LABEL[c.etapa_atual] || '';
    let html = `<div class="bg-slate-50 rounded-xl p-3 mb-4">
        <p class="text-xs font-black uppercase text-slate-400">Etapa atual do fluxo</p>
        <p class="font-bold text-navy-900">${c.etapa_atual}/7 — ${etapaLabel}</p>
    </div>`;

    const podeVerTudo = EH_ADMIN || ehDono;
    const podeVerFinanceiro = podeVerTudo || (EH_FINANCEIRO && Number(c.etapa_atual) >= 5);

    // BLOCO 1: DADOS BÁSICOS (Visível ao Financeiro e Dono)
    if (podeVerFinanceiro || podeVerTudo) {
        html += campoDetalhe('Objeto/Serviço', c.servico_objeto)
              + campoDetalhe('CNPJ', c.cnpj)
              + campoDetalhe('Nº Contrato', c.numero_contrato)
              + campoDetalhe('Vigência', `${c.data_inicio || '—'} até ${c.data_vencimento}`)
              + campoDetalhe('Valor Contratado', 'R$ ' + Number(c.valor || 0).toLocaleString('pt-BR', {minimumFractionDigits:2}));
    }

    // BLOCO 2: DADOS RESTRITOS (SÓ Dono ou Admin + Botão de Download do PDF)
    if (podeVerTudo) {
        html += `<div class="pt-3 mt-3 border-t border-slate-100"><p class="text-xs font-black uppercase text-slate-400 mb-2">Dados Restritos da Área Gestora</p></div>`;
        html += campoDetalhe('Setor', c.setor)
              + campoDetalhe('Empresa', c.empresa)
              + campoDetalhe('Código do Sistema', c.codigo_sistema)
              + campoDetalhe('Cláusula Técnica', c.clausula_tecnica);
              
        if (c.arquivo_path) {
            html += `<a href="${c.arquivo_path}" target="_blank" class="block text-center bg-navy-900 text-white font-bold text-sm py-2.5 rounded-xl mt-3 mb-4 hover:bg-navy-800 transition-colors">📎 Baixar contrato anexado</a>`;
        }
    }

    // BLOCO 3: RESUMO FINANCEIRO (Financeiro e Dono)
    if (podeVerFinanceiro) {
        html += `<div class="pt-3 mt-3 border-t border-slate-100"><p class="text-xs font-black uppercase text-slate-400 mb-2">Resumo Financeiro (Contato Financeiro)</p></div>`;
        html += campoDetalhe('Forma de Pagamento', c.forma_pagamento)
              + campoDetalhe('Quantidade de Parcelas', c.quantidade_parcelas)
              + campoDetalhe('Periodicidade', c.periodicidade)
              + campoDetalhe('Índices de Reajuste', c.indices_reajuste)
              + campoDetalhe('Centro de Custo', c.centro_custo)
              + campoDetalhe('Multa / Carência / Aviso', c.multa_carencia)
              + campoDetalhe('Dados Bancários', c.dados_bancarios_fornecedor)
              + campoDetalhe('Retenções Tributárias', c.retencoes_tributarias)
              + campoDetalhe('Condições de Pagamento', c.condicoes_pagamento)
              + campoDetalhe('Responsável pela Aprovação', c.responsavel_aprovacao_servico)
              + campoDetalhe('Contato Financeiro', `${c.contato_financeiro_nome || ''} · ${c.contato_financeiro_email || ''} · ${c.contato_financeiro_telefone || ''}`);
    } else if (!podeVerTudo) {
        html += `<div class="bg-amber-50 text-amber-700 text-sm font-medium rounded-xl p-4 mt-3">Este contrato ainda não foi compartilhado.</div>`;
    }

    document.getElementById('det-conteudo').innerHTML = html;
    document.getElementById('slideover-detalhes').classList.remove('hidden');
}

function campoDetalhe(label, valor) {
    if (!valor) return '';
    return `<div><p class="text-[11px] font-black uppercase tracking-wider text-slate-400">${label}</p><p class="font-medium text-slate-700">${valor}</p></div>`;
}

function fecharDetalhes() {
    document.getElementById('slideover-detalhes').classList.add('hidden');
}

// =====================================================================
// AÇÕES DE FLUXO (AJAX)
// =====================================================================
function postAcao(acao, campos) {
    const fd = new FormData();
    fd.append('acao', acao);
    for (const k in campos) fd.append(k, campos[k]);
    return fetch('api/ContratoController.php', { method: 'POST', body: fd }).then(r => r.text());
}

function compartilharContrato(id) {
    if (!confirm('Compartilhar o Resumo Financeiro deste contrato com o Contas a Pagar?')) return;
    postAcao('compartilhar_contrato', { contrato_id: id }).then(resp => {
        if (resp.trim() === 'sucesso') location.reload();
        else alert(resp);
    });
}

function confirmarUso(id) {
    if (!confirm('Confirmar o recebimento/uso destas informações pelo Contas a Pagar?')) return;
    postAcao('confirmar_uso', { contrato_id: id }).then(resp => {
        if (resp.trim() === 'sucesso') location.reload();
        else alert(resp);
    });
}

let idDivergenciaAtual = null;
function abrirDivergencia(id) {
    idDivergenciaAtual = id;
    document.getElementById('div-descricao').value = '';
    document.getElementById('modal-divergencia').classList.remove('hidden');
    document.getElementById('modal-divergencia').classList.add('flex');
}
function fecharModalDivergencia() {
    document.getElementById('modal-divergencia').classList.add('hidden');
    document.getElementById('modal-divergencia').classList.remove('flex');
}
function enviarDivergencia() {
    const descricao = document.getElementById('div-descricao').value.trim();
    if (!descricao) { alert('Descreva a divergência de valores.'); return; }
    postAcao('registrar_divergencia', { contrato_id: idDivergenciaAtual, descricao }).then(resp => {
        if (resp.trim() === 'sucesso') location.reload();
        else alert(resp);
    });
}

// =====================================================================
// FILTROS
// =====================================================================
const inputBusca   = document.getElementById('busca-contrato');
const selectSetor  = document.getElementById('filtro-setor');
const selectEtapa  = document.getElementById('filtro-etapa');
const linhas       = document.querySelectorAll('.linha-contrato');

function aplicarFiltros() {
    const termo  = inputBusca.value.toLowerCase().trim();
    const setor  = selectSetor ? selectSetor.value.toLowerCase().trim() : '';
    const etapa  = selectEtapa.value;

    linhas.forEach(linha => {
        const bateuNome  = termo === '' || linha.dataset.nome.includes(termo);
        const bateuSetor = setor === '' || linha.dataset.setor.toLowerCase() === setor;
        const bateuEtapa = etapa === '' || linha.dataset.etapa === etapa;
        linha.style.display = (bateuNome && bateuSetor && bateuEtapa) ? '' : 'none';
    });
}

inputBusca.addEventListener('input', aplicarFiltros);
if (selectSetor) selectSetor.addEventListener('change', aplicarFiltros);
selectEtapa.addEventListener('change', aplicarFiltros);
</script>

<?php include 'includes/footer.php'; ?>