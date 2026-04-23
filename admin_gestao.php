<?php
require_once 'config.php';

// =====================================================================
// 1. PROCESSAMENTO DE FORMULÁRIOS (Backend embutido)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    // A. SALVAR USUÁRIO (Permissões Individuais e Grupos)
    if ($_POST['acao'] === 'salvar_usuario') {
        $uid = $_POST['user_id'];
        
        // 1. Salva Permissões Individuais do Sistema
        $p_admin = isset($_POST['p_admin']) ? 1 : 0;
        $p_docs  = isset($_POST['p_docs']) ? 1 : 0;
        $p_feed  = isset($_POST['p_feed']) ? 1 : 0;
        $p_aces  = isset($_POST['p_acessos']) ? 1 : 0;
        
        $sql_perm = "INSERT INTO usuarios_permissoes (usuario_id, is_admin, pode_gerenciar_docs, pode_postar_feed, pode_gerenciar_acessos) 
                     VALUES (?, ?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE 
                     is_admin = VALUES(is_admin), pode_gerenciar_docs = VALUES(pode_gerenciar_docs), 
                     pode_postar_feed = VALUES(pode_postar_feed), pode_gerenciar_acessos = VALUES(pode_gerenciar_acessos)";
        $pdo_intra->prepare($sql_perm)->execute([$uid, $p_admin, $p_docs, $p_feed, $p_aces]);

        // 2. Salva os Grupos que o usuário pertence
        $pdo_intra->prepare("DELETE FROM usuarios_grupos WHERE usuario_id = ?")->execute([$uid]);
        if (!empty($_POST['grupos'])) {
            $stmt_g = $pdo_intra->prepare("INSERT INTO usuarios_grupos (usuario_id, grupo_id) VALUES (?, ?)");
            foreach ($_POST['grupos'] as $gid) { $stmt_g->execute([$uid, $gid]); }
        }

        // 3. Salva as Pastas Individuais
        $pdo_intra->prepare("DELETE FROM permissoes_pastas WHERE user_id = ?")->execute([$uid]);
        if (!empty($_POST['pastas'])) {
            $stmt_p = $pdo_intra->prepare("INSERT INTO permissoes_pastas (user_id, pasta_nome) VALUES (?, ?)");
            foreach ($_POST['pastas'] as $pasta) { $stmt_p->execute([$uid, $pasta]); }
        }
        
        $msg_sucesso = "✅ Permissões do usuário atualizadas com sucesso!";
    }

    // B. SALVAR GRUPO
    if ($_POST['acao'] === 'salvar_grupo') {
        $gid = $_POST['grupo_id'];
        $nome = trim($_POST['nome_grupo']);
        $g_admin = isset($_POST['g_admin']) ? 1 : 0;
        $g_docs  = isset($_POST['g_docs']) ? 1 : 0;
        $g_feed  = isset($_POST['g_feed']) ? 1 : 0;
        $g_aces  = isset($_POST['g_acessos']) ? 1 : 0;

        if (empty($gid)) {
            // Novo Grupo
            $sql = "INSERT INTO grupos_intranet (nome, is_admin, pode_gerenciar_docs, pode_postar_feed, pode_gerenciar_acessos) VALUES (?, ?, ?, ?, ?)";
            $pdo_intra->prepare($sql)->execute([$nome, $g_admin, $g_docs, $g_feed, $g_aces]);
            $gid = $pdo_intra->lastInsertId();
        } else {
            // Edita Grupo
            $sql = "UPDATE grupos_intranet SET nome = ?, is_admin = ?, pode_gerenciar_docs = ?, pode_postar_feed = ?, pode_gerenciar_acessos = ? WHERE id = ?";
            $pdo_intra->prepare($sql)->execute([$nome, $g_admin, $g_docs, $g_feed, $g_aces, $gid]);
        }

        // Salva as pastas do grupo
        $pdo_intra->prepare("DELETE FROM grupos_pastas WHERE grupo_id = ?")->execute([$gid]);
        if (!empty($_POST['pastas_grupo'])) {
            $stmt_p = $pdo_intra->prepare("INSERT INTO grupos_pastas (grupo_id, pasta_nome) VALUES (?, ?)");
            foreach ($_POST['pastas_grupo'] as $pasta) { $stmt_p->execute([$gid, $pasta]); }
        }
        
        $msg_sucesso = "✅ Grupo salvo com sucesso!";
    }

    // C. EXCLUIR GRUPO
    if ($_POST['acao'] === 'excluir_grupo') {
        $gid = $_POST['grupo_id'];
        $pdo_intra->prepare("DELETE FROM grupos_intranet WHERE id = ?")->execute([$gid]);
        $pdo_intra->prepare("DELETE FROM grupos_pastas WHERE grupo_id = ?")->execute([$gid]);
        $pdo_intra->prepare("DELETE FROM usuarios_grupos WHERE grupo_id = ?")->execute([$gid]);
        $msg_sucesso = "🗑️ Grupo excluído com sucesso!";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';

// Proteção: Se não for admin ou não tiver permissão
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    if (!isset($_SESSION['pode_gerenciar_acessos']) || $_SESSION['pode_gerenciar_acessos'] !== true) {
        die("<script>window.location.href='index.php';</script>");
    }
}

// =====================================================================
// 2. BUSCA DE DADOS PARA A TELA
// =====================================================================

// A. Pastas Físicas
$diretorio_docs = __DIR__ . '/docs/';
$pastas_fisicas = [];
if (is_dir($diretorio_docs)) {
    $dirs = scandir($diretorio_docs);
    foreach ($dirs as $d) {
        if ($d !== '.' && $d !== '..' && is_dir($diretorio_docs . $d)) {
            $pastas_fisicas[] = strtoupper($d);
        }
    }
}

// B. Todos os Grupos
$grupos = $pdo_intra->query("SELECT * FROM grupos_intranet ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$grupos_map = []; // Para facilitar o JS
foreach ($grupos as &$g) {
    $stmt = $pdo_intra->prepare("SELECT pasta_nome FROM grupos_pastas WHERE grupo_id = ?");
    $stmt->execute([$g['id']]);
    $g['pastas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $grupos_map[$g['id']] = $g;
}

// C. Todos os Usuários
$usuarios = $pdo_glpi->query("
    SELECT u.id, u.name as login, u.firstname, u.realname, l.name as setor 
    FROM glpi_users u 
    LEFT JOIN glpi_locations l ON u.locations_id = l.id 
    WHERE u.is_deleted = 0 AND u.is_active = 1
    ORDER BY u.firstname ASC
")->fetchAll(PDO::FETCH_ASSOC);

$usuarios_json = []; // Para o modal
foreach ($usuarios as &$u) {
    // Busca Permissões Individuais
    $stmt_p = $pdo_intra->prepare("SELECT * FROM usuarios_permissoes WHERE usuario_id = ?");
    $stmt_p->execute([$u['id']]);
    $u['perms'] = $stmt_p->fetch(PDO::FETCH_ASSOC) ?: [];

    // Busca Pastas Individuais
    $stmt_pastas = $pdo_intra->prepare("SELECT pasta_nome FROM permissoes_pastas WHERE user_id = ?");
    $stmt_pastas->execute([$u['id']]);
    $u['pastas_indiv'] = $stmt_pastas->fetchAll(PDO::FETCH_COLUMN);

    // Busca Grupos do Usuário
    $stmt_ug = $pdo_intra->prepare("SELECT grupo_id FROM usuarios_grupos WHERE usuario_id = ?");
    $stmt_ug->execute([$u['id']]);
    $u['meus_grupos'] = $stmt_ug->fetchAll(PDO::FETCH_COLUMN);

    $usuarios_json[$u['id']] = $u;
}
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-8">
    <div class="max-w-7xl mx-auto">
        
        <?php if(!empty($msg_sucesso)): ?>
            <div class="mb-6 bg-emerald-50 text-emerald-700 p-4 rounded-2xl font-bold border border-emerald-100 shadow-sm animate-pulse">
                <?php echo $msg_sucesso; ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h2 class="text-3xl font-black text-navy-900 tracking-tight uppercase italic">Centro de Controle RBAC</h2>
                <p class="text-slate-500 font-medium mt-1">Gerencie grupos, permissões de sistema e acessos a pastas.</p>
            </div>

            <div class="flex p-1.5 bg-white border border-slate-200 rounded-2xl shadow-sm">
                <button onclick="switchTab('usuarios')" id="btn-usuarios" class="px-6 py-2.5 rounded-xl font-bold text-sm transition-all bg-navy-900 text-white shadow-md">
                    👤 Usuários
                </button>
                <button onclick="switchTab('grupos')" id="btn-grupos" class="px-6 py-2.5 rounded-xl font-bold text-sm transition-all text-slate-500 hover:text-navy-900">
                    🛡️ Grupos de Acesso
                </button>
            </div>
        </div>

        <div id="tab-usuarios" class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden transition-all duration-500">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-navy-900">Colaboradores do GLPI</h3>
                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-black"><?php echo count($usuarios); ?> ativos</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Colaborador</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Setor Raiz</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Grupos Atribuídos</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Permissões Extras</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($usuarios as $u): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="font-bold text-navy-900 text-sm"><?php echo $u['firstname'] . ' ' . $u['realname']; ?></div>
                                <div class="text-[10px] text-slate-400 font-medium">@<?php echo $u['login']; ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-[10px] font-black uppercase"><?php echo $u['setor'] ?: 'SEM SETOR'; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <?php if(empty($u['meus_grupos'])) echo '<span class="text-[10px] text-slate-300 italic">Nenhum</span>'; ?>
                                    <?php foreach($u['meus_grupos'] as $gid): ?>
                                        <span class="px-2 py-1 bg-purple-50 text-purple-700 border border-purple-100 rounded text-[9px] font-black uppercase tracking-widest">
                                            <?php echo $grupos_map[$gid]['nome'] ?? 'Desconhecido'; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $tags = [];
                                    if(!empty($u['perms']['is_admin'])) $tags[] = '<span class="px-2 py-1 bg-red-50 text-red-600 rounded text-[9px] font-black uppercase border border-red-100">Super Admin</span>';
                                    if(!empty($u['perms']['pode_gerenciar_docs'])) $tags[] = '<span class="px-2 py-1 bg-emerald-50 text-emerald-600 rounded text-[9px] font-black uppercase border border-emerald-100">Docs</span>';
                                    if(!empty($u['perms']['pode_postar_feed'])) $tags[] = '<span class="px-2 py-1 bg-amber-50 text-amber-600 rounded text-[9px] font-black uppercase border border-amber-100">Feed</span>';
                                    if(!empty($u['pastas_indiv'])) $tags[] = '<span class="px-2 py-1 bg-blue-50 text-blue-600 rounded text-[9px] font-black uppercase border border-blue-100">+ Pastas VIP</span>';
                                    
                                    if(empty($tags)) echo '<span class="text-[10px] text-slate-300 italic">Herdando do Setor/Grupo</span>';
                                    else echo implode('', $tags);
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button onclick="abrirModalUser(<?php echo $u['id']; ?>)" class="text-[10px] bg-white border border-slate-200 text-slate-600 hover:bg-corporate-blue hover:text-white hover:border-corporate-blue px-3 py-1.5 rounded-lg font-bold uppercase transition-all shadow-sm">
                                    Ajustar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-grupos" class="hidden bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden transition-all duration-500">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-navy-900">Grupos de Acesso (RBAC)</h3>
                <button onclick="abrirModalGrupo(0)" class="bg-emerald-500 text-white px-4 py-2 rounded-xl text-xs font-black uppercase shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 transition-all">
                    + Criar Novo Grupo
                </button>
            </div>
            
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if(empty($grupos)): ?>
                    <div class="col-span-full text-center py-12 text-slate-400 italic font-medium">Nenhum grupo criado ainda.</div>
                <?php endif; ?>

                <?php foreach ($grupos as $g): ?>
                <div class="bg-white border border-slate-200 p-5 rounded-3xl shadow-sm hover:shadow-lg transition-all relative group flex flex-col">
                    <h4 class="text-lg font-black text-navy-900 uppercase italic mb-3 pr-8"><?php echo $g['nome']; ?></h4>
                    
                    <div class="space-y-2 mb-4 flex-1">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Poderes no Sistema:</p>
                        <div class="flex flex-wrap gap-1">
                            <?php if($g['is_admin']) echo '<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-[9px] font-bold">Acesso Total (Admin)</span>'; ?>
                            <?php if($g['pode_gerenciar_docs']) echo '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[9px] font-bold">Manuais</span>'; ?>
                            <?php if($g['pode_postar_feed']) echo '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[9px] font-bold">Feed</span>'; ?>
                            <?php if($g['pode_gerenciar_acessos']) echo '<span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-[9px] font-bold">Gestão</span>'; ?>
                            <?php if(!$g['is_admin'] && !$g['pode_gerenciar_docs'] && !$g['pode_postar_feed'] && !$g['pode_gerenciar_acessos']) echo '<span class="text-[10px] text-slate-400 italic">Apenas leitura</span>'; ?>
                        </div>

                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-3">Acesso às Pastas:</p>
                        <div class="flex flex-wrap gap-1">
                            <?php if(empty($g['pastas'])) echo '<span class="text-[10px] text-slate-400 italic">Nenhuma pasta extra</span>'; ?>
                            <?php foreach($g['pastas'] as $p) echo '<span class="px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 rounded text-[9px] font-bold">'.$p.'</span>'; ?>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-auto pt-4 border-t border-slate-50">
                        <button onclick="abrirModalGrupo(<?php echo $g['id']; ?>)" class="flex-1 bg-slate-50 hover:bg-slate-900 hover:text-white text-slate-600 py-2 rounded-xl text-[10px] font-black uppercase transition-all">Editar</button>
                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir o grupo <?php echo $g['nome']; ?>?');">
                            <input type="hidden" name="acao" value="excluir_grupo">
                            <input type="hidden" name="grupo_id" value="<?php echo $g['id']; ?>">
                            <button type="submit" class="bg-red-50 hover:bg-red-500 hover:text-white text-red-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all">X</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<div id="modalUser" class="fixed inset-0 bg-navy-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <form method="POST" class="flex flex-col h-full">
            <input type="hidden" name="acao" value="salvar_usuario">
            <input type="hidden" name="user_id" id="mu_id">
            
            <div class="px-8 py-6 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div>
                    <h3 class="text-xl font-black italic uppercase tracking-tighter" id="mu_nome">Nome do Usuário</h3>
                    <p class="text-[10px] text-blue-400 font-bold uppercase tracking-widest mt-1">Configuração de Acessos Individuais</p>
                </div>
                <button type="button" onclick="fecharModais()" class="text-white/50 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            
            <div class="p-8 overflow-y-auto custom-scrollbar space-y-8 flex-1">
                
                <div>
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">🛡️ Inserir em Grupos</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach($grupos as $g): ?>
                        <label class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:border-corporate-blue transition-all group">
                            <input type="checkbox" name="grupos[]" value="<?php echo $g['id']; ?>" class="w-4 h-4 text-corporate-blue rounded border-slate-300 chk-mu-grupo">
                            <span class="text-[10px] font-bold text-slate-700 uppercase group-hover:text-corporate-blue"><?php echo $g['nome']; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">⚡ Poderes VIP (Individuais)</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 bg-red-50 border border-red-100 rounded-xl cursor-pointer">
                            <input type="checkbox" name="p_admin" id="mu_p_admin" class="w-4 h-4 text-red-600 rounded border-red-300">
                            <span class="text-[10px] font-bold text-red-800 uppercase">Super Admin (Acesso Total)</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-emerald-50 border border-emerald-100 rounded-xl cursor-pointer">
                            <input type="checkbox" name="p_docs" id="mu_p_docs" class="w-4 h-4 text-emerald-600 rounded border-emerald-300">
                            <span class="text-[10px] font-bold text-emerald-800 uppercase">Gerenciar Manuais</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-100 rounded-xl cursor-pointer">
                            <input type="checkbox" name="p_feed" id="mu_p_feed" class="w-4 h-4 text-amber-600 rounded border-amber-300">
                            <span class="text-[10px] font-bold text-amber-800 uppercase">Postar no Feed</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-purple-50 border border-purple-100 rounded-xl cursor-pointer">
                            <input type="checkbox" name="p_acessos" id="mu_p_acessos" class="w-4 h-4 text-purple-600 rounded border-purple-300">
                            <span class="text-[10px] font-bold text-purple-800 uppercase">Gestão de Acessos</span>
                        </label>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">📂 Pastas VIP (Apenas ele)</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        <?php foreach($pastas_fisicas as $pasta): ?>
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer transition-all">
                            <input type="checkbox" name="pastas[]" value="<?php echo $pasta; ?>" class="w-3.5 h-3.5 text-corporate-blue rounded border-slate-300 chk-mu-pasta">
                            <span class="text-[10px] font-bold text-slate-600 truncate"><?php echo $pasta; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            
            <div class="p-6 bg-slate-50 border-t border-slate-200 flex gap-4 shrink-0">
                <button type="button" onclick="fecharModais()" class="flex-1 py-3 text-xs font-black text-slate-500 uppercase tracking-widest hover:text-slate-800">Cancelar</button>
                <button type="submit" class="flex-[2] bg-corporate-blue text-white rounded-xl text-xs font-black uppercase tracking-widest shadow-xl shadow-blue-500/20 hover:bg-corporate-blueDark transition-all">Salvar Armadura</button>
            </div>
        </form>
    </div>
</div>

<div id="modalGroup" class="fixed inset-0 bg-navy-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh]">
        <form method="POST" class="flex flex-col h-full">
            <input type="hidden" name="acao" value="salvar_grupo">
            <input type="hidden" name="grupo_id" id="mg_id">
            
            <div class="px-8 py-6 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div>
                    <h3 class="text-xl font-black italic uppercase tracking-tighter" id="mg_titulo">Novo Grupo</h3>
                    <p class="text-[10px] text-emerald-400 font-bold uppercase tracking-widest mt-1">Configuração de Regras</p>
                </div>
                <button type="button" onclick="fecharModais()" class="text-white/50 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            
            <div class="p-8 overflow-y-auto custom-scrollbar space-y-6 flex-1">
                
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3 block mb-1">Nome do Grupo</label>
                    <input type="text" name="nome_grupo" id="mg_nome" required placeholder="Ex: Equipe T.I" class="w-full bg-slate-50 border border-slate-200 p-4 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-bold uppercase">
                </div>

                <div>
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">⚡ Poderes do Grupo</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 bg-red-50 border border-red-100 rounded-xl cursor-pointer">
                            <input type="checkbox" name="g_admin" id="mg_g_admin" class="w-4 h-4 text-red-600 rounded border-red-300">
                            <span class="text-[10px] font-bold text-red-800 uppercase">Super Admin</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-emerald-50 border border-emerald-100 rounded-xl cursor-pointer">
                            <input type="checkbox" name="g_docs" id="mg_g_docs" class="w-4 h-4 text-emerald-600 rounded border-emerald-300">
                            <span class="text-[10px] font-bold text-emerald-800 uppercase">Manuais</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-100 rounded-xl cursor-pointer">
                            <input type="checkbox" name="g_feed" id="mg_g_feed" class="w-4 h-4 text-amber-600 rounded border-amber-300">
                            <span class="text-[10px] font-bold text-amber-800 uppercase">Postar no Feed</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-purple-50 border border-purple-100 rounded-xl cursor-pointer">
                            <input type="checkbox" name="g_acessos" id="mg_g_acessos" class="w-4 h-4 text-purple-600 rounded border-purple-300">
                            <span class="text-[10px] font-bold text-purple-800 uppercase">Gestão</span>
                        </label>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">📂 Pastas Liberadas</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach($pastas_fisicas as $pasta): ?>
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer transition-all">
                            <input type="checkbox" name="pastas_grupo[]" value="<?php echo $pasta; ?>" class="w-3.5 h-3.5 text-emerald-500 rounded border-slate-300 chk-mg-pasta">
                            <span class="text-[10px] font-bold text-slate-600 truncate"><?php echo $pasta; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            
            <div class="p-6 bg-slate-50 border-t border-slate-200 flex gap-4 shrink-0">
                <button type="button" onclick="fecharModais()" class="flex-1 py-3 text-xs font-black text-slate-500 uppercase tracking-widest hover:text-slate-800">Cancelar</button>
                <button type="submit" class="flex-[2] bg-emerald-500 text-white rounded-xl text-xs font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 hover:bg-emerald-600 transition-all">Salvar Grupo</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 1. Controle das Abas (Tabs)
    function switchTab(tab) {
        document.getElementById('tab-usuarios').classList.add('hidden');
        document.getElementById('tab-grupos').classList.add('hidden');
        
        document.getElementById('btn-usuarios').className = "px-6 py-2.5 rounded-xl font-bold text-sm transition-all text-slate-500 hover:text-navy-900";
        document.getElementById('btn-grupos').className = "px-6 py-2.5 rounded-xl font-bold text-sm transition-all text-slate-500 hover:text-navy-900";

        document.getElementById('tab-' + tab).classList.remove('hidden');
        document.getElementById('btn-' + tab).className = "px-6 py-2.5 rounded-xl font-bold text-sm transition-all bg-navy-900 text-white shadow-md";
    }

    // Dados injetados pelo PHP para preencher os modais rápido
    const dadosUsuarios = <?php echo json_encode($usuarios_json); ?>;
    const dadosGrupos = <?php echo json_encode($grupos_map); ?>;

    function fecharModais() {
        document.getElementById('modalUser').classList.replace('flex', 'hidden');
        document.getElementById('modalGroup').classList.replace('flex', 'hidden');
    }

    // Modal de Usuário
    function abrirModalUser(id) {
        const u = dadosUsuarios[id];
        document.getElementById('mu_id').value = id;
        document.getElementById('mu_nome').innerText = u.firstname + ' ' + u.realname;
        
        // Limpa tudo
        document.querySelectorAll('.chk-mu-grupo').forEach(c => c.checked = false);
        document.querySelectorAll('.chk-mu-pasta').forEach(c => c.checked = false);
        document.getElementById('mu_p_admin').checked = false;
        document.getElementById('mu_p_docs').checked = false;
        document.getElementById('mu_p_feed').checked = false;
        document.getElementById('mu_p_acessos').checked = false;

        // Preenche com os dados do banco
        u.meus_grupos.forEach(gid => {
            const cb = document.querySelector(`.chk-mu-grupo[value="${gid}"]`);
            if(cb) cb.checked = true;
        });
        u.pastas_indiv.forEach(p => {
            const cb = document.querySelector(`.chk-mu-pasta[value="${p}"]`);
            if(cb) cb.checked = true;
        });

        if(u.perms.is_admin == 1) document.getElementById('mu_p_admin').checked = true;
        if(u.perms.pode_gerenciar_docs == 1) document.getElementById('mu_p_docs').checked = true;
        if(u.perms.pode_postar_feed == 1) document.getElementById('mu_p_feed').checked = true;
        if(u.perms.pode_gerenciar_acessos == 1) document.getElementById('mu_p_acessos').checked = true;

        document.getElementById('modalUser').classList.replace('hidden', 'flex');
    }

    // Modal de Grupo
    function abrirModalGrupo(id) {
        // Limpa tudo
        document.getElementById('mg_id').value = '';
        document.getElementById('mg_titulo').innerText = 'NOVO GRUPO';
        document.getElementById('mg_nome').value = '';
        document.querySelectorAll('.chk-mg-pasta').forEach(c => c.checked = false);
        document.getElementById('mg_g_admin').checked = false;
        document.getElementById('mg_g_docs').checked = false;
        document.getElementById('mg_g_feed').checked = false;
        document.getElementById('mg_g_acessos').checked = false;

        // Se for edição, preenche!
        if (id !== 0) {
            const g = dadosGrupos[id];
            document.getElementById('mg_id').value = id;
            document.getElementById('mg_titulo').innerText = 'EDITAR: ' + g.nome;
            document.getElementById('mg_nome').value = g.nome;

            if(g.is_admin == 1) document.getElementById('mg_g_admin').checked = true;
            if(g.pode_gerenciar_docs == 1) document.getElementById('mg_g_docs').checked = true;
            if(g.pode_postar_feed == 1) document.getElementById('mg_g_feed').checked = true;
            if(g.pode_gerenciar_acessos == 1) document.getElementById('mg_g_acessos').checked = true;

            g.pastas.forEach(p => {
                const cb = document.querySelector(`.chk-mg-pasta[value="${p}"]`);
                if(cb) cb.checked = true;
            });
        }

        document.getElementById('modalGroup').classList.replace('hidden', 'flex');
    }
</script>

<?php include 'includes/footer.php'; ?>