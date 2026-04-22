<?php
// 1. LIGA O ESCUDO: Tudo que o PHP tentar imprimir (erros, <br />) fica preso na memória!
ob_start(); 

require_once '../config.php';

// Desliga os erros para o restante do arquivo
error_reporting(0);
ini_set('display_errors', 0);

// Proteção de Sessão
if (!isset($_SESSION['user_id'])) {
    ob_clean(); // Joga fora o lixo da memória
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$acao = $_GET['acao'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($acao) {
        case 'buscar':
            $ultimo_id = (int)($_GET['ultimo_id'] ?? 0);
            $destino = (int)($_GET['destino'] ?? 0);

            $sql = "SELECT m.id, m.mensagem, m.data_hora, m.remetente_id, u.firstname as nome
                    FROM chat_mensagens m
                    JOIN " . DB_GLPI . ".glpi_users u ON m.remetente_id = u.id
                    WHERE m.id > ? AND m.destino_id = ?
                    ORDER BY m.id ASC";
            
            $stmt = $pdo_intra->prepare($sql);
            $stmt->execute([$ultimo_id, $destino]);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 2. ENTREGA LIMPA: Limpa qualquer erro do buffer e entrega só o JSON
            ob_clean(); 
            header('Content-Type: application/json');
            echo json_encode($resultado);
            break;

        case 'enviar':
            $msg = trim($_POST['mensagem'] ?? '');
            $destino = (int)($_POST['destino'] ?? 0);

            if (!empty($msg) && $destino > 0) {
                $stmt = $pdo_intra->prepare("INSERT INTO chat_mensagens (remetente_id, destino_id, mensagem) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $destino, $msg]);
                
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'sucesso']);
            }
            break;

        case 'listar_grupos':
            $stmt = $pdo_intra->query("SELECT id, nome FROM chat_grupos ORDER BY CASE WHEN id = 1 THEN 0 ELSE 1 END, nome ASC");
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($resultado);
            break;
    }
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['erro' => $e->getMessage()]);
}
exit;