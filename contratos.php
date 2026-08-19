<?php
require_once 'config.php';
require_once __DIR__ . '/api/ContratoAuth.php';

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
$auth           = new ContratoAuth($pdo_intra, (int) $user_id_sessao, $eh_admin);
$pode_criar     = $auth->pode('criar');
$pode_editar    = $auth->pode('editar');
$pode_excluir   = $auth->pode('excluir');
$pode_compartilhar = $auth->pode('compartilhar');
$pode_confirmar = $auth->pode('confirmar_uso');
$pode_divergir  = $auth->pode('registrar_divergencia');
$pode_financeiro = $auth->pode('ver_financeiro');
$pode_restritos = $auth->pode('ver_restritos');
$pode_baixar    = $auth->pode('baixar_anexo');
$csrf_token     = contratoCsrfToken();

// A permissão do próprio módulo é validada antes de renderizar qualquer HTML.
// A sidebar apenas oculta o link; esta trava impede acesso digitando a URL.
if (!$auth->pode('acessar_modulo') || !$auth->pode('visualizar')) {
    http_response_code(403);
    header('Location: index.php?erro=' . urlencode('Você não possui acesso à Gestão de Contratos.'));
    exit;
}

// RECUPERA MENSAGENS DA URL
$msg_sucesso = $_GET['sucesso'] ?? '';
$msg_erro    = $_GET['erro'] ?? '';

include 'includes/header.php';
include 'includes/sidebar.php';

// =====================================================================
// 4. BUSCA DE DADOS
// =====================================================================
function calcularAlerta(?string $data_vencimento, string $setor = '', bool $recorrente = false, string $status = 'ATIVO'): array {
    if ($status !== 'ATIVO' || empty($data_vencimento)) {
        return ['texto' => 'Sem alerta', 'cor' => 'slate', 'ativo' => false, 'situacao' => 'sem_alerta'];
    }
    $hoje  = new DateTime('today');
    $venc  = new DateTime($data_vencimento);
    $dias  = (int) $hoje->diff($venc)->days;
    $antecedencia = str_contains(mb_strtoupper($setor, 'UTF-8'), 'FACILITIES') ? 90 : 60;
    if ($venc < $hoje) return ['texto' => 'Vencido', 'cor' => 'rose', 'ativo' => true, 'situacao' => 'vencido'];
    if ($dias <= 15) return ['texto' => "Vence em {$dias}d", 'cor' => 'rose', 'ativo' => true, 'situacao' => 'vencendo'];
    if ($dias <= $antecedencia) return ['texto' => "Vence em {$dias}d", 'cor' => 'amber', 'ativo' => true, 'situacao' => 'vencendo'];
    return ['texto' => "{$dias}d", 'cor' => 'slate', 'ativo' => false, 'situacao' => 'regular'];
}

[$filtroContratos, $paramsContratos] = $auth->filtroContratosSql();
$stmt = $pdo_intra->prepare("SELECT c.* FROM contratos c WHERE {$filtroContratos} ORDER BY c.data_vencimento ASC");
$stmt->execute($paramsContratos);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$divergencias_abertas = $pdo_intra->query("SELECT contrato_id, COUNT(*) as qtd FROM contratos_divergencias WHERE status = 'ABERTA' GROUP BY contrato_id")
                                   ->fetchAll(PDO::FETCH_KEY_PAIR);
$divergencias_por_contrato = [];
if ($contratos) {
    $ids = array_map('intval', array_column($contratos, 'id'));
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt_div = $pdo_intra->prepare("SELECT id, contrato_id, descricao, status, usuario_id FROM contratos_divergencias WHERE contrato_id IN ($marcadores) ORDER BY id DESC");
    $stmt_div->execute($ids);
    foreach ($stmt_div->fetchAll(PDO::FETCH_ASSOC) as $div) {
        $divergencias_por_contrato[(int) $div['contrato_id']][] = $div;
    }
}

function camposEssenciaisPendentes(array $contrato): array {
    $campos = ['fornecedor'=>'Fornecedor','cnpj'=>'CNPJ do fornecedor','servico_objeto'=>'Objeto / serviço',
        'setor'=>'Setor responsável','empresa'=>'Empresa contratante','cnpj_empresa_contratante'=>'CNPJ da contratante',
        'data_inicio'=>'Início da vigência','tipo_prazo'=>'Tipo de prazo','tipo_pagamento'=>'Tipo de pagamento',
        'forma_pagamento'=>'Forma de pagamento','arquivo_path'=>'Contrato em PDF'];
    $pendentes = [];
    foreach ($campos as $campo => $rotulo) {
        if (!array_key_exists($campo, $contrato) || $contrato[$campo] === null || trim((string) $contrato[$campo]) === '') {
            $pendentes[] = $rotulo;
        }
    }
    if (($contrato['tipo_prazo'] ?? '') === 'DETERMINADO' && empty($contrato['data_vencimento'])) $pendentes[] = 'Data final';
    if (($contrato['tipo_prazo'] ?? '') === 'DETERMINADO' && !in_array(($contrato['renovacao_automatica'] ?? ''), [0,1,'0','1'], true)) $pendentes[] = 'Renovação automática';
    if (($contrato['tipo_pagamento'] ?? '') === 'UNICO' && (float)($contrato['valor'] ?? 0) <= 0) $pendentes[] = 'Valor total';
    if (($contrato['tipo_pagamento'] ?? '') === 'PARCELADO') {
        if ((float)($contrato['valor'] ?? 0) <= 0) $pendentes[] = 'Valor total';
        if ((int)($contrato['quantidade_parcelas'] ?? 0) < 2) $pendentes[] = 'Quantidade de parcelas';
        if (empty($contrato['periodicidade'])) $pendentes[] = 'Periodicidade';
    }
    if (($contrato['tipo_pagamento'] ?? '') === 'RECORRENTE_MENSAL') {
        if ((float)($contrato['valor_parcela'] ?? 0) <= 0) $pendentes[] = 'Valor mensal';
        if ((int)($contrato['dia_vencimento'] ?? 0) < 1 || (int)($contrato['dia_vencimento'] ?? 0) > 31) $pendentes[] = 'Dia do vencimento';
    }
    foreach (['possui_reajuste'=>'Reajuste','possui_aviso_cancelamento'=>'Aviso prévio','possui_multa'=>'Multa','possui_carencia'=>'Carência'] as $campo=>$rotulo) {
        if (!array_key_exists($campo,$contrato) || $contrato[$campo] === null || $contrato[$campo] === '') $pendentes[]=$rotulo;
    }
    if ((string)($contrato['possui_reajuste'] ?? '') === '1') {
        if (empty($contrato['indice_reajuste'])) $pendentes[]='Índice de reajuste';
        if (empty($contrato['periodicidade_reajuste'])) $pendentes[]='Periodicidade do reajuste';
        if ((int)($contrato['mes_base_reajuste'] ?? 0) < 1) $pendentes[]='Mês-base do reajuste';
        if (($contrato['indice_reajuste'] ?? '') === 'OUTRO' && empty($contrato['indice_reajuste_outro'])) $pendentes[]='Nome do índice de reajuste';
    }
    if (($contrato['possui_aviso_cancelamento'] ?? '') === 'SIM' && ($contrato['prazo_comunicacao_cancelamento'] ?? '') === '') $pendentes[]='Aviso prévio em dias';
    if (($contrato['possui_multa'] ?? '') === 'SIM' && empty($contrato['multa_contratual'])) $pendentes[]='Descrição da multa';
    if (($contrato['possui_carencia'] ?? '') === 'SIM' && empty($contrato['carencia_contratual'])) $pendentes[]='Descrição da carência';
    return $pendentes;
}

// KPIs
$total_contratos  = count($contratos);
$total_ativos     = count(array_filter($contratos, fn($c) => $c['status'] === 'ATIVO'));
$total_alertas    = count(array_filter($contratos, fn($c) => calcularAlerta($c['data_vencimento'] ?? null, $c['setor'] ?? '', !empty($c['recorrente']), $c['status'] ?? 'ATIVO')['ativo']));
$total_incompletos = count(array_filter($contratos, fn($c) => count(camposEssenciaisPendentes($c)) > 0));

$stmt_setores = $pdo_intra->query(
    "SELECT DISTINCT TRIM(SETOR) AS setor
       FROM matriz_comunicacao
      WHERE SETOR IS NOT NULL
        AND TRIM(SETOR) <> ''
      ORDER BY setor"
);
$setores_distintos = $stmt_setores->fetchAll(PDO::FETCH_COLUMN);
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
                    <?php if ($pode_financeiro && !$pode_criar && !$eh_admin): ?>
                        Contratos compartilhados com o Contas a Pagar.
                    <?php elseif (!$eh_admin): ?>
                        Contratos do setor <?php echo htmlspecialchars($setor_usuario); ?>.
                    <?php else: ?>
                        Painel de acompanhamento — todos os setores.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($pode_criar): ?>
            <button onclick="abrirWizard()" class="bg-navy-900 hover:bg-navy-800 text-white font-bold px-5 py-3 rounded-2xl shadow-md transition-all">
                + Novo Contrato
            </button>
            <?php endif; ?>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
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
            <div class="bg-rose-50 rounded-2xl border border-rose-100 p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wider text-rose-600">Cadastros Incompletos</p>
                <p class="text-3xl font-black text-rose-700 mt-1"><?php echo $total_incompletos; ?></p>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-2.5 mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-blue-800">
            <span class="font-black">🔔 Alertas de término</span>
            <span><strong>Facilities:</strong> 90 dias</span>
            <span><strong>Demais setores:</strong> 60 dias</span>
            <span><strong>Crítico:</strong> 15 dias ou menos</span>
            <span class="text-blue-600">Prazo indeterminado não gera alerta.</span>
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
            <select id="filtro-situacao" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600">
                <option value="">Todas as situações</option>
                <option value="andamento">Em andamento</option>
                <option value="aguardando_financeiro">Aguardando Contas a Pagar</option>
                <option value="divergencia">Com divergência</option>
                <option value="confirmado">Uso confirmado</option>
                <option value="vencendo">Vencendo</option>
                <option value="vencido">Vencidos</option>
                <option value="incompleto">Cadastros incompletos</option>
            </select>
        </div>

        <!-- Visão gerencial compacta: uma linha por contrato, preparada para grandes volumes. -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="w-full overflow-visible">
        <table id="tabela-contratos" class="w-full text-left border-collapse table-fixed" style="table-layout:fixed">
            <thead class="bg-slate-50 text-[9px] uppercase tracking-wide text-slate-500 sticky top-0 z-10">
                <tr>
                    <th class="w-[17%] px-2 py-3 border-b border-slate-200"><button type="button" class="ordenar-coluna font-black" data-coluna="fornecedor">Fornecedor / serviço ↕</button></th>
                    <?php if ($eh_admin): ?><th class="w-[7%] px-2 py-3 border-b border-slate-200"><button type="button" class="ordenar-coluna font-black" data-coluna="setor">Setor ↕</button></th><?php endif; ?>
                    <th class="w-[10%] px-2 py-3 border-b border-slate-200 text-right"><button type="button" class="ordenar-coluna font-black" data-coluna="valor">Valor mensal ↕</button></th>
                    <th class="w-[7%] px-2 py-3 border-b border-slate-200">Período</th>
                    <th class="w-[8%] px-2 py-3 border-b border-slate-200 text-right">Total</th>
                    <th class="w-[5%] px-2 py-3 border-b border-slate-200 text-center">Parcelas</th>
                    <th class="w-[8%] px-2 py-3 border-b border-slate-200"><button type="button" class="ordenar-coluna font-black" data-coluna="vigencia">Vigência ↕</button></th>
                    <th class="w-[6%] px-2 py-3 border-b border-slate-200">Aviso</th>
                    <th class="w-[7%] px-2 py-3 border-b border-slate-200">Reajuste</th>
                    <th class="w-[8%] px-2 py-3 border-b border-slate-200">Multa</th>
                    <th class="w-[7%] px-2 py-3 border-b border-slate-200">Carência</th>
                    <th class="w-[9%] px-2 py-3 border-b border-slate-200"><button type="button" class="ordenar-coluna font-black" data-coluna="situacao">Situação ↕</button></th>
                    <th class="w-[8%] px-2 py-3 border-b border-slate-200 text-right sticky right-0 bg-slate-50">Ações</th>
                </tr>
            </thead>
            <tbody id="corpo-tabela-contratos" class="divide-y divide-slate-100 text-[10px]">
                    <?php if (empty($contratos)): ?>
                        <tr><td colspan="13" class="text-center px-6 py-10 text-slate-400 font-medium">Nenhum contrato encontrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($contratos as $c):
                        $alerta   = calcularAlerta($c['data_vencimento'] ?? null, $c['setor'] ?? '', !empty($c['recorrente']), $c['status'] ?? 'ATIVO');
                        $etapa    = (int) $c['etapa_atual'];
                        $tem_div  = isset($divergencias_abertas[$c['id']]);
                        $eh_responsavel = (int) $c['gestor_id'] === (int) $user_id_sessao;
                        $eh_dono  = $eh_admin || $eh_responsavel;
                        $pode_editar_este = $pode_editar && ($eh_admin || $auth->podeAcessarContrato((int) $c['id']));
                        $pendentes = camposEssenciaisPendentes($c);
                        $c_cliente = $c;
                        if (!$pode_financeiro && !$eh_dono) {
                            foreach (['valor','valor_parcela','forma_pagamento','quantidade_parcelas','periodicidade','indices_reajuste','centro_custo','multa_carencia','prazo_comunicacao_cancelamento','renovacao_automatica','aviso_previo','multa_contratual','carencia_contratual','dados_bancarios_fornecedor','retencoes_tributarias','condicoes_pagamento','responsavel_aprovacao_servico','contato_financeiro_nome','contato_financeiro_email','contato_financeiro_telefone'] as $campo) unset($c_cliente[$campo]);
                        }
                        if (!$pode_restritos && !$eh_dono && !$pode_financeiro) {
                            foreach (['clausula_tecnica','codigo_sistema','arquivo_path'] as $campo) unset($c_cliente[$campo]);
                        }
                        $c_cliente['divergencias'] = $divergencias_por_contrato[(int) $c['id']] ?? [];
                        $c_cliente['campos_pendentes'] = array_keys(array_filter([
                            'fornecedor'=>in_array('Fornecedor',$pendentes,true),'cnpj'=>in_array('CNPJ do fornecedor',$pendentes,true),
                            'servico_objeto'=>in_array('Objeto / serviço',$pendentes,true),'setor'=>in_array('Setor responsável',$pendentes,true),
                            'empresa'=>in_array('Empresa contratante',$pendentes,true),'cnpj_empresa_contratante'=>in_array('CNPJ da contratante',$pendentes,true),
                            'data_inicio'=>in_array('Início da vigência',$pendentes,true),'tipo_prazo'=>in_array('Tipo de prazo',$pendentes,true),
                            'tipo_pagamento'=>in_array('Tipo de pagamento',$pendentes,true),'forma_pagamento'=>in_array('Forma de pagamento',$pendentes,true),
                            'arquivo_contrato'=>in_array('Contrato em PDF',$pendentes,true),'data_vencimento'=>in_array('Data final',$pendentes,true),
                            'valor'=>in_array('Valor total',$pendentes,true),'valor_parcela'=>in_array('Valor mensal',$pendentes,true),
                            'quantidade_parcelas'=>in_array('Quantidade de parcelas',$pendentes,true),'periodicidade'=>in_array('Periodicidade',$pendentes,true),
                            'dia_vencimento'=>in_array('Dia do vencimento',$pendentes,true),'possui_reajuste'=>in_array('Reajuste',$pendentes,true),
                            'indice_reajuste'=>in_array('Índice de reajuste',$pendentes,true),'periodicidade_reajuste'=>in_array('Periodicidade do reajuste',$pendentes,true),
                            'mes_base_reajuste'=>in_array('Mês-base do reajuste',$pendentes,true),'possui_aviso_cancelamento'=>in_array('Aviso prévio',$pendentes,true),
                            'possui_multa'=>in_array('Multa',$pendentes,true),'possui_carencia'=>in_array('Carência',$pendentes,true),
                        ]));
                        $situacoes = [];
                        $status_fluxo = $c['status_fluxo'] ?? 'RASCUNHO';
                        if ($tem_div || $status_fluxo === 'COM_DIVERGENCIA') $situacoes[] = 'divergencia';
                        if ($status_fluxo === 'RASCUNHO') $situacoes[] = 'andamento';
                        if ($status_fluxo === 'AGUARDANDO_FINANCEIRO') $situacoes[] = 'aguardando_financeiro';
                        if ($status_fluxo === 'CONFIRMADO' && !$tem_div) $situacoes[] = 'confirmado';
                        if (in_array($alerta['situacao'], ['vencendo', 'vencido'], true)) $situacoes[] = $alerta['situacao'];
                        if ($pendentes) $situacoes[] = 'incompleto';
                    ?>
                    <tr class="linha-contrato group hover:bg-blue-50/40 transition-colors"
                        data-nome="<?php echo htmlspecialchars(mb_strtolower($c['fornecedor'] . ' ' . $c['servico_objeto'] . ' ' . $c['cnpj'])); ?>"
                        data-setor="<?php echo htmlspecialchars($c['setor']); ?>"
                        data-situacao="<?php echo htmlspecialchars(implode(' ', $situacoes)); ?>"
                        data-fornecedor="<?php echo htmlspecialchars(mb_strtolower($c['fornecedor'])); ?>"
                        data-valor="<?php echo (float) ($c['valor_parcela'] ?? 0); ?>"
                        data-vigencia="<?php echo htmlspecialchars($c['data_vencimento'] ?: '9999-12-31'); ?>">
                        <td class="px-2 py-3 min-w-0 break-words">
                            <p class="font-bold text-navy-900"><?php echo htmlspecialchars($c['fornecedor']); ?></p>
                            <p class="text-slate-400 truncate" title="<?php echo htmlspecialchars($c['servico_objeto']); ?>"><?php echo htmlspecialchars($c['servico_objeto']); ?></p>
                            <?php if ($tem_div): ?><span class="inline-block mt-1 text-[9px] font-black uppercase text-rose-700">⚠ Divergência</span><?php endif; ?>
                        </td>
                        <?php if ($eh_admin): ?>
                        <td class="px-2 py-3 break-words"><span class="bg-slate-100 text-slate-700 text-[9px] font-black uppercase px-1.5 py-1 rounded-lg"><?php echo htmlspecialchars($c['setor']); ?></span></td>
                        <?php endif; ?>
                        <td class="px-2 py-3 text-right break-words font-black <?php echo $c['valor_parcela'] === null || $c['valor_parcela'] === '' ? 'text-rose-600' : 'text-navy-900'; ?>"><?php echo $c['valor_parcela'] === null || $c['valor_parcela'] === '' ? 'Pendente' : 'R$ ' . number_format((float) $c['valor_parcela'], 2, ',', '.') . (($c['tipo_pagamento'] ?? '') === 'RECORRENTE_MENSAL' ? '/mês' : ''); ?></td>
                        <td class="px-2 py-3 break-words"><p class="font-bold text-slate-700"><?php echo htmlspecialchars($c['periodicidade'] ?: '—'); ?></p><p class="text-[9px] text-slate-400"><?php echo htmlspecialchars($c['forma_pagamento'] ?: '—'); ?></p></td>
                        <td class="px-2 py-3 text-right break-words"><?php echo ($c['tipo_prazo'] ?? '') === 'INDETERMINADO' ? '—' : 'R$ ' . number_format((float) ($c['valor'] ?? 0), 2, ',', '.'); ?></td>
                        <td class="px-2 py-3 text-center"><?php echo ($c['tipo_prazo'] ?? '') === 'INDETERMINADO' ? '—' : (int) ($c['quantidade_parcelas'] ?? 0); ?></td>
                        <td class="px-2 py-3 break-words"><p class="font-bold"><?php echo !empty($c['data_inicio']) ? (new DateTime($c['data_inicio']))->format('d/m/Y') : '—'; ?></p><p class="text-[9px] text-slate-400"><?php echo !empty($c['prazo_indeterminado']) ? 'Indeterminado' : (!empty($c['data_vencimento']) ? 'até ' . (new DateTime($c['data_vencimento']))->format('d/m/Y') : 'Final pendente'); ?></p></td>
                        <td class="px-2 py-3 break-words"><?php echo ($c['possui_aviso_cancelamento'] ?? '') === 'SIM' ? (int) $c['prazo_comunicacao_cancelamento'] . ' dias' : (($c['possui_aviso_cancelamento'] ?? '') === 'NAO' ? 'Não possui' : '—'); ?></td>
                        <td class="px-2 py-3 break-words"><?php if ((string) ($c['possui_reajuste'] ?? '') === '1'): ?><p class="font-bold text-emerald-700"><?php echo htmlspecialchars(($c['indice_reajuste'] ?? '') === 'OUTRO' ? ($c['indice_reajuste_outro'] ?: 'Outro') : ($c['indice_reajuste'] ?: '—')); ?></p><p class="text-[9px] text-slate-400"><?php echo htmlspecialchars($c['periodicidade_reajuste'] ?: '—'); ?></p><?php elseif ((string) ($c['possui_reajuste'] ?? '') === '0'): ?>Não possui<?php else: ?>—<?php endif; ?></td>
                        <td class="px-2 py-3 break-words leading-tight" title="<?php echo htmlspecialchars($c['multa_contratual'] ?? ''); ?>"><?php echo ($c['possui_multa'] ?? '') === 'SIM' ? htmlspecialchars($c['multa_contratual'] ?: 'Pendente') : (($c['possui_multa'] ?? '') === 'NAO' ? 'Não possui' : '—'); ?></td>
                        <td class="px-2 py-3 break-words leading-tight" title="<?php echo htmlspecialchars($c['carencia_contratual'] ?? ''); ?>"><?php echo ($c['possui_carencia'] ?? '') === 'SIM' ? htmlspecialchars($c['carencia_contratual'] ?: 'Pendente') : (($c['possui_carencia'] ?? '') === 'NAO' ? 'Não possui' : '—'); ?></td>
                        <td class="px-2 py-3 break-words" data-status-ordenacao="<?php echo htmlspecialchars($status_fluxo); ?>">
                            <span class="bg-<?php echo $alerta['cor']; ?>-100 text-<?php echo $alerta['cor']; ?>-700 text-xs font-bold px-2 py-1 rounded-full"><?php echo $alerta['texto']; ?></span>
                            <p class="text-[11px] text-slate-500 mt-1 font-bold"><?php echo htmlspecialchars(ucfirst(mb_strtolower(str_replace('_', ' ', $c['status_fluxo'] ?? 'RASCUNHO'), 'UTF-8'))); ?></p>
                            <?php if ($pendentes): ?><span title="<?php echo htmlspecialchars(implode(', ', $pendentes)); ?>" class="inline-block mt-1 bg-rose-100 text-rose-700 text-[10px] font-black uppercase px-2 py-1 rounded-full"><?php echo count($pendentes); ?> pendência(s)</span><?php endif; ?>
                        </td>
                        <td class="px-2 py-3 text-right sticky right-0 bg-white group-hover:bg-blue-50">
                            <div class="relative menu-gerenciamento">
                            <button type="button" onclick="alternarMenuGerenciamento(event, 'menu-contrato-<?php echo (int) $c['id']; ?>')" class="inline-flex items-center gap-1 bg-navy-900 text-white text-[10px] font-black px-2.5 py-2 rounded-lg shadow-sm hover:bg-navy-800">
                                Gerenciar <span aria-hidden="true">▾</span>
                            </button>
                            <div id="menu-contrato-<?php echo (int) $c['id']; ?>" class="menu-gerenciamento-opcoes hidden fixed z-[70] w-56 bg-white border border-slate-200 rounded-2xl shadow-xl p-2 text-left">
                            <?php if ($pode_editar_este): ?>
                                <button onclick='fecharMenusGerenciamento(); abrirDetalhesEPendencias(<?php echo json_encode($c_cliente, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)' class="w-full text-left text-xs font-black <?php echo $pendentes ? 'text-rose-700 bg-rose-50 hover:bg-rose-100' : 'text-slate-700 hover:bg-slate-50'; ?> px-3 py-2.5 rounded-xl">Ver detalhes<?php echo $pendentes ? ' e ' . count($pendentes) . ' pendência(s)' : ''; ?></button>
                            <?php else: ?>
                                <button onclick='fecharMenusGerenciamento(); abrirDetalhes(<?php echo json_encode($c_cliente, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>, <?php echo $eh_responsavel ? "true" : "false"; ?>)' class="w-full text-left text-xs font-bold text-slate-700 px-3 py-2.5 rounded-xl hover:bg-slate-50">Ver detalhes</button>
                            <?php endif; ?>

                            <?php if ($pode_compartilhar && ($status_fluxo === 'RASCUNHO' || $status_fluxo === 'COM_DIVERGENCIA')): ?>
                                <button onclick="fecharMenusGerenciamento(); compartilharContrato(<?php echo $c['id']; ?>)" class="w-full text-left text-xs font-bold text-blue-700 px-3 py-2.5 rounded-xl hover:bg-blue-50">Compartilhar com Contas a Pagar</button>
                            <?php endif; ?>

                            <?php if ($pode_confirmar && ($etapa === 5 || $tem_div)): ?>
                                <button onclick="fecharMenusGerenciamento(); confirmarUso(<?php echo $c['id']; ?>, <?php echo $tem_div ? 'true' : 'false'; ?>)" class="w-full text-left text-xs font-bold text-emerald-700 px-3 py-2.5 rounded-xl hover:bg-emerald-50"><?php echo $tem_div ? 'Confirmar correção' : 'Confirmar uso'; ?></button>
                            <?php endif; ?>

                            <?php if ($pode_divergir && $etapa >= 5 && !$tem_div): ?>
                                <button onclick="fecharMenusGerenciamento(); abrirDivergencia(<?php echo $c['id']; ?>)" class="w-full text-left text-xs font-bold text-rose-700 px-3 py-2.5 rounded-xl hover:bg-rose-50">Registrar divergência</button>
                            <?php endif; ?>

                            </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <p id="resumo-paginacao" class="text-slate-500"></p>
            <div class="flex items-center gap-2">
                <label class="text-slate-500">Exibir <select id="itens-por-pagina" class="border border-slate-200 rounded-lg px-2 py-1 bg-white"><option>20</option><option>50</option><option>100</option></select></label>
                <button type="button" id="pagina-anterior" class="border border-slate-200 rounded-lg px-3 py-1.5 font-bold disabled:opacity-40">Anterior</button>
                <span id="pagina-atual" class="font-bold text-navy-900"></span>
                <button type="button" id="proxima-pagina" class="border border-slate-200 rounded-lg px-3 py-1.5 font-bold disabled:opacity-40">Próxima</button>
            </div>
        </div>
        </div>
    </div>
</main>

<!-- MODAL CENTRAL DE DETALHES -->
<div id="slideover-detalhes" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/30" onclick="fecharDetalhes()"></div>
    <div class="relative w-full max-w-4xl max-h-[90vh] bg-white rounded-2xl shadow-2xl overflow-y-auto p-6">
        <div class="flex justify-between items-start mb-4">
            <h3 id="det-titulo" class="text-xl font-black text-navy-900"></h3>
            <button onclick="fecharDetalhes()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
        </div>
        <div id="det-conteudo" class="space-y-4 text-sm"></div>
    </div>
</div>

<!-- MODAL DE ATUALIZAÇÃO DOS DADOS CONTRATUAIS -->
<div id="modal-atualizar-financeiro" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/30 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
        <div class="flex justify-between items-start mb-4">
            <div><h3 class="text-lg font-black text-navy-900">Atualizar dados contratuais</h3><p class="text-xs text-slate-400 mt-1">Complete os campos pendentes destacados na visualização do contrato.</p></div>
            <button type="button" onclick="fecharAtualizacaoFinanceiro()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
        </div>
        <form id="form-atualizar-financeiro" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="contrato_id">
            <label class="text-xs font-bold text-slate-500 uppercase">Nome Fantasia *<input required name="nome_fantasia" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></label>
            <label class="text-xs font-bold text-slate-500 uppercase">Código do Sistema *<input required name="codigo_sistema" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></label>
            <label class="text-xs font-bold text-slate-500 uppercase">Contato do Fornecedor — Nome *<input required name="contato_fornecedor_nome" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></label>
            <label class="text-xs font-bold text-slate-500 uppercase">Contato do Fornecedor — Telefone *<input required name="contato_fornecedor_telefone" inputmode="tel" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></label>
            <label class="text-xs font-bold text-slate-500 uppercase">Aviso prévio para cancelamento (dias) *<input required type="number" min="0" name="prazo_comunicacao_cancelamento" placeholder="Ex: 60" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></label>
            <label class="text-xs font-bold text-slate-500 uppercase">Renovação Automática *<select required name="renovacao_automatica" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"><option value="">Selecione</option><option value="1">Sim</option><option value="0">Não</option></select></label>
            <label class="text-xs font-bold text-slate-500 uppercase">Valor por Pagamento/Parcela *<input required name="valor_parcela" inputmode="numeric" placeholder="R$ 0,00" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></label>
            <label class="text-xs font-bold text-slate-500 uppercase">Multa Contratual *<input required name="multa_contratual" placeholder="Ex: 10% do saldo contratual" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></label>
            <label class="text-xs font-bold text-slate-500 uppercase">Carência Contratual *<input required name="carencia_contratual" placeholder="Ex: Sem carência" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></label>
            <label class="md:col-span-2 text-xs font-bold text-slate-500 uppercase">Cláusula Técnica / Regra de Cancelamento *<textarea required name="clausula_tecnica" rows="3" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case"></textarea></label>
            <div class="md:col-span-2 flex justify-end gap-2 pt-2"><button type="button" onclick="fecharAtualizacaoFinanceiro()" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500">Cancelar</button><button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-navy-900">Salvar atualização</button></div>
        </form>
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

<!-- FORMULÁRIO ÚNICO NOVO / EDITAR CONTRATO -->
<div id="modal-wizard" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto">
        <form id="form-contrato" method="POST" action="api/ContratoController.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="acao" value="salvar_contrato">
            <input type="hidden" name="modo_salvamento" id="w-modo-salvamento" value="RASCUNHO">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="contrato_id" id="w-contrato-id">
            <input type="hidden" name="arquivo_atual" id="w-arquivo-atual">
            <!-- Campos antigos preservados apenas para não apagar dados de contratos já cadastrados. -->
            <input type="hidden" name="numero_contrato">
            <input type="hidden" name="multa_carencia">
            <input type="hidden" name="indices_reajuste">
            <input type="hidden" name="centro_custo">
            <input type="hidden" name="dados_bancarios_fornecedor">
            <input type="hidden" name="retencoes_tributarias">
            <input type="hidden" name="condicoes_pagamento">
            <input type="hidden" name="responsavel_aprovacao_servico">
            <input type="hidden" name="contato_financeiro_nome">
            <input type="hidden" name="contato_financeiro_email">
            <input type="hidden" name="contato_financeiro_telefone">
            <input type="hidden" name="aviso_previo">

            <div class="p-6 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white z-10">
                <div>
                    <h3 id="w-titulo" class="text-lg font-black text-navy-900">Novo Contrato</h3>
                    <p id="w-passo-label" class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-0.5">Preencha somente as informações utilizadas na gestão do contrato</p>
                </div>
                <button type="button" onclick="fecharWizard()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>

            <div class="w-passo p-6 space-y-4" data-passo="1">
                <div><h4 class="font-black text-navy-900">Dados do contrato</h4><p class="text-xs text-slate-400">Identificação, vigência e condição de pagamento.</p></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Fornecedor</label>
                        <input required name="fornecedor" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Nome Fantasia <span class="text-red-500">*</span></label>
                        <input required name="nome_fantasia" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">CNPJ do Fornecedor <span class="text-red-500">*</span></label>
                        <input required name="cnpj" maxlength="18" inputmode="numeric" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Contato do Fornecedor — Nome <span class="text-red-500">*</span></label>
                        <input required name="contato_fornecedor_nome" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Contato do Fornecedor — Telefone <span class="text-red-500">*</span></label>
                        <input required name="contato_fornecedor_telefone" inputmode="tel" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Tipo de Pagamento</label>
                        <select name="tipo_pagamento" id="w-tipo-pagamento" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                            <option value="">Selecione</option><option value="UNICO">Pagamento único</option><option value="PARCELADO">Parcelamento fixo</option>
                        </select>
                    </div>
                    <div id="bloco-periodicidade">
                        <label class="text-xs font-bold text-slate-500 uppercase">Periodicidade <span class="text-red-500">*</span></label>
                        <select required name="periodicidade" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue"><option value="">Selecione</option><option>Mensal</option><option>Quinzenal</option><option>Semanal</option><option>Trimestral</option><option>Semestral</option><option>Anual</option><option>Pagamento único</option><option>Outro</option></select>
                    </div>
                    <div class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Código do Sistema <span class="text-red-500">*</span></label>
                        <input required name="codigo_sistema" placeholder="Se não houver, informe NÃO SE APLICA" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Forma de Pagamento <span class="text-red-500">*</span></label>
                        <select required name="forma_pagamento" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue"><option value="">Selecione</option><option>BOLETO</option><option>CARTÃO</option><option>PIX</option></select>
                    </div>
                    <div id="bloco-valor-total">
                        <label class="text-xs font-bold text-slate-500 uppercase">Valor Contratado (R$) <span class="text-red-500">*</span></label>
                        <input required id="w-valor-total" name="valor" inputmode="numeric" placeholder="R$ 0,00" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue read-only:bg-slate-200 read-only:text-slate-400">
                    </div>
                    <div id="bloco-valor-parcela">
                        <label id="w-label-valor-parcela" class="text-xs font-bold text-slate-500 uppercase">Valor por Pagamento/Parcela (R$)</label>
                        <input required id="w-valor-parcela" name="valor_parcela" inputmode="numeric" placeholder="R$ 0,00" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue read-only:bg-slate-200 read-only:text-slate-500">
                    </div>
                    <div id="bloco-quantidade-parcelas">
                        <label class="text-xs font-bold text-slate-500 uppercase">Quantidade de Pagamentos / Parcelas <span class="text-red-500">*</span></label>
                        <input required id="w-quantidade-parcelas" type="number" min="0" name="quantidade_parcelas" placeholder="Ex: 12" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue read-only:bg-slate-200 read-only:text-slate-400">
                        <p id="w-parcelas-ajuda" class="mt-1 text-xs text-slate-400">Use 0 quando for recorrente sem quantidade final definida.</p>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Objeto / Serviço</label>
                        <input required name="servico_objeto" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Setor</label>
                        <select required name="setor" id="w-setor" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                            <option value="">Selecione o setor</option>
                            <?php foreach ($setores_distintos as $setor_cadastro): ?>
                                <option value="<?php echo htmlspecialchars($setor_cadastro); ?>"
                                    <?php echo mb_strtoupper(trim($setor_cadastro), 'UTF-8') === $setor_usuario ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($setor_cadastro); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Empresa Contratante <span class="text-red-500">*</span></label>
                        <input required name="empresa" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">CNPJ da Empresa Contratante <span class="text-red-500">*</span></label>
                        <input required name="cnpj_empresa_contratante" maxlength="18" inputmode="numeric" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Início da Vigência</label>
                        <input required type="date" name="data_inicio" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div id="bloco-data-vencimento">
                        <label class="text-xs font-bold text-slate-500 uppercase">Vencimento</label>
                        <input required type="date" id="w-data-vencimento" name="data_vencimento" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue disabled:bg-slate-200 disabled:border-slate-300 disabled:text-slate-400 disabled:cursor-not-allowed disabled:opacity-100">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Prazo do Contrato</label>
                        <select name="tipo_prazo" id="w-tipo-prazo" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                            <option value="">Selecione</option><option value="DETERMINADO">Prazo determinado</option><option value="INDETERMINADO">Prazo indeterminado</option>
                        </select>
                    </div>
                    <div id="bloco-dia-vencimento" class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Dia do Vencimento Mensal</label>
                        <input type="number" min="1" max="31" name="dia_vencimento" placeholder="Ex: 10" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue">
                    </div>
                    <div class="col-span-2 hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Cláusula Técnica / Regra de Cancelamento <span class="text-red-500">*</span></label>
                        <textarea required name="clausula_tecnica" rows="2" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue"></textarea>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Anexo do Contrato (PDF) <span class="text-red-500">*</span></label>
                        <input type="file" id="w-arquivo-contrato" name="arquivo_contrato" accept=".pdf" class="mt-1 w-full text-sm">
                        <p id="w-arquivo-ajuda" class="text-xs text-slate-400 mt-1">Pode ficar pendente no rascunho; obrigatório antes do envio.</p>
                    </div>
                </div>
            </div>

            <div class="w-passo px-6 pb-6 space-y-4" data-passo="2">
                <div class="pt-5 border-t border-slate-100"><h4 class="font-black text-navy-900">Reajuste e cancelamento</h4><p class="text-xs text-slate-400">Mostraremos campos adicionais somente quando a resposta for “Sim”.</p></div>
                <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 text-xs text-emerald-800 font-medium">
                    ✅ Estes campos compõem o <strong>Resumo Financeiro</strong> visível ao Contas a Pagar.
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Possui reajuste?</label>
                        <select name="possui_reajuste" id="w-possui-reajuste" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"><option value="">Selecione</option><option value="1">Sim</option><option value="0">Não</option></select>
                    </div>
                    <div id="bloco-indice-reajuste" class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Índice de reajuste</label>
                        <select name="indice_reajuste" id="w-indice-reajuste" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"><option value="">Selecione</option><option>IPCA</option><option>IGP-M</option><option>INPC</option><option>OUTRO</option></select>
                    </div>
                    <div id="bloco-indice-outro" class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Informe o índice</label><input name="indice_reajuste_outro" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div id="bloco-periodicidade-reajuste" class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Periodicidade do reajuste</label><select name="periodicidade_reajuste" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"><option value="">Selecione</option><option value="ANUAL">Anual</option><option value="SEMESTRAL">Semestral</option><option value="OUTRA">Outra</option></select>
                    </div>
                    <div id="bloco-mes-base" class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Mês-base do reajuste</label><select name="mes_base_reajuste" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"><option value="">Selecione</option><?php foreach ([1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'] as $numero_mes=>$nome_mes): ?><option value="<?php echo $numero_mes; ?>"><?php echo $nome_mes; ?></option><?php endforeach; ?></select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Existe aviso prévio para cancelamento?</label>
                        <select name="possui_aviso_cancelamento" id="w-possui-aviso" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"><option value="">Selecione</option><option value="SIM">Sim</option><option value="NAO">Não</option><option value="NAO_INFORMADO">Não informado no contrato</option></select>
                    </div>
                    <div id="bloco-aviso-dias" class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Aviso prévio para cancelamento</label>
                        <div class="mt-1 flex items-center gap-2"><input type="number" min="0" name="prazo_comunicacao_cancelamento" placeholder="60" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"><span class="text-sm font-bold text-slate-500">dias</span></div>
                    </div>
                    <div id="bloco-renovacao-automatica">
                        <label class="text-xs font-bold text-slate-500 uppercase">Renovação Automática <span class="text-red-500">*</span></label>
                        <select required name="renovacao_automatica" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-corporate-blue"><option value="">Selecione</option><option value="1">Sim</option><option value="0">Não</option></select>
                    </div>
                    <div><label class="text-xs font-bold text-slate-500 uppercase">Possui multa contratual?</label><select name="possui_multa" id="w-possui-multa" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"><option value="">Selecione</option><option value="SIM">Sim</option><option value="NAO">Não</option><option value="NAO_INFORMADO">Não informado</option></select></div>
                    <div id="bloco-multa" class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Descrição da multa</label><input name="multa_contratual" placeholder="Ex: 10% do saldo contratual" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div><label class="text-xs font-bold text-slate-500 uppercase">Possui carência?</label><select name="possui_carencia" id="w-possui-carencia" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm"><option value="">Selecione</option><option value="SIM">Sim</option><option value="NAO">Não</option><option value="NAO_INFORMADO">Não informado</option></select></div>
                    <div id="bloco-carencia" class="hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Descrição da carência</label><input name="carencia_contratual" placeholder="Ex: 90 dias" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                    </div>
                </div>
            </div>

            <div class="w-passo px-6 pb-6 space-y-4" data-passo="3">
                <div class="pt-5 border-t border-slate-100"><h4 class="font-black text-navy-900">Finalizar cadastro</h4><p class="text-xs text-slate-400">Salve como rascunho ou envie o cadastro completo ao Contas a Pagar.</p></div>
                <div class="bg-red-50 border border-red-100 rounded-xl p-4 text-xs text-red-700">
                    <p class="font-black uppercase tracking-wider mb-2">⚠ Informações restritas — NÃO compartilhar</p>
                    <p class="leading-relaxed">Estratégias comerciais, histórico de negociações e cláusulas de confidencialidade não devem ir para campos públicos.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input required type="checkbox" id="w-confirma" class="w-4 h-4">
                    <label for="w-confirma" class="text-sm font-bold text-slate-600">Confirmo que as informações restritas foram tratadas corretamente.</label>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"><span class="block font-black">Salve quando ainda estiver preenchendo.</span><span class="block text-xs mt-1">O envio ao Contas a Pagar só será liberado quando todas as informações necessárias estiverem completas.</span></div>
            </div>

            <div class="p-6 border-t border-slate-100 flex justify-end sticky bottom-0 bg-white">
                <div class="flex gap-2">
                    <button type="button" onclick="fecharWizard()" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" onclick="definirModoSalvamento('RASCUNHO')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-navy-900 bg-slate-100 hover:bg-slate-200">Salvar rascunho</button>
                    <button type="submit" id="w-btn-salvar" onclick="definirModoSalvamento('ENVIAR_FINANCEIRO')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-navy-900 hover:bg-navy-800">Enviar ao Contas a Pagar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const ETAPAS_LABEL = <?php echo json_encode($ETAPAS, JSON_UNESCAPED_UNICODE); ?>;
const EH_ADMIN      = <?php echo $eh_admin ? 'true' : 'false'; ?>;
const PODE_FINANCEIRO = <?php echo $pode_financeiro ? 'true' : 'false'; ?>;
const PODE_RESTRITOS = <?php echo $pode_restritos ? 'true' : 'false'; ?>;
const PODE_BAIXAR = <?php echo $pode_baixar ? 'true' : 'false'; ?>;
const CSRF_TOKEN = <?php echo json_encode($csrf_token); ?>;
const SETOR_USUARIO = <?php echo json_encode($setor_usuario, JSON_UNESCAPED_UNICODE); ?>;

function abrirWizard() {
    document.getElementById('form-contrato').reset();
    document.getElementById('w-contrato-id').value = '';
    document.getElementById('w-arquivo-atual').value = '';
    document.getElementById('w-titulo').innerText = 'Novo Contrato';
    document.getElementById('w-arquivo-contrato').required = true;
    document.getElementById('w-arquivo-ajuda').textContent = 'Pode ficar pendente no rascunho; obrigatório antes do envio.';
    atualizarVencimento();
    atualizarParcelas();
    limparDestaquesPendencia();
    document.getElementById('modal-wizard').classList.remove('hidden');
    document.getElementById('modal-wizard').classList.add('flex');
}

function editarContrato(c) {
    // O responsável sempre utiliza o editor completo do contrato.
    // A edição resumida permanece exclusiva para o Contas a Pagar.
    document.getElementById('form-contrato').reset();
    document.getElementById('w-contrato-id').value = c.id;
    document.getElementById('w-arquivo-atual').value = c.arquivo_path || '';
    document.getElementById('w-titulo').innerText = 'Editar Contrato';
    document.getElementById('w-arquivo-contrato').required = !c.arquivo_path;
    document.getElementById('w-arquivo-ajuda').textContent = c.arquivo_path
        ? 'Já existe um PDF anexado. Selecione outro somente para substituí-lo.'
        : 'Este contrato ainda não possui PDF; anexe-o para concluir o cadastro.';

    const form = document.getElementById('form-contrato');
    for (const campo in c) {
        const el = form.querySelector(`[name="${campo}"]`);
        if (el && el.type !== 'file' && el.type !== 'checkbox') el.value = c[campo] ?? '';
    }
    formatarCampoMoeda(document.getElementById('w-valor-total'));
    formatarCampoMoeda(document.getElementById('w-valor-parcela'));
    atualizarFormularioDinamico();

    limparDestaquesPendencia();
    document.getElementById('modal-wizard').classList.remove('hidden');
    document.getElementById('modal-wizard').classList.add('flex');
}

function fecharWizard() {
    document.getElementById('modal-wizard').classList.add('hidden');
    document.getElementById('modal-wizard').classList.remove('flex');
}

function limparDestaquesPendencia() {
    document.querySelectorAll('#form-contrato .campo-pendente').forEach(el => {
        el.classList.remove('campo-pendente', 'border-amber-400', 'bg-amber-50', 'ring-2', 'ring-amber-100');
    });
}

function abrirDetalhesEPendencias(c) {
    editarContrato(c);
    const form = document.getElementById('form-contrato');
    const pendentes = (c.campos_pendentes || []).map(campo => form.querySelector(`[name="${campo}"]`)).filter(Boolean);
    pendentes.forEach(el => el.classList.add('campo-pendente', 'border-amber-400', 'bg-amber-50', 'ring-2', 'ring-amber-100'));
    document.getElementById('w-titulo').innerText = pendentes.length ? 'Detalhes e pendências do contrato' : 'Detalhes do contrato';
    document.getElementById('w-passo-label').innerText = pendentes.length
        ? `${pendentes.length} campo(s) amarelo(s) precisam ser preenchidos`
        : 'Cadastro completo — revise ou atualize qualquer informação abaixo';
    if (pendentes[0]) setTimeout(() => pendentes[0].scrollIntoView({behavior: 'smooth', block: 'center'}), 150);
}

document.getElementById('form-contrato').addEventListener('submit', function (e) {
    const enviando = document.getElementById('w-modo-salvamento').value === 'ENVIAR_FINANCEIRO';
    if (enviando && !document.getElementById('w-confirma').checked) {
        e.preventDefault();
        alert('Confirme que nenhuma informação restrita foi incluída antes de salvar.');
        return;
    }
    document.getElementById('w-tipo-pagamento').disabled = false;
});

function definirModoSalvamento(modo) {
    document.getElementById('w-modo-salvamento').value = modo;
}

function formatarCnpj(valor) {
    return valor.replace(/\D/g, '').slice(0, 14)
        .replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
}

function formatarTelefone(valor) {
    const digitos = valor.replace(/\D/g, '').slice(0, 11);
    return digitos.length > 10
        ? digitos.replace(/^(\d{2})(\d{5})(\d{0,4})$/, '($1) $2-$3')
        : digitos.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
}

const campoCnpj = document.querySelector('[name="cnpj"]');
const campoCnpjContratante = document.querySelector('[name="cnpj_empresa_contratante"]');
const campoTelefone = document.querySelector('[name="contato_financeiro_telefone"]');
const campoTelefoneFornecedor = document.querySelector('[name="contato_fornecedor_telefone"]');
if (campoCnpj) campoCnpj.addEventListener('input', e => e.target.value = formatarCnpj(e.target.value));
if (campoCnpjContratante) campoCnpjContratante.addEventListener('input', e => e.target.value = formatarCnpj(e.target.value));
if (campoTelefone) campoTelefone.addEventListener('input', e => e.target.value = formatarTelefone(e.target.value));
if (campoTelefoneFornecedor) campoTelefoneFornecedor.addEventListener('input', e => e.target.value = formatarTelefone(e.target.value));
document.querySelectorAll('[name="numero_contrato"], [name="codigo_sistema"], [name="centro_custo"]').forEach(el => {
    el.addEventListener('input', e => e.target.value = e.target.value.toLocaleUpperCase('pt-BR'));
});

function atualizarVencimento() {
    const tipoPrazo = document.getElementById('w-tipo-prazo').value;
    const indeterminado = tipoPrazo === 'INDETERMINADO';
    const determinado = tipoPrazo === 'DETERMINADO';
    const vencimento = document.getElementById('w-data-vencimento');
    document.getElementById('bloco-data-vencimento').classList.toggle('hidden', !determinado);
    document.getElementById('bloco-renovacao-automatica').classList.toggle('hidden', !determinado);
    vencimento.required = determinado;
    vencimento.disabled = !determinado;
    vencimento.setAttribute('aria-disabled', indeterminado ? 'true' : 'false');
    if (indeterminado) vencimento.value = '';
    if (indeterminado) document.getElementById('w-tipo-pagamento').value = 'RECORRENTE_MENSAL';
    atualizarCalculoPagamento();
}

function atualizarParcelas() {
    atualizarCalculoPagamento();
}

function valorNumericoMoeda(valor) {
    const texto = String(valor || '').replace(/[^0-9,.-]/g, '');
    if (texto.includes(',')) return Number(texto.replaceAll('.', '').replace(',', '.')) || 0;
    return Number(texto) || 0;
}

function formatarCampoMoeda(campo, valor = null) {
    const numero = valor === null ? valorNumericoMoeda(campo.value) : Number(valor);
    campo.value = numero > 0 ? numero.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}) : '';
}

function atualizarCalculoPagamento() {
    const indeterminado = document.getElementById('w-tipo-prazo').value === 'INDETERMINADO';
    const tipo = indeterminado ? 'RECORRENTE_MENSAL' : document.getElementById('w-tipo-pagamento').value;
    const total = document.getElementById('w-valor-total');
    const parcela = document.getElementById('w-valor-parcela');
    const quantidade = document.getElementById('w-quantidade-parcelas');
    const ajuda = document.getElementById('w-parcelas-ajuda');

    document.getElementById('w-tipo-pagamento').disabled = indeterminado;
    document.getElementById('bloco-valor-total').classList.toggle('hidden', tipo === '' || tipo === 'RECORRENTE_MENSAL');
    document.getElementById('bloco-valor-parcela').classList.toggle('hidden', tipo === '');
    document.getElementById('bloco-quantidade-parcelas').classList.toggle('hidden', tipo !== 'PARCELADO');
    document.getElementById('bloco-periodicidade').classList.toggle('hidden', tipo !== 'PARCELADO');
    document.getElementById('bloco-dia-vencimento').classList.toggle('hidden', tipo !== 'RECORRENTE_MENSAL');
    document.getElementById('w-label-valor-parcela').textContent = tipo === 'RECORRENTE_MENSAL' ? 'Valor mensal atual (R$)' : 'Valor por pagamento/parcela (R$)';
    if (tipo === 'RECORRENTE_MENSAL') {
        total.required = false;
        total.readOnly = true;
        total.value = '';
        quantidade.readOnly = true;
        quantidade.min = '0';
        quantidade.value = '0';
        parcela.readOnly = false;
        parcela.required = true;
        ajuda.textContent = 'Informe somente o valor mensal atual; não existe total nem quantidade final.';
        return;
    }

    total.required = true;
    total.readOnly = false;
    quantidade.required = tipo === 'PARCELADO';
    quantidade.readOnly = tipo !== 'PARCELADO';
    quantidade.min = tipo === 'PARCELADO' ? '2' : '1';
    if (tipo === 'UNICO') quantidade.value = '1';
    parcela.required = true;
    parcela.readOnly = true;
    ajuda.textContent = tipo === 'UNICO' ? 'Pagamento único: o valor do pagamento é igual ao total.' : 'Parcela calculada automaticamente: valor total ÷ quantidade.';
    const qtd = Number(quantidade.value || 0);
    const valorTotal = valorNumericoMoeda(total.value);
    formatarCampoMoeda(parcela, tipo === 'UNICO' ? valorTotal : (qtd > 0 ? valorTotal / qtd : 0));
}

function atualizarRegrasContratuais() {
    const reajuste = document.getElementById('w-possui-reajuste').value === '1';
    ['bloco-indice-reajuste','bloco-periodicidade-reajuste','bloco-mes-base'].forEach(id => document.getElementById(id).classList.toggle('hidden', !reajuste));
    document.getElementById('bloco-indice-outro').classList.toggle('hidden', !reajuste || document.getElementById('w-indice-reajuste').value !== 'OUTRO');
    document.getElementById('bloco-aviso-dias').classList.toggle('hidden', document.getElementById('w-possui-aviso').value !== 'SIM');
    document.getElementById('bloco-multa').classList.toggle('hidden', document.getElementById('w-possui-multa').value !== 'SIM');
    document.getElementById('bloco-carencia').classList.toggle('hidden', document.getElementById('w-possui-carencia').value !== 'SIM');
}
function atualizarFormularioDinamico() { atualizarVencimento(); atualizarRegrasContratuais(); }

document.getElementById('w-tipo-prazo').addEventListener('change', atualizarFormularioDinamico);
document.getElementById('w-tipo-pagamento').addEventListener('change', atualizarCalculoPagamento);
document.getElementById('w-quantidade-parcelas').addEventListener('input', atualizarCalculoPagamento);
['w-possui-reajuste','w-indice-reajuste','w-possui-aviso','w-possui-multa','w-possui-carencia'].forEach(id => document.getElementById(id).addEventListener('change', atualizarRegrasContratuais));
atualizarFormularioDinamico();

document.querySelectorAll('[name="valor"], [name="valor_parcela"]').forEach(campo => campo.addEventListener('input', e => {
    const centavos = e.target.value.replace(/\D/g, '').slice(0, 15);
    e.target.value = centavos ? (Number(centavos) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '';
    if (e.target.id === 'w-valor-total') atualizarCalculoPagamento();
}));

// =====================================================================
// SLIDE-OVER DE DETALHES (COM AS REGRAS DE VISIBILIDADE)
// =====================================================================
let contratoDetalheAtual = null;
function abrirDetalhes(c, ehDono) {
    contratoDetalheAtual = c;
    document.getElementById('det-titulo').innerText = c.fornecedor;
    const statusFluxo = String(c.status_fluxo || 'RASCUNHO').replaceAll('_', ' ');
    let html = `<div class="bg-slate-50 rounded-xl p-3 mb-4">
        <p class="text-xs font-black uppercase text-slate-400">Status do contrato</p>
        <p class="font-bold text-navy-900">${escaparHtml(statusFluxo)}</p>
    </div>`;

    const podeVerTudo = EH_ADMIN || ehDono || PODE_RESTRITOS;
    const podeVerFinanceiro = podeVerTudo || (PODE_FINANCEIRO && Number(c.etapa_atual) >= 5);
    const visaoExclusivaFinanceiro = PODE_FINANCEIRO && !podeVerTudo;
    const visaoResumoContratual = visaoExclusivaFinanceiro || ehDono;

    if (visaoResumoContratual && podeVerFinanceiro) {
        const campoFinanceiro = (label, valor) => campoDetalhe(label, valor, true);
        const prazoPeriodicidade = [c.periodicidade, c.condicoes_pagamento].filter(Boolean).join(' · ');
        const indeterminado = Number(c.prazo_indeterminado) === 1 || c.tipo_prazo === 'INDETERMINADO';
        const dataFinal = indeterminado ? 'Prazo indeterminado' : formatarDataDetalhe(c.data_vencimento);
        const legado = !c.multa_contratual ? c.multa_carencia : '';
        const avisoCancelamento = c.possui_aviso_cancelamento === 'NAO' ? 'Não possui'
            : (c.prazo_comunicacao_cancelamento !== null && c.prazo_comunicacao_cancelamento !== '' ? `${c.prazo_comunicacao_cancelamento} dias antes` : '');
        const reajuste = String(c.possui_reajuste ?? '') === '0' ? 'Não possui'
            : (String(c.possui_reajuste ?? '') === '1'
                ? [c.indice_reajuste === 'OUTRO' ? c.indice_reajuste_outro : c.indice_reajuste, c.periodicidade_reajuste].filter(Boolean).join(' · ')
                : '');
        const multa = c.possui_multa === 'NAO' ? 'Não possui' : c.multa_contratual;
        const carencia = c.possui_carencia === 'NAO' ? 'Não possui' : c.carencia_contratual;

        html += `<div class="grid grid-cols-1 md:grid-cols-2 gap-4">`
              + campoFinanceiro('Razão Social do Fornecedor', c.fornecedor)
              + campoFinanceiro('Serviço Contratado', c.servico_objeto)
              + campoFinanceiro('CNPJ do Fornecedor', c.cnpj)
              + campoFinanceiro(indeterminado ? 'Valor Mensal Atual' : 'Valor por Pagamento / Parcela', formatarMoedaDetalhe(c.valor_parcela))
              + (indeterminado ? '' : campoFinanceiro('Valor Total do Contrato', formatarMoedaDetalhe(c.valor)))
              + campoFinanceiro('Prazo / Periodicidade', prazoPeriodicidade)
              + (indeterminado ? '' : campoFinanceiro('Quantidade de Pagamentos / Parcelas', c.quantidade_parcelas))
              + campoFinanceiro('Início da Vigência', formatarDataDetalhe(c.data_inicio))
              + campoFinanceiro('Aviso Prévio para Cancelamento', avisoCancelamento)
              + campoFinanceiro('Data Final', dataFinal)
              + (indeterminado ? '' : campoFinanceiro('Renovação Automática', c.renovacao_automatica === null || c.renovacao_automatica === '' ? '' : (Number(c.renovacao_automatica) ? 'Sim' : 'Não')))
              + campoFinanceiro('Reajuste', reajuste)
              + campoFinanceiro('Multa Contratual', multa)
              + campoFinanceiro('Carência Contratual', carencia)
              + (legado ? campoDetalhe('Condição Contratual Anterior', legado) : '')
              + campoFinanceiro('Empresa Contratante', c.empresa)
              + campoFinanceiro('Departamento / Setor Contratante', c.setor)
              + `</div>`;
        if (ehDono) {
            html += `<button type="button" onclick="fecharDetalhes(); editarContrato(contratoDetalheAtual)" class="w-full mt-4 bg-blue-50 text-blue-800 border border-blue-200 font-bold text-sm py-2.5 rounded-xl hover:bg-blue-100">✏ Editar contrato completo</button>`;
        } else {
            html += `<div class="mt-4 bg-blue-50 text-blue-800 border border-blue-200 font-medium text-sm p-3 rounded-xl">O Contas a Pagar confere as informações. Se algo estiver incorreto, registre uma divergência para o responsável corrigir.</div>`;
        }
        if (PODE_BAIXAR) {
            html += `<a href="api/ContratoDownload.php?id=${Number(c.id)}" class="block text-center bg-navy-900 text-white font-bold text-sm py-2.5 rounded-xl mt-4 hover:bg-navy-800 transition-colors">📎 Baixar contrato anexado</a>`;
        }
    } else {

    // BLOCO 1: DADOS BÁSICOS (Visível ao Financeiro e Dono)
    if (podeVerFinanceiro || podeVerTudo) {
        html += campoDetalhe('Objeto/Serviço', c.servico_objeto)
              + campoDetalhe('Nome Fantasia', c.nome_fantasia)
              + campoDetalhe('Contato do Fornecedor', [c.contato_fornecedor_nome, c.contato_fornecedor_telefone].filter(Boolean).join(' · '))
              + campoDetalhe('CNPJ do Fornecedor', c.cnpj)
              + campoDetalhe('Empresa Contratante', c.empresa)
              + campoDetalhe('CNPJ da Empresa Contratante', c.cnpj_empresa_contratante)
              + campoDetalhe('Nº Contrato', c.numero_contrato)
              + campoDetalhe('Vigência', Number(c.prazo_indeterminado) ? `${c.data_inicio || '—'} — prazo indeterminado` : `${c.data_inicio || '—'} até ${c.data_vencimento || '—'}`)
              + campoDetalhe('Valor por Pagamento / Parcela', formatarMoedaDetalhe(c.valor_parcela))
              + campoDetalhe('Valor Total do Contrato', Number(c.prazo_indeterminado) ? 'Sem valor total definido' : formatarMoedaDetalhe(c.valor));
    }

    // BLOCO 2: DADOS RESTRITOS (SÓ Dono ou Admin + Botão de Download do PDF)
    if (podeVerTudo) {
        html += `<div class="pt-3 mt-3 border-t border-slate-100"><p class="text-xs font-black uppercase text-slate-400 mb-2">Dados Restritos da Área Gestora</p></div>`;
        html += campoDetalhe('Setor', c.setor)
              + campoDetalhe('Empresa', c.empresa)
              + campoDetalhe('Código do Sistema', c.codigo_sistema)
              + campoDetalhe('Cláusula Técnica', c.clausula_tecnica);
              
    }

    if (PODE_BAIXAR) {
        html += `<a href="api/ContratoDownload.php?id=${Number(c.id)}" class="block text-center bg-navy-900 text-white font-bold text-sm py-2.5 rounded-xl mt-3 mb-4 hover:bg-navy-800 transition-colors">📎 Baixar contrato anexado</a>`;
    }

    // BLOCO 3: RESUMO FINANCEIRO (Financeiro e Dono)
    if (podeVerFinanceiro) {
        html += `<div class="pt-3 mt-3 border-t border-slate-100"><p class="text-xs font-black uppercase text-slate-400 mb-2">Resumo Financeiro (Contato Financeiro)</p></div>`;
        html += campoDetalhe('Forma de Pagamento', c.forma_pagamento)
              + campoDetalhe('Quantidade de Parcelas', c.quantidade_parcelas)
              + campoDetalhe('Periodicidade', c.periodicidade)
              + campoDetalhe('Índices de Reajuste', c.indices_reajuste)
              + campoDetalhe('Centro de Custo', c.centro_custo)
              + campoDetalhe('Aviso Prévio para Cancelamento', c.prazo_comunicacao_cancelamento !== null && c.prazo_comunicacao_cancelamento !== '' ? `${c.prazo_comunicacao_cancelamento} dias antes` : '')
              + campoDetalhe('Renovação Automática', c.renovacao_automatica === null || c.renovacao_automatica === '' ? '' : (Number(c.renovacao_automatica) ? 'Sim' : 'Não'))
              + campoDetalhe('Multa Contratual', c.multa_contratual)
              + campoDetalhe('Carência Contratual', c.carencia_contratual)
              + campoDetalhe('Condição Contratual Anterior', !c.multa_contratual ? c.multa_carencia : '')
              + campoDetalhe('Dados Bancários', c.dados_bancarios_fornecedor)
              + campoDetalhe('Retenções Tributárias', c.retencoes_tributarias)
              + campoDetalhe('Condições de Pagamento', c.condicoes_pagamento)
              + campoDetalhe('Responsável pela Aprovação', c.responsavel_aprovacao_servico)
              + campoDetalhe('Contato Financeiro', `${c.contato_financeiro_nome || ''} · ${c.contato_financeiro_email || ''} · ${c.contato_financeiro_telefone || ''}`);
    } else if (!podeVerTudo) {
        html += `<div class="bg-amber-50 text-amber-700 text-sm font-medium rounded-xl p-4 mt-3">Este contrato ainda não foi compartilhado.</div>`;
    }
    }

    if (Array.isArray(c.divergencias) && c.divergencias.length) {
        html += `<div class="pt-3 mt-3 border-t border-slate-100"><p class="text-xs font-black uppercase text-slate-400 mb-2">Divergências e histórico</p></div>`;
        html += c.divergencias.map(div => {
            const aberta = div.status === 'ABERTA';
            return `<div class="rounded-xl border ${aberta ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50'} p-3 mb-2">
                <p class="text-[11px] font-black uppercase ${aberta ? 'text-rose-700' : 'text-emerald-700'}">${aberta ? 'Divergência aberta' : 'Divergência resolvida'}</p>
                <p class="text-sm text-slate-700 mt-1">${escaparHtml(div.descricao)}</p>
            </div>`;
        }).join('');
    }

    document.getElementById('det-conteudo').innerHTML = html;
    document.getElementById('slideover-detalhes').classList.remove('hidden');
    document.getElementById('slideover-detalhes').classList.add('flex');
}

function formatarDataDetalhe(data) {
    if (!data) return '';
    const partes = String(data).slice(0, 10).split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : data;
}

function formatarMoedaDetalhe(valor) {
    if (valor === null || valor === '' || Number.isNaN(Number(valor))) return '';
    return Number(valor).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
}

function escaparHtml(texto) {
    return String(texto ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function campoDetalhe(label, valor, mostrarPendente = false) {
    const escapar = (texto) => String(texto)
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const vazio = valor === null || valor === undefined || String(valor).trim() === '';
    if (vazio && !mostrarPendente) return '';
    return `<div class="rounded-xl ${vazio ? 'border border-amber-200 bg-amber-50 p-3' : ''}"><p class="text-[11px] font-black uppercase tracking-wider ${vazio ? 'text-amber-600' : 'text-slate-400'}">${escapar(label)}</p><p class="font-medium ${vazio ? 'text-amber-700' : 'text-slate-700'}">${vazio ? 'Não informado — precisa ser atualizado' : escapar(valor)}</p></div>`;
}

function abrirAtualizacaoFinanceiro() {
    const c = contratoDetalheAtual;
    if (!c) return;
    const form = document.getElementById('form-atualizar-financeiro');
    ['contrato_id','nome_fantasia','codigo_sistema','contato_fornecedor_nome','contato_fornecedor_telefone','valor_parcela','prazo_comunicacao_cancelamento','renovacao_automatica','multa_contratual','carencia_contratual','clausula_tecnica'].forEach(campo => {
        form.elements[campo].value = c[campo] ?? '';
    });
    document.getElementById('modal-atualizar-financeiro').classList.remove('hidden');
    document.getElementById('modal-atualizar-financeiro').classList.add('flex');
}

function fecharAtualizacaoFinanceiro() {
    document.getElementById('modal-atualizar-financeiro').classList.add('hidden');
    document.getElementById('modal-atualizar-financeiro').classList.remove('flex');
}

document.getElementById('form-atualizar-financeiro').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('acao', 'atualizar_dados_contas_pagar');
    fd.append('csrf_token', CSRF_TOKEN);
    fetch('api/ContratoController.php', {method: 'POST', body: fd}).then(r => r.text()).then(resp => {
        if (resp.trim() === 'sucesso') location.reload();
        else alert(resp);
    });
});

function fecharDetalhes() {
    document.getElementById('slideover-detalhes').classList.add('hidden');
    document.getElementById('slideover-detalhes').classList.remove('flex');
}

// =====================================================================
// AÇÕES DE FLUXO (AJAX)
// =====================================================================
function fecharMenusGerenciamento() {
    document.querySelectorAll('.menu-gerenciamento-opcoes').forEach(menu => menu.classList.add('hidden'));
}

function alternarMenuGerenciamento(event, menuId) {
    event.stopPropagation();
    const menu = document.getElementById(menuId);
    const estavaFechado = menu.classList.contains('hidden');
    fecharMenusGerenciamento();
    if (estavaFechado) {
        menu.classList.remove('hidden');
        const botao = event.currentTarget;
        const retangulo = botao.getBoundingClientRect();
        const largura = 224;
        const esquerda = Math.max(12, Math.min(window.innerWidth - largura - 12, retangulo.right - largura));
        menu.style.left = `${esquerda}px`;
        menu.style.top = `${retangulo.bottom + 6}px`;
        const altura = menu.getBoundingClientRect().height;
        if (retangulo.bottom + altura + 12 > window.innerHeight) {
            menu.style.top = `${Math.max(12, retangulo.top - altura - 6)}px`;
        }
    }
}

document.addEventListener('click', event => {
    if (!event.target.closest('.menu-gerenciamento')) fecharMenusGerenciamento();
});

function postAcao(acao, campos) {
    const fd = new FormData();
    fd.append('acao', acao);
    fd.append('csrf_token', CSRF_TOKEN);
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

function confirmarUso(id, temDivergencia = false) {
    const mensagem = temDivergencia
        ? 'Confirmar que a correção está adequada? A divergência será encerrada como resolvida.'
        : 'Confirmar o recebimento/uso destas informações pelo Contas a Pagar?';
    if (!confirm(mensagem)) return;
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
const selectSituacao = document.getElementById('filtro-situacao');
const corpoTabela  = document.getElementById('corpo-tabela-contratos');
const linhas       = Array.from(document.querySelectorAll('.linha-contrato'));
const itensPorPagina = document.getElementById('itens-por-pagina');
const botaoAnterior = document.getElementById('pagina-anterior');
const botaoProxima = document.getElementById('proxima-pagina');
let paginaAtual = 1;
let colunaOrdenacao = '';
let direcaoOrdenacao = 1;

function linhasFiltradas() {
    const termo = inputBusca.value.toLowerCase().trim();
    const setor = selectSetor ? selectSetor.value.toLowerCase().trim() : '';
    const situacao = selectSituacao.value;
    return linhas.filter(linha => {
        const bateuNome = termo === '' || linha.dataset.nome.includes(termo);
        const bateuSetor = setor === '' || linha.dataset.setor.toLowerCase() === setor;
        const bateuSituacao = situacao === '' || linha.dataset.situacao.split(' ').includes(situacao);
        return bateuNome && bateuSetor && bateuSituacao;
    });
}

function valorOrdenacao(linha, coluna) {
    if (coluna === 'valor') return Number(linha.dataset.valor || 0);
    if (coluna === 'situacao') return linha.dataset.situacao || '';
    return (linha.dataset[coluna] || '').toLocaleLowerCase('pt-BR');
}

function aplicarFiltros() {
    let filtradas = linhasFiltradas();
    if (colunaOrdenacao) {
        filtradas.sort((a, b) => {
            const va = valorOrdenacao(a, colunaOrdenacao);
            const vb = valorOrdenacao(b, colunaOrdenacao);
            return (typeof va === 'number' ? va - vb : String(va).localeCompare(String(vb), 'pt-BR', {numeric: true})) * direcaoOrdenacao;
        });
        filtradas.forEach(linha => corpoTabela.appendChild(linha));
    }

    const porPagina = Number(itensPorPagina.value || 20);
    const totalPaginas = Math.max(1, Math.ceil(filtradas.length / porPagina));
    paginaAtual = Math.min(paginaAtual, totalPaginas);
    const inicio = (paginaAtual - 1) * porPagina;
    const visiveis = new Set(filtradas.slice(inicio, inicio + porPagina));
    linhas.forEach(linha => linha.style.display = visiveis.has(linha) ? '' : 'none');

    const primeiro = filtradas.length ? inicio + 1 : 0;
    const ultimo = Math.min(inicio + porPagina, filtradas.length);
    document.getElementById('resumo-paginacao').textContent = `Exibindo ${primeiro}–${ultimo} de ${filtradas.length} contrato(s)`;
    document.getElementById('pagina-atual').textContent = `Página ${paginaAtual} de ${totalPaginas}`;
    botaoAnterior.disabled = paginaAtual <= 1;
    botaoProxima.disabled = paginaAtual >= totalPaginas;
}

function reiniciarFiltros() { paginaAtual = 1; aplicarFiltros(); }
inputBusca.addEventListener('input', reiniciarFiltros);
if (selectSetor) selectSetor.addEventListener('change', reiniciarFiltros);
selectSituacao.addEventListener('change', reiniciarFiltros);
itensPorPagina.addEventListener('change', reiniciarFiltros);
botaoAnterior.addEventListener('click', () => { if (paginaAtual > 1) { paginaAtual--; aplicarFiltros(); } });
botaoProxima.addEventListener('click', () => { paginaAtual++; aplicarFiltros(); });
document.querySelectorAll('.ordenar-coluna').forEach(botao => botao.addEventListener('click', () => {
    const coluna = botao.dataset.coluna;
    direcaoOrdenacao = colunaOrdenacao === coluna ? direcaoOrdenacao * -1 : 1;
    colunaOrdenacao = coluna;
    paginaAtual = 1;
    aplicarFiltros();
}));
aplicarFiltros();
</script>

<?php include 'includes/footer.php'; ?>
