<?php 
require_once 'config.php';
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

// 1. INFORMAÇÕES BÁSICAS DO USUÁRIO
$user_id = $_SESSION['user_id'] ?? 0;
$setor_usuario = strtoupper($_SESSION['setor_principal'] ?? '');
$is_admin = $_SESSION['is_admin'] ?? false;

// 2. BUSCA AS PASTAS EXTRAS QUE ELE TEM PERMISSÃO
$pastas_extras = [];
if ($user_id > 0) {
    $stmt_perm = $pdo_intra->prepare("SELECT pasta_nome FROM permissoes_pastas WHERE user_id = ?");
    $stmt_perm->execute([$user_id]);
    $pastas_extras = $stmt_perm->fetchAll(PDO::FETCH_COLUMN);
    $pastas_extras = array_map('strtoupper', $pastas_extras); 
}

// 🎬 3. OS CURSOS (O "Netflix" Local)
// IMPORTANTE: Ajuste o caminho em 'arquivo' para bater certinho com o nome do seu MP4!
$setores = [
    'TI' => [
        ['n' => '132', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  - 132 - Parâmetros da presidência.mp4'],
        ['n' => '131', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  131 - Permitir Acesso a Dados.mp4'],
        ['n' => '300', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  300 - Atualizar Funções de Venda.mp4'],
        ['n' => '500', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  500 - Atualizar Procedure.mp4'],
        ['n' => '506', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  506 - Executar Atualização Mensal.mp4'],
        ['n' => '507', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  507 - Executar Atualização Eventual.mp4'],
        ['n' => '528', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  528 - Cadastrar FuncSetor - Parte I.mp4'],
        ['n' => '528', 'p' => '2', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  528 - Cadastrar FuncSetor - Parte II.mp4'],
        ['n' => '528', 'p' => '3', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  528 - Cadastrar FuncSetor - Parte III.mp4'],
        ['n' => '530', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  530 - Permitir Acesso a Rotina - Parte I.mp4'],
        ['n' => '530', 'p' => '2', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  530 - Permitir Acesso a Rotina - Parte II.mp4'],
        ['n' => '532', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  532 - Cadastrar Parâmetro do Sistema.mp4'],
        ['n' => '535', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  535 - Cadastrar Filiais.mp4'],
        ['n' => '552', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  552 - Executar Atualização Semanal.mp4'],
        ['n' => '555', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  555 - Cadastrar Distribuição.mp4'],
        ['n' => '814', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  814- Atualização objeto de banco de dados.mp4'],
        ['n' => '1400', 'p' => '1', 'arquivo' => 'ROTINAS TI/Certificação TOTVS Distribuição e Varejo - Linha Winthor  1400 - Atualizar Procedures.mp4'],
    ],
    'TELEVENDAS' => [
        ['n' => '308', 'p' => '1', 'arquivo' => 'ROTINAS TELEVENDAS/Certificação TOTVS Distribuição e Varejo - Linha Winthor  308 - Cadastrar Cond Comerciais Cliente.mp4'],
        ['n' => '519', 'p' => '1', 'arquivo' => 'ROTINAS TELEVENDAS/Certificação TOTVS Distribuição e Varejo - Linha Winthor  519 - Cadastrar Regiões de Venda.mp4'],
        ['n' => '522', 'p' => '1', 'arquivo' => 'ROTINAS TELEVENDAS/Certificação TOTVS Distribuição e Varejo - Linha Winthor  522 - Cadastrar Cobrança - Parte I.mp4'],
        ['n' => '522', 'p' => '2', 'arquivo' => 'ROTINAS TELEVENDAS/Certificação TOTVS Distribuição e Varejo - Linha Winthor  522 - Cadastrar Cobrança - Parte II.mp4'],
        ['n' => '522', 'p' => '3', 'arquivo' => 'ROTINAS TELEVENDAS/Certificação TOTVS Distribuição e Varejo - Linha Winthor  522 - Cadastrar Cobrança - Parte III.mp4'],
        ['n' => '522', 'p' => '4', 'arquivo' => 'ROTINAS TELEVENDAS/Certificação TOTVS Distribuição e Varejo - Linha Winthor  522 - Cadastrar Cobrança - Parte IV.mp4'],
        ['n' => '523', 'p' => '1', 'arquivo' => 'ROTINAS TELEVENDAS/Certificação TOTVS Distribuição e Varejo - Linha Winthor  523 - Cadastrar Planos Pgtos - Parte I.mp4'],
    ],
    'LOGISTICA' => [
        ['n' => '518', 'p' => '1', 'arquivo' => 'ROTINAS LOGISTICA/Certificação TOTVS Distribuição e Varejo - Linha Winthor  518 - Cadastrar Motivo de Devolução.mp4'],
        ['n' => '520', 'p' => '1', 'arquivo' => 'ROTINAS LOGISTICA/Certificação TOTVS Distribuição e Varejo - Linha Winthor  520 - Cadastrar Rotas de Entrega.mp4'],
        ['n' => '521', 'p' => '1', 'arquivo' => 'ROTINAS LOGISTICA/Certificação TOTVS Distribuição e Varejo - Linha Winthor  521 - Cadastrar Veículos.mp4'],
        ['n' => '572', 'p' => '1', 'arquivo' => 'ROTINAS LOGISTICA/Certificação TOTVS Distribuição e Varejo - Linha Winthor  572 - Cadastrar Praças.mp4'],
        ['n' => '929', 'p' => '1', 'arquivo' => 'ROTINAS LOGISTICA/Certificação TOTVS Distribuição e Varejo - Linha Winthor  929 - Cadastrar Motorista.mp4'],
    ],
    'FISCAL' => [
        ['n' => '212', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  212 - Cadastrar Trib. Entrada - Parte I.mp4'],
        ['n' => '212', 'p' => '2', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  212 - Cadastrar Trib. Entrada - Parte II.mp4'],
        ['n' => '212', 'p' => '3', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  212 - Cadastrar Trib. Entrada - Parte III.mp4'],
        ['n' => '212', 'p' => '5', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  212 - Cadastrar Trib. Entrada - Parte V.mp4'],
        ['n' => '212', 'p' => '6', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  212 - Cadastrar Trib. Entrada - Parte VI.mp4'],
        ['n' => '212', 'p' => '7', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  212 - Cadastrar Trib. Entrada - Parte VII.mp4'],
        ['n' => '514', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  514 - Cadastrar Tipo  de Trib - Parte I.mp4'],
        ['n' => '514', 'p' => '2', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  514 - Cadastrar Tipo  de Trib - Parte II.mp4'],
        ['n' => '514', 'p' => '3', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  514 - Cadastrar Tipo  de Trib - Parte III.mp4'],
        ['n' => '543', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  543 - Cadastrar Código Fiscal (CFOP).mp4'],
        ['n' => '574', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  574 - Cadastrar Tributação nos Produtos.mp4'],
        ['n' => '580', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  580 - Cadastrar NCM.mp4'],
        ['n' => '3328', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  3328 - Cadastrar Tributos por FilialNCM.mp4'],
        ['n' => '4001', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  4001 - Cadastrar Tributação de PISCOFINS.mp4'],
        ['n' => '4004', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  4004 - Cadastrar CEST.mp4'],
        ['n' => '271', 'p' => '1', 'arquivo' => 'ROTINAS FISCAL/Certificação TOTVS Distribuição e Varejo -Linha Winthor  271-Cadastrar Tributação de Produtos Venda.mp4'],

    ],
    'FINANCEIRO' => [
        ['n' => '524', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  524 - Cadastrar Caixas e Contas Bancárias.mp4'],
        ['n' => '525', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  525 - Cadastrar Ocorrências Bancárias.mp4'],
        ['n' => '526', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  526 - Cadastrar Grupo de Conta Gerencial.mp4'],
        ['n' => '527', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  527 - Cadastrar Moedas.mp4'],
        ['n' => '536', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  536 - Cadastrar Histórico Padrão.mp4'],
        ['n' => '570', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  570 - Cadastrar Conta Gerencial.mp4'],
        ['n' => '1520', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  1520 -Manter dados de integração bancária.mp4'],
        ['n' => '1521', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  1521 - Layout integração bancária.mp4'],
    ],
    'FINANCEIRO 2' => [
        ['n' => '512', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO2/Certificação TOTVS Distribuição e Varejo - Linha Winthor  512 - Cadastrar Ramos de Atividade.mp4'],
        ['n' => '1203', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO2/Certificação TOTVS Distribuição e Varejo - Linha Winthor  1203 - Definir Limite de Crédito Cobrança.mp4'],
        ['n' => '3314', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO2/Certificação TOTVS Distribuição e Varejo - Linha Winthor  3314 - Cadastrar Tabela Preço Cliente.mp4'],
        ['n' => '3324', 'p' => '1', 'arquivo' => 'ROTINAS FINANCEIRO2/Certificação TOTVS Distribuição e Varejo - Linha Winthor  3324 - Cadastrar endereço de entrega.mp4'],
    ],
    'COMPRAS' => [
        ['n' => '1302', 'p' => '1', 'arquivo' => 'ROTINAS COMPRAS/TOTVS Distribuição e Varejo (Linha WinThor)  Fluxo de Compras  Devolução Fornecedor (Rotina 1302).mp4'],
        ['n' => '220', 'p' => '1', 'arquivo' => 'ROTINAS COMPRAS/TOTVS Distribuição e Varejo (Linha WinThor)  Fluxo de Compras  Digitar Pedido Compra (Rotina 220).mp4'],
        ['n' => '1106', 'p' => '1', 'arquivo' => 'ROTINAS COMPRAS/TOTVS Distribuição e Varejo (Linha WinThor)  Fluxo de Compras  Montar Bônus (Rotina 1106).mp4'],
        ['n' => '201', 'p' => '1', 'arquivo' => 'ROTINAS COMPRAS/TOTVS Distribuição e Varejo (Linha WinThor)  Fluxo de Compras  Precificar Produto (Rotina 201).mp4'],
        ['n' => '1301', 'p' => '1', 'arquivo' => 'ROTINAS COMPRAS/TOTVS Distribuição e Varejo (Linha WinThor)  Fluxo de Compras  Receber Mercadoria (Rotina 1301).mp4'],
    ],
    'COMERCIAL' => [
        ['n' => '516', 'p' => '1', 'arquivo' => 'ROTINAS COMERCIAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  516 - Cadastrar Supervisores de Venda.mp4'],
        ['n' => '517', 'p' => '1', 'arquivo' => 'ROTINAS COMERCIAL/Certificação TOTVS Distribuição e Varejo - Linha Winthor  517 - Cadastrar Vendedores (RCA).mp4'],
    ],
    'CADASTRO' => [
        ['n' => '202', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  202 - Cadastrar FornecTransp  - Parte I.mp4'],
        ['n' => '202', 'p' => '2', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  202 - Cadastrar FornecTransp  - Parte II.mp4'],
        ['n' => '203', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  203 - Cadastrar Produto - Parte I.mp4'],
        ['n' => '203', 'p' => '2', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  203 - Cadastrar Produto - Parte II.mp4'],
        ['n' => '203', 'p' => '3', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  203 - Cadastrar Produto - Parte III.mp4'],
        ['n' => '203', 'p' => '4', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  203 - Cadastrar Produto - Parte IV.mp4'],
        ['n' => '204', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  204 - Cadastrar Unidades.mp4'],
        ['n' => '256', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  256 - Cadastrar Parcelas de Pagamento.mp4'],
        ['n' => '282', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  282 -Informar Dados Logísticos do Produto.mp4'],
        ['n' => '292', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  292 - Cadastrar Embalagem.mp4'],
        ['n' => '513', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  513 - Cadastrar Departamentos.mp4'],
        ['n' => '549', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  549 - Cadastrar Categoria.mp4'],
        ['n' => '559', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  559 - Cadastrar Linha de Produtos.mp4'],
        ['n' => '564', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  564 - Cadastrar Marcas de Produtos.mp4'],
        ['n' => '569', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  569 - Cadastrar Subcategoria.mp4'],
        ['n' => '571', 'p' => '1', 'arquivo' => 'ROTINAS CADASTRO/Certificação TOTVS Distribuição e Varejo - Linha Winthor  571-Cadastrar Seções de cada Departamento.mp4'],
    ],
    'WMS' => [
        ['n' => 'WMS ABASTECIMENTO (1701-1795-1723)', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS ABASTECIMENTO ROTINAS 1701-1795-1723 PART 1.mp4'],
        ['n' => 'WMS ABASTECIMENTO (1781-3714-1756-1755)', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS ABASTECIMENTO ROTINAS 1781-3714-1756-1755.mp4'],
        ['n' => 'WMS ARMAZENAGEM', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS ARMAZENAGEM ROTINAS 3713-1755.mp4'],
        ['n' => 'WMS CONFERENCIA', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS CONFERENCIA ROTINA 3724.mp4'],
        ['n' => 'WMS ENTRADAS ROTINA 1705', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS ENTRADAS ROTINA 1705.mp4'],
        ['n' => 'WMS ENTRADAS ROTINA 1757', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS ENTRADAS ROTINA 1757.mp4'],
        ['n' => 'WMS ENTRADAS ROTINA3712', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS ENTRADAS ROTINA 3712 PALETS.mp4'],
        ['n' => 'WMS ENTRADAS ROTINAS 3712-1704-1708-1756', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS ENTRADAS ROTINAS 3712-1704-1708-1756.mp4'],
        ['n' => 'WMS INVENTARIO ROTINA 1730', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS INVENTARIO ROTINA 1730 PART I.mp4'],
        ['n' => 'WMS INVENTARIO ROTINA 1730 PART II', 'p' => '2', 'arquivo' => 'ROTINAS WMS/WMS INVENTARIO ROTINA 1730 PART II.mp4'],
        ['n' => 'WMS INVENTARIO ROTINA 1731', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS INVENTARIO ROTINA 1731.mp4'],
        ['n' => 'WMS INVENTARIO ROTINA 1737', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS INVENTARIO ROTINA 1737.mp4'],
        ['n' => 'WMS PESO VARIAVEL PARAMETROS ROTINA 1795-1701', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS PESO VARIAVEL PARAMETROS ROTINA 1795-1701.mp4'],
        ['n' => 'WMS PESO VARIAVEL RECEBIMENTO ROTINA 1771-3712', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS PESO VARIAVEL RECEBIMENTO ROTINA 1771-3712.mp4'],
        ['n' => 'WMS PESO VARIAVEL SAIDAS PART 1', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS PESO VARIAVEL SAIDAS PART 1.mp4'],
        ['n' => 'WMS PESO VARIAVEL SAIDAS PART 2', 'p' => '2', 'arquivo' => 'ROTINAS WMS/WMS PESO VARIAVEL SAIDAS PART 2.mp4'],
        ['n' => 'WMS PESOS VARIAVEIS ROTINA 1760', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS PESOS VARIAVEIS ROTINA 1760.mp4'],
        ['n' => 'WMS PROCESSO DE AVARIA ROTINA 1721-1747-1725-1756-1755-PART 1', 'p' => '1', 'arquivo' => 'WMS PROCESSO DE AVARIA ROTINA 1795-518-1702-1721-1781-1756-1755-PART 1.mp4'],
        ['n' => 'WMS PROCESSO DE AVARIA ROTINA 1721-1747-1725-1756-1755-PART 2', 'p' => '2', 'arquivo' => 'ROTINAS WMS/WMS PROCESSO DE AVARIA ROTINA 1721-1747-1725-1756-1755-PART 2.mp4'],
        ['n' => 'WMS ROTINAS 3712-1704-1708-1756', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS ROTINAS 3712-1704-1708-1756.mp4'],
        ['n' => 'WMS SAIDAS ROTINA 1751 - PART 1', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS SAIDAS ROTINA 1751 - PART 1.mp4'],
        ['n' => 'WMS SAIDAS ROTINA 1751 - PART 2', 'p' => '2', 'arquivo' => 'ROTINAS WMS/WMS SAIDAS ROTINA 1751 - PART 2.mp4'],
        ['n' => 'WMS SAIDAS ROTINA 3734 PART 4', 'p' => '4', 'arquivo' => 'ROTINAS WMS/WMS SAIDAS ROTINA 3734 PART 4.mp4'],
        ['n' => 'WMS SAIDAS ROTINAS 1743-1767-3735 PART 1', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS SAIDAS ROTINAS 1743-1767-3735 PART 1.mp4'],
        ['n' => 'WMS SAIDAS ROTINAS 1757-1725-1756-3735-1755 PART 4', 'p' => '4', 'arquivo' => 'ROTINAS WMS/WMS SAIDAS ROTINAS 1757-1725-1756-3735-1755 PART 4.mp4'],
        ['n' => 'WMS SAIDAS ROTINAS 1757-1752-1756-1781 PART 3', 'p' => '3', 'arquivo' => 'ROTINAS WMS/WMS SAIDAS ROTINAS 1757-1752-1756-1781 PART 3.mp4'],
        ['n' => 'WMS SAIDAS ROTINAS 1759-1762-1728-1754 PART 2', 'p' => '2', 'arquivo' => 'ROTINAS WMS/WMS SAIDAS ROTINAS 1759-1762-1728-1754 PART 2.mp4'],
        ['n' => 'WMS TRANSFERENCIA DE ENDEREÇOS ROTINA 1709', 'p' => '1', 'arquivo' => 'ROTINAS WMS/WMS TRANSFERENCIA DE ENDEREÇOS ROTINA 1709.mp4'],
    ],
];

// 4. MÁGICA DE PERMISSÕES
$setores_permitidos = [];
foreach(array_keys($setores) as $s) {
    $s_upper = strtoupper($s);
    if( $is_admin || 
        $setor_usuario === $s_upper || 
        in_array($s_upper, $pastas_extras) || 
        ($s_upper === 'TI' && in_array('FACILITIES & TI', $pastas_extras))
    ) {
        $setores_permitidos[] = $s;
    }
}
$setor_inicial = !empty($setores_permitidos) ? $setores_permitidos[0] : '';
?>

<main class="flex-1 bg-slate-50 p-8 overflow-y-auto">
    <div class="max-w-[95%] 2xl:max-w-[1600px] mx-auto mb-8">
        <h1 id="main-title" class="text-3xl font-black text-navy-900 italic uppercase tracking-tighter">Academia Winthor</h1>
        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Selecione um setor para iniciar as rotinas</p>
        
        <div class="flex gap-2 mt-6 overflow-x-auto pb-2 custom-scrollbar">
            <?php foreach($setores_permitidos as $setor): ?>
                <button onclick="filtrarSetor('<?= $setor ?>')" class="btn-setor px-6 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition-all shadow-sm whitespace-nowrap">
                    <?= $setor ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if(!empty($setor_inicial)): ?>
    <div class="max-w-[95%] 2xl:max-w-[1600px] mx-auto grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-black rounded-[2.5rem] shadow-2xl overflow-hidden border-4 border-white aspect-video relative flex items-center justify-center group">
                <video id="videoPlayer" class="w-full h-full outline-none" controls controlsList="nodownload" preload="metadata">
                    <source src="" type="video/mp4">
                    Seu navegador não suporta vídeos HTML5.
                </video>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-200">
                <h3 id="video-title" class="font-bold text-lg text-navy-900 mb-2">Carregando...</h3>
                <p class="text-slate-600 text-sm italic">Treinamento oficial de rotinas operacionais.</p>
            </div>
        </div>

        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-200">
                <h3 class="text-xs font-black text-slate-400 uppercase mb-4 tracking-widest">Playlist do Setor</h3>
                <div id="playlist-container" class="space-y-2 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                    </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="max-w-[95%] 2xl:max-w-[1600px] mx-auto text-center py-20 bg-white rounded-[2.5rem] border border-slate-200 shadow-sm mt-8">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">🎓</div>
        <h2 class="text-xl font-black text-navy-900 uppercase">Nenhum treinamento disponível</h2>
        <p class="text-slate-500 mt-2 font-medium">Os treinamentos do seu setor ainda não foram cadastrados na Academia Winthor ou você não possui permissão de acesso.</p>
    </div>
    <?php endif; ?>

</main>

<script>
    const dadosCursos = <?= json_encode($setores) ?>;
    const setorInicial = '<?= $setor_inicial ?>'; 

    function filtrarSetor(setor) {
        if(!setor || !dadosCursos[setor]) return;

        const container = document.getElementById('playlist-container');
        container.innerHTML = ''; 

        dadosCursos[setor].forEach((aula, index) => {
            const item = document.createElement('div');
            item.className = "p-4 bg-slate-50 border border-transparent rounded-2xl flex items-center gap-3 hover:bg-blue-50 hover:border-blue-100 cursor-pointer transition-all group";
            
            // 🎬 Passa o caminho do arquivo no clique
            item.onclick = () => carregarVideo(aula.arquivo, aula.n, aula.p);
            
            item.innerHTML = `
                <span class="w-8 h-8 rounded-lg bg-white text-slate-400 group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center text-xs font-black shadow-sm transition-all shrink-0">▶</span>
                <div class="overflow-hidden">
                    <p class="text-[10px] font-black text-navy-900 uppercase truncate">Rotina ${aula.n}</p>
                    <p class="text-[9px] text-slate-400 font-bold group-hover:text-blue-600">PARTE ${aula.p}</p>
                </div>
            `;
            container.appendChild(item);
        });

        // Carrega o primeiro vídeo da playlist
        if(dadosCursos[setor].length > 0) {
            const primeira = dadosCursos[setor][0];
            carregarVideo(primeira.arquivo, primeira.n, primeira.p);
        }
    }

    function carregarVideo(arquivo_mp4, rotina, parte) {
        const player = document.getElementById('videoPlayer');
        
        // 🎬 O caminho mágico! Aponta para a pasta "videos" dentro do seu sistema
        player.src = `videos/${arquivo_mp4}`; 
        
        player.load(); // Força o HTML a ler o novo vídeo
        
        // Tenta dar o play automático (Alguns navegadores bloqueiam play automático com som, então usamos o catch)
        player.play().catch(e => console.log("Usuário precisa dar o play manualmente.", e)); 
        
        document.getElementById('video-title').innerText = `Rotina ${rotina} - Parte ${parte}`;
    }

    window.onload = () => {
        if(setorInicial) filtrarSetor(setorInicial);
    };
</script>