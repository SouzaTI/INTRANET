<?php
ob_start();

require_once '../config.php';

error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$acao = $_POST['acao'] ?? '';
$user_id = $_SESSION['user_id'];
$comunicado_id = (int)($_POST['comunicado_id'] ?? 0);
$ip_acesso = $_SERVER['REMOTE_ADDR']; // Pega o IP de quem interagiu

try {
    // 1. LÓGICA DE CURTIR
    if ($acao === 'curtir' && $comunicado_id > 0) {
        $stmtCheck = $pdo_intra->prepare("SELECT id FROM feed_curtidas WHERE comunicado_id = ? AND user_id = ?");
        $stmtCheck->execute([$comunicado_id, $user_id]);
        
        if ($stmtCheck->fetch()) {
            $stmtDel = $pdo_intra->prepare("DELETE FROM feed_curtidas WHERE comunicado_id = ? AND user_id = ?");
            $stmtDel->execute([$comunicado_id, $user_id]);
            $status_acao = 'descurtiu';
            
            // 🚀 LOG DE DESCURTIR
            registrarLog($pdo_intra, 'INTERAÇÃO FEED', "Removeu a curtida do comunicado ID: $comunicado_id", $user_id, $ip_acesso);
        } else {
            $stmtIns = $pdo_intra->prepare("INSERT INTO feed_curtidas (comunicado_id, user_id) VALUES (?, ?)");
            $stmtIns->execute([$comunicado_id, $user_id]);
            $status_acao = 'curtiu';
            
            // 🚀 LOG DE CURTIR
            registrarLog($pdo_intra, 'INTERAÇÃO FEED', "Curtiu o comunicado ID: $comunicado_id", $user_id, $ip_acesso);
        }

        $stmtCount = $pdo_intra->prepare("SELECT COUNT(*) as total FROM feed_curtidas WHERE comunicado_id = ?");
        $stmtCount->execute([$comunicado_id]);
        $total_curtidas = $stmtCount->fetch()['total'];

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'sucesso', 'acao' => $status_acao, 'total' => $total_curtidas]);
        exit;
    }
    
    // 2. LÓGICA DE LISTAR COMENTÁRIOS
    elseif ($acao === 'listar_comentarios' && $comunicado_id > 0) {
        $sql = "SELECT c.comentario, DATE_FORMAT(c.data_hora, '%d/%m %H:%i') as data_hora, 
                CONCAT_WS(' ', u.firstname, u.realname) as nome
                FROM feed_comentarios c
                JOIN " . DB_GLPI . ".glpi_users u ON c.user_id = u.id
                WHERE c.comunicado_id = ?
                ORDER BY c.id ASC";
                
        $stmt = $pdo_intra->prepare($sql);
        $stmt->execute([$comunicado_id]);
        $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'sucesso', 'comentarios' => $comentarios]);
        exit;
    }

    // 3. LÓGICA DE SALVAR COMENTÁRIO
    elseif ($acao === 'comentar' && $comunicado_id > 0) {
        $texto = trim($_POST['comentario'] ?? '');
        
        if (!empty($texto)) {
            $stmt = $pdo_intra->prepare("INSERT INTO feed_comentarios (comunicado_id, user_id, comentario) VALUES (?, ?, ?)");
            $stmt->execute([$comunicado_id, $user_id, $texto]);
            
            // 🚀 LOG DE COMENTÁRIO
            registrarLog($pdo_intra, 'INTERAÇÃO FEED', "Adicionou um comentário no comunicado ID: $comunicado_id", $user_id, $ip_acesso);
        }
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'sucesso']);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}