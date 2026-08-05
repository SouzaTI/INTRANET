<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fornecedor'])) {
    try {
        $fornecedor  = $_POST['fornecedor'] ?? null;
        $empresa     = $_POST['empresa'] ?? null;
        $servico     = $_POST['servico'] ?? null;
        $valor       = $_POST['valor'] ?? null;
        $dadosExtras = $_POST['dados_extras_json'] ?? '{}';

        if (empty($fornecedor)) {
            throw new Exception('O campo Fornecedor/Razão Social é obrigatório.');
        }

        // 4. Tratamento do Upload do Documento (PDF) direcionado para a pasta criada
        $caminho_documento = null;
        if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
            $pasta_destino = "upload_pdf/PDF/";
            if (!is_dir($pasta_destino)) {
                mkdir($pasta_destino, 0755, true);
            }
            $nome_arquivo = time() . "_" . basename($_FILES['documento']['name']);
            $caminho_destino = $pasta_destino . $nome_arquivo;
            
            if (move_uploaded_file($_FILES['documento']['tmp_name'], $caminho_destino)) {
                $caminho_documento = $caminho_destino;
            }
        }

        // Mapeando os dados para as colunas reais da tabela (com setor_contrato e documento)
        $sql = "INSERT INTO intranet.contratos (
                    razao_social, nome_fantasia, contato, codigo_sistema, servico, 
                    cnpj, valor, prazo, qtd_pagamentos, data_inicio, 
                    tipo_vencimento, data_final, dia_venc_recorrente, renovacao_automatica, 
                    aviso_previo, multa, clausula_tecnica, empresa, setor, documento, status
                ) VALUES (
                    :razao_social, :nome_fantasia, :contato, :codigo_sistema, :servico, 
                    :cnpj, :valor, :prazo, :qtd_pagamentos, :data_inicio, 
                    :tipo_vencimento, :data_final, :dia_venc_recorrente, :renovacao_automatica, 
                    :aviso_previo, :multa, :clausula_tecnica, :empresa, :setor, :documento, :status
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':razao_social'         => $_POST['fornecedor'] ?? null,
            ':nome_fantasia'        => $_POST['nome_fantasia'] ?? null,
            ':contato'              => $_POST['contato'] ?? null,
            ':codigo_sistema'       => $_POST['codigo_sistema'] ?? null,
            ':servico'              => $_POST['servico'] ?? null,
            ':cnpj'                 => $_POST['cnpj'] ?? null,
            ':valor'                => $_POST['valor'] ?? null,
            ':prazo'                => $_POST['prazo'] ?? null,
            ':qtd_pagamentos'       => $_POST['qtd_pagamentos'] ?? null,
            ':data_inicio'          => !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null,
            ':tipo_vencimento'      => $_POST['tipo_vencimento'] ?? 'unico',
            ':data_final'           => !empty($_POST['data_vencimento']) ? $_POST['data_vencimento'] : null,
            ':dia_venc_recorrente'  => !empty($_POST['dia_vencimento_recorrente']) ? $_POST['dia_vencimento_recorrente'] : null,
            ':renovacao_automatica' => isset($_POST['renovacao_automatica']) ? 1 : 0,
            ':aviso_previo'         => $_POST['aviso_previo'] ?? null,
            ':multa'                => $_POST['multa'] ?? null,
            ':clausula_tecnica'     => $_POST['clausula_tecnica'] ?? null,
            ':empresa'              => $_POST['empresa'] ?? null,
            ':setor'                => !empty($_POST['setor_contrato']) ? $_POST['setor_contrato'] : 'GERAL', // <--- Pega o input hidden certo do form!
            ':documento'            => $caminho_documento, // <--- Aqui entra o PDF tratado!
            ':status'               => 'ATIVO'
        ]);

        header("Location: painel_contratos.php");
        exit;

    } catch (Exception $e) {
        echo "<script>alert('Erro ao salvar no banco: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
}

$setores_contrato = [
    'rh' => [
        'label' => 'Recursos Humanos', 'sigla' => 'RH', 'icone' => '🧑‍💼', 'cor_bg' => '#d1fae5', 'cor_text' => '#065f46', 'cor_border' => '#6ee7b7',
        'campos' => [
            ['id'=>'qtd_vidas','label'=>'Quantidade de vidas','tipo'=>'number','placeholder'=>'Ex: 320'],
            ['id'=>'lgpd','label'=>'Contém dados sensíveis (LGPD)','tipo'=>'switch'],
        ],
    ],
    'fiscal' => [
        'label' => 'Fiscal', 'sigla' => 'Fiscal', 'icone' => '🧾', 'cor_bg' => '#fef3c7', 'cor_text' => '#92400e', 'cor_border' => '#fcd34d',
        'campos' => [
            ['id'=>'regime_tributario','label'=>'Regime tributário do parceiro','tipo'=>'select','opcoes'=>['Simples Nacional','Lucro Presumido','Lucro Real','MEI']],
            ['id'=>'retencao_fonte','label'=>'Haverá retenção na fonte?','tipo'=>'switch'],
        ],
    ],
    'facilities' => [
        'label' => 'Facilities & TI', 'sigla' => 'Facilities', 'icone' => '🛠️', 'cor_bg' => '#dbeafe', 'cor_text' => '#1e40af', 'cor_border' => '#93c5fd',
        'campos' => [
            ['id'=>'periodicidade','label'=>'Periodicidade da manutenção','tipo'=>'select','opcoes'=>['Mensal','Bimestral','Trimestral','Semestral','Anual']],
            ['id'=>'trabalho_altura','label'=>'Exige trabalho em altura (NR-35)?','tipo'=>'switch'],
        ],
    ],
    'marketing' => [
        'label' => 'Marketing', 'sigla' => 'Mkt', 'icone' => '📣', 'cor_bg' => '#ede9fe', 'cor_text' => '#5b21b6', 'cor_border' => '#c4b5fd',
        'campos' => [
            ['id'=>'tipo_midia','label'=>'Tipo de mídia','tipo'=>'select','opcoes'=>['Digital','Impresso','OOH','Misto']],
            ['id'=>'exclusividade','label'=>'Cláusula de exclusividade?','tipo'=>'switch'],
        ],
    ],
];

$estrutura_formulario = [
    'passo_2' => [
        'titulo' => 'Identificação do Fornecedor',
        'campos' => [
            ['id' => 'fornecedor', 'label' => 'Razão Social', 'tipo' => 'text', 'obrigatorio' => true, 'col' => 'md:col-span-2', 'placeholder' => 'Ex: Vertical Manutenção Predial Ltda.'],
            ['id' => 'nome_fantasia', 'label' => 'Nome Fantasia', 'tipo' => 'text', 'placeholder' => 'Opcional'],
            ['id' => 'cnpj', 'label' => 'CNPJ', 'tipo' => 'text', 'placeholder' => '00.000.000/0000-00', 'maxlength' => 18, 'oninput' => 'mascararCNPJ(this)'],
            ['id' => 'contato', 'label' => 'Contato (Nome / Telefone / E-mail)', 'tipo' => 'text', 'col' => 'md:col-span-2', 'placeholder' => 'Ex: João Silva — (11) 98765-4321 — joao@empresa.com'],
            ['id' => 'codigo_sistema', 'label' => 'Código no Sistema', 'tipo' => 'text', 'placeholder' => 'Ex: RH-2024-001'],
            ['id' => 'empresa', 'label' => 'Empresa do Grupo', 'tipo' => 'select_empresas', 'obrigatorio' => true],
        ]
    ],
    'passo_3' => [
        'titulo' => 'Dados do Contrato',
        'campos' => [
            ['id' => 'servico', 'label' => 'Serviço / Objeto', 'tipo' => 'text', 'obrigatorio' => true, 'col' => 'md:col-span-2', 'placeholder' => 'Ex: VALE TRANSPORTE, SISTEMA, SEG.TRABALHO…'],
            ['id' => 'valor', 'label' => 'Valor (R$)', 'tipo' => 'text', 'obrigatorio' => true, 'placeholder' => '0,00', 'oninput' => 'mascararValor(this)'],
            ['id' => 'prazo_qtd', 'label' => 'Prazo Contratual', 'tipo' => 'prazo_duplo'],
            ['id' => 'qtd_pagamentos', 'label' => 'Qtd. Pagamentos', 'tipo' => 'number', 'placeholder' => '24'],
            ['id' => 'data_inicio', 'label' => 'Data de Início', 'tipo' => 'date'],
            ['id' => 'aviso_previo', 'label' => 'Aviso Prévio (dias)', 'tipo' => 'number', 'placeholder' => 'Ex: 30'],
            ['id' => 'multa', 'label' => 'Multa Rescisória', 'tipo' => 'text', 'placeholder' => 'Ex: 10% sobre o valor total'],
            ['id' => 'clausula_tecnica', 'label' => 'Cláusula Técnica (resumo)', 'tipo' => 'textarea', 'col' => 'md:col-span-2', 'placeholder' => 'Obrigações técnicas relevantes…'],
            ['id' => 'renovacao_automatica', 'label' => 'Renovação Automática?', 'tipo' => 'switch', 'col' => 'md:col-span-2']
        ]
    ],
    'passo_4' => [
        'titulo' => 'Vencimento',
        'subtitulo' => 'Data Única para contratos com prazo definido · Recorrente Mensal para mensalidades e assinaturas sem término',
        'campos' => [
            [
                'id' => 'tipo_vencimento_selector',
                'tipo' => 'abas_vencimento',
                'opcoes' => [
                    ['id' => 'unico', 'label' => '📅 Data Única'],
                    ['id' => 'recorrente', 'label' => '🔁 Recorrente Mensal']
                ]
            ],
            [
                'id' => 'painel_unico',
                'tipo' => 'painel_condicional',
                'condicao' => 'unico',
                'campos_internos' => [
                    ['id' => 'data_vencimento', 'label' => 'Data de Vencimento', 'tipo' => 'date', 'max_width' => '220px', 'help' => 'O alerta de 60/90 dias será calculado automaticamente.']
                ]
            ],
            [
                'id' => 'painel_recorrente',
                'tipo' => 'painel_condicional',
                'condicao' => 'recorrente',
                'campos_internos' => [
                    ['id' => 'dia_vencimento_recorrente', 'label' => 'Dia de Vencimento (1–31)', 'tipo' => 'number', 'min' => 1, 'max' => 31, 'placeholder' => '15', 'oninput' => 'atualizarPreviewRecorrente(this.value)', 'help' => 'O painel calculará os dias até a próxima ocorrência automaticamente.']
                ]
            ]
        ]
    ]
];

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
    $stmt_contratos = $pdo->prepare("SELECT * FROM intranet.contratos ORDER BY CASE WHEN data_final IS NULL OR data_final = '' THEN 1 ELSE 0 END, data_final ASC");
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

  $contratos_filtrados = array_filter($lista_contratos, function($c) use ($filtro_setor, $filtro_empresa, $filtro_status, $busca, $regra_alerta) {
    if ($filtro_setor !== 'todos' && strcasecmp($c['setor'] ?? '', $filtro_setor) !== 0) return false;
    if ($filtro_empresa !== 'todas' && ($c['empresa'] ?? '') !== $filtro_empresa) return false;
    
    if ($filtro_status !== 'todos') {
        if ($filtro_status === 'ALERTA') {
            $is_rec     = ($c['tipo_vencimento'] ?? '') === 'recorrente';
            $encerrado  = ($c['status'] ?? '') === 'ENCERRADO';
            $dias       = calcularDiasRestantes($c);
            $limite     = $regra_alerta[$c['setor']] ?? 60;
            $tem_alerta = !$encerrado && !$is_rec && $dias <= $limite;
            
            if (!$tem_alerta) return false;
        } else {
            if (($c['status'] ?? '') !== $filtro_status) return false;
        }
    }
    
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
      <button onclick="abrirNovoContrato()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 px-5 rounded-2xl shadow-lg transition-all uppercase tracking-widest text-[11px] inline-flex items-center gap-2 w-fit cursor-pointer">
        ➕ Novo Contrato
    </button>
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
                <option value="todos" <?php echo $filtro_status==='todos'?'selected':''; ?>>Todos</option>
                <option value="ALERTA" <?php echo $filtro_status==='ALERTA'?'selected':''; ?>>⚠️ Alertas de Vencimento</option>
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

                <?php 
                // Inicializa o contador aqui, antes do loop rodar
                $contador = 1; 
                foreach ($contratos_filtrados as $c):
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

                    <!-- ID Sequencial Ajustado -->
                    <td class="px-5 py-4 font-mono text-xs text-slate-400"><?php echo $contador++; ?></td>

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

<!-- MODAL NOVO CONTRATO -->
<div id="modal-novo-contrato" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[200] hidden items-center justify-center px-4" onclick="fecharNovoContrato(event)">
    <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-200 max-w-4xl w-full overflow-hidden max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        
        <!-- Cabeçalho do Modal -->
        <div class="px-7 py-5 border-b border-slate-100 flex items-start justify-between shrink-0 bg-white z-10">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Gestão de Contratos</p>
                <h3 class="text-lg font-black text-navy-900 uppercase italic tracking-tighter leading-tight">Novo Cadastro</h3>
            </div>
            <button onclick="fecharNovoContrato()" class="text-slate-300 hover:text-navy-900 text-2xl leading-none mt-1">&times;</button>
        </div>

        <!-- Corpo com Rolagem e o Formulário Completo -->
        <div class="overflow-y-auto p-6 space-y-6 flex-1 bg-slate-50/50">
            <form action="painel_contratos.php" method="POST" enctype="multipart/form-data" id="form-contrato" class="space-y-6">
                <input type="hidden" name="acao" value="inserir_contrato">
                <input type="hidden" name="setor_contrato" id="input_setor_contrato" value="">
                <input type="hidden" name="tipo_vencimento" id="input_tipo_vencimento" value="unico">

                <!-- STEP 1: SELEÇÃO DE SETOR (Fixo, pois depende do loop do PHP dos setores) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Passo 1</p>
                    <p class="text-xs font-black text-navy-900 uppercase tracking-wider mb-3">Departamento Contratante</p>
                    <div class="flex flex-wrap gap-2.5" id="abas-setor">
                        <?php foreach ($setores_contrato as $key => $s): ?>
                        <button type="button" onclick="selecionarSetor('<?php echo $key; ?>')" id="tab_<?php echo $key; ?>"
                            class="setor-tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider border border-slate-200 bg-slate-50 text-slate-600 transition-all hover:bg-slate-100" 
                            data-key="<?php echo $key; ?>"
                            data-bg="<?php echo $s['cor_bg']; ?>" data-text="<?php echo $s['cor_text']; ?>" data-border="<?php echo $s['cor_border']; ?>">
                            <span><?php echo $s['icone']; ?></span>
                            <span><?php echo $s['sigla']; ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- STEP 2: IDENTIFICAÇÃO (Renderizado via JS pelo array) -->
                <div id="container-passo_2"></div>

                <!-- STEP 3: DADOS DO CONTRATO (Renderizado via JS pelo array) -->
                <div id="container-passo_3"></div>

                <!-- STEP 4: VENCIMENTO (Renderizado via JS pelo array) -->
                <div id="container-passo_4"></div>

                <!-- STEP 5: CAMPOS ESPECÍFICOS DO SETOR -->
                <?php foreach ($setores_contrato as $key => $s): ?>
                <div id="campos_<?php echo $key; ?>" style="display:none;">
                    <div class="bg-white p-5 rounded-2xl border-2 shadow-sm space-y-4" style="border-color:<?php echo $s['cor_border']; ?>;">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Passo 5</p>
                        <p class="text-xs font-black uppercase tracking-wider" style="color:<?php echo $s['cor_text']; ?>">
                            <?php echo $s['icone'].' Campos — '.$s['label']; ?>
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($s['campos'] as $c): ?>
                                <?php if ($c['tipo'] === 'number'): ?>
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1"><?php echo $c['label']; ?></label>
                                    <input type="number" name="<?php echo $c['id']; ?>" placeholder="<?php echo $c['placeholder']??''; ?>" class="w-full p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-navy-900">
                                </div>
                                <?php elseif ($c['tipo'] === 'text'): ?>
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1"><?php echo $c['label']; ?></label>
                                    <input type="text" name="<?php echo $c['id']; ?>" placeholder="<?php echo $c['placeholder']??''; ?>" class="w-full p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-navy-900">
                                </div>
                                <?php elseif ($c['tipo'] === 'select'): ?>
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1"><?php echo $c['label']; ?></label>
                                    <select name="<?php echo $c['id']; ?>" class="w-full p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-slate-600 cursor-pointer">
                                        <option value="" disabled selected>Selecione…</option>
                                        <?php foreach ($c['opcoes'] as $op): ?>
                                        <option value="<?php echo $op; ?>"><?php echo $op; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php elseif ($c['tipo'] === 'switch'): ?>
                                <div class="md:col-span-2">
                                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-200">
                                        <span class="text-xs font-black text-slate-600 uppercase tracking-wider"><?php echo $c['label']; ?></span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="<?php echo $c['id']; ?>" value="1" class="sr-only peer">
                                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- STEP 6: UPLOAD -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Passo 6</p>
                    <p class="text-xs font-black text-navy-900 uppercase tracking-wider">Documento</p>
                    <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center bg-slate-50 hover:bg-slate-100 transition-all cursor-pointer">
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">Anexe o contrato assinado em PDF</p>
                        <input type="file" name="documento" accept="application/pdf" required class="text-xs text-slate-500 cursor-pointer mx-auto block">
                    </div>
                </div>
            </form>
        </div>

        <!-- Rodapé Fixo do Modal com Ações -->
        <div class="px-7 py-4 border-t border-slate-100 shrink-0 flex justify-end gap-3 bg-white z-10">
            <button type="button" onclick="fecharNovoContrato()" class="border border-slate-200 text-slate-500 hover:bg-slate-50 font-black text-[10px] uppercase tracking-widest px-5 py-3 rounded-xl transition-all">Cancelar</button>
            <button type="submit" form="form-contrato" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black text-[10px] uppercase tracking-widest px-6 py-3 rounded-xl transition-all shadow-md">💾 Salvar Contrato</button>
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

function abrirNovoContrato() {
    const modal = document.getElementById('modal-novo-contrato');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function fecharNovoContrato() {
    const modal = document.getElementById('modal-novo-contrato');
    if (modal) {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }
}

function selecionarSetor(key) {
    // 1. Atualiza o input hidden com o setor escolhido
    document.getElementById('input_setor_contrato').value = key;

    // 2. Remove o destaque de todos os botões de setor e esconde todos os blocos de campos específicos
    const botoes = document.querySelectorAll('.setor-tab-btn');
    botoes.forEach(btn => {
        btn.classList.remove('bg-slate-900', 'text-white', 'border-slate-900', 'shadow-md');
        btn.classList.add('bg-slate-50', 'text-slate-600', 'border-slate-200');
    });

    // Esconde todos os blocos do passo 5 de todos os setores
    <?php foreach ($setores_contrato as $k => $s): ?>
    const bloco_<?php echo $k; ?> = document.getElementById('campos_<?php echo $k; ?>');
    if (bloco_<?php echo $k; ?>) bloco_<?php echo $k; ?>.style.display = 'none';
    <?php endforeach; ?>

    // 3. Ativa o botão que o usuário clicou
    const btnAtivo = document.getElementById('tab_' + key);
    if (btnAtivo) {
        btnAtivo.classList.remove('bg-slate-50', 'text-slate-600', 'border-slate-200');
        btnAtivo.classList.add('bg-slate-900', 'text-white', 'border-slate-900', 'shadow-md');
    }

    // 4. Mostra o bloco de campos correspondente ao setor selecionado
    const blocoAlvo = document.getElementById('campos_' + key);
    if (blocoAlvo) {
        blocoAlvo.style.display = 'block';
    }
}

// Função para alternar as abas de Vencimento (Passo 4)
function selecionarTipoVenc(tipo) {
    document.getElementById('input_tipo_vencimento').value = tipo;
    
    const tabUnico = document.getElementById('tvtab_unico');
    const tabRecorrente = document.getElementById('tvtab_recorrente');
    const painelUnico = document.getElementById('painel_venc_unico');
    const painelRecorrente = document.getElementById('painel_venc_recorrente');

    if (tipo === 'unico') {
        tabUnico.className = "px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider border border-slate-800 bg-navy-900 text-white transition-all";
        tabRecorrente.className = "px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider border border-slate-200 bg-white text-slate-600 transition-all hover:bg-slate-50";
        painelUnico.style.display = 'block';
        painelRecorrente.style.display = 'none';
    } else {
        tabRecorrente.className = "px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider border border-slate-800 bg-navy-900 text-white transition-all";
        tabUnico.className = "px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider border border-slate-200 bg-white text-slate-600 transition-all hover:bg-slate-50";
        painelRecorrente.style.display = 'block';
        painelUnico.style.display = 'none';
    }
}

// Pega o array do PHP e joga para o JS com segurança
const estruturaForm = <?php echo json_encode($estrutura_formulario); ?>;

// Supremos array de empresas do grupo que vem do PHP
const empresasGrupo = ["Souza", "Mixkar", "CSA","Autoweb","Compremix"]; 

function renderizarFormularioDinamico() {
    for (const [passoKey, passoData] of Object.entries(estruturaForm)) {
        let containerId = `container-${passoKey}`; // Ex: container-passo_4
        let container = document.getElementById(containerId);
        
        if (!container) continue;

        let numeroPassoFormatado = passoKey.replace('_', ' ').toUpperCase();

        let html = `
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">${numeroPassoFormatado}</p>
                <p class="text-xs font-black text-navy-900 uppercase tracking-wider">${passoData.titulo}</p>
        `;

        if (passoData.subtitulo) {
            html += `<p class="text-xs text-slate-400">${passoData.subtitulo}</p>`;
        }

        html += `<div class="grid grid-cols-1 md:grid-cols-2 gap-4">`;

        passoData.campos.forEach(c => {
            let colClass = c.col || 'col-span-2'; // Se não tiver coluna definida, ocupa o card todo
            
            if (c.tipo === 'text' || c.tipo === 'number' || c.tipo === 'date') {
                html += `
                    <div class="${colClass}">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">${c.label}</label>
                        <input type="${c.tipo}" name="${c.id}" id="${c.id}" ${c.obrigatorio ? 'required' : ''} placeholder="${c.placeholder || ''}" ${c.min ? 'min="'+c.min+'"' : ''} ${c.max ? 'max="'+c.max+'"' : ''} ${c.maxlength ? 'maxlength="'+c.maxlength+'"' : ''} ${c.oninput ? 'oninput="'+c.oninput+'"' : ''} class="w-full p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-navy-900 focus:bg-white focus:border-blue-300 transition-all">
                        ${c.help ? `<span class="text-[10px] text-slate-400 mt-1 block">${c.help}</span>` : ''}
                    </div>
                `;
            } else if (c.tipo === 'textarea') {
                html += `
                    <div class="${colClass}">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">${c.label}</label>
                        <textarea name="${c.id}" id="${c.id}" rows="2" placeholder="${c.placeholder || ''}" class="w-full p-3 text-xs font-bold text-navy-900 bg-slate-50 border border-slate-200 rounded-xl outline-none resize-none focus:bg-white focus:border-blue-300 transition-all"></textarea>
                    </div>
                `;
            } else if (c.tipo === 'switch') {
                html += `
                    <div class="${colClass}">
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-xs font-black text-slate-600 uppercase tracking-wider">${c.label}</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="${c.id}" id="${c.id}" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                    </div>
                `;
            } else if (c.tipo === 'select_empresas') {
                let opcoesHtml = '<option value="" disabled selected>Selecione…</option>';
                empresasGrupo.forEach(emp => {
                    opcoesHtml += `<option value="${emp}">${emp}</option>`;
                });

                html += `
                    <div class="${colClass}">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">${c.label}</label>
                        <select name="${c.id}" id="${c.id}" ${c.obrigatorio ? 'required' : ''} class="w-full p-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl outline-none font-bold text-slate-600 cursor-pointer focus:bg-white focus:border-blue-300 transition-all">
                            ${opcoesHtml}
                        </select>
                    </div>
                `;
            } 
            // NOVO TRATAMENTO: Abas de Vencimento (Passo 4)
            else if (c.tipo === 'abas_vencimento') {
                html += `
                    <div class="${colClass} flex gap-2 mb-2">
                `;
                c.opcoes.forEach((aba, idx) => {
                    let ativoClass = idx === 0 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600';
                    html += `
                        <button type="button" class="flex-1 py-2 px-4 rounded-xl font-bold text-xs transition-all aba-vencimento-btn ${ativoClass}" data-alvo="${aba.id}" onclick="mudarAbaVencimento('${aba.id}')">
                            ${aba.label}
                        </button>
                    `;
                });
                html += `</div>`;
            }
            // NOVO TRATAMENTO: Painéis Condicionais (Data única ou Recorrente)
            else if (c.tipo === 'painel_condicional') {
                let displayStyle = c.condicao === 'unico' ? 'block' : 'none'; // Começa mostrando o único por padrão
                html += `
                    <div class="${colClass} painel-condicional-vencimento" id="${c.id}" style="display: ${displayStyle};">
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                `;
                
                c.campos_internos.forEach(ci => {
                    html += `
                        <div>
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">${ci.label}</label>
                            <input type="${ci.tipo}" name="${ci.id}" id="${ci.id}" ${ci.min ? 'min="'+ci.min+'"' : ''} ${ci.max ? 'max="'+ci.max+'"' : ''} placeholder="${ci.placeholder || ''}" ${ci.oninput ? 'oninput="'+ci.oninput+'"' : ''} class="w-full p-2.5 text-sm bg-white border border-slate-200 rounded-xl outline-none font-bold text-navy-900 focus:border-blue-300 transition-all">
                            ${ci.help ? `<span class="text-[10px] text-slate-400 mt-1 block">${ci.help}</span>` : ''}
                        </div>
                    `;
                });

                html += `</div></div>`;
            }
        });

        html += `</div></div>`;
        container.innerHTML = html;
    }
}

// Função para mascarar CNPJ (Ex: 00.000.000/0000-00)
function mascararCNPJ(input) {
    let valor = input.value.replace(/\D/g, ""); // Remove tudo que não é dígito
    
    if (valor.length > 14) {
        valor = valor.substring(0, 14);
    }
    
    // Aplica a máscara passo a passo
    if (valor.length > 12) {
        valor = valor.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, "$1.$2.$3/$4-$2"); // Ajuste fino nos grupos
    }
    // De forma mais simples e robusta para CNPJ:
    valor = valor.replace(/^(\d{2})(\d)/, "$1.$2");
    valor = valor.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
    valor = valor.replace(/\.(\d{3})(\d)/, ".$1/$2");
    valor = valor.replace(/(\d{4})(\d)/, "$1-$2");
    
    input.value = valor;
}

// Função para mascarar Valor Monetário (Ex: R$ 999.999,99)
function mascararValor(input) {
    let valor = input.value.replace(/\D/g, ""); // Remove tudo que não é dígito
    
    if (valor === "") {
        input.value = "";
        return;
    }
    
    // Converte para centavos e formata como moeda brasileira
    let numero = (parseInt(valor, 10) / 100).toFixed(2);
    let partes = numero.split(".");
    
    partes[0] = partes[0].split(/(?=(?:\d{3})+(?!\d))/).join(".");
    
    input.value = "R$ " + partes.join(",");
}

// Função auxiliar para alternar o comportamento visual das abas do Passo 4
function mudarAbaVencimento(tipoEscolhido) {
    // Alterna o estilo dos botões das abas
    document.querySelectorAll('.aba-vencimento-btn').forEach(btn => {
        if (btn.getAttribute('data-alvo') === tipoEscolhido) {
            btn.classList.remove('bg-slate-100', 'text-slate-600');
            btn.classList.add('bg-blue-600', 'text-white');
        } else {
            btn.classList.remove('bg-blue-600', 'text-white');
            btn.classList.add('bg-slate-100', 'text-slate-600');
        }
    });

    // Alterna a exibição dos painéis condicionais
    const painelUnico = document.getElementById('painel_unico');
    const painelRecorrente = document.getElementById('painel_recorrente');

    if (tipoEscolhido === 'unico') {
        if (painelUnico) painelUnico.style.display = 'block';
        if (painelRecorrente) painelRecorrente.style.display = 'none';
    } else {
        if (painelUnico) painelUnico.style.display = 'none';
        if (painelRecorrente) painelRecorrente.style.display = 'block';
    }
}

// Dispara a renderização assim que a página carregar
document.addEventListener("DOMContentLoaded", renderizarFormularioDinamico);

document.getElementById('form-contrato').addEventListener('submit', function(e) {
    let dadosExtras = {};
    if (typeof estruturaForm !== 'undefined') {
        for (const [passoKey, passoData] of Object.entries(estruturaForm)) {
            passoData.campos.forEach(c => {
                let elemento = document.querySelector(`[name="${c.id}"]`);
                if (elemento) {
                    dadosExtras[c.id] = elemento.type === 'checkbox' ? (elemento.checked ? 1 : 0) : elemento.value;
                }
            });
        }
    }

    let inputHidden = document.createElement('input');
    inputHidden.type = 'hidden';
    inputHidden.name = 'dados_extras_json';
    inputHidden.value = JSON.stringify(dadosExtras);
    this.appendChild(inputHidden);

    let btnSubmit = this.querySelector('button[type="submit"]');
    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Salvando...';
    }
});

</script>

<?php include 'includes/footer.php'; ?>