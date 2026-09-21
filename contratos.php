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

$auth           = new ContratoAuth($pdo_intra, (int) $user_id_sessao, $eh_admin);
// O setor exibido/usado pelo módulo agora vem do vínculo grupo -> setor da intranet.
$setor_usuario  = $auth->setorUsuario();
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

$visao_encerrados = (($_GET['visao'] ?? '') === 'encerrados');

// Encerrados são arquivados logicamente: não poluem a lista operacional,
// mas continuam disponíveis para consulta e auditoria.
$stmt_encerrados = $pdo_intra->prepare("
    SELECT COUNT(*)
      FROM contratos c
     WHERE {$filtroContratos}
       AND COALESCE(c.status, 'ATIVO') = 'ENCERRADO'
");
$stmt_encerrados->execute($paramsContratos);
$total_encerrados = (int) $stmt_encerrados->fetchColumn();

$condicao_status_lista = $visao_encerrados
    ? "COALESCE(c.status, 'ATIVO') = 'ENCERRADO'"
    : "COALESCE(c.status, 'ATIVO') <> 'ENCERRADO'";

$stmt = $pdo_intra->prepare("
    SELECT c.*
      FROM contratos c
     WHERE {$filtroContratos}
       AND {$condicao_status_lista}
     ORDER BY c.data_vencimento ASC
");
$stmt->execute($paramsContratos);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$anexos_por_contrato = [];
if ($contratos) {
    $ids_anexos = array_map('intval', array_column($contratos, 'id'));
    $marcadores_anexos = implode(',', array_fill(0, count($ids_anexos), '?'));
    $stmt_anexos = $pdo_intra->prepare(
        "SELECT id, contrato_id, nome_original, arquivo_path, tamanho_bytes, criado_em
           FROM contratos_anexos
          WHERE contrato_id IN ($marcadores_anexos)
          ORDER BY criado_em, id"
    );
    $stmt_anexos->execute($ids_anexos);
    foreach ($stmt_anexos->fetchAll(PDO::FETCH_ASSOC) as $anexo) {
        $anexos_por_contrato[(int) $anexo['contrato_id']][] = $anexo;
    }
}

// Prioridade gerencial padrão: vencidos -> críticos -> alertas -> regulares -> sem data.
// A mesma prioridade também é aplicada no JavaScript para permanecer correta após filtros/paginação.
usort($contratos, static function (array $a, array $b): int {
    $alertaA = calcularAlerta($a['data_vencimento'] ?? null, $a['setor'] ?? '', !empty($a['recorrente']), $a['status'] ?? 'ATIVO');
    $alertaB = calcularAlerta($b['data_vencimento'] ?? null, $b['setor'] ?? '', !empty($b['recorrente']), $b['status'] ?? 'ATIVO');

    $prioridade = static function (array $alerta): int {
        if (($alerta['situacao'] ?? '') === 'vencido') return 0;
        if (($alerta['situacao'] ?? '') === 'vencendo' && ($alerta['cor'] ?? '') === 'rose') return 1;
        if (($alerta['situacao'] ?? '') === 'vencendo') return 2;
        if (($alerta['situacao'] ?? '') === 'regular') return 3;
        return 4;
    };

    $pa = $prioridade($alertaA);
    $pb = $prioridade($alertaB);
    if ($pa !== $pb) return $pa <=> $pb;

    $dataA = !empty($a['data_vencimento']) ? (string) $a['data_vencimento'] : '9999-12-31';
    $dataB = !empty($b['data_vencimento']) ? (string) $b['data_vencimento'] : '9999-12-31';
    if ($dataA !== $dataB) return strcmp($dataA, $dataB);

    return strcasecmp((string) ($a['fornecedor'] ?? ''), (string) ($b['fornecedor'] ?? ''));
});

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

$renovacoes_por_contrato = [];
if ($contratos) {
    $ids_ren = array_map('intval', array_column($contratos, 'id'));
    $marcadores_ren = implode(',', array_fill(0, count($ids_ren), '?'));
    $stmt_ren = $pdo_intra->prepare("
        SELECT id, contrato_id, tipo_prazo_anterior, data_inicio_anterior, data_vencimento_anterior,
               tipo_prazo_novo, data_inicio_nova, data_vencimento_nova, observacao, usuario_id, criado_em
          FROM contratos_renovacoes
         WHERE contrato_id IN ($marcadores_ren)
         ORDER BY id DESC
    ");
    $stmt_ren->execute($ids_ren);
    foreach ($stmt_ren->fetchAll(PDO::FETCH_ASSOC) as $ren) {
        $renovacoes_por_contrato[(int) $ren['contrato_id']][] = $ren;
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


// =====================================================================
// V6 - COLUNAS INTELIGENTES + NAVEGAÇÃO GERENCIAL
// Colunas auxiliares só aparecem quando existe informação útil na base.
// Isso evita ocupar espaço com campos completamente vazios após importações.
// =====================================================================
$mostrar_col_valores = false;
$mostrar_col_pagamento = false;
$mostrar_col_condicoes = false;

foreach ($contratos as $contrato_coluna) {
    if ((float) ($contrato_coluna['valor_parcela'] ?? 0) > 0 || (float) ($contrato_coluna['valor'] ?? 0) > 0) {
        $mostrar_col_valores = true;
    }

    if (
        trim((string) ($contrato_coluna['periodicidade'] ?? '')) !== '' ||
        trim((string) ($contrato_coluna['forma_pagamento'] ?? '')) !== '' ||
        (int) ($contrato_coluna['quantidade_parcelas'] ?? 0) > 0 ||
        trim((string) ($contrato_coluna['tipo_pagamento'] ?? '')) !== ''
    ) {
        $mostrar_col_pagamento = true;
    }

    $campos_condicoes = [
        $contrato_coluna['possui_aviso_cancelamento'] ?? null,
        $contrato_coluna['possui_reajuste'] ?? null,
        $contrato_coluna['possui_multa'] ?? null,
        $contrato_coluna['possui_carencia'] ?? null,
    ];
    foreach ($campos_condicoes as $valor_condicao) {
        if ($valor_condicao !== null && trim((string) $valor_condicao) !== '' && $valor_condicao !== 'NAO_INFORMADO') {
            $mostrar_col_condicoes = true;
            break;
        }
    }
}

$colunas_tabela = 5
    + ($mostrar_col_valores ? 1 : 0)
    + ($mostrar_col_pagamento ? 1 : 0)
    + ($mostrar_col_condicoes ? 1 : 0);
$largura_minima_tabela = 820
    + ($mostrar_col_valores ? 135 : 0)
    + ($mostrar_col_pagamento ? 145 : 0)
    + ($mostrar_col_condicoes ? 170 : 0);

$stmt_setores = $pdo_intra->query(
    "SELECT DISTINCT TRIM(SETOR) AS setor
       FROM matriz_comunicacao
      WHERE SETOR IS NOT NULL
        AND TRIM(SETOR) <> ''
      ORDER BY setor"
);
$setores_distintos = $stmt_setores->fetchAll(PDO::FETCH_COLUMN);
?>

<main class="flex-1 overflow-y-auto bg-slate-50 px-3 py-5 sm:px-4 lg:px-5">
    <div class="w-full max-w-none mx-auto">

        <?php if (!empty($msg_sucesso)): ?>
            <div class="mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-xl font-bold border border-emerald-100 shadow-sm"><?php echo htmlspecialchars($msg_sucesso); ?></div>
        <?php endif; ?>
        <?php if (!empty($msg_erro)): ?>
            <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded-xl font-bold border border-red-100 shadow-sm"><?php echo htmlspecialchars($msg_erro); ?></div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 gap-3">
            <div>
                <h2 class="text-2xl lg:text-3xl font-black text-navy-900 tracking-tight uppercase italic">Gestão de Contratos</h2>
                <p class="text-slate-500 font-medium mt-0.5 text-sm">
                    <?php if ($visao_encerrados): ?>
                        Arquivo de contratos encerrados — consulta e histórico.
                    <?php elseif ($auth->isDiretoria() && !$eh_admin): ?>
                        Diretoria — visão geral; gestão somente dos contratos sob sua responsabilidade.
                    <?php elseif ($pode_financeiro && !$pode_criar && !$eh_admin): ?>
                        Contratos compartilhados com o Contas a Pagar.
                    <?php elseif (!$eh_admin): ?>
                        Contratos do setor <?php echo htmlspecialchars($setor_usuario); ?>.
                    <?php else: ?>
                        Painel de acompanhamento — todos os setores.
                    <?php endif; ?>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <?php if ($visao_encerrados): ?>
                    <a href="contratos.php" class="border border-slate-200 bg-white text-slate-700 font-bold px-4 py-2.5 rounded-xl shadow-sm hover:bg-slate-50 whitespace-nowrap">
                        ← Voltar aos contratos
                    </a>
                <?php else: ?>
                    <a href="contratos.php?visao=encerrados" class="border border-slate-200 bg-white text-slate-600 font-bold px-4 py-2.5 rounded-xl shadow-sm hover:bg-slate-50 whitespace-nowrap">
                        Encerrados (<?php echo $total_encerrados; ?>)
                    </a>
                    <?php if ($pode_criar): ?>
                    <button onclick="abrirWizard()" class="bg-navy-900 hover:bg-navy-800 text-white font-bold px-5 py-2.5 rounded-xl shadow-md transition-all whitespace-nowrap">
                        + Novo Contrato
                    </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($visao_encerrados): ?>
        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Contratos encerrados</p>
            <div class="flex items-end gap-2 mt-0.5">
                <p class="text-2xl font-black text-navy-900"><?php echo $total_contratos; ?></p>
                <span class="text-xs font-medium text-slate-500 mb-1">arquivado(s) nesta visão</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">Estes contratos não aparecem na rotina operacional, mas permanecem disponíveis para consulta e auditoria.</p>
        </div>
        <?php else: ?>
        <!-- KPIs clicáveis: funcionam também como filtros rápidos -->
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
            <button type="button" class="kpi-filtro group text-left bg-white rounded-xl border border-slate-200 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-200" data-filtro-kpi="ativo" aria-pressed="false" title="Mostrar somente contratos ativos">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Contratos Ativos</p>
                        <p class="text-2xl font-black text-navy-900 mt-0.5"><?php echo $total_ativos; ?></p>
                    </div>
                    <span class="text-[9px] font-black text-blue-600 opacity-0 transition group-hover:opacity-100">Filtrar</span>
                </div>
            </button>
            <button type="button" class="kpi-filtro group text-left bg-white rounded-xl border border-slate-200 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-200" data-filtro-kpi="todos" aria-pressed="true" title="Mostrar todos os contratos">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total de Contratos</p>
                <div class="flex items-end gap-2"><p class="text-2xl font-black text-navy-900 mt-0.5"><?php echo $total_contratos; ?></p><span class="text-[9px] font-bold text-slate-400 mb-1">ver todos</span></div>
            </button>
            <button type="button" class="kpi-filtro group text-left bg-amber-50 rounded-xl border border-amber-200 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-400 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber-200" data-filtro-kpi="alerta" aria-pressed="false" title="Mostrar somente contratos próximos do vencimento ou vencidos">
                <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Alertas de Vencimento</p>
                <div class="flex items-end gap-2"><p class="text-2xl font-black text-amber-700 mt-0.5"><?php echo $total_alertas; ?></p><span class="text-[10px] font-bold text-amber-600 mb-1">clique para ver</span></div>
            </button>
            <button type="button" class="kpi-filtro group text-left bg-rose-50 rounded-xl border border-rose-200 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-rose-400 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-rose-200" data-filtro-kpi="incompleto" aria-pressed="false" title="Mostrar somente cadastros incompletos">
                <p class="text-[10px] font-black uppercase tracking-wider text-rose-700">Cadastros Incompletos</p>
                <div class="flex items-end gap-2"><p class="text-2xl font-black text-rose-700 mt-0.5"><?php echo $total_incompletos; ?></p><span class="text-[10px] font-bold text-rose-500 mb-1">clique para ver</span></div>
            </button>
        </div>
        <?php endif; ?>

        <?php if (!$visao_encerrados): ?>
        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-blue-100 bg-blue-50 px-4 py-2.5 text-[11px] text-blue-800">
            <span class="font-black">Prioridade automática:</span>
            <span><strong class="text-rose-700">1.</strong> Vencidos</span>
            <span><strong class="text-rose-600">2.</strong> Até 15 dias</span>
            <span><strong class="text-amber-700">3.</strong> Dentro do alerta</span>
            <span class="text-blue-600">Facilities: 90 dias • Demais setores: 60 dias</span>
        </div>
        <?php endif; ?>

        <!-- Filtros compactos -->
        <div class="bg-white rounded-xl border border-slate-200 p-3 mb-3 flex flex-col lg:flex-row gap-2.5 shadow-sm">
            <div class="relative flex-1 min-w-0">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
                <input type="text" id="busca-contrato" placeholder="Fornecedor, serviço, CNPJ..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 text-sm font-medium outline-none focus:border-corporate-blue">
            </div>
            <?php if ($eh_admin || $auth->isDiretoria()): ?>
            <select id="filtro-setor" class="lg:w-56 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-600">
                <option value="">Todos os setores</option>
                <?php foreach ($setores_distintos as $s): ?>
                    <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select id="filtro-situacao" class="lg:w-64 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-600">
                <option value="">Todas as situações</option>
                <option value="ativo">Contratos ativos</option>
                <option value="alerta">Próximos do vencimento / vencidos</option>
                <option value="vencido">Somente vencidos</option>
                <option value="vencendo">Somente dentro do alerta</option>
                <option value="andamento">Em andamento</option>
                <option value="aguardando_financeiro">Aguardando Contas a Pagar</option>
                <option value="divergencia">Com divergência</option>
                <option value="confirmado">Uso confirmado</option>
                <option value="incompleto">Cadastros incompletos</option>
                <?php if ($visao_encerrados): ?><option value="encerrado">Encerrados</option><?php endif; ?>
            </select>
            <?php if (!$visao_encerrados): ?>
            <button type="button" id="priorizar-vencimentos" class="lg:w-auto whitespace-nowrap border border-amber-200 bg-amber-50 text-amber-800 rounded-lg px-4 py-2.5 text-xs font-black hover:bg-amber-100">
                ↑ Priorizar vencimentos
            </button>
            <?php endif; ?>
        </div>

        <style>
            #tabela-scroll thead th { position: sticky; top: 0; z-index: 30; background: #f1f5f9; }
            #tabela-scroll thead th:last-child { right: 0; z-index: 45; }
            #tabela-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f8fafc; }
            #tabela-scroll::-webkit-scrollbar { width: 10px; height: 10px; }
            #tabela-scroll::-webkit-scrollbar-track { background: #f8fafc; }
            #tabela-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; border: 2px solid #f8fafc; }
        </style>


        <!-- V7 VISUAL: alta legibilidade. Somente CSS; nenhuma regra de negócio, permissão, fluxo ou JavaScript foi alterado. -->
        <style id="contratos-v7-acessibilidade">
            /* Texto neutro mais escuro e firme */
            main { color: #0f172a; }
            main .text-slate-400,
            #slideover-detalhes .text-slate-400,
            #modal-atualizar-financeiro .text-slate-400,
            #modal-renovacao .text-slate-400,
            #modal-encerramento .text-slate-400,
            #modal-divergencia .text-slate-400 {
                color: #64748b !important;
                font-weight: 700 !important;
            }
            main .text-slate-500,
            #slideover-detalhes .text-slate-500,
            #modal-atualizar-financeiro .text-slate-500,
            #modal-renovacao .text-slate-500,
            #modal-encerramento .text-slate-500,
            #modal-divergencia .text-slate-500 {
                color: #475569 !important;
                font-weight: 700 !important;
            }
            main .text-slate-600,
            #slideover-detalhes .text-slate-600,
            #modal-atualizar-financeiro .text-slate-600,
            #modal-renovacao .text-slate-600,
            #modal-encerramento .text-slate-600,
            #modal-divergencia .text-slate-600 {
                color: #334155 !important;
                font-weight: 700 !important;
            }

            /* Cabeçalho e cards */
            main h2 { font-weight: 950 !important; }
            main h2 + p { color: #334155 !important; font-weight: 700 !important; }
            main .kpi-filtro {
                border-width: 2px !important;
                box-shadow: 0 1px 2px rgba(15,23,42,.08) !important;
            }
            main .kpi-filtro p,
            main .kpi-filtro span { font-weight: 800 !important; }
            main .kpi-filtro .text-2xl { font-weight: 950 !important; }

            /* Caixa de prioridade */
            main .bg-blue-50.border-blue-100 {
                border-width: 2px !important;
                border-color: #93c5fd !important;
                color: #1e3a8a !important;
                font-weight: 700 !important;
            }

            /* Inputs, selects e textareas */
            main input, main select, main textarea,
            #slideover-detalhes input, #slideover-detalhes select, #slideover-detalhes textarea,
            #modal-atualizar-financeiro input, #modal-atualizar-financeiro select, #modal-atualizar-financeiro textarea,
            #modal-renovacao input, #modal-renovacao select, #modal-renovacao textarea,
            #modal-encerramento input, #modal-encerramento select, #modal-encerramento textarea,
            #modal-divergencia input, #modal-divergencia select, #modal-divergencia textarea {
                border-width: 2px !important;
                border-color: #94a3b8 !important;
                color: #0f172a !important;
                font-weight: 700 !important;
                background-color: #fff !important;
            }
            main input::placeholder, main textarea::placeholder,
            #modal-atualizar-financeiro input::placeholder, #modal-atualizar-financeiro textarea::placeholder,
            #modal-renovacao input::placeholder, #modal-renovacao textarea::placeholder,
            #modal-encerramento input::placeholder, #modal-encerramento textarea::placeholder,
            #modal-divergencia input::placeholder, #modal-divergencia textarea::placeholder {
                color: #64748b !important;
                opacity: 1 !important;
                font-weight: 600 !important;
            }
            main input:focus, main select:focus, main textarea:focus,
            #modal-atualizar-financeiro input:focus, #modal-atualizar-financeiro select:focus, #modal-atualizar-financeiro textarea:focus,
            #modal-renovacao input:focus, #modal-renovacao select:focus, #modal-renovacao textarea:focus,
            #modal-encerramento input:focus, #modal-encerramento select:focus, #modal-encerramento textarea:focus,
            #modal-divergencia input:focus, #modal-divergencia select:focus, #modal-divergencia textarea:focus {
                border-color: #1d4ed8 !important;
                outline: 3px solid rgba(59,130,246,.18) !important;
                outline-offset: 1px;
            }

            /* Botões */
            main button, main a[href*="contratos.php"],
            .menu-gerenciamento-opcoes button,
            #slideover-detalhes button,
            #modal-atualizar-financeiro button,
            #modal-renovacao button,
            #modal-encerramento button,
            #modal-divergencia button {
                font-weight: 850 !important;
            }
            main button.border, main a.border,
            #slideover-detalhes button.border,
            #modal-atualizar-financeiro button.border,
            #modal-renovacao button.border,
            #modal-encerramento button.border,
            #modal-divergencia button.border {
                border-width: 2px !important;
            }

            /* Tabela */
            #tabela-scroll { border-top: 1px solid #94a3b8; }
            #tabela-contratos thead {
                color: #334155 !important;
                font-size: 11px !important;
                font-weight: 900 !important;
            }
            #tabela-contratos thead th {
                border-bottom-width: 2px !important;
                border-bottom-color: #94a3b8 !important;
            }
            #tabela-contratos thead button,
            #tabela-contratos thead th {
                font-weight: 950 !important;
                color: #334155 !important;
            }
            #corpo-tabela-contratos { font-size: 12px !important; }
            #corpo-tabela-contratos > tr { border-bottom: 1px solid #cbd5e1 !important; }
            #corpo-tabela-contratos td { color: #1e293b; }
            #corpo-tabela-contratos td p,
            #corpo-tabela-contratos td span:not(.text-slate-300) { font-weight: 700; }
            #corpo-tabela-contratos td:first-child p:first-of-type,
            #corpo-tabela-contratos .font-black,
            #corpo-tabela-contratos .text-navy-900 { font-weight: 950 !important; }
            #corpo-tabela-contratos .text-\\[9px\\] { font-size: 10px !important; }
            #corpo-tabela-contratos .text-\\[10px\\] { font-size: 11px !important; }
            #corpo-tabela-contratos .text-\\[11px\\],
            #corpo-tabela-contratos .text-\\[12px\\] { font-size: 12px !important; }
            #corpo-tabela-contratos span.rounded-full,
            #corpo-tabela-contratos span.rounded-md {
                border: 1px solid rgba(71,85,105,.38);
                font-weight: 900 !important;
            }
            #tabela-contratos th:last-child,
            #tabela-contratos td:last-child { border-left: 1px solid #cbd5e1; }
            #tabela-contratos td:last-child > div > button {
                border: 2px solid #0f172a !important;
                font-size: 11px !important;
                font-weight: 950 !important;
            }

            /* Menu Gerenciar */
            .menu-gerenciamento-opcoes {
                border-width: 2px !important;
                border-color: #94a3b8 !important;
            }
            .menu-gerenciamento-opcoes button {
                font-size: 12px !important;
                font-weight: 850 !important;
                border: 1px solid transparent;
            }
            .menu-gerenciamento-opcoes button:hover { border-color: #cbd5e1; }

            /* Paginação */
            #resumo-paginacao, #pagina-atual, #itens-por-pagina,
            #pagina-primeira, #pagina-anterior, #proxima-pagina,
            #pagina-ultima, #paginacao-numeros button {
                font-weight: 850 !important;
                color: #334155 !important;
            }
            #pagina-primeira, #pagina-anterior, #proxima-pagina,
            #pagina-ultima, #paginacao-numeros button, #itens-por-pagina {
                border-width: 2px !important;
                border-color: #94a3b8 !important;
            }

            /* Modais */
            #slideover-detalhes > div.relative,
            #modal-atualizar-financeiro > div,
            #modal-renovacao > div,
            #modal-encerramento > div,
            #modal-divergencia > div {
                border: 2px solid #94a3b8 !important;
            }
            #slideover-detalhes h3,
            #modal-atualizar-financeiro h3,
            #modal-renovacao h3,
            #modal-encerramento h3,
            #modal-divergencia h3 {
                font-weight: 950 !important;
                color: #0f172a !important;
            }
            #slideover-detalhes label,
            #modal-atualizar-financeiro label,
            #modal-renovacao label,
            #modal-encerramento label,
            #modal-divergencia label {
                color: #334155 !important;
                font-weight: 850 !important;
            }
            #det-conteudo .border,
            #det-conteudo [class*="border-"] { border-width: 2px !important; }
            #det-conteudo p,
            #det-conteudo span,
            #det-conteudo strong { font-weight: 700; }
            #det-conteudo strong,
            #det-conteudo .font-black,
            #det-conteudo .font-bold { font-weight: 900 !important; }

            main .text-slate-300 {
                color: #94a3b8 !important;
                font-weight: 700 !important;
            }

            @media (max-width: 768px) {
                #corpo-tabela-contratos { font-size: 11px !important; }
                main input, main select, main button { font-size: 13px; }
            }
        </style>

        <!-- V6: tabela inteligente, cabeçalho congelado, rolagem interna e paginação -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
                <p class="text-[10px] font-bold text-slate-500">Visualização enxuta: campos sem informação não ocupam espaço na tabela.</p>
                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 whitespace-nowrap">V6.1 • menu corrigido + filtros rápidos</span>
            </div>
            <div id="tabela-scroll" class="w-full overflow-auto max-h-[58vh]" style="scrollbar-gutter: stable;">
                <table id="tabela-contratos" class="w-full text-left border-collapse table-auto" style="min-width: <?php echo (int) $largura_minima_tabela; ?>px;">
                    <thead class="bg-slate-100 text-[10px] uppercase tracking-wide text-slate-500 sticky top-0 z-30 shadow-sm">
                        <tr>
                            <th class="min-w-[240px] px-4 py-3 border-b border-slate-200"><button type="button" class="ordenar-coluna font-black hover:text-navy-900" data-coluna="fornecedor">Contrato ↕</button></th>
                            <th class="min-w-[150px] px-4 py-3 border-b border-slate-200"><button type="button" class="ordenar-coluna font-black hover:text-navy-900" data-coluna="setor">Setor / Empresa ↕</button></th>
                            <?php if ($mostrar_col_valores): ?>
                            <th class="min-w-[135px] px-4 py-3 border-b border-slate-200 text-right"><button type="button" class="ordenar-coluna font-black hover:text-navy-900" data-coluna="valor">Valor ↕</button></th>
                            <?php endif; ?>
                            <?php if ($mostrar_col_pagamento): ?>
                            <th class="min-w-[145px] px-4 py-3 border-b border-slate-200">Pagamento</th>
                            <?php endif; ?>
                            <th class="min-w-[245px] px-4 py-3 border-b border-slate-200"><button type="button" class="ordenar-coluna font-black hover:text-navy-900" data-coluna="vigencia">Vigência / Alerta ↕</button></th>
                            <?php if ($mostrar_col_condicoes): ?>
                            <th class="min-w-[175px] px-4 py-3 border-b border-slate-200">Condições</th>
                            <?php endif; ?>
                            <th class="min-w-[125px] px-4 py-3 border-b border-slate-200"><button type="button" class="ordenar-coluna font-black hover:text-navy-900" data-coluna="situacao">Situação ↕</button></th>
                            <th class="w-[105px] min-w-[105px] px-4 py-3 border-b border-slate-200 text-right sticky top-0 right-0 z-40 bg-slate-100">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="corpo-tabela-contratos" class="divide-y divide-slate-100 text-[11px]">
                        <?php if (empty($contratos)): ?>
                            <tr><td colspan="<?php echo (int) $colunas_tabela; ?>" class="text-center px-6 py-10 text-slate-400 font-medium">Nenhum contrato encontrado.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($contratos as $c):
                            $alerta   = calcularAlerta($c['data_vencimento'] ?? null, $c['setor'] ?? '', !empty($c['recorrente']), $c['status'] ?? 'ATIVO');
                            $etapa    = (int) $c['etapa_atual'];
                            $tem_div  = isset($divergencias_abertas[$c['id']]);
                            $eh_responsavel = (int) $c['gestor_id'] === (int) $user_id_sessao;
                            $eh_dono  = $eh_admin || $eh_responsavel;

                            // Permissões agora são avaliadas CONTRATO A CONTRATO.
                            // Diretoria: tudo nos próprios contratos; somente leitura nos demais.
                            $contrato_encerrado = (($c['status'] ?? 'ATIVO') === 'ENCERRADO');

                            $pode_editar_este = !$contrato_encerrado && $auth->podeNoContrato('editar', (int) $c['id']);
                            $pode_compartilhar_este = !$contrato_encerrado && $auth->podeNoContrato('compartilhar', (int) $c['id']);
                            $pode_confirmar_este = !$contrato_encerrado && $auth->podeNoContrato('confirmar_uso', (int) $c['id']);
                            $pode_divergir_este = !$contrato_encerrado && $auth->podeNoContrato('registrar_divergencia', (int) $c['id']);
                            $pode_excluir_este = !$contrato_encerrado && $auth->podeNoContrato('excluir', (int) $c['id']);
                            $pode_financeiro_este = $auth->podeNoContrato('ver_financeiro', (int) $c['id']);
                            $pode_restritos_este = $auth->podeNoContrato('ver_restritos', (int) $c['id']);
                            $pode_baixar_este = $auth->podeNoContrato('baixar_anexo', (int) $c['id']);
                            $somente_visualizacao = ($auth->isDiretoria() && !$eh_responsavel && !$eh_admin) || $contrato_encerrado;

                            $pendentes = camposEssenciaisPendentes($c);

                            $anexos_contrato = $anexos_por_contrato[(int) $c['id']] ?? [];
                            $arquivo_legado = trim((string) ($c['arquivo_path'] ?? ''));
                            $legado_ja_listado = false;
                            foreach ($anexos_contrato as $anexo_contrato) {
                                if ((string) $anexo_contrato['arquivo_path'] === $arquivo_legado) {
                                    $legado_ja_listado = true;
                                    break;
                                }
                            }
                            if ($arquivo_legado !== '' && !$legado_ja_listado) {
                                array_unshift($anexos_contrato, [
                                    'id' => null,
                                    'contrato_id' => (int) $c['id'],
                                    'nome_original' => 'Contrato principal.pdf',
                                    'arquivo_path' => $arquivo_legado,
                                    'tamanho_bytes' => null,
                                    'criado_em' => null,
                                ]);
                            }
                            $anexos_cliente = array_map(static fn(array $anexo): array => [
                                'id' => $anexo['id'] !== null ? (int) $anexo['id'] : null,
                                'nome' => (string) $anexo['nome_original'],
                                'tamanho' => $anexo['tamanho_bytes'] !== null ? (int) $anexo['tamanho_bytes'] : null,
                                'criado_em' => $anexo['criado_em'],
                            ], $anexos_contrato);

                            $c_cliente = $c;
                            if (!$pode_financeiro_este && !$eh_dono) {
                                foreach (['valor','valor_parcela','forma_pagamento','quantidade_parcelas','periodicidade','indices_reajuste','centro_custo','multa_carencia','prazo_comunicacao_cancelamento','renovacao_automatica','aviso_previo','multa_contratual','carencia_contratual','dados_bancarios_fornecedor','retencoes_tributarias','condicoes_pagamento','responsavel_aprovacao_servico','contato_financeiro_nome','contato_financeiro_email','contato_financeiro_telefone'] as $campo) unset($c_cliente[$campo]);
                            }
                            if (!$pode_restritos_este && !$eh_dono && !$pode_financeiro_este) {
                                foreach (['clausula_tecnica','codigo_sistema','arquivo_path'] as $campo) unset($c_cliente[$campo]);
                            }
                            $c_cliente['_pode_editar'] = $pode_editar_este;
                            $c_cliente['_pode_compartilhar'] = $pode_compartilhar_este;
                            $c_cliente['_pode_confirmar'] = $pode_confirmar_este;
                            $c_cliente['_pode_divergir'] = $pode_divergir_este;
                            $c_cliente['_pode_excluir'] = $pode_excluir_este;
                            $c_cliente['_pode_financeiro'] = $pode_financeiro_este;
                            $c_cliente['_pode_restritos'] = $pode_restritos_este;
                            $c_cliente['_pode_baixar'] = $pode_baixar_este;
                            $c_cliente['_somente_visualizacao'] = $somente_visualizacao;
                            $c_cliente['_tem_anexo'] = !empty($anexos_cliente);
                            $c_cliente['_qtd_anexos'] = count($anexos_cliente);
                            $c_cliente['anexos'] = $pode_baixar_este ? $anexos_cliente : [];
                            $c_cliente['divergencias'] = $divergencias_por_contrato[(int) $c['id']] ?? [];
                            $c_cliente['renovacoes'] = $renovacoes_por_contrato[(int) $c['id']] ?? [];
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
                            if (($c['status'] ?? '') === 'ATIVO') $situacoes[] = 'ativo';
                            if (($c['status'] ?? '') === 'ENCERRADO') $situacoes[] = 'encerrado';
                            if (!empty($alerta['ativo'])) $situacoes[] = 'alerta';
                            if (in_array($alerta['situacao'], ['vencendo', 'vencido'], true)) $situacoes[] = $alerta['situacao'];
                            if ($pendentes) $situacoes[] = 'incompleto';

                            if ($contrato_encerrado) {
                                $prioridade_alerta = 9;
                                $linha_classe = 'bg-slate-50/70 hover:bg-slate-100';
                                $borda_classe = 'border-l-4 border-l-slate-300';
                                $badge_alerta = 'bg-slate-200 text-slate-600 border border-slate-300';
                                $alerta = ['texto' => 'Encerrado', 'cor' => 'slate', 'ativo' => false, 'situacao' => 'sem_alerta'];
                            } elseif (($alerta['situacao'] ?? '') === 'vencido') {
                                $prioridade_alerta = 0;
                                $linha_classe = 'bg-rose-50/70 hover:bg-rose-50';
                                $borda_classe = 'border-l-4 border-l-rose-500';
                                $badge_alerta = 'bg-rose-100 text-rose-700 border border-rose-200';
                            } elseif (($alerta['situacao'] ?? '') === 'vencendo' && ($alerta['cor'] ?? '') === 'rose') {
                                $prioridade_alerta = 1;
                                $linha_classe = 'bg-rose-50/40 hover:bg-rose-50';
                                $borda_classe = 'border-l-4 border-l-rose-400';
                                $badge_alerta = 'bg-rose-100 text-rose-700 border border-rose-200';
                            } elseif (($alerta['situacao'] ?? '') === 'vencendo') {
                                $prioridade_alerta = 2;
                                $linha_classe = 'bg-amber-50/60 hover:bg-amber-50';
                                $borda_classe = 'border-l-4 border-l-amber-400';
                                $badge_alerta = 'bg-amber-100 text-amber-800 border border-amber-200';
                            } elseif (($alerta['situacao'] ?? '') === 'regular') {
                                $prioridade_alerta = 3;
                                $linha_classe = 'hover:bg-slate-50';
                                $borda_classe = 'border-l-4 border-l-transparent';
                                $badge_alerta = 'bg-slate-100 text-slate-600 border border-slate-200';
                            } else {
                                $prioridade_alerta = 4;
                                $linha_classe = 'hover:bg-slate-50';
                                $borda_classe = 'border-l-4 border-l-transparent';
                                $badge_alerta = 'bg-slate-100 text-slate-500 border border-slate-200';
                            }

                            $inicio_fmt = !empty($c['data_inicio']) ? (new DateTime($c['data_inicio']))->format('d/m/Y') : '';
                            $fim_fmt = !empty($c['data_vencimento']) ? (new DateTime($c['data_vencimento']))->format('d/m/Y') : '';
                            $valor_parcela_num = (float) ($c['valor_parcela'] ?? 0);
                            $valor_total_num = (float) ($c['valor'] ?? 0);
                            $qtd_parcelas = (int) ($c['quantidade_parcelas'] ?? 0);

                            $condicoes_linha = [];
                            if (($c['possui_aviso_cancelamento'] ?? '') === 'SIM') {
                                $dias_aviso = (int) ($c['prazo_comunicacao_cancelamento'] ?? 0);
                                $condicoes_linha[] = $dias_aviso > 0 ? 'Aviso ' . $dias_aviso . 'd' : 'Com aviso';
                            } elseif (($c['possui_aviso_cancelamento'] ?? '') === 'NAO') {
                                $condicoes_linha[] = 'Sem aviso';
                            }
                            if ((string) ($c['possui_reajuste'] ?? '') === '1') {
                                $indice = ($c['indice_reajuste'] ?? '') === 'OUTRO' ? ($c['indice_reajuste_outro'] ?? '') : ($c['indice_reajuste'] ?? '');
                                $condicoes_linha[] = trim((string) $indice) !== '' ? 'Reajuste ' . $indice : 'Com reajuste';
                            } elseif ((string) ($c['possui_reajuste'] ?? '') === '0') {
                                $condicoes_linha[] = 'Sem reajuste';
                            }
                            if (($c['possui_multa'] ?? '') === 'SIM') $condicoes_linha[] = 'Com multa';
                            elseif (($c['possui_multa'] ?? '') === 'NAO') $condicoes_linha[] = 'Sem multa';
                            if (($c['possui_carencia'] ?? '') === 'SIM') $condicoes_linha[] = 'Com carência';
                            elseif (($c['possui_carencia'] ?? '') === 'NAO') $condicoes_linha[] = 'Sem carência';

                            $status_labels = [
                                'RASCUNHO' => 'Rascunho',
                                'AGUARDANDO_FINANCEIRO' => 'Aguard. Financeiro',
                                'CONFIRMADO' => 'Confirmado',
                                'COM_DIVERGENCIA' => 'Com divergência',
                            ];
                            $status_label = $contrato_encerrado
                                ? 'Encerrado'
                                : ($status_labels[$status_fluxo] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $status_fluxo), 'UTF-8')));
                        ?>
                        <tr class="linha-contrato group transition-colors <?php echo $linha_classe; ?>"
                            data-nome="<?php echo htmlspecialchars(mb_strtolower(($c['fornecedor'] ?? '') . ' ' . ($c['servico_objeto'] ?? '') . ' ' . ($c['cnpj'] ?? '') . ' ' . ($c['empresa'] ?? ''))); ?>"
                            data-setor="<?php echo htmlspecialchars($c['setor'] ?? ''); ?>"
                            data-situacao="<?php echo htmlspecialchars(implode(' ', $situacoes)); ?>"
                            data-fornecedor="<?php echo htmlspecialchars(mb_strtolower($c['fornecedor'] ?? '')); ?>"
                            data-valor="<?php echo $valor_parcela_num > 0 ? $valor_parcela_num : $valor_total_num; ?>"
                            data-vigencia="<?php echo htmlspecialchars($c['data_vencimento'] ?: '9999-12-31'); ?>"
                            data-alerta-prioridade="<?php echo $prioridade_alerta; ?>">

                            <td class="px-4 py-3.5 align-middle <?php echo $borda_classe; ?>">
                                <div class="min-w-0">
                                    <div class="flex items-start gap-2">
                                        <p class="font-black text-[12px] leading-tight text-navy-900 break-words"><?php echo htmlspecialchars($c['fornecedor']); ?></p>
                                        <?php if ($tem_div): ?><span class="shrink-0 text-[9px] font-black uppercase text-rose-700">⚠</span><?php endif; ?>
                                    </div>
                                    <?php if (trim((string) ($c['servico_objeto'] ?? '')) !== ''): ?>
                                        <p class="mt-1 text-[10px] font-semibold text-slate-500 truncate" title="<?php echo htmlspecialchars($c['servico_objeto']); ?>"><?php echo htmlspecialchars($c['servico_objeto']); ?></p>
                                    <?php endif; ?>
                                    <?php if (trim((string) ($c['cnpj'] ?? '')) !== ''): ?>
                                        <p class="mt-0.5 text-[9px] text-slate-400 truncate"><?php echo htmlspecialchars($c['cnpj']); ?></p>
                                    <?php elseif (trim((string) ($c['nome_fantasia'] ?? '')) !== ''): ?>
                                        <p class="mt-0.5 text-[9px] text-slate-400 truncate"><?php echo htmlspecialchars($c['nome_fantasia']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-4 py-3.5 align-middle">
                                <?php if (trim((string) ($c['setor'] ?? '')) !== ''): ?>
                                    <span class="inline-flex max-w-full rounded-md bg-slate-100 px-2 py-1 text-[9px] font-black uppercase text-slate-700 break-words"><?php echo htmlspecialchars($c['setor']); ?></span>
                                <?php endif; ?>
                                <?php if (trim((string) ($c['empresa'] ?? '')) !== ''): ?>
                                    <p class="mt-1.5 text-[10px] font-bold text-slate-500 truncate" title="<?php echo htmlspecialchars($c['empresa']); ?>"><?php echo htmlspecialchars($c['empresa']); ?></p>
                                <?php endif; ?>
                            </td>

                            <?php if ($mostrar_col_valores): ?>
                            <td class="px-4 py-3.5 align-middle text-right whitespace-nowrap">
                                <?php if ($valor_parcela_num > 0): ?>
                                    <p class="text-[12px] font-black text-navy-900">R$ <?php echo number_format($valor_parcela_num, 2, ',', '.'); ?></p>
                                <?php elseif ($valor_total_num > 0): ?>
                                    <p class="text-[12px] font-black text-navy-900">R$ <?php echo number_format($valor_total_num, 2, ',', '.'); ?></p>
                                <?php else: ?>
                                    <span class="text-slate-300">—</span>
                                <?php endif; ?>
                                <?php if ($valor_total_num > 0 && abs($valor_total_num - $valor_parcela_num) > 0.009): ?>
                                    <p class="mt-1 text-[9px] text-slate-400">Total R$ <?php echo number_format($valor_total_num, 2, ',', '.'); ?></p>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($mostrar_col_pagamento): ?>
                            <td class="px-4 py-3.5 align-middle">
                                <?php $tem_pagamento_linha = false; ?>
                                <?php if (trim((string) ($c['periodicidade'] ?? '')) !== ''): $tem_pagamento_linha = true; ?>
                                    <p class="font-bold text-slate-700"><?php echo htmlspecialchars($c['periodicidade']); ?></p>
                                <?php endif; ?>
                                <?php if (trim((string) ($c['forma_pagamento'] ?? '')) !== ''): $tem_pagamento_linha = true; ?>
                                    <p class="mt-1 text-[9px] text-slate-400"><?php echo htmlspecialchars($c['forma_pagamento']); ?></p>
                                <?php endif; ?>
                                <?php if (($c['tipo_prazo'] ?? '') !== 'INDETERMINADO' && $qtd_parcelas > 0): $tem_pagamento_linha = true; ?>
                                    <p class="mt-1 text-[9px] font-bold text-slate-500"><?php echo $qtd_parcelas; ?> parcela(s)</p>
                                <?php endif; ?>
                                <?php if (!$tem_pagamento_linha): ?><span class="text-slate-300">—</span><?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <?php if ($inicio_fmt !== '' || $fim_fmt !== ''): ?>
                                            <p class="font-bold text-slate-800 whitespace-nowrap">
                                                <?php if ($inicio_fmt !== '') echo $inicio_fmt; ?>
                                                <?php if ($inicio_fmt !== '' && $fim_fmt !== ''): ?><span class="mx-1 text-slate-300">→</span><?php endif; ?>
                                                <?php if ($fim_fmt !== '') echo $fim_fmt; ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($c['prazo_indeterminado'])): ?>
                                            <p class="mt-1 text-[9px] text-slate-400">Prazo indeterminado</p>
                                        <?php elseif (($c['tipo_prazo'] ?? '') === 'DETERMINADO'): ?>
                                            <p class="mt-1 text-[9px] text-slate-400">Prazo determinado</p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[9px] font-black whitespace-nowrap <?php echo $badge_alerta; ?>"><?php echo htmlspecialchars($alerta['texto']); ?></span>
                                </div>
                            </td>

                            <?php if ($mostrar_col_condicoes): ?>
                            <td class="px-4 py-3.5 align-middle">
                                <?php if ($condicoes_linha): ?>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php foreach ($condicoes_linha as $condicao_txt): ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600"><?php echo htmlspecialchars($condicao_txt); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <td class="px-4 py-3.5 align-middle" data-status-ordenacao="<?php echo htmlspecialchars($status_fluxo); ?>">
                                <p class="text-[10px] font-black <?php echo $contrato_encerrado ? 'text-slate-500' : 'text-slate-700'; ?> leading-tight"><?php echo htmlspecialchars($status_label); ?></p>
                                <?php if ($contrato_encerrado && !empty($c['data_encerramento'])): ?>
                                    <span class="inline-flex mt-1.5 rounded-full bg-slate-200 px-2 py-0.5 text-[9px] font-black text-slate-600">
                                        <?php echo (new DateTime($c['data_encerramento']))->format('d/m/Y'); ?>
                                    </span>
                                <?php elseif ($pendentes): ?>
                                    <span title="<?php echo htmlspecialchars(implode(', ', $pendentes)); ?>" class="inline-flex mt-1.5 rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black text-rose-700 whitespace-nowrap"><?php echo count($pendentes); ?> pend.</span>
                                <?php else: ?>
                                    <span class="inline-flex mt-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black text-emerald-700">Completo</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-4 py-3.5 align-middle text-right sticky right-0 <?php echo $prioridade_alerta <= 2 ? ($prioridade_alerta <= 1 ? 'bg-rose-50' : 'bg-amber-50') : 'bg-white'; ?> group-hover:bg-slate-50">
                                <div class="relative menu-gerenciamento inline-block">
                                    <button type="button" onclick="alternarMenuGerenciamento(event, 'menu-contrato-<?php echo (int) $c['id']; ?>')" class="inline-flex items-center gap-1 bg-navy-900 text-white text-[10px] font-black px-3 py-2 rounded-lg shadow-sm hover:bg-navy-800 whitespace-nowrap">
                                        Gerenciar <span aria-hidden="true">▾</span>
                                    </button>
                                    <div id="menu-contrato-<?php echo (int) $c['id']; ?>" class="menu-gerenciamento-opcoes hidden fixed z-[70] w-56 bg-white border border-slate-200 rounded-2xl shadow-xl p-2 text-left">
                                        <?php if ($pode_editar_este): ?>
                                            <button onclick='fecharMenusGerenciamento(); abrirDetalhesEPendencias(<?php echo json_encode($c_cliente, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)' class="w-full text-left text-xs font-black <?php echo $pendentes ? 'text-rose-700 bg-rose-50 hover:bg-rose-100' : 'text-slate-700 hover:bg-slate-50'; ?> px-3 py-2.5 rounded-xl">Ver detalhes<?php echo $pendentes ? ' e ' . count($pendentes) . ' pendência(s)' : ''; ?></button>
                                        <?php else: ?>
                                            <button onclick='fecharMenusGerenciamento(); abrirDetalhes(<?php echo json_encode($c_cliente, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>, <?php echo $eh_responsavel ? "true" : "false"; ?>)' class="w-full text-left text-xs font-bold text-slate-700 px-3 py-2.5 rounded-xl hover:bg-slate-50">Ver detalhes</button>
                                        <?php endif; ?>

                                        <?php if ($pode_compartilhar_este && ($status_fluxo === 'RASCUNHO' || $status_fluxo === 'COM_DIVERGENCIA')): ?>
                                            <button onclick="fecharMenusGerenciamento(); compartilharContrato(<?php echo $c['id']; ?>)" class="w-full text-left text-xs font-bold text-blue-700 px-3 py-2.5 rounded-xl hover:bg-blue-50">Compartilhar com Contas a Pagar</button>
                                        <?php endif; ?>

                                        <?php if ($pode_confirmar_este && ($etapa === 5 || $tem_div)): ?>
                                            <button onclick="fecharMenusGerenciamento(); confirmarUso(<?php echo $c['id']; ?>, <?php echo $tem_div ? 'true' : 'false'; ?>)" class="w-full text-left text-xs font-bold text-emerald-700 px-3 py-2.5 rounded-xl hover:bg-emerald-50"><?php echo $tem_div ? 'Confirmar correção' : 'Confirmar uso'; ?></button>
                                        <?php endif; ?>

                                        <?php if ($pode_divergir_este && $etapa >= 5 && !$tem_div): ?>
                                            <button onclick="fecharMenusGerenciamento(); abrirDivergencia(<?php echo $c['id']; ?>)" class="w-full text-left text-xs font-bold text-rose-700 px-3 py-2.5 rounded-xl hover:bg-rose-50">Registrar divergência</button>
                                        <?php endif; ?>

                                        <?php if (
                                            $pode_editar_este
                                            && !$contrato_encerrado
                                            && ($c['tipo_prazo'] ?? '') === 'DETERMINADO'
                                            && ($alerta['situacao'] ?? '') === 'vencido'
                                        ): ?>
                                            <button onclick='fecharMenusGerenciamento(); abrirRenovacao(<?php echo json_encode($c_cliente, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)' class="w-full text-left text-xs font-bold text-emerald-700 px-3 py-2.5 rounded-xl hover:bg-emerald-50">↻ Renovar contrato</button>
                                        <?php endif; ?>

                                        <?php if ($pode_editar_este && !$contrato_encerrado): ?>
                                            <button onclick='fecharMenusGerenciamento(); abrirEncerramento(<?php echo json_encode($c_cliente, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)' class="w-full text-left text-xs font-bold text-slate-600 px-3 py-2.5 rounded-xl hover:bg-slate-100">Encerrar contrato</button>
                                        <?php endif; ?>

                                        <?php if ($somente_visualizacao): ?>
                                            <div class="mt-1 border-t border-slate-100 pt-2 px-3 pb-1">
                                                <?php if ($contrato_encerrado): ?>
                                                    <p class="text-[9px] font-black uppercase tracking-wide text-slate-400">Contrato encerrado · somente leitura</p>
                                                    <p class="mt-1 text-[10px] leading-snug text-slate-500">O histórico permanece disponível, mas o registro não pode mais ser alterado.</p>
                                                <?php else: ?>
                                                    <p class="text-[9px] font-black uppercase tracking-wide text-slate-400">Diretoria · somente visualização</p>
                                                    <p class="mt-1 text-[10px] leading-snug text-slate-500">Somente o dono deste contrato pode realizar alterações.</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-200 flex flex-col xl:flex-row items-center justify-between gap-3 text-xs bg-slate-50/80">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <p id="resumo-paginacao" class="text-slate-500 font-medium"></p>
                    <label class="text-slate-500">Exibir
                        <select id="itens-por-pagina" class="ml-1 border border-slate-200 rounded-lg px-2 py-1 bg-white font-bold">
                            <option value="10">10</option><option value="20" selected>20</option><option value="30">30</option><option value="50">50</option><option value="100">100</option>
                        </select>
                        por página
                    </label>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    <button type="button" id="pagina-primeira" class="border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 font-black disabled:opacity-35 hover:bg-slate-100" title="Primeira página">«</button>
                    <button type="button" id="pagina-anterior" class="border border-slate-200 bg-white rounded-lg px-3 py-1.5 font-bold disabled:opacity-35 hover:bg-slate-100">Anterior</button>
                    <div id="paginacao-numeros" class="flex items-center gap-1"></div>
                    <button type="button" id="proxima-pagina" class="border border-slate-200 bg-white rounded-lg px-3 py-1.5 font-bold disabled:opacity-35 hover:bg-slate-100">Próxima</button>
                    <button type="button" id="pagina-ultima" class="border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 font-black disabled:opacity-35 hover:bg-slate-100" title="Última página">»</button>
                    <span id="pagina-atual" class="ml-1 font-bold text-navy-900 whitespace-nowrap"></span>
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

<!-- MODAL RENOVAÇÃO -->
<div id="modal-renovacao" class="fixed inset-0 z-[65] hidden items-center justify-center bg-black/30 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-6">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-lg font-black text-navy-900">Renovar contrato</h3>
                <p id="renovar-subtitulo" class="text-xs text-slate-400 mt-1"></p>
            </div>
            <button type="button" onclick="fecharRenovacao()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
        </div>

        <form id="form-renovacao" class="space-y-4">
            <input type="hidden" name="contrato_id">

            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Nova vigência *</label>
                <select required name="tipo_prazo_novo" id="renovar-tipo-prazo" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                    <option value="">Selecione</option>
                    <option value="DETERMINADO">Possui prazo / data final</option>
                    <option value="INDETERMINADO">Prazo indeterminado</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="text-xs font-bold text-slate-500 uppercase">
                    Início da nova vigência *
                    <input required type="date" name="data_inicio_nova" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case">
                </label>

                <label id="renovar-bloco-data-final" class="text-xs font-bold text-slate-500 uppercase hidden">
                    Nova data final *
                    <input type="date" name="data_vencimento_nova" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case">
                </label>
            </div>

            <label class="block text-xs font-bold text-slate-500 uppercase">
                Observação da renovação
                <textarea name="observacao" rows="3" maxlength="1000" placeholder="Ex: renovado nas mesmas condições comerciais."
                          class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-normal normal-case"></textarea>
            </label>

            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-xs text-emerald-800">
                A vigência anterior será preservada no histórico. O contrato continuará sendo o mesmo registro.
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <button type="button" onclick="fecharRenovacao()" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-700 hover:bg-emerald-600">Confirmar renovação</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ENCERRAMENTO -->
<div id="modal-encerramento" class="fixed inset-0 z-[65] hidden items-center justify-center bg-black/30 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-lg font-black text-navy-900">Encerrar contrato</h3>
                <p id="encerrar-subtitulo" class="text-xs text-slate-400 mt-1"></p>
            </div>
            <button type="button" onclick="fecharEncerramento()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
        </div>

        <form id="form-encerramento" class="space-y-4">
            <input type="hidden" name="contrato_id">

            <label class="block text-xs font-bold text-slate-500 uppercase">
                Data do encerramento *
                <input required type="date" name="data_encerramento" class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-normal normal-case">
            </label>

            <label class="block text-xs font-bold text-slate-500 uppercase">
                Motivo / observação *
                <textarea required name="motivo_encerramento" rows="4" maxlength="500"
                          placeholder="Ex: contrato encerrado por término da prestação do serviço."
                          class="mt-1 w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-normal normal-case"></textarea>
            </label>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                O contrato será retirado da lista operacional e ficará disponível em <strong>Encerrados</strong> para consulta e auditoria.
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <button type="button" onclick="fecharEncerramento()" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-slate-700 hover:bg-slate-600">Encerrar contrato</button>
            </div>
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
                        <label class="text-xs font-bold text-slate-500 uppercase">Anexos do Contrato (PDF) <span class="text-red-500">*</span></label>
                        <input type="file" id="w-arquivo-contrato" name="arquivos_contrato[]" accept="application/pdf,.pdf" multiple class="mt-1 w-full text-sm">
                        <p id="w-arquivo-ajuda" class="text-xs text-slate-400 mt-1">Selecione até 10 PDFs por envio, com 10 MB por arquivo e 50 MB no total.</p>
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
    document.getElementById('w-arquivo-ajuda').textContent = 'Selecione até 10 PDFs por envio, com 10 MB por arquivo e 50 MB no total.';
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
    document.getElementById('w-arquivo-contrato').required = !c._tem_anexo;
    document.getElementById('w-arquivo-ajuda').textContent = c._tem_anexo
        ? `${Number(c._qtd_anexos || 1)} anexo(s) existente(s). Os novos PDFs serão adicionados sem substituir os atuais.`
        : 'Este contrato ainda não possui PDF; anexe ao menos um para concluir o cadastro.';

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

    const podeRestritosContrato = Boolean(c._pode_restritos);
    const podeFinanceiroContrato = Boolean(c._pode_financeiro);
    const podeBaixarContrato = Boolean(c._pode_baixar);
    const podeEditarContrato = Boolean(c._pode_editar);

    const podeVerTudo = EH_ADMIN || ehDono || podeRestritosContrato;
    const podeVerFinanceiro = podeVerTudo || (podeFinanceiroContrato && Number(c.etapa_atual) >= 5);
    const visaoExclusivaFinanceiro = podeFinanceiroContrato && !podeVerTudo;
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
        if (podeEditarContrato) {
            html += `<button type="button" onclick="fecharDetalhes(); editarContrato(contratoDetalheAtual)" class="w-full mt-4 bg-blue-50 text-blue-800 border border-blue-200 font-bold text-sm py-2.5 rounded-xl hover:bg-blue-100">✏ Editar contrato completo</button>`;
        } else if (String(c.status || '') === 'ENCERRADO') {
            html += `<div class="mt-4 bg-slate-50 text-slate-600 border border-slate-200 font-medium text-sm p-3 rounded-xl"><strong>Contrato encerrado.</strong> O registro permanece disponível somente para consulta e histórico.</div>`;
        } else if (Boolean(c._somente_visualizacao)) {
            html += `<div class="mt-4 bg-slate-50 text-slate-600 border border-slate-200 font-medium text-sm p-3 rounded-xl"><strong>Somente visualização.</strong> Este contrato pertence a outro responsável e não pode ser alterado pela Diretoria.</div>`;
        } else {
            html += `<div class="mt-4 bg-blue-50 text-blue-800 border border-blue-200 font-medium text-sm p-3 rounded-xl">O Contas a Pagar confere as informações. Se algo estiver incorreto, registre uma divergência para o responsável corrigir.</div>`;
        }
        if (podeBaixarContrato) html += renderizarAnexosContrato(c);
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

    if (podeBaixarContrato) html += renderizarAnexosContrato(c);

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

    if (String(c.status || '') === 'ENCERRADO') {
        html += `<div class="pt-3 mt-3 border-t border-slate-100"><p class="text-xs font-black uppercase text-slate-400 mb-2">Encerramento</p></div>`;
        html += `<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-[11px] font-black uppercase text-slate-500">Contrato encerrado</p>
            <p class="text-sm text-slate-700 mt-1"><strong>Data:</strong> ${escaparHtml(formatarDataDetalhe(c.data_encerramento) || '—')}</p>
            <p class="text-sm text-slate-700 mt-1"><strong>Motivo:</strong> ${escaparHtml(c.motivo_encerramento || 'Não informado')}</p>
        </div>`;
    }

    if (Array.isArray(c.renovacoes) && c.renovacoes.length) {
        html += `<div class="pt-3 mt-3 border-t border-slate-100"><p class="text-xs font-black uppercase text-slate-400 mb-2">Histórico de renovações</p></div>`;
        html += c.renovacoes.map(ren => {
            const anterior = ren.tipo_prazo_anterior === 'INDETERMINADO'
                ? 'Prazo indeterminado'
                : `${formatarDataDetalhe(ren.data_inicio_anterior) || '—'} → ${formatarDataDetalhe(ren.data_vencimento_anterior) || '—'}`;
            const novo = ren.tipo_prazo_novo === 'INDETERMINADO'
                ? `${formatarDataDetalhe(ren.data_inicio_nova) || '—'} → prazo indeterminado`
                : `${formatarDataDetalhe(ren.data_inicio_nova) || '—'} → ${formatarDataDetalhe(ren.data_vencimento_nova) || '—'}`;
            return `<div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 mb-2">
                <p class="text-[11px] font-black uppercase text-emerald-700">Renovação registrada</p>
                <p class="text-sm text-slate-700 mt-1"><strong>Anterior:</strong> ${escaparHtml(anterior)}</p>
                <p class="text-sm text-slate-700 mt-1"><strong>Nova vigência:</strong> ${escaparHtml(novo)}</p>
                ${ren.observacao ? `<p class="text-sm text-slate-600 mt-1">${escaparHtml(ren.observacao)}</p>` : ''}
            </div>`;
        }).join('');
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

function renderizarAnexosContrato(contrato) {
    const anexos = Array.isArray(contrato.anexos) ? contrato.anexos : [];
    if (!anexos.length) return '';

    const itens = anexos.map((anexo, indice) => {
        const parametro = anexo.id ? `&anexo_id=${Number(anexo.id)}` : '';
        const nome = escaparHtml(anexo.nome || `Anexo ${indice + 1}`);
        const tamanho = Number(anexo.tamanho || 0);
        const tamanhoTexto = tamanho > 0
            ? ` · ${(tamanho / 1024 / 1024).toLocaleString('pt-BR', {maximumFractionDigits: 1})} MB`
            : '';
        return `<a href="api/ContratoDownload.php?id=${Number(contrato.id)}${parametro}"
                   class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-navy-900 hover:border-blue-300 hover:bg-blue-50 transition-colors">
                    <span class="min-w-0 truncate">📎 ${nome}</span>
                    <span class="shrink-0 text-[10px] font-black uppercase text-slate-400">Baixar${tamanhoTexto}</span>
                </a>`;
    }).join('');

    return `<div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 p-3">
        <p class="mb-2 text-xs font-black uppercase tracking-wider text-slate-500">Anexos (${anexos.length})</p>
        <div class="space-y-2">${itens}</div>
    </div>`;
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
// RENOVAÇÃO / ENCERRAMENTO
// =====================================================================
function somarUmDiaISO(dataIso) {
    if (!dataIso) return '';
    const data = new Date(`${String(dataIso).slice(0, 10)}T12:00:00`);
    if (Number.isNaN(data.getTime())) return '';
    data.setDate(data.getDate() + 1);
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

function abrirRenovacao(c) {
    const form = document.getElementById('form-renovacao');
    form.reset();
    form.elements.contrato_id.value = c.id;
    form.elements.data_inicio_nova.value = somarUmDiaISO(c.data_vencimento);
    document.getElementById('renovar-subtitulo').textContent =
        `${c.fornecedor} · vencimento atual ${formatarDataDetalhe(c.data_vencimento) || 'não informado'}`;
    atualizarCamposRenovacao();
    document.getElementById('modal-renovacao').classList.remove('hidden');
    document.getElementById('modal-renovacao').classList.add('flex');
}

function fecharRenovacao() {
    document.getElementById('modal-renovacao').classList.add('hidden');
    document.getElementById('modal-renovacao').classList.remove('flex');
}

function atualizarCamposRenovacao() {
    const tipo = document.getElementById('renovar-tipo-prazo').value;
    const bloco = document.getElementById('renovar-bloco-data-final');
    const campo = document.querySelector('#form-renovacao [name="data_vencimento_nova"]');
    const determinado = tipo === 'DETERMINADO';
    bloco.classList.toggle('hidden', !determinado);
    campo.required = determinado;
    if (!determinado) campo.value = '';
}

document.getElementById('renovar-tipo-prazo').addEventListener('change', atualizarCamposRenovacao);

document.getElementById('form-renovacao').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('acao', 'renovar_contrato');
    fd.append('csrf_token', CSRF_TOKEN);

    fetch('api/ContratoController.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(resp => {
            if (resp.trim() === 'sucesso') {
                location.href = 'contratos.php?sucesso=' + encodeURIComponent('Contrato renovado com sucesso.');
            } else {
                alert(resp);
            }
        });
});

function dataHojeISO() {
    const data = new Date();
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

function abrirEncerramento(c) {
    const form = document.getElementById('form-encerramento');
    form.reset();
    form.elements.contrato_id.value = c.id;
    form.elements.data_encerramento.value = dataHojeISO();
    document.getElementById('encerrar-subtitulo').textContent = `${c.fornecedor} · ${c.servico_objeto || 'contrato'}`;
    document.getElementById('modal-encerramento').classList.remove('hidden');
    document.getElementById('modal-encerramento').classList.add('flex');
}

function fecharEncerramento() {
    document.getElementById('modal-encerramento').classList.add('hidden');
    document.getElementById('modal-encerramento').classList.remove('flex');
}

document.getElementById('form-encerramento').addEventListener('submit', function (e) {
    e.preventDefault();

    if (!confirm('Encerrar este contrato e removê-lo da lista operacional?')) return;

    const fd = new FormData(this);
    fd.append('acao', 'encerrar_contrato');
    fd.append('csrf_token', CSRF_TOKEN);

    fetch('api/ContratoController.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(resp => {
            if (resp.trim() === 'sucesso') {
                location.href = 'contratos.php?sucesso=' + encodeURIComponent('Contrato encerrado e arquivado com sucesso.');
            } else {
                alert(resp);
            }
        });
});

// =====================================================================
// AÇÕES DE FLUXO (AJAX)
// =====================================================================
// O menu de Gerenciar é movido temporariamente para o <body> quando aberto.
// Isso evita que o overflow/scroll da tabela e a coluna sticky de Ações recortem o menu.
const origemMenusGerenciamento = new Map();
let menuGerenciamentoAberto = null;

function restaurarMenuGerenciamento(menu) {
    if (!menu) return;
    menu.classList.add('hidden');
    menu.style.left = '';
    menu.style.top = '';
    menu.style.zIndex = '';

    const origem = origemMenusGerenciamento.get(menu.id);
    if (origem && origem.isConnected) origem.appendChild(menu);
    origemMenusGerenciamento.delete(menu.id);

    if (menuGerenciamentoAberto === menu) menuGerenciamentoAberto = null;
}

function fecharMenusGerenciamento() {
    if (menuGerenciamentoAberto) {
        restaurarMenuGerenciamento(menuGerenciamentoAberto);
        return;
    }
    document.querySelectorAll('.menu-gerenciamento-opcoes').forEach(restaurarMenuGerenciamento);
}

function posicionarMenuGerenciamento(menu, botao) {
    const retangulo = botao.getBoundingClientRect();
    const margem = 12;
    const largura = Math.max(224, menu.offsetWidth || 224);

    let esquerda = retangulo.right - largura;
    esquerda = Math.max(margem, Math.min(window.innerWidth - largura - margem, esquerda));

    menu.style.left = `${Math.round(esquerda)}px`;
    menu.style.top = `${Math.round(retangulo.bottom + 7)}px`;
    menu.style.zIndex = '99999';

    const altura = menu.getBoundingClientRect().height;
    if (retangulo.bottom + altura + margem > window.innerHeight) {
        menu.style.top = `${Math.max(margem, Math.round(retangulo.top - altura - 7))}px`;
    }
}

function alternarMenuGerenciamento(event, menuId) {
    event.preventDefault();
    event.stopPropagation();

    const botao = event.currentTarget;
    const menu = document.getElementById(menuId);
    if (!menu) return;

    const estavaFechado = menu.classList.contains('hidden') || menuGerenciamentoAberto !== menu;
    fecharMenusGerenciamento();
    if (!estavaFechado) return;

    const origem = botao.closest('.menu-gerenciamento');
    if (origem) origemMenusGerenciamento.set(menu.id, origem);

    // Portal para fora da área com overflow/sticky.
    document.body.appendChild(menu);
    menu.classList.remove('hidden');
    menuGerenciamentoAberto = menu;
    posicionarMenuGerenciamento(menu, botao);
}

document.addEventListener('click', event => {
    if (!event.target.closest('.menu-gerenciamento') && !event.target.closest('.menu-gerenciamento-opcoes')) {
        fecharMenusGerenciamento();
    }
});

// Ao rolar a tabela ou redimensionar a janela, fecha o menu para ele nunca ficar solto.
const tabelaScrollGerenciamento = document.getElementById('tabela-scroll');
if (tabelaScrollGerenciamento) {
    tabelaScrollGerenciamento.addEventListener('scroll', fecharMenusGerenciamento, { passive: true });
}
window.addEventListener('resize', fecharMenusGerenciamento, { passive: true });

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
// FILTROS / ORDENAÇÃO GERENCIAL - V6
// =====================================================================
const inputBusca      = document.getElementById('busca-contrato');
const selectSetor     = document.getElementById('filtro-setor');
const selectSituacao  = document.getElementById('filtro-situacao');
const corpoTabela     = document.getElementById('corpo-tabela-contratos');
const tabelaScroll    = document.getElementById('tabela-scroll');
const linhas          = Array.from(document.querySelectorAll('.linha-contrato'));
const itensPorPagina  = document.getElementById('itens-por-pagina');
const botaoPrimeira   = document.getElementById('pagina-primeira');
const botaoAnterior   = document.getElementById('pagina-anterior');
const botaoProxima    = document.getElementById('proxima-pagina');
const botaoUltima     = document.getElementById('pagina-ultima');
const paginacaoNumeros = document.getElementById('paginacao-numeros');
const botaoPriorizar  = document.getElementById('priorizar-vencimentos');
const kpiBotoes       = Array.from(document.querySelectorAll('.kpi-filtro'));

let paginaAtual = 1;
let totalPaginasAtual = 1;
let colunaOrdenacao = 'prioridade';
let direcaoOrdenacao = 1;

function linhasFiltradas() {
    const termo = inputBusca.value.toLowerCase().trim();
    const setor = selectSetor ? selectSetor.value.toLowerCase().trim() : '';
    const situacao = selectSituacao.value;

    return linhas.filter(linha => {
        const situacoesLinha = (linha.dataset.situacao || '').split(' ').filter(Boolean);
        const bateuNome = termo === '' || (linha.dataset.nome || '').includes(termo);
        const bateuSetor = setor === '' || (linha.dataset.setor || '').toLowerCase() === setor;
        const bateuSituacao = situacao === '' || situacoesLinha.includes(situacao);
        return bateuNome && bateuSetor && bateuSituacao;
    });
}

function valorOrdenacao(linha, coluna) {
    if (coluna === 'prioridade') return Number(linha.dataset.alertaPrioridade ?? 99);
    if (coluna === 'valor') return Number(linha.dataset.valor || 0);
    if (coluna === 'situacao') return linha.dataset.situacao || '';
    if (coluna === 'vigencia') return linha.dataset.vigencia || '9999-12-31';
    return (linha.dataset[coluna] || '').toLocaleLowerCase('pt-BR');
}

function compararLinhas(a, b) {
    if (colunaOrdenacao === 'prioridade') {
        const prioridadeA = Number(a.dataset.alertaPrioridade ?? 99);
        const prioridadeB = Number(b.dataset.alertaPrioridade ?? 99);
        if (prioridadeA !== prioridadeB) return (prioridadeA - prioridadeB) * direcaoOrdenacao;

        const vigenciaA = a.dataset.vigencia || '9999-12-31';
        const vigenciaB = b.dataset.vigencia || '9999-12-31';
        const cmpData = vigenciaA.localeCompare(vigenciaB);
        if (cmpData !== 0) return cmpData;

        return (a.dataset.fornecedor || '').localeCompare(b.dataset.fornecedor || '', 'pt-BR');
    }

    const va = valorOrdenacao(a, colunaOrdenacao);
    const vb = valorOrdenacao(b, colunaOrdenacao);
    const cmp = typeof va === 'number'
        ? va - vb
        : String(va).localeCompare(String(vb), 'pt-BR', { numeric: true });
    return cmp * direcaoOrdenacao;
}

function rolarTabelaParaTopo() {
    if (tabelaScroll) tabelaScroll.scrollTo({ top: 0, behavior: 'smooth' });
}

function marcarKpiAtivo() {
    const situacao = selectSituacao.value;
    kpiBotoes.forEach(botao => {
        const filtro = botao.dataset.filtroKpi;
        let ativo = false;
        if (filtro === 'todos') ativo = situacao === '';
        else if (filtro === 'alerta') ativo = ['alerta', 'vencido', 'vencendo'].includes(situacao);
        else ativo = situacao === filtro;

        botao.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        botao.classList.toggle('ring-2', ativo);
        botao.classList.toggle('ring-offset-1', ativo);
        botao.classList.toggle('ring-blue-300', ativo && !['alerta', 'incompleto'].includes(filtro));
        botao.classList.toggle('ring-amber-300', ativo && filtro === 'alerta');
        botao.classList.toggle('ring-rose-300', ativo && filtro === 'incompleto');
    });
}

function renderizarPaginacao(totalPaginas) {
    totalPaginasAtual = totalPaginas;
    paginacaoNumeros.innerHTML = '';

    const inicio = Math.max(1, Math.min(paginaAtual - 2, totalPaginas - 4));
    const fim = Math.min(totalPaginas, Math.max(5, paginaAtual + 2));

    for (let pagina = inicio; pagina <= fim; pagina++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = String(pagina);
        btn.className = pagina === paginaAtual
            ? 'min-w-8 rounded-lg bg-navy-900 px-2.5 py-1.5 font-black text-white shadow-sm'
            : 'min-w-8 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 font-bold text-slate-600 hover:bg-slate-100';
        btn.addEventListener('click', () => {
            paginaAtual = pagina;
            aplicarFiltros(true);
        });
        paginacaoNumeros.appendChild(btn);
    }
}

function aplicarFiltros(rolarTopo = false) {
    const filtradas = linhasFiltradas();
    filtradas.sort(compararLinhas);
    filtradas.forEach(linha => corpoTabela.appendChild(linha));

    const porPagina = Number(itensPorPagina.value || 20);
    const totalPaginas = Math.max(1, Math.ceil(filtradas.length / porPagina));
    paginaAtual = Math.min(Math.max(1, paginaAtual), totalPaginas);

    const inicio = (paginaAtual - 1) * porPagina;
    const visiveis = new Set(filtradas.slice(inicio, inicio + porPagina));
    linhas.forEach(linha => linha.style.display = visiveis.has(linha) ? '' : 'none');

    const primeiro = filtradas.length ? inicio + 1 : 0;
    const ultimo = Math.min(inicio + porPagina, filtradas.length);
    document.getElementById('resumo-paginacao').textContent = `Exibindo ${primeiro}–${ultimo} de ${filtradas.length} contrato(s)`;
    document.getElementById('pagina-atual').textContent = `Página ${paginaAtual} de ${totalPaginas}`;

    botaoPrimeira.disabled = paginaAtual <= 1;
    botaoAnterior.disabled = paginaAtual <= 1;
    botaoProxima.disabled = paginaAtual >= totalPaginas;
    botaoUltima.disabled = paginaAtual >= totalPaginas;
    renderizarPaginacao(totalPaginas);
    marcarKpiAtivo();

    if (botaoPriorizar) {
        const ativo = colunaOrdenacao === 'prioridade';
        botaoPriorizar.classList.toggle('ring-2', ativo);
        botaoPriorizar.classList.toggle('ring-amber-300', ativo);
    }

    if (rolarTopo) rolarTabelaParaTopo();
}

function reiniciarFiltros() {
    paginaAtual = 1;
    aplicarFiltros(true);
}

inputBusca.addEventListener('input', reiniciarFiltros);
if (selectSetor) selectSetor.addEventListener('change', reiniciarFiltros);
selectSituacao.addEventListener('change', reiniciarFiltros);
itensPorPagina.addEventListener('change', reiniciarFiltros);

botaoPrimeira.addEventListener('click', () => {
    if (paginaAtual !== 1) {
        paginaAtual = 1;
        aplicarFiltros(true);
    }
});
botaoAnterior.addEventListener('click', () => {
    if (paginaAtual > 1) {
        paginaAtual--;
        aplicarFiltros(true);
    }
});
botaoProxima.addEventListener('click', () => {
    if (paginaAtual < totalPaginasAtual) {
        paginaAtual++;
        aplicarFiltros(true);
    }
});
botaoUltima.addEventListener('click', () => {
    if (paginaAtual !== totalPaginasAtual) {
        paginaAtual = totalPaginasAtual;
        aplicarFiltros(true);
    }
});

if (botaoPriorizar) {
    botaoPriorizar.addEventListener('click', () => {
        colunaOrdenacao = 'prioridade';
        direcaoOrdenacao = 1;
        paginaAtual = 1;
        aplicarFiltros(true);
    });
}

kpiBotoes.forEach(botao => {
    botao.addEventListener('click', () => {
        const filtro = botao.dataset.filtroKpi || 'todos';

        // O clique no KPI é um atalho gerencial: limpa os demais filtros para mostrar a categoria inteira.
        inputBusca.value = '';
        if (selectSetor) selectSetor.value = '';
        selectSituacao.value = filtro === 'todos' ? '' : filtro;

        colunaOrdenacao = 'prioridade';
        direcaoOrdenacao = 1;
        paginaAtual = 1;
        aplicarFiltros(true);
    });
});

document.querySelectorAll('.ordenar-coluna').forEach(botao => {
    botao.addEventListener('click', () => {
        const coluna = botao.dataset.coluna;
        direcaoOrdenacao = colunaOrdenacao === coluna ? direcaoOrdenacao * -1 : 1;
        colunaOrdenacao = coluna;
        paginaAtual = 1;
        aplicarFiltros(true);
    });
});

aplicarFiltros(false);
</script>

<?php include 'includes/footer.php'; ?>
