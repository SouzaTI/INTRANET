<?php
require_once 'config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Carrega assinantes disponíveis do GLPI
$stmt_users = $pdo_intra->query("
    SELECT u.id, CONCAT(u.firstname, ' ', u.realname) AS nome_completo,
           l.name AS setor
    FROM   " . DB_GLPI . ".glpi_users u
    LEFT JOIN " . DB_GLPI . ".glpi_useremails ue ON ue.users_id = u.id
    LEFT JOIN " . DB_GLPI . ".glpi_groups_users gu ON gu.users_id = u.id
    LEFT JOIN " . DB_GLPI . ".glpi_groups l ON l.id = gu.groups_id
    WHERE  u.is_active = 1 AND u.is_deleted = 0
    GROUP  BY u.id
    ORDER  BY u.firstname ASC, u.realname ASC
");
$usuarios_glpi = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// Serializa para o JS usar
$usuarios_json = json_encode($usuarios_glpi, JSON_HEX_TAG | JSON_HEX_APOS);
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6 md:p-10">
<div class="max-w-3xl mx-auto space-y-6">

    <!-- Breadcrumb -->
    <a href="minhas_assinaturas.php"
       class="inline-flex items-center gap-2 text-slate-400 hover:text-navy-900 font-bold text-xs uppercase tracking-widest transition-colors">
        ← Voltar para Assinaturas
    </a>

    <!-- Cabeçalho -->
    <div>
        <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 mb-1">Novo Documento</p>
        <h1 class="text-3xl font-black text-navy-900 uppercase tracking-tighter italic leading-none">
            Criar Envelope
        </h1>
    </div>

    <!-- ── FORMULÁRIO ────────────────────────────────────────────────────── -->
    <form id="formEnvelope"
          method="POST"
          action="api/cadastrar_envelope.php"
          enctype="multipart/form-data"
          novalidate
          class="space-y-5">

        <!-- 1 · Título -->
        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6 space-y-2">
            <label for="titulo"
                   class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 block">
                Título do Envelope
            </label>
            <input type="text"
                   id="titulo"
                   name="titulo"
                   required
                   maxlength="255"
                   placeholder="Ex: Contrato de Prestação de Serviços — Jun/2026"
                   class="w-full px-5 py-3.5 bg-slate-50 border-2 border-slate-100 rounded-2xl
                          text-navy-900 font-bold placeholder:text-slate-300 placeholder:font-medium
                          focus:border-corporate-blue focus:bg-white focus:outline-none transition-all" />
            <p id="erroTitulo" class="hidden text-rose-500 text-[11px] font-bold"></p>
        </div>

        <!-- 2 · Tipo de Fluxo -->
        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6 space-y-3">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Tipo de Fluxo</p>

            <div class="grid grid-cols-2 gap-3" id="fluxoSelector">
                <!-- Paralelo -->
                <label id="labelParalelo"
                       class="fluxo-opcao cursor-pointer relative flex flex-col gap-2 rounded-2xl border-2
                              border-corporate-blue bg-corporate-blue/5 p-5 transition-all"
                       data-valor="paralelo">
                    <input type="radio" name="tipo_fluxo" value="paralelo"
                           class="sr-only" checked />
                    <div class="flex items-center justify-between">
                        <span class="text-xl">⚡</span>
                        <span id="checkParalelo"
                              class="w-5 h-5 rounded-full border-2 border-corporate-blue bg-corporate-blue
                                     flex items-center justify-center transition-all">
                            <svg class="w-2.5 h-2.5 text-white" viewBox="0 0 10 8" fill="none">
                                <path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.8"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </div>
                    <span class="font-black text-navy-900 text-sm uppercase tracking-tight">Paralelo</span>
                    <span class="text-slate-400 text-[11px] font-medium leading-snug">
                        Todos assinam simultaneamente, em qualquer ordem.
                    </span>
                </label>

                <!-- Sequencial -->
                <label id="labelSequencial"
                       class="fluxo-opcao cursor-pointer relative flex flex-col gap-2 rounded-2xl border-2
                              border-slate-200 bg-white p-5 transition-all"
                       data-valor="sequencial">
                    <input type="radio" name="tipo_fluxo" value="sequencial"
                           class="sr-only" />
                    <div class="flex items-center justify-between">
                        <span class="text-xl">⛓</span>
                        <span id="checkSequencial"
                              class="w-5 h-5 rounded-full border-2 border-slate-200 bg-white transition-all">
                        </span>
                    </div>
                    <span class="font-black text-navy-900 text-sm uppercase tracking-tight">Sequencial</span>
                    <span class="text-slate-400 text-[11px] font-medium leading-snug">
                        Assinaturas em ordem definida, uma após a outra.
                    </span>
                </label>
            </div>
        </div>

        <!-- 3 · Dropzone PDF -->
        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6 space-y-3">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Documento PDF</p>

            <div id="dropzone"
                 class="relative border-2 border-dashed border-slate-200 rounded-2xl
                        bg-slate-50 hover:bg-slate-100 hover:border-corporate-blue
                        transition-all duration-200 cursor-pointer group"
                 onclick="document.getElementById('inputPDF').click()"
                 ondragover="dragOver(event)"
                 ondragleave="dragLeave(event)"
                 ondrop="dropArquivo(event)">

                <!-- Estado vazio -->
                <div id="dropzoneVazio" class="flex flex-col items-center gap-3 py-12 px-6">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 group-hover:bg-corporate-blue/10
                                flex items-center justify-center transition-all">
                        <svg class="w-7 h-7 text-slate-300 group-hover:text-corporate-blue transition-colors"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775
                                     5.25 5.25 0 0 1 10.338-2.32 3.75 3.75 0 0 1 3.823 5.095"/>
                        </svg>
                    </div>
                    <div class="text-center">
                        <p class="font-black text-navy-900 text-sm">Arraste o PDF aqui</p>
                        <p class="text-slate-400 text-xs font-medium mt-0.5">ou clique para selecionar</p>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-300 bg-slate-100
                                 px-3 py-1 rounded-full">
                        Apenas PDF · Máx. 10 MB
                    </span>
                </div>

                <!-- Estado com arquivo -->
                <div id="dropzoneArquivo" class="hidden items-center gap-4 p-5">
                    <div class="w-12 h-12 rounded-xl bg-rose-50 border border-rose-100
                                flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-rose-400" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875
                                     1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0
                                     0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0
                                     0 0 9 1.5H5.625Z"/>
                            <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5
                                     a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p id="nomeArquivo" class="font-black text-navy-900 text-sm truncate"></p>
                        <p id="tamanhoArquivo" class="text-slate-400 text-[11px] font-medium mt-0.5"></p>
                    </div>
                    <button type="button"
                            onclick="removerArquivo(event)"
                            class="shrink-0 w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-500
                                   text-rose-400 hover:text-white flex items-center justify-center
                                   transition-all text-base font-black"
                            title="Remover arquivo">
                        ×
                    </button>
                </div>
            </div>

            <input type="file" id="inputPDF" name="pdf" accept="application/pdf"
                   class="sr-only" onchange="selecionarArquivo(this.files[0])" />
            <p id="erroPDF" class="hidden text-rose-500 text-[11px] font-bold"></p>
        </div>

        <!-- 4 · Destinatários -->
        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                        Destinatários do Fluxo
                    </p>
                    <p id="infoOrdem"
                       class="hidden text-[10px] text-violet-600 font-bold mt-0.5 uppercase tracking-wide">
                        ⛓ Modo sequencial: defina a ordem de cada assinante.
                    </p>
                </div>
                <button type="button"
                        id="btnAddAssinante"
                        onclick="adicionarAssinante()"
                        class="flex items-center gap-2 bg-navy-900 hover:bg-corporate-blue text-white
                               font-black text-[11px] uppercase tracking-widest px-4 py-2.5 rounded-xl
                               shadow-sm hover:shadow-md transition-all">
                    + Assinante
                </button>
            </div>

            <!-- Lista dinâmica -->
            <div id="listaAssinantes" class="space-y-2.5"></div>

            <!-- Empty state -->
            <div id="emptyAssinantes"
                 class="flex flex-col items-center gap-2 py-8 text-center">
                <span class="text-3xl">👥</span>
                <p class="text-slate-400 text-[11px] font-bold uppercase tracking-widest">
                    Nenhum assinante adicionado
                </p>
                <p class="text-slate-300 text-xs font-medium">
                    Clique em "+ Assinante" para começar.
                </p>
            </div>

            <p id="erroAssinantes" class="hidden text-rose-500 text-[11px] font-bold"></p>
        </div>

        <!-- ── Submit ──────────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="minhas_assinaturas.php"
               class="flex-1 text-center py-4 rounded-2xl border-2 border-slate-200 text-slate-400
                      hover:border-slate-300 hover:text-slate-600 font-black text-xs uppercase
                      tracking-widest transition-all">
                Cancelar
            </a>
            <button type="submit"
                    id="btnSubmit"
                    class="flex-1 bg-navy-900 hover:bg-corporate-blue text-white font-black py-4
                           rounded-2xl shadow-lg hover:shadow-xl transition-all text-xs uppercase
                           tracking-widest flex items-center justify-center gap-2
                           disabled:opacity-50 disabled:cursor-not-allowed">
                <span id="btnSubmitTexto">🚀 Criar Envelope e Enviar</span>
                <svg id="btnSubmitSpinner"
                     class="hidden animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="white" stroke-width="4"/>
                    <path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
            </button>
        </div>

    </form>
</div>
</main>

<!-- ══════════════════════════════════════════════════════════════
     TEMPLATE de linha de assinante (clonado pelo JS)
══════════════════════════════════════════════════════════════ -->
<template id="templateAssinante">
    <div class="assinante-linha flex items-center gap-3 bg-slate-50 border border-slate-100
                rounded-2xl p-3 transition-all animate-[fadeSlide_.18s_ease-out]">

        <!-- Índice visual -->
        <div class="ordem-badge shrink-0 w-7 h-7 rounded-xl bg-navy-900 text-white
                    text-[11px] font-black flex items-center justify-center select-none">
        </div>

        <!-- Select de usuário -->
        <select name="assinantes[]"
                required
                class="flex-1 px-4 py-2.5 bg-white border-2 border-slate-200 rounded-xl
                       text-navy-900 font-bold text-sm focus:border-corporate-blue
                       focus:outline-none transition-all appearance-none cursor-pointer
                       select-usuario">
            <option value="" disabled selected>Selecione o assinante…</option>
            <!-- opções injetadas pelo JS -->
        </select>

        <!-- Input de ordem (só aparece em modo sequencial) -->
        <div class="campo-ordem hidden shrink-0 w-20">
            <input type="number"
                   name="ordem[]"
                   min="1"
                   max="99"
                   placeholder="Ordem"
                   class="w-full px-3 py-2.5 bg-white border-2 border-violet-200 rounded-xl
                          text-navy-900 font-black text-sm text-center
                          focus:border-violet-500 focus:outline-none transition-all" />
        </div>

        <!-- Botão remover -->
        <button type="button"
                onclick="removerAssinante(this)"
                class="shrink-0 w-9 h-9 rounded-xl bg-white border border-slate-200
                       hover:bg-rose-50 hover:border-rose-200 text-slate-300 hover:text-rose-500
                       flex items-center justify-center transition-all"
                title="Remover assinante">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0
                         01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0
                         00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>
    </div>
</template>

<script>
// ── Dados de usuários vindos do PHP ──────────────────────────────────────
const USUARIOS = <?= $usuarios_json ?>;

// ── Estado ────────────────────────────────────────────────────────────────
let fluxoAtual    = 'paralelo';
let contadorLinhas = 0;

// ── Seletor de Fluxo ─────────────────────────────────────────────────────
document.querySelectorAll('.fluxo-opcao').forEach(label => {
    label.addEventListener('click', () => {
        const valor = label.dataset.valor;
        label.querySelector('input[type=radio]').checked = true;
        fluxoAtual = valor;
        atualizarUI_Fluxo();
    });
});

function atualizarUI_Fluxo() {
    const isSeq = fluxoAtual === 'sequencial';

    // Visual dos cards de opção
    ['paralelo', 'sequencial'].forEach(v => {
        const lbl   = document.getElementById('label' + capitalize(v));
        const check = document.getElementById('check'  + capitalize(v));
        const ativo = v === fluxoAtual;

        lbl.classList.toggle('border-corporate-blue',   ativo);
        lbl.classList.toggle('bg-corporate-blue/5',     ativo && v === 'paralelo');
        lbl.classList.toggle('border-violet-500',       ativo && v === 'sequencial');
        lbl.classList.toggle('bg-violet-50',            ativo && v === 'sequencial');
        lbl.classList.toggle('border-slate-200',        !ativo);
        lbl.classList.toggle('bg-white',                !ativo);

        if (ativo) {
            check.classList.add('border-corporate-blue', 'bg-corporate-blue');
            check.innerHTML = `<svg class="w-2.5 h-2.5 text-white" viewBox="0 0 10 8" fill="none">
                <path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.8"
                      stroke-linecap="round" stroke-linejoin="round"/></svg>`;
        } else {
            check.classList.remove('border-corporate-blue', 'bg-corporate-blue');
            check.innerHTML = '';
        }
    });

    // Campos de ordem nas linhas
    document.querySelectorAll('.campo-ordem').forEach(el => {
        el.classList.toggle('hidden', !isSeq);
        el.querySelector('input').required = isSeq;
    });

    // Aviso informativo
    document.getElementById('infoOrdem').classList.toggle('hidden', !isSeq);

    // Reordena badges
    atualizarBadges();
}

// ── Adicionar assinante ───────────────────────────────────────────────────
function adicionarAssinante() {
    contadorLinhas++;
    const template = document.getElementById('templateAssinante');
    const clone    = template.content.cloneNode(true);
    const linha    = clone.querySelector('.assinante-linha');

    // Popula o <select> com os usuários do GLPI
    const select = linha.querySelector('.select-usuario');
    USUARIOS.forEach(u => {
        const opt    = document.createElement('option');
        opt.value    = u.id;
        opt.textContent = u.nome_completo + (u.setor ? ` — ${u.setor}` : '');
        select.appendChild(opt);
    });

    // Visibilidade do campo ordem
    const campoOrdem = linha.querySelector('.campo-ordem');
    const inputOrdem = campoOrdem.querySelector('input');
    const isSeq      = fluxoAtual === 'sequencial';
    campoOrdem.classList.toggle('hidden', !isSeq);
    inputOrdem.required = isSeq;
    if (isSeq) inputOrdem.value = contadorLinhas; // pré-preenche com posição atual

    document.getElementById('listaAssinantes').appendChild(clone);
    document.getElementById('emptyAssinantes').classList.add('hidden');

    atualizarBadges();
    // Foca no select recém-adicionado
    document.querySelector('.assinante-linha:last-child .select-usuario')?.focus();
}

// ── Remover assinante ─────────────────────────────────────────────────────
function removerAssinante(btn) {
    const linha = btn.closest('.assinante-linha');
    linha.style.opacity   = '0';
    linha.style.transform = 'translateX(8px)';
    linha.style.transition = 'all .15s ease';
    setTimeout(() => {
        linha.remove();
        atualizarBadges();
        const lista = document.getElementById('listaAssinantes');
        if (!lista.children.length) {
            document.getElementById('emptyAssinantes').classList.remove('hidden');
            contadorLinhas = 0;
        }
    }, 150);
}

// ── Atualiza números dos badges e reordena inputs de ordem ───────────────
function atualizarBadges() {
    document.querySelectorAll('.assinante-linha').forEach((linha, i) => {
        linha.querySelector('.ordem-badge').textContent = i + 1;
        const inputOrdem = linha.querySelector('.campo-ordem input');
        if (fluxoAtual === 'sequencial' && !inputOrdem.value) {
            inputOrdem.value = i + 1;
        }
    });
}

// ── Dropzone ──────────────────────────────────────────────────────────────
const MAX_MB = 10;

function dragOver(e) {
    e.preventDefault();
    document.getElementById('dropzone').classList.add('border-corporate-blue', 'bg-blue-50/40');
}

function dragLeave(e) {
    document.getElementById('dropzone').classList.remove('border-corporate-blue', 'bg-blue-50/40');
}

function dropArquivo(e) {
    e.preventDefault();
    dragLeave(e);
    const arquivo = e.dataTransfer.files[0];
    if (arquivo) selecionarArquivo(arquivo);
}

function selecionarArquivo(arquivo) {
    const erroEl = document.getElementById('erroPDF');
    erroEl.classList.add('hidden');

    if (!arquivo) return;
    if (arquivo.type !== 'application/pdf') {
        mostrarErroCampo(erroEl, 'Apenas arquivos PDF são aceitos.');
        return;
    }
    if (arquivo.size > MAX_MB * 1024 * 1024) {
        mostrarErroCampo(erroEl, `O arquivo excede ${MAX_MB} MB.`);
        return;
    }

    // Transfere para o input real (necessário para submit)
    const dt = new DataTransfer();
    dt.items.add(arquivo);
    document.getElementById('inputPDF').files = dt.files;

    document.getElementById('dropzoneVazio').classList.add('hidden');
    const est = document.getElementById('dropzoneArquivo');
    est.classList.remove('hidden');
    est.classList.add('flex');
    document.getElementById('nomeArquivo').textContent    = arquivo.name;
    document.getElementById('tamanhoArquivo').textContent = formatarBytes(arquivo.size);
}

function removerArquivo(e) {
    e.stopPropagation();
    document.getElementById('inputPDF').value = '';
    document.getElementById('dropzoneVazio').classList.remove('hidden');
    const est = document.getElementById('dropzoneArquivo');
    est.classList.add('hidden');
    est.classList.remove('flex');
}

// ── Validação e Submit ────────────────────────────────────────────────────
document.getElementById('formEnvelope').addEventListener('submit', function(e) {
    e.preventDefault();
    let valido = true;

    // Título
    const titulo = document.getElementById('titulo');
    const erroT  = document.getElementById('erroTitulo');
    if (!titulo.value.trim()) {
        mostrarErroCampo(erroT, 'Informe o título do envelope.');
        titulo.focus();
        valido = false;
    } else { erroT.classList.add('hidden'); }

    // PDF
    const erroPDF = document.getElementById('erroPDF');
    if (!document.getElementById('inputPDF').files.length) {
        mostrarErroCampo(erroPDF, 'Selecione um arquivo PDF.');
        valido = false;
    } else { erroPDF.classList.add('hidden'); }

    // Assinantes
    const erroA   = document.getElementById('erroAssinantes');
    const linhas  = document.querySelectorAll('.assinante-linha');
    if (!linhas.length) {
        mostrarErroCampo(erroA, 'Adicione ao menos um assinante.');
        valido = false;
    } else {
        // IDs duplicados?
        const ids = [...linhas].map(l => l.querySelector('.select-usuario').value);
        if (ids.some(id => !id)) {
            mostrarErroCampo(erroA, 'Selecione o usuário em todas as linhas.');
            valido = false;
        } else if (new Set(ids).size !== ids.length) {
            mostrarErroCampo(erroA, 'O mesmo assinante não pode aparecer mais de uma vez.');
            valido = false;
        } else {
            erroA.classList.add('hidden');
        }
    }

    if (!valido) return;

    // Submit
    const btn     = document.getElementById('btnSubmit');
    const txt     = document.getElementById('btnSubmitTexto');
    const spinner = document.getElementById('btnSubmitSpinner');
    btn.disabled  = true;
    txt.textContent = 'Enviando…';
    spinner.classList.remove('hidden');

    this.submit();
});

// ── Helpers ───────────────────────────────────────────────────────────────
function mostrarErroCampo(el, msg) {
    el.textContent = msg;
    el.classList.remove('hidden');
}

function formatarBytes(bytes) {
    if (bytes < 1024)        return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>

<style>
@keyframes fadeSlide {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0);    }
}
</style>

<?php include 'includes/footer.php'; ?>