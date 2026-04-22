<?php
/**
 * PAINEL DE CONTROLE DE DOCUMENTAÇÃO - NAVI PRO
 */
require_once 'config.php';

// Bloqueio de Segurança
if (!isset($_SESSION['pode_gerenciar_docs']) || $_SESSION['pode_gerenciar_docs'] !== true) {
    header("Location: index.php");
    exit;
}

$diretorio_docs = __DIR__ . '/docs/';
$diretorio_img  = __DIR__ . '/img/';
$mensagem = "";

// Variáveis para Log
$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['user_name'] ?? 'Sistema';
$ip_address = $_SERVER['REMOTE_ADDR'];

// --- LÓGICA DE EXCLUSÃO E MANUTENÇÃO (COM RASTREABILIDADE) ---
if (isset($_GET['excluir_arq'])) {
    $caminho = base64_decode($_GET['excluir_arq']);
    if (file_exists($caminho) && strpos($caminho, 'docs') !== false) {
        $nome_arquivo_log = basename($caminho);
        unlink($caminho);
        // Registro de Auditoria
        registrarLog($pdo_intra, 'EXCLUIR DOC', "Usuário $user_name excluiu o documento: $nome_arquivo_log", $user_id, $ip_address);
        $mensagem = "🗑️ Arquivo excluído com sucesso!";
    }
}

if (isset($_GET['excluir_img'])) {
    $caminho = base64_decode($_GET['excluir_img']);
    if (file_exists($caminho) && strpos($caminho, 'img') !== false) {
        $nome_img_log = basename($caminho);
        unlink($caminho);
        // Registro de Auditoria
        registrarLog($pdo_intra, 'EXCLUIR MÍDIA', "Usuário $user_name removeu a imagem: $nome_img_log da biblioteca", $user_id, $ip_address);
        $mensagem = "🗑️ Imagem removida da biblioteca!";
    }
}

if (isset($_GET['acao']) && $_GET['acao'] == 'renomear') {
    $antigo_nome = basename($_GET['antigo']);
    $novo_nome = basename($_GET['novo']);
    $antigo = $diretorio_img . $antigo_nome;
    $novo = $diretorio_img . $novo_nome;
    
    if (file_exists($antigo) && !file_exists($novo)) {
        rename($antigo, $novo);
        // Registro de Auditoria
        registrarLog($pdo_intra, 'RENOMEAR MÍDIA', "Usuário $user_name renomeou $antigo_nome para $novo_nome", $user_id, $ip_address);
        header("Location: admin_docs.php?msg=Arquivo renomeado!");
        exit;
    }
}

// --- 1. LÓGICA: CARREGAR ARQUIVO PARA EDIÇÃO ---
$conteudo_editar = "";
$nome_editar = "";
$setor_editar = "";

if (isset($_GET['editar'])) {
    $arquivo_path = base64_decode($_GET['editar']);
    if (file_exists($arquivo_path)) {
        $conteudo_editar = file_get_contents($arquivo_path);
        $nome_editar = basename($arquivo_path);
        $setor_editar = basename(dirname($arquivo_path));
    }
}

// --- 2. LÓGICA: SALVAR OU CRIAR ARQUIVO .MD (COM RASTREABILIDADE) ---
if (isset($_POST['salvar_documento'])) {
    $setor = $_POST['setor_destino'];
    $nome_arquivo = trim($_POST['nome_arquivo']);
    $conteudo = $_POST['conteudo_md'];

    if (!str_ends_with($nome_arquivo, '.md')) $nome_arquivo .= '.md';
    $caminho = $diretorio_docs . $setor . '/' . $nome_arquivo;

    if (file_put_contents($caminho, $conteudo)) {
        // Registro de Auditoria
        $acao_log = isset($_GET['editar']) ? 'EDITAR DOC' : 'CRIAR DOC';
        registrarLog($pdo_intra, $acao_log, "Usuário $user_name salvou o documento: $nome_arquivo no setor $setor", $user_id, $ip_address);
        
        $mensagem = "✅ Documento '$nome_arquivo' salvo com sucesso!";
        $conteudo_editar = $conteudo;
        $nome_editar = $nome_arquivo;
        $setor_editar = $setor;
    } else {
        $mensagem = "❌ Erro ao salvar. Verifique permissões.";
    }
}

// --- 3. LÓGICA: CRIAR NOVA PASTA (COM RASTREABILIDADE) ---
if (isset($_POST['nova_pasta']) && !empty($_POST['nome_pasta'])) {
    $nova = strtoupper(trim($_POST['nome_pasta']));
    if (!is_dir($diretorio_docs . $nova)) {
        mkdir($diretorio_docs . $nova, 0777, true);
        // Registro de Auditoria
        registrarLog($pdo_intra, 'CRIAR PASTA', "Usuário $user_name criou o setor: $nova", $user_id, $ip_address);
        $mensagem = "✅ Pasta '$nova' criada!";
    }
}

// --- 4. LÓGICA: UPLOAD DE IMAGEM (VIA FORM OU AJAX COM RASTREABILIDADE) ---
if (isset($_FILES['arquivo_img'])) {
    $nome = $_FILES['arquivo_img']['name'];
    $nome = str_replace(['..', ' '], ['.', '_'], $nome);
    
    if (move_uploaded_file($_FILES['arquivo_img']['tmp_name'], $diretorio_img . $nome)) {
        // Registro de Auditoria
        registrarLog($pdo_intra, 'UPLOAD MÍDIA', "Usuário $user_name subiu a imagem: $nome", $user_id, $ip_address);
        
        if(isset($_POST['ajax_upload'])) { echo "success"; exit; }
        $mensagem = "🖼️ Imagem '$nome' enviada!";
    }
}

// Listagem de pastas e imagens para uso no HTML e no Modal
$pastas = array_filter(glob($diretorio_docs . '*'), 'is_dir');
$imagens_biblioteca = array_filter(glob($diretorio_img . '*'), 'is_file');
usort($pastas, function($a, $b) { return strcasecmp($a, $b); });
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>NAVI | Editor Dinâmico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .editor-font { font-family: 'Fira Code', monospace; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        #preview_md h1 { font-size: 1.75rem; font-weight: 900; color: #0f172a; margin: 1.5rem 0 1rem; border-bottom: 2px solid #f1f5f9; }
        #preview_md h2 { font-size: 1.4rem; font-weight: 800; color: #1e293b; margin: 1.2rem 0 0.8rem; }
        #preview_md h3 { font-size: 1.1rem; font-weight: 700; color: #334155; margin: 1rem 0 0.5rem; }
        #preview_md p { margin-bottom: 1rem; color: #475569; line-height: 1.6; }
        #preview_md ul { list-style: disc; margin-left: 1.5rem; margin-bottom: 1rem; }
        #preview_md strong { font-weight: 700; color: #0f172a; }
        #preview_md img { max-width: 100%; border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin: 1rem 0; }
        
        .alert-info { background: #eff6ff; border-left: 4px solid #2563eb; padding: 1rem; border-radius: 0.5rem; color: #1e40af; font-weight: 600; font-size: 0.85rem; margin: 1rem 0; }
        .alert-danger { background: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; border-radius: 0.5rem; color: #991b1b; font-weight: 600; font-size: 0.85rem; margin: 1rem 0; }
    </style>
</head>
<body class="bg-slate-100 p-4">

<div class="max-w-[1800px] mx-auto space-y-4">
    
    <header class="flex justify-between items-center bg-slate-900 p-5 rounded-3xl text-white shadow-xl border border-white/5">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-xl shadow-lg shadow-blue-500/20">📝</div>
            <div>
                <h1 class="text-lg font-black tracking-tighter uppercase italic leading-none">Editor NAVI PRO</h1>
                <p class="text-blue-400 text-[9px] font-bold uppercase tracking-widest mt-1">Dinamismo & Performance</p>
            </div>
        </div>
        <div class="flex gap-3">
            <button onclick="toggleModalMidia()" class="bg-orange-500 hover:bg-orange-600 px-5 py-2.5 rounded-xl text-[10px] font-black transition-all shadow-lg shadow-orange-500/20 uppercase tracking-widest">Biblioteca de Mídia</button>
            <a href="admin_docs.php" onclick="localStorage.removeItem('rascunho_navi_novo'); localStorage.removeItem('rascunho_navi_' + document.getElementById('nome_arquivo').value);" class="bg-white/5 hover:bg-white/10 px-4 py-2.5 rounded-xl text-[10px] font-bold transition-all border border-white/5">LIMPAR / NOVO</a>
            <a href="index.php" class="bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white px-4 py-2.5 rounded-xl text-[10px] font-bold transition-all border border-red-500/20 uppercase">Sair</a>
        </div>
    </header>

    <?php if($mensagem): ?>
        <div class="bg-emerald-600 text-white p-3 rounded-2xl font-bold text-xs text-center shadow-lg animate-pulse"> <?= $mensagem ?> </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <section class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200">
                <h3 class="text-[10px] font-black text-slate-400 uppercase mb-4 tracking-widest">Documentos Atuais</h3>
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                    <?php foreach($pastas as $p): 
                        $nome_setor = basename($p);
                        $arquivos = glob($p . '/*.md');
                    ?>
                        <div>
                            <p class="text-[9px] font-black text-blue-600 uppercase mb-1 bg-blue-50 px-2 py-0.5 rounded inline-block"><?= $nome_setor ?></p>
                            <ul class="space-y-1 ml-1 border-l-2 border-slate-50 pl-3">
                                <?php foreach($arquivos as $arq): 
                                    $nome_arq = basename($arq);
                                    $link_edit = "admin_docs.php?editar=" . base64_encode($arq);
                                    $estilo_link = ($nome_arq == $nome_editar) ? 'text-blue-700 font-bold bg-slate-100' : 'text-slate-500 hover:text-blue-600';
                                ?>
                                    <li class="flex justify-between items-center group">
                                        <a href="<?= $link_edit ?>" class="text-[10px] block py-1 transition-all rounded px-1 <?= $estilo_link ?>">
                                            📄 <?= $nome_arq ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200">
                <h3 class="text-[10px] font-black text-slate-400 uppercase mb-3 tracking-widest">Novo Setor</h3>
                <form method="POST" class="flex gap-2">
                    <input type="text" name="nome_pasta" placeholder="Ex: TI" class="flex-1 bg-slate-50 border border-slate-200 p-2 rounded-xl text-xs outline-none focus:ring-2 focus:ring-blue-500 font-bold uppercase">
                    <button name="nova_pasta" class="bg-slate-900 text-white px-4 rounded-xl font-bold">+</button>
                </form>
            </section>
        </div>

        <div class="lg:col-span-10">
            <form method="POST" id="form_principal" class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-200 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-3">Nome do Arquivo (.md)</label>
                        <input type="text" id="nome_arquivo" name="nome_arquivo" required value="<?= $nome_editar ?>" placeholder="exemplo.md" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-3">Pasta de Destino</label>
                        <select name="setor_destino" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-2xl text-sm font-bold">
                            <?php foreach($pastas as $p): 
                                $n = basename($p); 
                                $selected = ($n == $setor_editar) ? 'selected' : '';
                                echo "<option value='$n' $selected>$n</option>"; 
                            endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 py-2 border-y border-slate-50">
                    <button type="button" onclick="inserirFormato('**', '**')" class="px-3 py-2 bg-slate-100 rounded-xl text-[10px] font-black hover:bg-slate-900 hover:text-white transition-all">NEGRITO</button>
                    <button type="button" onclick="inserirFormato('### ', '')" class="px-3 py-2 bg-slate-100 rounded-xl text-[10px] font-black hover:bg-slate-900 hover:text-white transition-all">TÍTULO</button>
                    <button type="button" onclick="inserirTemplate('info')" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-xl text-[10px] font-black hover:bg-blue-600 hover:text-white transition-all uppercase tracking-widest">📘 Info Box</button>
                    <button type="button" onclick="inserirTemplate('danger')" class="px-4 py-2 bg-red-100 text-red-700 rounded-xl text-[10px] font-black hover:bg-red-600 hover:text-white transition-all uppercase tracking-widest">⚠️ Atenção</button>
                    <button type="button" onclick="inserirTemplate('separador')" class="px-3 py-2 bg-slate-800 text-white rounded-xl text-[10px] font-black hover:bg-black transition-all">LINHA</button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 h-[650px]">
                    <div class="relative h-full">
                        <textarea id="editor_md" name="conteudo_md" required 
                            class="w-full h-full bg-slate-900 text-emerald-400 p-8 rounded-[2.5rem] editor-font text-sm outline-none border-8 border-slate-800 focus:border-blue-600 transition-all leading-relaxed resize-none custom-scrollbar"
                            oninput="atualizarPreview()"><?= $conteudo_editar ?></textarea>
                        <div class="absolute bottom-6 left-6 px-3 py-1 bg-white/5 text-white/20 text-[8px] font-black rounded-full pointer-events-none tracking-[0.3em] uppercase">Ambiente de Código</div>
                    </div>

                    <div id="preview_md" class="w-full h-full bg-white p-10 rounded-[2.5rem] border-4 border-slate-50 overflow-y-auto custom-scrollbar shadow-inner prose prose-slate max-w-none">
                    </div>
                </div>

                <input type="hidden" id="user_sessao" value="<?= $user_name ?>">

                <button type="submit" name="salvar_documento" class="w-full bg-blue-600 text-white py-5 rounded-3xl font-black text-xs tracking-[0.2em] uppercase shadow-xl hover:bg-blue-700 hover:scale-[1.005] transition-all flex items-center justify-center gap-3">
                    Publicar Documentação Agora 🚀
                </button>
            </form>
        </div>
    </div>
</div>

<div id="modalMidia" class="hidden fixed inset-0 bg-slate-950/90 backdrop-blur-md z-[2000] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-5xl rounded-[3.5rem] shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        <div class="bg-slate-900 p-8 text-white flex justify-between items-center">
            <div>
                <h3 class="text-xl font-black uppercase italic tracking-tighter leading-none">Gerenciador de Mídia</h3>
                <p class="text-[9px] text-orange-400 font-bold uppercase tracking-[0.2em] mt-2">Uploads dinâmicos e biblioteca de GIFs</p>
            </div>
            <button onclick="toggleModalMidia()" class="w-12 h-12 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-3xl transition-all">&times;</button>
        </div>
        
        <div class="p-10 grid grid-cols-1 md:grid-cols-12 gap-8">
            <div class="md:col-span-4 space-y-6">
                <form id="form_img_ajax" class="space-y-4">
                    <label class="w-full flex flex-col items-center px-4 py-12 bg-orange-50 text-orange-600 rounded-[2.5rem] border-2 border-dashed border-orange-200 cursor-pointer hover:bg-orange-100 transition-all group">
                        <span class="text-[10px] font-black uppercase text-center group-hover:scale-110 transition-transform">Arraste ou clique para subir GIF/IMG</span>
                        <input type="file" id="input_img_ajax" name="arquivo_img" class="hidden">
                    </label>
                    <div id="upload_status" class="hidden text-center text-[10px] font-black text-emerald-500 uppercase italic animate-pulse">Sincronizando arquivo...</div>
                </form>
                <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100 text-[10px] text-slate-400 leading-relaxed font-medium">
                    <b class="text-slate-600 block mb-1 uppercase">Dica:</b>
                    Ao clicar em "Inserir" na lista ao lado, o código Markdown será injetado exatamente onde está o cursor do seu editor.
                </div>
            </div>

            <div class="md:col-span-8 space-y-4">
                <div class="relative">
                    <input type="text" id="filtro_midia" placeholder="Buscar por nome (ex: login)..." class="w-full pl-6 pr-4 py-4 bg-slate-100 border-none rounded-2xl text-xs font-bold outline-none focus:ring-4 focus:ring-blue-500/10">
                </div>
                <div class="max-h-[450px] overflow-y-auto custom-scrollbar border border-slate-50 rounded-[2rem] bg-white">
                    <table class="w-full text-left">
                        <tbody id="lista-midia-modal">
                            <?php 
                            foreach($imagens_biblioteca as $img): 
                                $n = basename($img); 
                                $link_del = "admin_docs.php?excluir_img=" . base64_encode($img);
                            ?>
                            <tr class="border-b border-slate-50 hover:bg-blue-50 transition-all group" data-nome="<?= strtolower($n) ?>">
                                <td class="p-4 w-16" onclick="inserirNoEditor('<?= $n ?>')">
                                    <div class="w-10 h-10 rounded-lg bg-white border border-slate-100 flex items-center justify-center overflow-hidden shadow-sm cursor-pointer">
                                        <img src="img/<?= $n ?>" class="max-w-full max-h-full object-contain">
                                    </div>
                                </td>
                                <td class="p-4 cursor-pointer" onclick="inserirNoEditor('<?= $n ?>')">
                                    <p class="text-[11px] font-bold text-slate-700 truncate"><?= $n ?></p>
                                    <p class="text-[8px] text-slate-400 font-black uppercase mt-1 tracking-widest"><?= round(filesize($img)/1024, 1) ?> KB</p>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="event.stopPropagation(); renomearArquivo('<?= $n ?>')" title="Renomear" class="p-2 rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-500 hover:text-white transition-all">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M15.414 2.414a2 2 0 012.828 0L21 5.586a2 2 0 010 2.828l-7 7-4 1 1-4 7-7z" /></svg>
                                        </button>
                                        <a href="<?= $link_del ?>" onclick="event.stopPropagation(); return confirm('Excluir permanentemente?')" title="Excluir" class="p-2 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-all">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    marked.setOptions({ gfm: true, breaks: true, sanitize: false });

    function atualizarPreview() {
        const rawText = document.getElementById('editor_md').value;
        const nomeArq = document.getElementById('nome_arquivo').value;
        
        // Se tem nome, salva específico. Se não, salva no rascunho temporário.
        const chave = nomeArq ? 'rascunho_navi_' + nomeArq : 'rascunho_navi_novo';
        localStorage.setItem(chave, rawText);

        let formattedText = rawText
            .replace(/:::info([\s\S]*?):::/g, '<div class="alert-info">$1</div>')
            .replace(/:::danger([\s\S]*?):::/g, '<div class="alert-danger">$1</div>');

        document.getElementById('preview_md').innerHTML = marked.parse(formattedText);
    }

    window.onload = function() {
        const nomeArq = document.getElementById('nome_arquivo').value;
        // Tenta carregar rascunho específico ou o temporário de "novo"
        const chave = nomeArq ? 'rascunho_navi_' + nomeArq : 'rascunho_navi_novo';
        const salvo = localStorage.getItem(chave);
        
        if (salvo && !document.getElementById('editor_md').value) {
            document.getElementById('editor_md').value = salvo;
        }
        atualizarPreview();
    };

    // ==========================================
    // CÃO DE GUARDA (Bloqueia F5 ou Fechar a Aba)
    // ==========================================
    let intencaoDeSalvar = false; // Variável de controle

    window.addEventListener('beforeunload', function (e) {
        const conteudo = document.getElementById('editor_md').value.trim();
        
        // Só exibe o alerta se a pessoa digitou algo E não clicou no botão "Salvar"
        if (conteudo.length > 0 && !intencaoDeSalvar) {
            e.preventDefault();
            e.returnValue = ''; // Padrão do navegador para exibir a caixa "Tem certeza que deseja sair?"
        }
    });

    // Quando clicar no botão "Publicar", liberamos o cão de guarda para a página carregar
    document.getElementById('form_principal').addEventListener('submit', function() {
        intencaoDeSalvar = true; 
    });

    // LIMPEZA AUTOMÁTICA APÓS SALVAR [Pilastra da Lógica]
    <?php if ($mensagem && strpos($mensagem, '✅') !== false): ?>
        const nomeAtual = document.getElementById('nome_arquivo').value;
        localStorage.removeItem('rascunho_navi_' + nomeAtual);
        localStorage.removeItem('rascunho_navi_novo');
    <?php endif; ?>

    function inserirFormato(inicio, fim) {
        const editor = document.getElementById('editor_md');
        const start = editor.selectionStart;
        const end = editor.selectionEnd;
        const sel = editor.value.substring(start, end);
        editor.value = editor.value.substring(0, start) + inicio + sel + fim + editor.value.substring(end);
        atualizarPreview();
        editor.focus();
    }

    function inserirTemplate(tipo) {
        const editor = document.getElementById('editor_md');
        const user = document.getElementById('user_sessao').value;
        const dataHoje = new Intl.DateTimeFormat('pt-BR').format(new Date());
        let t = "";

        if (tipo === 'info') t = `:::info Informações do Manual\n**Setor:** \n**Assinado por:** ${user}\n**Data:** ${dataHoje}\n:::\n\n`;
        else if (tipo === 'danger') t = `:::danger ATENÇÃO\nEscreva o aviso aqui.\n:::\n\n`;
        else if (tipo === 'separador') t = `\n---\n\n`;

        const pos = editor.selectionStart;
        editor.value = editor.value.substring(0, pos) + t + editor.value.substring(pos);
        atualizarPreview();
        editor.focus();
    }

    function toggleModalMidia() {
        document.getElementById('modalMidia').classList.toggle('hidden');
    }

    function inserirNoEditor(nome) {
        const editor = document.getElementById('editor_md');
        const pos = editor.selectionStart;
        const code = `\n![IA](img/${nome})\n`;
        editor.value = editor.value.substring(0, pos) + code + editor.value.substring(pos);
        toggleModalMidia();
        atualizarPreview();
        editor.focus();
    }

    document.getElementById('input_img_ajax').addEventListener('change', function() {
        const fd = new FormData();
        fd.append('arquivo_img', this.files[0]);
        fd.append('ajax_upload', 'true');
        document.getElementById('upload_status').classList.remove('hidden');

        fetch('admin_docs.php', { method: 'POST', body: fd })
        .then(() => { location.reload(); }); 
    });

    document.getElementById('filtro_midia').addEventListener('input', function(e) {
        const t = e.target.value.toLowerCase();
        document.querySelectorAll('#lista-midia-modal tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(t) ? 'table-row' : 'none';
        });
    });

    function renomearArquivo(antigo) {
        const novo = prompt("Novo nome para o arquivo (mantenha a extensão):", antigo);
        if(novo && novo !== antigo) {
            window.location.href = `admin_docs.php?acao=renomear&antigo=${encodeURIComponent(antigo)}&novo=${encodeURIComponent(novo)}`;
        }
    }
</script>
</body>
</html>