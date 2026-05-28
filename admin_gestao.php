<?php
require_once 'config.php';

// =====================================================================
// 1. PROCESSAMENTO DE FORMULÁRIOS (Padrão PRG Aplicado)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    $admin_id = $_SESSION['user_id'] ?? 0;
    $admin_ip = $_SERVER['REMOTE_ADDR'];

    try {
        // A. SALVAR USUÁRIO
        if ($_POST['acao'] === 'salvar_usuario') {
            $uid = $_POST['user_id'];
            
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

            $pdo_intra->prepare("DELETE FROM usuarios_grupos WHERE usuario_id = ?")->execute([$uid]);
            if (!empty($_POST['grupos'])) {
                $stmt_g = $pdo_intra->prepare("INSERT INTO usuarios_grupos (usuario_id, grupo_id) VALUES (?, ?)");
                foreach ($_POST['grupos'] as $gid) { $stmt_g->execute([$uid, $gid]); }
            }

            $pdo_intra->prepare("DELETE FROM permissoes_pastas WHERE user_id = ?")->execute([$uid]);
            if (!empty($_POST['pastas'])) {
                $stmt_p = $pdo_intra->prepare("INSERT INTO permissoes_pastas (user_id, pasta_nome) VALUES (?, ?)");
                foreach ($_POST['pastas'] as $pasta) { $stmt_p->execute([$uid, $pasta]); }
            }
            
            $pdo_intra->prepare("DELETE FROM permissoes_videos WHERE user_id = ?")->execute([$uid]);
            if (!empty($_POST['videos'])) {
                $stmt_v = $pdo_intra->prepare("INSERT INTO permissoes_videos (user_id, pasta_video) VALUES (?, ?)");
                foreach ($_POST['videos'] as $video) { $stmt_v->execute([$uid, $video]); }
            }

            // Limpa permissões antigas
            $pdo_intra->prepare("DELETE FROM permissoes_sistemas WHERE user_id = ?")->execute([$uid]);
            
            // Grava as novas permissões individuais de forma explícita
            if (!empty($_POST['sistemas'])) {
                // Forçamos o insert ignorando conflito de índices antigos se houver
                $stmt_s = $pdo_intra->prepare("INSERT IGNORE INTO permissoes_sistemas (user_id, sistema_id) VALUES (?, ?)");
                foreach ($_POST['sistemas'] as $sid) { 
                    $stmt_s->execute([$uid, (int)$sid]); 
                }
            }
            
            registrarLog($pdo_intra, 'ALTEROU ACESSOS', "Modificou a matriz de permissões/grupos do usuário ID: $uid", $admin_id, $admin_ip);
            
            header("Location: admin_gestao.php?sucesso=" . urlencode("Permissões do usuário atualizadas com sucesso!"));
            exit;
        }

        // B. SALVAR GRUPO
        if ($_POST['acao'] === 'salvar_grupo') {
            $gid = $_POST['grupo_id'];
            $nome = strtoupper(trim($_POST['nome_grupo'])); 

            $g_admin = isset($_POST['g_admin']) ? 1 : 0;
            $g_docs  = isset($_POST['g_docs']) ? 1 : 0;
            $g_feed  = isset($_POST['g_feed']) ? 1 : 0;
            $g_aces  = isset($_POST['g_acessos']) ? 1 : 0; 

            if (empty($gid)) {
                // TRAVA DE ENGENHARIA: Verifica se já existe um grupo com esse nome
                $stmt_check = $pdo_intra->prepare("SELECT id FROM grupos_intranet WHERE nome = ?");
                $stmt_check->execute([$nome]);
                if ($stmt_check->fetch()) {
                    header("Location: admin_gestao.php?erro=" . urlencode("Já existe um grupo com o nome '$nome'."));
                    exit;
                }

                $sql = "INSERT INTO grupos_intranet (nome, is_admin, pode_gerenciar_docs, pode_postar_feed, pode_gerenciar_acessos) VALUES (?, ?, ?, ?, ?)";
                $pdo_intra->prepare($sql)->execute([$nome, $g_admin, $g_docs, $g_feed, $g_aces]);
                $gid = $pdo_intra->lastInsertId();
                registrarLog($pdo_intra, 'CRIOU GRUPO', "Criou o novo grupo de acessos: $nome", $admin_id, $admin_ip);
            } else {
                $sql = "UPDATE grupos_intranet SET nome = ?, is_admin = ?, pode_gerenciar_docs = ?, pode_postar_feed = ?, pode_gerenciar_acessos = ? WHERE id = ?";
                $pdo_intra->prepare($sql)->execute([$nome, $g_admin, $g_docs, $g_feed, $g_aces, $gid]);
                registrarLog($pdo_intra, 'EDITOU GRUPO', "Alterou as regras do grupo: $nome", $admin_id, $admin_ip);
            }

            $pdo_intra->prepare("DELETE FROM grupos_pastas WHERE grupo_id = ?")->execute([$gid]);
            if (!empty($_POST['pastas_grupo'])) {
                $stmt_p = $pdo_intra->prepare("INSERT INTO grupos_pastas (grupo_id, pasta_nome) VALUES (?, ?)");
                foreach ($_POST['pastas_grupo'] as $pasta) { $stmt_p->execute([$gid, $pasta]); }
            }

            $pdo_intra->prepare("DELETE FROM grupos_videos WHERE grupo_id = ?")->execute([$gid]);
            if (!empty($_POST['videos_grupo'])) {
                $stmt_v = $pdo_intra->prepare("INSERT INTO grupos_videos (grupo_id, pasta_video) VALUES (?, ?)");
                foreach ($_POST['videos_grupo'] as $video) { $stmt_v->execute([$gid, $video]); }
            }

            $pdo_intra->prepare("DELETE FROM grupos_sistemas WHERE grupo_id = ?")->execute([$gid]);
            if (!empty($_POST['sistemas_grupo'])) {
                $stmt_s = $pdo_intra->prepare("INSERT INTO grupos_sistemas (grupo_id, sistema_id) VALUES (?, ?)");
                foreach ($_POST['sistemas_grupo'] as $sid) { $stmt_s->execute([$gid, $sid]); }
            }
            
            header("Location: admin_gestao.php?sucesso=" . urlencode("Grupo '$nome' salvo com sucesso!"));
            exit;
        }

        // C. EXCLUIR GRUPO
        if ($_POST['acao'] === 'excluir_grupo') {
            $gid = $_POST['grupo_id'];
            $pdo_intra->prepare("DELETE FROM grupos_intranet WHERE id = ?")->execute([$gid]);
            $pdo_intra->prepare("DELETE FROM grupos_pastas WHERE grupo_id = ?")->execute([$gid]);
            $pdo_intra->prepare("DELETE FROM grupos_videos WHERE grupo_id = ?")->execute([$gid]);
            $pdo_intra->prepare("DELETE FROM usuarios_grupos WHERE grupo_id = ?")->execute([$gid]);
            $pdo_intra->prepare("DELETE FROM grupos_sistemas WHERE grupo_id = ?")->execute([$gid]);
            registrarLog($pdo_intra, 'EXCLUIU GRUPO', "Deletou o grupo de ID: $gid", $admin_id, $admin_ip);
            
            header("Location: admin_gestao.php?sucesso=" . urlencode("Grupo excluído com sucesso!"));
            exit;
        }

        // D. SALVAR SISTEMA COM SUPORTE A HIERARQUIA
        if ($_POST['acao'] === 'salvar_sistema') {
            $nome   = trim($_POST['sys_nome']);
            $url    = trim($_POST['sys_url']);
            $icone  = trim($_POST['sys_icone']);
            $cor    = trim($_POST['sys_cor']);
            $sid    = $_POST['sistema_id'] ?? '';
            
            // Se não escolher nenhum pai, o valor gravado será NULL (Vira bolinha raiz)
            $pai_id = !empty($_POST['sys_pai_id']) ? (int)$_POST['sys_pai_id'] : null;

            if (empty($sid)) {
                $pdo_intra->prepare("INSERT INTO sistemas_lista (nome, url, icone, cor, pai_id) VALUES (?, ?, ?, ?, ?)")
                          ->execute([$nome, $url, $icone, $cor, $pai_id]);
                registrarLog($pdo_intra, 'CRIOU SISTEMA', "Criou o sistema: $nome", $admin_id, $admin_ip);
            } else {
                $pdo_intra->prepare("UPDATE sistemas_lista SET nome=?, url=?, icone=?, cor=?, pai_id=? WHERE id=?")
                          ->execute([$nome, $url, $icone, $cor, $pai_id, $sid]);
                registrarLog($pdo_intra, 'EDITOU SISTEMA', "Editou as regras do sistema ID: $sid", $admin_id, $admin_ip);
            }
            
            header("Location: admin_gestao.php?sucesso=" . urlencode("Sistema salvo com sucesso!"));
            exit;
        }
        
        // E. EXCLUIR SISTEMA
        if ($_POST['acao'] === 'excluir_sistema') {
            $sid = $_POST['sistema_id'];
            $pdo_intra->prepare("DELETE FROM sistemas_lista WHERE id = ?")->execute([$sid]);
            $pdo_intra->prepare("DELETE FROM permissoes_sistemas WHERE sistema_id = ?")->execute([$sid]);
            $pdo_intra->prepare("DELETE FROM grupos_sistemas WHERE sistema_id = ?")->execute([$sid]);
            registrarLog($pdo_intra, 'EXCLUIU SISTEMA', "Deletou o sistema ID: $sid", $admin_id, $admin_ip);
            
            header("Location: admin_gestao.php?sucesso=" . urlencode("Sistema excluído com sucesso!"));
            exit;
        }

        // 🔥 GATILHO PARA RESETAR SENHAS EM MASSA (MÚLTIPLOS SELECIONADOS)
        if ($_POST['acao'] === 'resetar_senha_massa') {
            if (!isset($_POST['usuarios_ids']) || !is_array($_POST['usuarios_ids'])) {
                echo "Erro: Nenhum colaborador selecionado.";
                exit;
            }

            // Sanitiza os IDs recebidos para garantir inteiros seguros
            $ids_selecionados = array_map('intval', $_POST['usuarios_ids']);
            
            // 1. Gera o Hash Bcrypt padrão corporativo (Souza@123)
            $senha_padrao_hash = password_hash('Souza@123', PASSWORD_BCRYPT);
            
            // Cria os placeholders dinâmicos (?,?,?) para a query SQL IN
            $placeholders = implode(',', array_fill(0, count($ids_selecionados), '?'));

            // 2. Atualiza em lote na tabela do GLPI
            $sql_glpi = "UPDATE glpi_users SET password = ? WHERE id IN ($placeholders)";
            $stmt_glpi = $pdo_glpi->prepare($sql_glpi);
            // Mescla o hash da senha como primeiro parâmetro com o array de IDs
            $stmt_glpi->execute(array_merge([$senha_padrao_hash], $ids_selecionados));

            // 3. Força o estado de primeiro login em lote na Intranet
            $sql_intra = "INSERT INTO usuarios_permissoes (usuario_id, primeiro_login) VALUES ";
            $valores_query = [];
            $pares_query = [];
            
            foreach ($ids_selecionados as $id_u) {
                $pares_query[] = "(?, 1)";
                $valores_query[] = $id_u;
            }
            
            $sql_intra .= implode(', ', $pares_query) . " ON DUPLICATE KEY UPDATE primeiro_login = 1";
            $stmt_intra = $pdo_intra->prepare($sql_intra);
            $stmt_intra->execute($valores_query);

            // Grava a auditoria dos múltiplos resets nos logs
            $qtd_afetados = count($ids_selecionados);
            registrarLog($pdo_intra, 'RESET EM MASSA', "Resetou a senha de $qtd_afetados colaboradores em lote para Souza@123", $admin_id, $admin_ip);

            echo "sucesso";
            exit;
        }

        // 🔥 GATILHO INDIVIDUAL: RESETAR SENHA DE APENAS UM USUÁRIO (Botão 🔄)
        if ($_POST['acao'] === 'resetar_senha_usuario') {
            $uid = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$uid) {
                echo "Erro: ID inválido.";
                exit;
            }
            
            // 1. Gera o Hash Bcrypt padrão para "Souza@123"
            $senha_padrao_hash = password_hash('Souza@123', PASSWORD_BCRYPT);
            
            // 2. Atualiza direto no banco do GLPI
            $stmt_glpi = $pdo_glpi->prepare("UPDATE glpi_users SET password = ? WHERE id = ?");
            $stmt_glpi->execute([$senha_padrao_hash, $uid]);
            
            // 3. Marca na intranet para forçar a troca no primeiro login
            $stmt_intra = $pdo_intra->prepare("
                INSERT INTO usuarios_permissoes (usuario_id, primeiro_login) 
                VALUES (?, 1) 
                ON DUPLICATE KEY UPDATE primeiro_login = 1
            ");
            $stmt_intra->execute([$uid]);
            
            registrarLog($pdo_intra, 'RESET SENHA', "Resetou a senha do usuário ID $uid de forma individual para Souza@123", $admin_id, $admin_ip);

            echo "sucesso";
            exit;
        }

    } catch (Exception $e) {
        // Redireciona com erro genérico se algo falhar no banco
        header("Location: admin_gestao.php?erro=" . urlencode("Erro no banco de dados ao salvar as informações."));
        exit;
    }
}

// RECUPERA AS MENSAGENS DA URL
$msg_sucesso = $_GET['sucesso'] ?? '';
$msg_erro = $_GET['erro'] ?? '';

include 'includes/header.php';
include 'includes/sidebar.php';

// TRAVA DE SEGURANÇA DA PÁGINA
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    if (!isset($_SESSION['pode_gerenciar_acessos']) || $_SESSION['pode_gerenciar_acessos'] !== true) {
        die("<script>window.location.href='index.php';</script>");
    }
}

// =====================================================================
// 2. BUSCA DE DADOS PARA A TELA
// =====================================================================
$diretorio_docs = __DIR__ . '/docs/';
$pastas_fisicas = [];
if (is_dir($diretorio_docs)) {
    $dirs = scandir($diretorio_docs);
    foreach ($dirs as $d) {
        if ($d !== '.' && $d !== '..' && is_dir($diretorio_docs . $d)) $pastas_fisicas[] = strtoupper($d);
    }
}

$diretorio_videos = __DIR__ . '/videos/';
$pastas_videos = [];
if (is_dir($diretorio_videos)) {
    $dirs_v = scandir($diretorio_videos);
    foreach ($dirs_v as $dv) {
        if ($dv !== '.' && $dv !== '..' && is_dir($diretorio_videos . $dv)) $pastas_videos[] = strtoupper($dv);
    }
}

$sistemas_db = $pdo_intra->query("SELECT * FROM sistemas_lista ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$grupos = $pdo_intra->query("SELECT * FROM grupos_intranet ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$grupos_map = [];
foreach ($grupos as &$g) {
    $stmt = $pdo_intra->prepare("SELECT pasta_nome FROM grupos_pastas WHERE grupo_id = ?");
    $stmt->execute([$g['id']]);
    $g['pastas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt_vid = $pdo_intra->prepare("SELECT pasta_video FROM grupos_videos WHERE grupo_id = ?");
    $stmt_vid->execute([$g['id']]);
    $g['videos'] = $stmt_vid->fetchAll(PDO::FETCH_COLUMN);

    $stmt_sys = $pdo_intra->prepare("SELECT sistema_id FROM grupos_sistemas WHERE grupo_id = ?");
    $stmt_sys->execute([$g['id']]);
    $g['sistemas'] = $stmt_sys->fetchAll(PDO::FETCH_COLUMN);

    $grupos_map[$g['id']] = $g;
}
unset($g);

$usuarios = $pdo_glpi->query("
    SELECT u.id, u.name as login, u.firstname, u.realname, l.name AS setor 
    FROM glpi_users u 
    LEFT JOIN glpi_locations l ON u.locations_id = l.id 
    WHERE u.is_deleted = 0 AND u.is_active = 1
    ORDER BY u.firstname ASC, u.realname ASC
")->fetchAll(PDO::FETCH_ASSOC);

$usuarios_json = [];
foreach ($usuarios as &$u) {
    $stmt_p = $pdo_intra->prepare("SELECT * FROM usuarios_permissoes WHERE usuario_id = ?");
    $stmt_p->execute([$u['id']]);
    $u['perms'] = $stmt_p->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt_pastas = $pdo_intra->prepare("SELECT pasta_nome FROM permissoes_pastas WHERE user_id = ?");
    $stmt_pastas->execute([$u['id']]);
    $u['pastas_indiv'] = $stmt_pastas->fetchAll(PDO::FETCH_COLUMN);

    $stmt_vid_u = $pdo_intra->prepare("SELECT pasta_video FROM permissoes_videos WHERE user_id = ?");
    $stmt_vid_u->execute([$u['id']]);
    $u['videos_indiv'] = $stmt_vid_u->fetchAll(PDO::FETCH_COLUMN);

    $stmt_ug = $pdo_intra->prepare("SELECT grupo_id FROM usuarios_grupos WHERE usuario_id = ?");
    $stmt_ug->execute([$u['id']]);
    $u['meus_grupos'] = $stmt_ug->fetchAll(PDO::FETCH_COLUMN);

    $stmt_usys = $pdo_intra->prepare("SELECT sistema_id FROM permissoes_sistemas WHERE user_id = ?");
    $stmt_usys->execute([$u['id']]);
    $u['meus_sistemas'] = $stmt_usys->fetchAll(PDO::FETCH_COLUMN);

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
        <?php if(!empty($msg_erro)): ?>
            <div class="mb-6 bg-red-50 text-red-700 p-4 rounded-2xl font-bold border border-red-100 shadow-sm animate-pulse">
                <?php echo $msg_erro; ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h2 class="text-3xl font-black text-navy-900 tracking-tight uppercase italic">Centro de Controle RBAC</h2>
                <p class="text-slate-500 font-medium mt-1">Gerencie grupos, permissões e sistemas externos.</p>
            </div>

            <div class="flex p-1.5 bg-white border border-slate-200 rounded-2xl shadow-sm">
                <button onclick="switchTab('usuarios')" id="btn-usuarios" class="px-5 py-2.5 rounded-xl font-bold text-sm transition-all bg-navy-900 text-white shadow-md">👤 Usuários</button>
                <button onclick="switchTab('grupos')" id="btn-grupos" class="px-5 py-2.5 rounded-xl font-bold text-sm transition-all text-slate-500 hover:text-navy-900">🛡️ Grupos</button>
                <button onclick="switchTab('sistemas')" id="btn-sistemas" class="px-5 py-2.5 rounded-xl font-bold text-sm transition-all text-slate-500 hover:text-navy-900">🚀 Sistemas</button>
            </div>
        </div>

        <?php 
        $setores_usuarios = [];
        foreach ($usuarios as $u) {
            $setor_limpo = isset($u['setor']) ? trim($u['setor']) : '';
            if (!empty($setor_limpo)) {
                $setores_usuarios[mb_strtoupper($setor_limpo, 'UTF-8')] = $setor_limpo;
            }
        }
        ksort($setores_usuarios);
        ?>

        <div id="tab-usuarios" class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden transition-all duration-500">
            <div class="px-6 py-4 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center bg-slate-50/50 gap-4">
                <div>
                    <h3 class="font-bold text-navy-900 leading-tight">Colaboradores do GLPI</h3>
                    <span class="bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider mt-1 inline-block"><?php echo count($usuarios); ?> ativos</span>
                </div>
                
                <div class="w-full md:w-auto flex flex-col sm:flex-row gap-2 items-center flex-1 justify-end">
                    
                    <button type="button" id="btn-reset-massa" onclick="executarResetEmMassa()" class="hidden bg-rose-600 hover:bg-rose-500 text-white text-xs font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all shadow-md shadow-rose-900/20 animate-fade-in">
                        💥 Resetar Selecionados (<span id="contador-massa">0</span>)
                    </button>

                    <select id="filtro-setor" class="w-full sm:w-48 bg-white border border-slate-200 focus:border-corporate-blue focus:ring-1 focus:ring-corporate-blue text-xs rounded-xl px-3 py-2.5 outline-none font-bold text-slate-600 shadow-sm cursor-pointer appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23475569%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:10px_auto] bg-[right_12px_center] bg-no-repeat pr-8">
                        <option value="">🏢 TODOS OS SETORES</option>
                        <?php foreach($setores_usuarios as $key => $nome_setor): ?>
                            <option value="<?php echo strtolower($key); ?>"><?php echo mb_strtoupper($nome_setor, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="w-full sm:w-64 relative">
                        <input type="text" id="busca-colaborador" placeholder="🔍 Buscar por nome ou login..." 
                            class="w-full bg-white border border-slate-200 focus:border-corporate-blue focus:ring-1 focus:ring-corporate-blue text-xs rounded-xl pl-4 pr-10 py-2.5 outline-none font-medium text-slate-700 placeholder-slate-400 transition-all shadow-sm">
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="px-4 py-4 w-10 text-center">
                                <input type="checkbox" id="chk-selecionar-todos" onchange="toggleSelecionarTodos(this)" class="w-4 h-4 text-corporate-blue rounded border-slate-300 focus:ring-corporate-blue cursor-pointer">
                            </th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Colaborador</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Setor Raiz</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Grupos Atribuídos</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($usuarios as $u): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-4 py-4 text-center">
                                <input type="checkbox" value="<?php echo $u['id']; ?>" class="chk-usuario-massa w-4 h-4 text-corporate-blue rounded border-slate-300 focus:ring-corporate-blue cursor-pointer" onchange="atualizarInterfaceMassa()">
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-navy-900 text-sm"><?php echo $u['firstname'] . ' ' . $u['realname']; ?></div>
                                <div class="text-[10px] text-slate-400 font-medium">@<?php echo $u['login']; ?></div>
                            </td>
                            <td class="px-6 py-4"><span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-[10px] font-black uppercase"><?php echo $u['setor'] ?: 'SEM SETOR'; ?></span></td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <?php if(empty($u['meus_grupos'])) echo '<span class="text-[10px] text-slate-300 italic">Nenhum</span>'; ?>
                                    <?php foreach($u['meus_grupos'] as $gid): ?>
                                        <span class="px-2 py-1 bg-purple-50 text-purple-700 border border-purple-100 rounded text-[9px] font-black uppercase tracking-widest"><?php echo $grupos_map[$gid]['nome'] ?? 'Desconhecido'; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center flex items-center justify-center gap-1.5">
                                <button onclick="abrirModalUser(<?php echo $u['id']; ?>)" class="text-[10px] bg-white border border-slate-200 text-slate-600 hover:bg-corporate-blue hover:text-white hover:border-corporate-blue px-3 py-1.5 rounded-lg font-bold uppercase transition-all shadow-sm">Ajustar</button>
                                
                                <button type="button" onclick="resetarSenhaUsuario(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['firstname'] . ' ' . $u['realname']); ?>')" class="bg-amber-50 hover:bg-amber-500 text-amber-600 hover:text-white border border-amber-200 hover:border-amber-500 text-[11px] w-8 h-8 rounded-lg flex items-center justify-center transition-all shadow-sm" title="Resetar Senha para Souza@123">
                                    🔄
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
                <h3 class="font-bold text-navy-900">Grupos de Acesso</h3>
                <button onclick="abrirModalGrupo(0)" class="bg-emerald-500 text-white px-4 py-2 rounded-xl text-xs font-black uppercase shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 transition-all">+ Criar Grupo</button>
            </div>
            
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($grupos as $g): ?>
                <div class="bg-white border border-slate-200 p-5 rounded-3xl shadow-sm hover:shadow-lg transition-all relative group flex flex-col">
                    <h4 class="text-lg font-black text-navy-900 uppercase italic mb-3 pr-8"><?php echo $g['nome']; ?></h4>
                    <div class="flex gap-2 mt-auto pt-4 border-t border-slate-50">
                        <button onclick="abrirModalGrupo(<?php echo $g['id']; ?>)" class="flex-1 bg-slate-50 hover:bg-slate-900 hover:text-white text-slate-600 py-2 rounded-xl text-[10px] font-black uppercase transition-all">Editar</button>
                        <form method="POST" class="inline" onsubmit="return confirm('Excluir?');">
                            <input type="hidden" name="acao" value="excluir_grupo">
                            <input type="hidden" name="grupo_id" value="<?php echo $g['id']; ?>">
                            <button type="submit" class="bg-red-50 hover:bg-red-500 hover:text-white text-red-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all">X</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="tab-sistemas" class="hidden bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden transition-all duration-500">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-navy-900">Inventário de Sistemas (Launchpad)</h3>
                <button onclick="abrirModalSistema(0)" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-black uppercase shadow-lg shadow-blue-600/20 hover:bg-blue-700 transition-all">+ Criar Bolinha</button>
            </div>
            
            <div class="p-6 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
                <?php foreach ($sistemas_db as $sys): ?>
                <div class="bg-white border border-slate-200 p-4 rounded-3xl shadow-sm flex flex-col items-center group relative text-center">
                    <form method="POST" class="absolute top-2 right-2 hidden group-hover:block" onsubmit="return confirm('Apagar este sistema e revogar acesso de todos?');">
                        <input type="hidden" name="acao" value="excluir_sistema">
                        <input type="hidden" name="sistema_id" value="<?php echo $sys['id']; ?>">
                        <button type="submit" class="bg-red-100 text-red-600 w-6 h-6 rounded-full text-xs font-bold hover:bg-red-600 hover:text-white">X</button>
                    </form>

                    <div class="w-16 h-16 rounded-full <?php echo $sys['cor']; ?> flex items-center justify-center text-3xl shadow-md mb-3 text-white">
                        <?php echo $sys['icone']; ?>
                    </div>
                    <span class="text-[10px] font-black text-navy-900 uppercase tracking-widest leading-tight mb-3"><?php echo $sys['nome']; ?></span>
                    <button onclick="abrirModalSistema(<?php echo htmlspecialchars(json_encode($sys)); ?>)" class="mt-auto bg-slate-100 text-slate-500 hover:bg-navy-900 hover:text-white px-3 py-1 rounded-lg text-[9px] font-bold uppercase transition-all w-full">Editar</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</main>

<div id="modalSistema" class="fixed inset-0 bg-navy-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden flex flex-col animate-in zoom-in-95 duration-300">
        <form method="POST" class="flex flex-col">
            <input type="hidden" name="acao" value="salvar_sistema">
            <input type="hidden" name="sistema_id" id="msys_id">
            
            <div class="px-8 py-6 bg-blue-600 text-white flex justify-between items-center">
                <h3 class="text-xl font-black italic uppercase" id="msys_titulo">Novo Sistema</h3>
                <button type="button" onclick="fecharModais()" class="text-white/50 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            
            <div class="p-8 space-y-4 max-h-[65vh] overflow-y-auto custom-scrollbar-compact relative">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3">Nome do Sistema / Grupo</label>
                    <input type="text" name="sys_nome" id="msys_nome" required placeholder="Ex: T.I ou POWER BI" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm font-bold uppercase focus:ring-2 focus:ring-blue-500/20 outline-none">
                </div>
                
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3">Link/URL do Sistema</label>
                    <input type="text" name="sys_url" id="msys_url" placeholder="Ex: http://192.168.0.50/sistema" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 outline-none">
                    <p class="text-[10px] text-amber-600 font-bold mt-1.5 ml-2 bg-amber-50 border border-amber-200/60 px-3 py-1.5 rounded-lg flex items-center gap-1.5 animate-pulse">
                        💡 <span><b>DICA:</b> Digite apenas <b>#</b> neste campo para que este item vire um <b>Grupo/Pasta</b></span>
                    </p>
                </div>

                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3">Vincular ao Módulo (Se for um subitem)</label>
                    <select name="sys_pai_id" id="msys_pai_id" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm font-bold text-slate-600 focus:ring-2 focus:ring-blue-500/20 outline-none appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23475569%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:10px_auto] bg-[right_14px_center] bg-no-repeat pr-8">
                        <option value="">📁 NENHUM </option>
                        <?php 
                        foreach ($sistemas_db as $possivel_pai) {
                            if ($possivel_pai['url'] === '#') {
                                echo "<option value='{$possivel_pai['id']}'>📦 GRUPO: " . mb_strtoupper($possivel_pai['nome'], 'UTF-8') . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 items-end relative">
                    <div class="relative">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3 block mb-1">Emoji / Ícone</label>
                        <button type="button" onclick="document.getElementById('picker-emoji-modal-sys').classList.toggle('hidden')" id="btn-gatilho-emoji-sys" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-xl text-center hover:bg-slate-100 transition-colors shadow-sm flex items-center justify-center gap-2">
                            💻 <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">(Mudar)</span>
                        </button>
                        <input type="hidden" name="sys_icone" id="msys_icone" value="💻">
                        
                        <div id="picker-emoji-modal-sys" class="hidden absolute left-0 bottom-14 bg-white border border-slate-200 shadow-2xl rounded-2xl p-3 grid grid-cols-5 gap-1.5 z-[200] w-56 animate-in fade-in duration-150">
                            <?php 
                            $icones_corporativos = ['💻','🛠️','📦','📊','💼','📢','🎨','📞','🎓','📂','🚀','🎯','📝','📅','👍','🔥','🚨','🌐','💰','🛒'];
                            foreach($icones_corporativos as $emo): 
                            ?>
                                <button type="button" onclick="definirEmojiFormLaunchpad('<?= $emo ?>')" class="hover:bg-slate-100 p-1.5 rounded-xl text-lg transition-colors flex items-center justify-center"><?= $emo ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3">Cor de Borda</label>
                        <select name="sys_cor" id="msys_cor" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-sm font-bold text-slate-600 appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23475569%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:10px_auto] bg-[right_14px_center] bg-no-repeat pr-8">
                            <option value="bg-blue-600">Azul</option>
                            <option value="bg-emerald-600">Verde</option>
                            <option value="bg-amber-500">Amarelo</option>
                            <option value="bg-red-600">Vermelho</option>
                            <option value="bg-purple-600">Roxo</option>
                            <option value="bg-slate-800">Escuro</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="p-6 bg-slate-50 border-t border-slate-200 flex gap-4">
                <button type="submit" class="w-full bg-blue-600 text-white py-3.5 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-blue-700 shadow-md transition-all active:scale-95">Salvar Configuração</button>
            </div>
        </form>
    </div>
</div>

<div id="modalUser" class="fixed inset-0 bg-navy-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh] animate-in zoom-in-95 duration-300">
        <form method="POST" class="flex flex-col h-full overflow-hidden min-h-0">
            <input type="hidden" name="acao" value="salvar_usuario">
            <input type="hidden" name="user_id" id="mu_id">
            
            <div class="px-8 py-6 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <h3 class="text-xl font-black italic uppercase" id="mu_nome">Nome</h3>
                <button type="button" onclick="fecharModais()" class="text-white/50 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            
            <div class="p-8 overflow-y-auto custom-scrollbar space-y-8 flex-1">
                
                <div>
                    <h4 class="text-xs font-black text-red-500 uppercase tracking-widest mb-3 flex items-center gap-2">⚠️ Permissões Individuais Extras</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer border border-slate-100 shadow-sm">
                            <input type="checkbox" name="p_admin" id="mu_p_admin" class="w-4 h-4 text-corporate-blue rounded">
                            <span class="text-[9px] font-bold text-navy-900 uppercase">Admin Total</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer border border-slate-100 shadow-sm">
                            <input type="checkbox" name="p_feed" id="mu_p_feed" class="w-4 h-4 text-amber-500 rounded">
                            <span class="text-[9px] font-bold text-navy-900 uppercase">Postar Feed</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer border border-slate-100 shadow-sm">
                            <input type="checkbox" name="p_docs" id="mu_p_docs" class="w-4 h-4 text-blue-500 rounded">
                            <span class="text-[9px] font-bold text-navy-900 uppercase">Gerenciar Docs</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer border border-slate-100 shadow-sm">
                            <input type="checkbox" name="p_acessos" id="mu_p_acessos" class="w-4 h-4 text-emerald-500 rounded">
                            <span class="text-[9px] font-bold text-navy-900 uppercase">Acessos</span>
                        </label>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black text-amber-500 uppercase tracking-widest mb-3 flex items-center gap-2">📁 Pastas Extras (Docs)</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <?php foreach($pastas_fisicas as $pasta): ?>
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer border border-slate-100 shadow-sm">
                            <input type="checkbox" name="pastas[]" value="<?php echo $pasta; ?>" class="chk-mu-pasta w-3.5 h-3.5 text-amber-500 rounded">
                            <span class="text-[9px] font-bold text-navy-900 truncate uppercase"><?php echo $pasta; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black text-rose-500 uppercase tracking-widest mb-3 flex items-center gap-2">🎬 Vídeos Extras (Individuais)</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <?php foreach($pastas_videos as $video_folder): ?>
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer border border-slate-100 shadow-sm">
                            <input type="checkbox" name="videos[]" value="<?php echo $video_folder; ?>" class="chk-mu-video w-3.5 h-3.5 text-rose-500 rounded">
                            <span class="text-[9px] font-bold text-navy-900 truncate uppercase"><?php echo str_replace('ROTINAS ', '', $video_folder); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">🛡️ Grupos</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach($grupos as $g): ?>
                        <label class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:border-corporate-blue transition-all">
                            <input type="checkbox" name="grupos[]" value="<?php echo $g['id']; ?>" class="chk-mu-grupo w-4 h-4 text-corporate-blue rounded">
                            <span class="text-[10px] font-bold text-slate-700 uppercase"><?php echo $g['nome']; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black text-blue-500 uppercase tracking-widest mb-3 flex items-center gap-2">🚀 Sistemas do Launchpad</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <?php foreach($sistemas_db as $sys): ?>
                        <label class="flex items-center gap-2 p-2 rounded hover:bg-slate-50 cursor-pointer border border-slate-100 shadow-sm">
                            <input type="checkbox" name="sistemas[]" value="<?php echo $sys['id']; ?>" class="chk-mu-sistema w-3.5 h-3.5 text-blue-600 rounded">
                            <span class="text-[9px] font-bold text-navy-900 truncate uppercase"><?php echo $sys['icone'] . ' ' . $sys['nome']; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="p-6 bg-slate-50 border-t border-slate-200 flex gap-4 shrink-0">
                <button type="submit" class="flex-[2] bg-corporate-blue text-white rounded-xl text-xs font-black uppercase tracking-widest py-3 hover:scale-[1.02] transition-all">Salvar Armadura</button>
            </div>
        </form>
    </div>
</div>

<div id="modalGroup" class="fixed inset-0 bg-navy-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_grupo">
            <input type="hidden" name="grupo_id" id="mg_id">
            
            <div class="px-8 py-6 bg-corporate-blue text-white flex justify-between items-center">
                <h3 class="text-xl font-black italic uppercase tracking-tighter" id="mg_titulo">Gerenciar Grupo</h3>
                <button type="button" onclick="fecharModais()" class="text-white/50 hover:text-white text-3xl">&times;</button>
            </div>
            
            <div class="p-8 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <div class="mb-8">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3">Nome do Grupo</label>
                    <input type="text" name="nome_grupo" id="mg_nome" required class="w-full bg-slate-50 border border-slate-200 p-4 rounded-2xl text-sm font-bold uppercase focus:ring-2 ring-corporate-blue outline-none transition-all">
                </div>

                <div class="mb-8">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 ml-3">Acessos Administrativos</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-transparent hover:border-slate-200 cursor-pointer transition-all">
                            <input type="checkbox" name="g_admin" id="mg_g_admin" class="w-5 h-5 rounded-lg border-slate-300 text-corporate-blue focus:ring-corporate-blue">
                            <div>
                                <p class="text-xs font-black text-navy-900 uppercase">Administrador Total</p>
                                <p class="text-[9px] text-slate-500 font-medium">Acesso a todas as configurações</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-transparent hover:border-slate-200 cursor-pointer transition-all">
                            <input type="checkbox" name="g_feed" id="mg_g_feed" class="w-5 h-5 rounded-lg border-slate-300 text-amber-500 focus:ring-amber-500">
                            <div>
                                <p class="text-xs font-black text-navy-900 uppercase">Gestão de Feed</p>
                                <p class="text-[9px] text-slate-500 font-medium">Postar e excluir comunicados</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-transparent hover:border-slate-200 cursor-pointer transition-all">
                            <input type="checkbox" name="g_docs" id="mg_g_docs" class="w-5 h-5 rounded-lg border-slate-300 text-blue-500 focus:ring-blue-500">
                            <div>
                                <p class="text-xs font-black text-navy-900 uppercase">Gestão de Docs</p>
                                <p class="text-[9px] text-slate-500 font-medium">Upload e remoção de arquivos</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-transparent hover:border-slate-200 cursor-pointer transition-all">
                            <input type="checkbox" name="g_acessos" id="mg_g_acessos" class="w-5 h-5 rounded-lg border-slate-300 text-emerald-500 focus:ring-emerald-500">
                            <div>
                                <p class="text-xs font-black text-navy-900 uppercase">Gestão de Acessos</p>
                                <p class="text-[9px] text-slate-500 font-medium">Editar usuários e grupos</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mb-8">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 ml-3">📁 Pastas de Documentos</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach ($pastas_fisicas as $pasta): ?>
                        <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-transparent hover:border-slate-200 cursor-pointer transition-all">
                            <input type="checkbox" name="pastas_grupo[]" value="<?php echo $pasta; ?>" class="chk-mg-pasta w-5 h-5 rounded-lg border-slate-300 text-corporate-blue focus:ring-corporate-blue">
                            <span class="text-xs font-bold text-navy-900 uppercase truncate"><?php echo $pasta; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-8">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 ml-3">🎬 Playlists da Academia (Vídeos)</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach ($pastas_videos as $video_folder): ?>
                        <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-transparent hover:border-slate-200 cursor-pointer transition-all">
                            <input type="checkbox" name="videos_grupo[]" value="<?php echo $video_folder; ?>" class="chk-mg-video w-5 h-5 rounded-lg border-slate-300 text-rose-500 focus:ring-rose-500">
                            <span class="text-[10px] font-bold text-navy-900 uppercase truncate"><?php echo str_replace('ROTINAS ', '', $video_folder); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 ml-3">Sistemas Visíveis (Launchpad)</p>
                    <div class="grid grid-cols-2 gap-3">
                        <?php foreach ($sistemas_db as $s): ?>
                        <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-transparent hover:border-slate-200 cursor-pointer transition-all">
                            <input type="checkbox" name="sistemas_grupo[]" value="<?php echo $s['id']; ?>" class="chk-mg-sistema w-5 h-5 rounded-lg border-slate-300 text-corporate-blue focus:ring-corporate-blue">
                            <div class="flex items-center gap-2">
                                <span class="text-lg"><?php echo $s['icone']; ?></span>
                                <span class="text-xs font-bold text-navy-900 uppercase"><?php echo $s['nome']; ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="p-6 bg-slate-50 border-t border-slate-200 flex gap-3">
                <button type="button" onclick="fecharModais()" class="flex-1 bg-white border border-slate-200 py-4 rounded-2xl text-xs font-black uppercase text-slate-500 hover:bg-slate-100 transition-all">Cancelar</button>
                <button type="submit" class="flex-1 bg-corporate-blue text-white py-4 rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-blue-900/20 hover:scale-[1.02] transition-all">Salvar Grupo</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.getElementById('tab-usuarios').classList.add('hidden');
        document.getElementById('tab-grupos').classList.add('hidden');
        document.getElementById('tab-sistemas').classList.add('hidden');
        
        document.getElementById('btn-usuarios').className = "px-5 py-2.5 rounded-xl font-bold text-sm transition-all text-slate-500 hover:text-navy-900";
        document.getElementById('btn-grupos').className = "px-5 py-2.5 rounded-xl font-bold text-sm transition-all text-slate-500 hover:text-navy-900";
        document.getElementById('btn-sistemas').className = "px-5 py-2.5 rounded-xl font-bold text-sm transition-all text-slate-500 hover:text-navy-900";

        document.getElementById('tab-' + tab).classList.remove('hidden');
        document.getElementById('btn-' + tab).className = "px-5 py-2.5 rounded-xl font-bold text-sm transition-all bg-navy-900 text-white shadow-md";
    }

    const dadosUsuarios = <?php echo json_encode($usuarios_json); ?>;
    const dadosGrupos = <?php echo json_encode($grupos_map); ?>;

    function fecharModais() {
        document.getElementById('modalUser').classList.replace('flex', 'hidden');
        document.getElementById('modalGroup').classList.replace('flex', 'hidden');
        document.getElementById('modalSistema').classList.replace('flex', 'hidden');
    }

    function abrirModalUser(id) {
        const u = dadosUsuarios[id];
        document.getElementById('mu_id').value = id;
        document.getElementById('mu_nome').innerText = u.firstname + ' ' + u.realname;
        
        // Limpa tudo
        document.querySelectorAll('.chk-mu-grupo').forEach(c => c.checked = false);
        document.querySelectorAll('.chk-mu-sistema').forEach(c => c.checked = false);
        document.querySelectorAll('.chk-mu-pasta').forEach(c => c.checked = false);
        document.querySelectorAll('.chk-mu-video').forEach(c => c.checked = false); // LIMPA VÍDEOS
        document.getElementById('mu_p_admin').checked = false;
        document.getElementById('mu_p_feed').checked = false;
        document.getElementById('mu_p_docs').checked = false;
        document.getElementById('mu_p_acessos').checked = false;

        // Seta permissões individuais
        if(u.perms) {
            document.getElementById('mu_p_admin').checked = (u.perms.is_admin == 1);
            document.getElementById('mu_p_feed').checked = (u.perms.pode_postar_feed == 1);
            document.getElementById('mu_p_docs').checked = (u.perms.pode_gerenciar_docs == 1);
            document.getElementById('mu_p_acessos').checked = (u.perms.pode_gerenciar_acessos == 1);
        }
        
        // Seta as Pastas
        if(u.pastas_indiv) {
            u.pastas_indiv.forEach(p => {
                const cb = document.querySelector(`.chk-mu-pasta[value="${p}"]`);
                if(cb) cb.checked = true;
            });
        }

        // Seta VÍDEOS
        if(u.videos_indiv) {
            u.videos_indiv.forEach(vid => {
                const cb = document.querySelector(`.chk-mu-video[value="${vid}"]`);
                if(cb) cb.checked = true;
            });
        }

        // Seta Grupos
        u.meus_grupos.forEach(gid => {
            const cb = document.querySelector(`.chk-mu-grupo[value="${gid}"]`);
            if(cb) cb.checked = true;
        });
        
        // Seta Sistemas
        if(u.meus_sistemas) {
            u.meus_sistemas.forEach(sid => {
                const cb = document.querySelector(`.chk-mu-sistema[value="${sid}"]`);
                if(cb) cb.checked = true;
            });
        }

        document.getElementById('modalUser').classList.replace('hidden', 'flex');
    }

    function abrirModalGrupo(id) {
        document.getElementById('mg_id').value = '';
        document.getElementById('mg_nome').value = '';
        document.getElementById('mg_titulo').innerText = 'Novo Grupo';
        
        document.getElementById('mg_g_admin').checked = false;
        document.getElementById('mg_g_feed').checked = false;
        document.getElementById('mg_g_docs').checked = false;
        document.getElementById('mg_g_acessos').checked = false;
        
        document.querySelectorAll('.chk-mg-sistema').forEach(cb => cb.checked = false);
        document.querySelectorAll('.chk-mg-pasta').forEach(cb => cb.checked = false);
        document.querySelectorAll('.chk-mg-video').forEach(cb => cb.checked = false); // LIMPA VÍDEOS

        if (id !== 0) {
            const g = dadosGrupos[id];
            if (g) {
                document.getElementById('mg_id').value = id;
                document.getElementById('mg_titulo').innerText = 'EDITAR: ' + g.nome;
                document.getElementById('mg_nome').value = g.nome;

                document.getElementById('mg_g_admin').checked = (g.is_admin == 1);
                document.getElementById('mg_g_feed').checked = (g.pode_postar_feed == 1);
                document.getElementById('mg_g_docs').checked = (g.pode_gerenciar_docs == 1);
                document.getElementById('mg_g_acessos').checked = (g.pode_gerenciar_acessos == 1);

                if(g.sistemas) {
                    g.sistemas.forEach(sid => {
                        const cb = document.querySelector(`.chk-mg-sistema[value="${sid}"]`);
                        if(cb) cb.checked = true;
                    });
                }
                
                // Pinta as Pastas de Documentos
                if(g.pastas) {
                    g.pastas.forEach(p => {
                        const cb = document.querySelector(`.chk-mg-pasta[value="${p}"]`);
                        if(cb) cb.checked = true;
                    });
                }

                // Pinta VÍDEOS
                if(g.videos) {
                    g.videos.forEach(vid => {
                        const cb = document.querySelector(`.chk-mg-video[value="${vid}"]`);
                        if(cb) cb.checked = true;
                    });
                }
            }
        }
        document.getElementById('modalGroup').classList.replace('hidden', 'flex');
    }

    function abrirModalSistema(dados) {
        document.getElementById('msys_id').value = '';
        document.getElementById('msys_nome').value = '';
        document.getElementById('msys_url').value = '';
        document.getElementById('msys_pai_id').value = ''; // Reseta o seletor de pai
        
        if(dados !== 0) {
            document.getElementById('msys_id').value = dados.id;
            document.getElementById('msys_nome').value = dados.nome;
            document.getElementById('msys_url').value = dados.url;
            document.getElementById('msys_cor').value = dados.cor;
            document.getElementById('msys_icone').value = dados.icone;
            document.getElementById('btn-gatilho-emoji-sys').innerHTML = `${dados.icone} <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">(Mudar)</span>`;
            
            // Seta dinamicamente o pai caso a linha possua pai_id no banco
            document.getElementById('msys_pai_id').value = dados.pai_id ? dados.pai_id : '';
            
            document.getElementById('msys_titulo').innerText = "Editar Sistema";
        } else {
            document.getElementById('msys_titulo').innerText = "Novo Sistema";
        }

        document.getElementById('modalSistema').classList.replace('hidden', 'flex');
    }

    function definirEmojiFormLaunchpad(emoji) {
        // Atualiza o valor oculto que vai para o banco
        document.getElementById('msys_icone').value = emoji;
        // Atualiza a pré-visualização do botão com o texto auxiliar de mudar
        document.getElementById('btn-gatilho-emoji-sys').innerHTML = `${emoji} <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">(Mudar)</span>`;
        // Esconde o picker
        document.getElementById('picker-emoji-modal-sys').classList.add('hidden');
    }

    // =====================================================================
    // 🔍 MOTOR DE FILTRAGEM CRUZADA: BUSCA POR NOME + SETOR (Sem Reload)
    // =====================================================================
    const inputBuscaNome = document.getElementById('busca-colaborador');
    const selectFiltroSetor = document.getElementById('filtro-setor');
    const linhasUsuarios = document.querySelectorAll('#tab-usuarios tbody tr');

    function aplicarFiltrosCombinados() {
        const termoNome = inputBuscaNome ? inputBuscaNome.value.toLowerCase().trim() : '';
        const termoSetor = selectFiltroSetor ? selectFiltroSetor.value.toLowerCase().trim() : '';

        linhasUsuarios.forEach(linha => {
            // 🔥 CORREÇÃO DE ÍNDICES: Avançamos 1 casa por causa da nova coluna do Checkbox
            const tdNome = linha.querySelector('td:nth-child(2)');  // Segunda coluna (Nome/Login)
            const tdSetor = linha.querySelector('td:nth-child(3)'); // Terceira coluna (Setor Raiz)

            if (tdNome && tdSetor) {
                const textoNomeCompleto = tdNome.textContent.toLowerCase();
                const textoSetorCompleto = tdSetor.textContent.toLowerCase().trim();

                const bateuNome = termoNome === '' || textoNomeCompleto.includes(termoNome);
                // Valida o setor buscando pelo texto original ou se o campo possui o valor selecionado
                const bateuSetor = termoSetor === '' || textoSetorCompleto === termoSetor || textoSetorCompleto.includes(termoSetor);

                if (bateuNome && bateuSetor) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            }
        });
    }

    if (inputBuscaNome) inputBuscaNome.addEventListener('input', aplicarFiltrosCombinados);
    if (selectFiltroSetor) selectFiltroSetor.addEventListener('change', aplicarFiltrosCombinados);

    // =====================================================================
    // 🔑 REQUISIÇÃO AJAX PARA RESET DE SENHA (Sem recarregar a tela)
    // =====================================================================
    function resetarSenhaUsuario(userId, nomeCompleto) {
        if (confirm(`Atenção: Deseja realmente redefinir a senha de "${nomeCompleto}" para o padrão corporativo (Souza@123)?\n\nO colaborador será bloqueado e obrigado a criar uma nova senha pessoal no próximo login.`)) {
            
            const formData = new FormData();
            formData.append('acao', 'resetar_senha_usuario');
            formData.append('user_id', userId);

            fetch('admin_gestao.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(resposta => {
                if (resposta.trim() === 'sucesso') {
                    alert('Senha redefinida com sucesso!\nO usuário foi configurado para o estado de Primeiro Login obrigatório.');
                    window.location.reload();
                } else {
                    alert('Erro retornado pelo servidor: ' + resposta);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de conexão ao tentar redefinir a senha.');
            });
        }
    }

    // =====================================================================
    // 💥 MOTOR DE SELEÇÃO E RESET DE SENHAS EM MASSA (LOTE)
    // =====================================================================
    
    // 1. Marca ou desmarca todos os checkboxes baseado no cabeçalho master
    function toggleSelecionarTodos(masterCheckbox) {
        // Seleciona apenas os checkboxes individuais que estão visíveis na tela atualmente
        const checkboxes = document.querySelectorAll('.chk-usuario-massa');
        
        checkboxes.forEach(cb => {
            // Só altera se a linha não estiver escondida por algum filtro de setor ou busca
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = masterCheckbox.checked;
            }
        });
        atualizarInterfaceMassa();
    }

    // 2. Controla o estado do botão vermelho baseado em quantas caixas estão marcadas
    function atualizarInterfaceMassa() {
        const marcados = document.querySelectorAll('.chk-usuario-massa:checked');
        const btnMassa = document.getElementById('btn-reset-massa');
        const contador = document.getElementById('contador-massa');

        if (marcados.length > 0) {
            contador.innerText = marcados.length;
            btnMassa.classList.remove('hidden');
            btnMassa.classList.add('inline-block');
        } else {
            btnMassa.classList.add('hidden');
            btnMassa.classList.remove('inline-block');
            
            // Desmarca o master caso o usuário limpe manualmente os itens
            const chkMaster = document.getElementById('chk-selecionar-todos');
            if (chkMaster) chkMaster.checked = false;
        }
    }

    // 3. Dispara o AJAX enviando o array com todos os IDs selecionados
    function executarResetEmMassa() {
        const marcados = document.querySelectorAll('.chk-usuario-massa:checked');
        if (marcados.length === 0) return;

        if (confirm(`⚠️ ALERTA DE SEGURANÇA:\nDeseja realmente resetar a senha de ${marcados.length} colaboradores selecionados de uma única vez para o padrão (Souza@123)?\n\nTodos eles serão configurados para a redefinição de primeiro login obrigatório.`)) {
            
            const formData = new FormData();
            formData.append('acao', 'resetar_senha_massa');
            
            // Injeta cada ID marcado dentro da estrutura de array do FormData
            marcados.forEach(cb => {
                formData.append('usuarios_ids[]', cb.value);
            });

            // Envia para o processamento em lote
            fetch('admin_gestao.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(resposta => {
                if (resposta.trim() === 'sucesso') {
                    alert(`Sucesso! As senhas de ${marcados.length} usuários foram redefinidas em lote.`);
                    window.location.reload();
                } else {
                    alert('Erro retornado pelo servidor: ' + resposta);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro na rede ao tentar processar o reset em lote.');
            });
        }
    }

</script>

<?php include 'includes/footer.php'; ?>