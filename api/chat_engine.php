<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once '../config.php';
session_start();

$usuario_logado = $_SESSION['user_id'] ?? 0;
$acao = $_GET['acao'] ?? '';

ob_clean();
header('Content-Type: application/json');

if (!$usuario_logado) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    switch ($acao) {
        case 'listar_contatos':
            $stmt = $pdo_intra->prepare("SELECT usuario_id, nome_usuario FROM controle_presenca WHERE usuario_id != ? AND ultima_atividade > DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY nome_usuario ASC");
            $stmt->execute([$usuario_logado]);
            $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // INJEÇÃO DO ROBÔ: Mantido conforme sua versão
            array_unshift($contatos, [
                'usuario_id' => 999, 
                'nome_usuario' => '🤖 NAVI BOT (IA)'
            ]);

            $stmtGrupos = $pdo_intra->prepare("SELECT g.id, g.nome_grupo FROM chat_grupos g INNER JOIN chat_grupos_membros gm ON g.id = gm.id_grupo WHERE gm.id_usuario = ?");
            $stmtGrupos->execute([$usuario_logado]);
            $grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['usuarios' => $contatos, 'grupos' => $grupos]);
            break;

        case 'listar_todos_usuarios':
            $stmt = $pdo_intra->prepare("SELECT id as usuario_id, firstname as nome_usuario FROM glpidb_att.glpi_users WHERE id != ? ORDER BY firstname ASC");
            $stmt->execute([$usuario_logado]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'verificar_notificacoes':
            // Esta query conta mensagens onde VOCÊ é o destinatário e elas estão com lida = 0
            $stmt = $pdo_intra->prepare("
                SELECT 
                    m.id_remetente, 
                    m.id_grupo,
                    IF(m.id_remetente = 999, '🤖 NAVI BOT (IA)', u.firstname) as nome_usuario,
                    COUNT(*) as total,
                    MAX(m.mensagem) as ultima_msg
                FROM chat_mensagens m
                LEFT JOIN glpidb_att.glpi_users u ON m.id_remetente = u.id
                WHERE (
                    (m.id_destinatario = ? AND m.id_grupo IS NULL) -- Mensagens privadas para mim
                    OR 
                    (m.id_grupo IN (SELECT id_grupo FROM chat_grupos_membros WHERE id_usuario = ?) AND m.id_remetente != ?) -- Mensagens de grupos que participo
                )
                AND m.lida = 0 
                AND m.excluida_pelo_usuario = 0
                GROUP BY m.id_remetente, m.id_grupo
            ");
            $stmt->execute([$usuario_logado, $usuario_logado, $usuario_logado]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'marcar_como_lida':
            $destino = $_GET['destino'] ?? '';
            if (strpos($destino, 'group_') === 0) {
                $id_group = str_replace('group_', '', $destino);
                $stmt = $pdo_intra->prepare("UPDATE chat_mensagens SET lida = 1 WHERE id_grupo = ? AND id_remetente != ?");
                $stmt->execute([$id_group, $usuario_logado]);
            } else {
                $stmt = $pdo_intra->prepare("UPDATE chat_mensagens SET lida = 1 WHERE id_remetente = ? AND id_destinatario = ?");
                $stmt->execute([$destino, $usuario_logado]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'listar_mensagens':
            $destino = $_GET['destino'] ?? 'global';
            // AJUSTE: Join com a tabela de usuários do GLPI para garantir que os nomes apareçam corretamente
            $base_sql = "SELECT m.*, IF(m.id_remetente = 999, '🤖 NAVI BOT (IA)', IFNULL(u.firstname, 'Colaborador')) as nome_usuario 
                         FROM chat_mensagens m 
                         LEFT JOIN glpidb_att.glpi_users u ON m.id_remetente = u.id 
                         WHERE m.excluida_pelo_usuario = 0 ";

            if ($destino === 'global' || $destino === 'null') {
                $sql = $base_sql . " AND m.id_destinatario IS NULL AND m.id_grupo IS NULL ORDER BY m.data_envio ASC LIMIT 100";
                $stmt = $pdo_intra->prepare($sql); $stmt->execute();
            } elseif (strpos($destino, 'group_') === 0) {
                $id_group = str_replace('group_', '', $destino);
                $sql = $base_sql . " AND m.id_grupo = ? ORDER BY m.data_envio ASC";
                $stmt = $pdo_intra->prepare($sql); $stmt->execute([$id_group]);
            } else {
                $sql = $base_sql . " AND ((m.id_remetente = ? AND m.id_destinatario = ?) OR (m.id_remetente = ? AND m.id_destinatario = ?)) ORDER BY m.data_envio ASC";
                $stmt = $pdo_intra->prepare($sql); $stmt->execute([$usuario_logado, $destino, $destino, $usuario_logado]);
            }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'enviar':
            $msg = trim($_POST['mensagem'] ?? '');
            $destino = $_POST['id_destinatario'] ?? null;
            if (!empty($msg)) {
                $id_dest = null; $id_grupo = null;
                if ($destino && $destino !== 'global' && $destino !== 'null') {
                    if (strpos($destino, 'group_') === 0) { $id_grupo = str_replace('group_', '', $destino); }
                    else { $id_dest = $destino; }
                }
                $stmt = $pdo_intra->prepare("INSERT INTO chat_mensagens (id_remetente, id_destinatario, id_grupo, mensagem) VALUES (?, ?, ?, ?)");
                $success = $stmt->execute([$usuario_logado, $id_dest, $id_grupo, $msg]);
                echo json_encode(['success' => $success]);
            }
            break;

        case 'criar_grupo':
            $nome_grupo = $_POST['nome_grupo'] ?? '';
            $membros = json_decode($_POST['membros'] ?? '[]');
            if (!empty($nome_grupo) && !empty($membros)) {
                $pdo_intra->beginTransaction();
                $stmt = $pdo_intra->prepare("INSERT INTO chat_grupos (nome_grupo, criado_por) VALUES (?, ?)");
                $stmt->execute([$nome_grupo, $usuario_logado]);
                $id_new_group = $pdo_intra->lastInsertId();
                $membros[] = $usuario_logado;
                $stmtMembro = $pdo_intra->prepare("INSERT INTO chat_grupos_membros (id_grupo, id_usuario) VALUES (?, ?)");
                foreach (array_unique($membros) as $id_m) { $stmtMembro->execute([$id_new_group, $id_m]); }
                $pdo_intra->commit();
                echo json_encode(['success' => true]);
            }
            break;

        case 'limpar_chat':
            $destino = $_GET['destino'] ?? '';
            if ($destino === 'global') {
                $stmt = $pdo_intra->prepare("UPDATE chat_mensagens SET excluida_pelo_usuario = 1 WHERE id_destinatario IS NULL AND id_grupo IS NULL");
                $stmt->execute();
            } elseif (strpos($destino, 'group_') === 0) {
                $id_grupo = str_replace('group_', '', $destino);
                $stmt = $pdo_intra->prepare("UPDATE chat_mensagens SET excluida_pelo_usuario = 1 WHERE id_grupo = ?");
                $stmt->execute([$id_grupo]);
            } else {
                $stmt = $pdo_intra->prepare("UPDATE chat_mensagens SET excluida_pelo_usuario = 1 WHERE (id_remetente = ? AND id_destinatario = ?) OR (id_remetente = ? AND id_destinatario = ?)");
                $stmt->execute([$usuario_logado, $destino, $destino, $usuario_logado]);
            }
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
exit;