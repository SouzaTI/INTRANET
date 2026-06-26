<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

$empresas_grupo = ['Souza', 'Mixkar', 'CSA', 'Autoweb', 'Compremix'];

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['setor_contrato'])) {
    echo "<script>alert('Contrato cadastrado com sucesso! (demo)'); window.location.href='painel_contratos.php';</script>";
}
?>

<style>
.field-label { display:block; font-size:10px; font-weight:900; color:#94a3b8; text-transform:uppercase; letter-spacing:.12em; margin-bottom:5px; }
.field-input { width:100%; padding:10px 14px; font-size:13px; font-weight:700; color:#1e293b; background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:12px; outline:none; transition:border-color .15s, box-shadow .15s; }
.field-input:focus { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(147,197,253,.18); background:#fff; }
.field-select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; padding-right:36px; cursor:pointer; }
.card { background:#fff; border-radius:28px; border:1.5px solid #e2e8f0; padding:28px 28px 24px; }
.card-title { font-size:11px; font-weight:900; color:#94a3b8; text-transform:uppercase; letter-spacing:.14em; margin-bottom:4px; }
.card-heading { font-size:18px; font-weight:900; color:#1e293b; text-transform:uppercase; letter-spacing:-.02em; font-style:italic; margin-bottom:20px; line-height:1.1; }
.section-divider { height:1px; background:#f1f5f9; margin:6px 0 20px; }
.switch-row { display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:12px; padding:10px 14px; }
.switch-label { font-size:12px; font-weight:700; color:#1e293b; }
.toggle-track { position:relative; display:inline-flex; align-items:center; cursor:pointer; }
.toggle-track input { position:absolute; opacity:0; width:0; height:0; }
.toggle-bg { width:40px; height:22px; background:#cbd5e1; border-radius:999px; transition:background .2s; }
.toggle-track input:checked ~ .toggle-bg { background:#10b981; }
.toggle-thumb { position:absolute; left:3px; top:3px; width:16px; height:16px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.18); }
.toggle-track input:checked ~ .toggle-thumb { transform:translateX(18px); }
.setor-tab-btn { display:flex; align-items:center; gap:8px; padding:10px 18px; border-radius:16px; font-size:12px; font-weight:900; text-transform:uppercase; letter-spacing:.06em; border:1.5px solid #e2e8f0; background:#fff; color:#94a3b8; cursor:pointer; transition:all .15s; }
.setor-tab-btn:hover { border-color:#cbd5e1; color:#475569; }
.venc-tab { padding:8px 18px; border-radius:10px; font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.06em; border:1.5px solid #e2e8f0; background:#fff; color:#94a3b8; cursor:pointer; transition:all .15s; }
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.col-span-2 { grid-column:1/-1; }
</style>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
<div class="max-w-4xl mx-auto" style="display:flex;flex-direction:column;gap:20px;">

    <!-- CABEÇALHO -->
    <div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Gestão de Contratos</p>
        <h1 class="text-2xl md:text-3xl font-black text-navy-900 uppercase tracking-tighter italic">Novo Cadastro</h1>
        <p class="text-sm text-slate-400 mt-1">Selecione o departamento e preencha os dados do contrato.</p>
    </div>

    <!-- STEP 1: SELEÇÃO DE SETOR -->
    <div class="card">
        <p class="card-title">Passo 1</p>
        <p class="card-heading">Departamento Contratante</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;" id="abas-setor">
            <?php foreach ($setores_contrato as $key => $s): ?>
            <button type="button" onclick="selecionarSetor('<?php echo $key; ?>')" id="tab_<?php echo $key; ?>"
                class="setor-tab-btn" data-key="<?php echo $key; ?>"
                data-bg="<?php echo $s['cor_bg']; ?>" data-text="<?php echo $s['cor_text']; ?>" data-border="<?php echo $s['cor_border']; ?>">
                <span><?php echo $s['icone']; ?></span>
                <span><?php echo $s['sigla']; ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FORMULÁRIO (aparece após escolher setor) -->
    <form method="POST" enctype="multipart/form-data" id="form-contrato" style="display:none;flex-direction:column;gap:20px;">
        <input type="hidden" name="setor_contrato" id="input_setor_contrato" value="">
        <input type="hidden" name="tipo_vencimento" id="input_tipo_vencimento" value="unico">

        <!-- STEP 2: IDENTIFICAÇÃO -->
        <div class="card">
            <p class="card-title">Passo 2</p>
            <p class="card-heading">Identificação do Fornecedor</p>
            <div class="grid-2">
                <div class="col-span-2">
                    <label class="field-label">Razão Social</label>
                    <input type="text" name="fornecedor" required placeholder="Ex: Vertical Manutenção Predial Ltda." class="field-input">
                </div>
                <div>
                    <label class="field-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" placeholder="Opcional" class="field-input">
                </div>
                <div>
                    <label class="field-label">CNPJ</label>
                    <input type="text" name="cnpj" placeholder="00.000.000/0000-00" maxlength="18" class="field-input" oninput="mascararCNPJ(this)">
                </div>
                <div class="col-span-2">
                    <label class="field-label">Contato (Nome / Telefone / E-mail)</label>
                    <input type="text" name="contato" placeholder="Ex: João Silva — (11) 98765-4321 — joao@empresa.com" class="field-input">
                </div>
                <div>
                    <label class="field-label">Código no Sistema</label>
                    <input type="text" name="codigo_sistema" placeholder="Ex: RH-2024-001" class="field-input">
                </div>
                <div>
                    <label class="field-label">Empresa do Grupo</label>
                    <select name="empresa" required class="field-input field-select">
                        <option value="" disabled selected>Selecione…</option>
                        <?php foreach ($empresas_grupo as $emp): ?>
                        <option value="<?php echo $emp; ?>"><?php echo $emp; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- STEP 3: DADOS DO CONTRATO -->
        <div class="card">
            <p class="card-title">Passo 3</p>
            <p class="card-heading">Dados do Contrato</p>
            <div class="grid-2">
                <div class="col-span-2">
                    <label class="field-label">Serviço / Objeto</label>
                    <input type="text" name="servico" required placeholder="Ex: VALE TRANSPORTE, SISTEMA, SEG.TRABALHO…" class="field-input">
                </div>
                <div>
                    <label class="field-label">Valor (R$)</label>
                    <input type="text" name="valor" required placeholder="0,00" class="field-input" oninput="mascararValor(this)">
                </div>
                <div>
                    <label class="field-label">Prazo Contratual</label>
                    <div style="display:flex;gap:8px;">
                        <input type="number" name="prazo_qtd" min="1" placeholder="24" class="field-input" style="width:80px;flex-shrink:0;">
                        <select name="prazo_unidade" class="field-input field-select">
                            <option value="MESES" selected>Meses</option>
                            <option value="ANOS">Anos</option>
                            <option value="DIAS">Dias</option>
                            <option value="INDETERMINADO">Indeterminado</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="field-label">Qtd. Pagamentos</label>
                    <input type="number" name="qtd_pagamentos" min="1" placeholder="24" class="field-input">
                </div>
                <div>
                    <label class="field-label">Data de Início</label>
                    <input type="date" name="data_inicio" class="field-input">
                </div>
                <div>
                    <label class="field-label">Aviso Prévio (dias)</label>
                    <input type="number" name="aviso_previo" min="0" placeholder="Ex: 30" class="field-input">
                </div>
                <div>
                    <label class="field-label">Multa Rescisória</label>
                    <input type="text" name="multa" placeholder="Ex: 10% sobre o valor total" class="field-input">
                </div>
                <div class="col-span-2">
                    <label class="field-label">Cláusula Técnica (resumo)</label>
                    <textarea name="clausula_tecnica" rows="2" placeholder="Obrigações técnicas relevantes…"
                        style="width:100%;padding:10px 14px;font-size:13px;font-weight:700;color:#1e293b;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;outline:none;resize:none;transition:border-color .15s;"
                        onfocus="this.style.borderColor='#93c5fd';this.style.background='#fff'" onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc'"></textarea>
                </div>
                <!-- Switch renovação -->
                <div class="col-span-2">
                    <div class="switch-row">
                        <span class="switch-label">Renovação Automática?</span>
                        <label class="toggle-track">
                            <input type="checkbox" name="renovacao_automatica" value="1">
                            <div class="toggle-bg"></div>
                            <div class="toggle-thumb"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 4: VENCIMENTO -->
        <div class="card">
            <p class="card-title">Passo 4</p>
            <p class="card-heading">Vencimento</p>
            <p style="font-size:12px;color:#94a3b8;margin-bottom:16px;margin-top:-12px;">
                <strong style="color:#64748b">Data Única</strong> para contratos com prazo definido ·
                <strong style="color:#64748b">Recorrente Mensal</strong> para mensalidades e assinaturas sem término
            </p>

            <!-- Tabs de tipo -->
            <div style="display:flex;gap:8px;margin-bottom:20px;">
                <button type="button" onclick="selecionarTipoVenc('unico')" id="tvtab_unico"
                    class="venc-tab" style="background:#1e293b;color:#fff;border-color:#1e293b;">📅 Data Única</button>
                <button type="button" onclick="selecionarTipoVenc('recorrente')" id="tvtab_recorrente"
                    class="venc-tab">🔁 Recorrente Mensal</button>
            </div>

            <!-- Painel data única -->
            <div id="painel_venc_unico">
                <label class="field-label">Data de Vencimento</label>
                <input type="date" name="data_vencimento" class="field-input" style="max-width:220px;">
                <p style="font-size:11px;color:#94a3b8;margin-top:8px;">O alerta de 60/90 dias será calculado automaticamente.</p>
            </div>

            <!-- Painel recorrente -->
            <div id="painel_venc_recorrente" style="display:none;">
                <label class="field-label">Dia de Vencimento (1–31)</label>
                <div style="display:flex;align-items:center;gap:12px;">
                    <input type="number" name="dia_vencimento_recorrente" min="1" max="31" placeholder="15"
                        id="input_dia_recorrente" class="field-input" style="max-width:100px;"
                        oninput="atualizarPreviewRecorrente(this.value)">
                    <span id="preview_recorrente" style="font-size:13px;font-weight:700;color:#1d4ed8;background:#dbeafe;padding:6px 14px;border-radius:10px;border:1px solid #93c5fd;display:none;"></span>
                </div>
                <p style="font-size:11px;color:#94a3b8;margin-top:8px;">O painel calculará os dias até a próxima ocorrência automaticamente.</p>
            </div>
        </div>

        <!-- STEP 5: CAMPOS ESPECÍFICOS DO SETOR -->
        <?php foreach ($setores_contrato as $key => $s): ?>
        <div id="campos_<?php echo $key; ?>" style="display:none;">
            <div class="card" style="border-color:<?php echo $s['cor_border']; ?>;">
                <p class="card-title">Passo 5</p>
                <p class="card-heading" style="color:<?php echo $s['cor_text']; ?>">
                    <?php echo $s['icone'].' Campos — '.$s['label']; ?>
                </p>
                <div class="grid-2">
                    <?php foreach ($s['campos'] as $c): ?>
                        <?php if ($c['tipo'] === 'number'): ?>
                        <div>
                            <label class="field-label"><?php echo $c['label']; ?></label>
                            <input type="number" name="<?php echo $c['id']; ?>" placeholder="<?php echo $c['placeholder']??''; ?>" class="field-input">
                        </div>
                        <?php elseif ($c['tipo'] === 'text'): ?>
                        <div>
                            <label class="field-label"><?php echo $c['label']; ?></label>
                            <input type="text" name="<?php echo $c['id']; ?>" placeholder="<?php echo $c['placeholder']??''; ?>" class="field-input">
                        </div>
                        <?php elseif ($c['tipo'] === 'select'): ?>
                        <div>
                            <label class="field-label"><?php echo $c['label']; ?></label>
                            <select name="<?php echo $c['id']; ?>" class="field-input field-select">
                                <option value="" disabled selected>Selecione…</option>
                                <?php foreach ($c['opcoes'] as $op): ?>
                                <option value="<?php echo $op; ?>"><?php echo $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php elseif ($c['tipo'] === 'switch'): ?>
                        <div class="col-span-2">
                            <div class="switch-row">
                                <span class="switch-label"><?php echo $c['label']; ?></span>
                                <label class="toggle-track">
                                    <input type="checkbox" name="<?php echo $c['id']; ?>" value="1">
                                    <div class="toggle-bg"></div>
                                    <div class="toggle-thumb"></div>
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
        <div class="card">
            <p class="card-title">Passo 6</p>
            <p class="card-heading">Documento</p>
            <div style="border:2px dashed #e2e8f0;border-radius:16px;padding:24px;text-align:center;background:#f8fafc;transition:background .15s;"
                 onmouseenter="this.style.background='#f1f5f9'" onmouseleave="this.style.background='#f8fafc'">
                <p style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px;">Anexe o contrato assinado em PDF</p>
                <input type="file" name="documento" accept="application/pdf" required
                    style="font-size:12px;color:#64748b;cursor:pointer;display:block;width:100%;">
            </div>
        </div>

        <!-- AÇÕES -->
        <div style="display:flex;justify-content:flex-end;gap:12px;padding-bottom:32px;">
            <a href="painel_contratos.php"
               style="border:1.5px solid #e2e8f0;color:#64748b;font-weight:900;padding:14px 28px;border-radius:16px;font-size:11px;text-transform:uppercase;letter-spacing:.1em;text-decoration:none;display:inline-flex;align-items:center;transition:background .15s;"
               onmouseenter="this.style.background='#f8fafc'" onmouseleave="this.style.background='transparent'">
                Cancelar
            </a>
            <button type="submit"
                style="background:#059669;color:#fff;font-weight:900;padding:14px 32px;border-radius:16px;font-size:11px;text-transform:uppercase;letter-spacing:.1em;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(5,150,105,.25);transition:background .15s;"
                onmouseenter="this.style.background='#047857'" onmouseleave="this.style.background='#059669'">
                💾 Salvar Contrato
            </button>
        </div>

    </form>
</div>
</main>

<script>
const setores = <?php
    $js = [];
    foreach ($setores_contrato as $k => $s) {
        $js[$k] = ['bg'=>$s['cor_bg'],'text'=>$s['cor_text'],'border'=>$s['cor_border']];
    }
    echo json_encode($js);
?>;

function selecionarSetor(setor) {
    // Reset todas as tabs
    document.querySelectorAll('.setor-tab-btn').forEach(btn => {
        btn.style.background = '#fff';
        btn.style.color = '#94a3b8';
        btn.style.borderColor = '#e2e8f0';
    });
    // Ativa tab selecionada
    const tab = document.getElementById('tab_' + setor);
    const s = setores[setor];
    tab.style.background   = s.bg;
    tab.style.color        = s.text;
    tab.style.borderColor  = s.border;

    // Mostra campos do setor
    document.querySelectorAll('[id^="campos_"]').forEach(d => d.style.display = 'none');
    document.getElementById('campos_' + setor).style.display = 'block';

    document.getElementById('input_setor_contrato').value = setor;

    const form = document.getElementById('form-contrato');
    form.style.display = 'flex';
}

function selecionarTipoVenc(tipo) {
    document.getElementById('input_tipo_vencimento').value = tipo;
    const tabUnico      = document.getElementById('tvtab_unico');
    const tabRec        = document.getElementById('tvtab_recorrente');
    const painelUnico   = document.getElementById('painel_venc_unico');
    const painelRec     = document.getElementById('painel_venc_recorrente');

    if (tipo === 'unico') {
        tabUnico.style.background = '#1e293b'; tabUnico.style.color = '#fff'; tabUnico.style.borderColor = '#1e293b';
        tabRec.style.background = '#fff'; tabRec.style.color = '#94a3b8'; tabRec.style.borderColor = '#e2e8f0';
        painelUnico.style.display = 'block';
        painelRec.style.display   = 'none';
    } else {
        tabRec.style.background = '#1e293b'; tabRec.style.color = '#fff'; tabRec.style.borderColor = '#1e293b';
        tabUnico.style.background = '#fff'; tabUnico.style.color = '#94a3b8'; tabUnico.style.borderColor = '#e2e8f0';
        painelUnico.style.display = 'none';
        painelRec.style.display   = 'block';
        atualizarPreviewRecorrente(document.getElementById('input_dia_recorrente').value);
    }
}

function atualizarPreviewRecorrente(dia) {
    const el = document.getElementById('preview_recorrente');
    const n  = parseInt(dia, 10);
    if (!dia || isNaN(n) || n < 1 || n > 31) { el.style.display = 'none'; return; }
    el.textContent  = 'Todo mês, dia ' + n;
    el.style.display = 'inline-block';
}

function mascararCNPJ(input) {
    let v = input.value.replace(/\D/g,'').slice(0,14);
    v = v.replace(/^(\d{2})(\d)/,'$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/,'.$1/$2');
    v = v.replace(/(\d{4})(\d)/,'$1-$2');
    input.value = v;
}

function mascararValor(input) {
    let v = input.value.replace(/\D/g,'');
    v = (parseInt(v||'0',10)/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
    input.value = v;
}

// Focus styles para textareas e inputs (acessibilidade)
document.querySelectorAll('.field-input').forEach(el => {
    el.addEventListener('focus', () => { el.style.borderColor='#93c5fd'; el.style.background='#fff'; el.style.boxShadow='0 0 0 3px rgba(147,197,253,.18)'; });
    el.addEventListener('blur',  () => { el.style.borderColor='#e2e8f0'; el.style.background='#f8fafc'; el.style.boxShadow='none'; });
});
</script>

<?php include 'includes/footer.php'; ?>