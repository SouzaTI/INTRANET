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
            $tipo = $_GET['tipo'] ?? 'grupo'; // NOVO: Descobre se é pessoa ou canal

            if ($tipo === 'grupo') {
                $sql = "SELECT m.id, m.mensagem, DATE_FORMAT(m.data_hora, '%H:%i') as hora, m.remetente_id, u.firstname as nome
                        FROM chat_mensagens m
                        JOIN " . DB_GLPI . ".glpi_users u ON m.remetente_id = u.id
                        WHERE m.id > ? AND m.destino_id = ? AND m.tipo = 'grupo'
                        ORDER BY m.id ASC";
                $stmt = $pdo_intra->prepare($sql);
                $stmt->execute([$ultimo_id, $destino]);
            } else {
                // MÁGICA DO 1x1: Traz o que eu mandei pra ele, OU o que ele mandou pra mim!
                $sql = "SELECT m.id, m.mensagem, DATE_FORMAT(m.data_hora, '%H:%i') as hora, m.remetente_id, u.firstname as nome
                        FROM chat_mensagens m
                        JOIN " . DB_GLPI . ".glpi_users u ON m.remetente_id = u.id
                        WHERE m.id > ? AND m.tipo = 'usuario' 
                        AND ((m.remetente_id = ? AND m.destino_id = ?) OR (m.remetente_id = ? AND m.destino_id = ?))
                        ORDER BY m.id ASC";
                $stmt = $pdo_intra->prepare($sql);
                $stmt->execute([$ultimo_id, $user_id, $destino, $destino, $user_id]);
            }
            
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ENTREGA LIMPA: Limpa qualquer erro do buffer e entrega só o JSON
            ob_clean(); 
            header('Content-Type: application/json');
            echo json_encode($resultado);
            break;

        case 'enviar':
            $msg = trim($_POST['mensagem'] ?? '');
            $destino = (int)($_POST['destino'] ?? 0);
            $tipo = $_POST['tipo'] ?? 'grupo'; // NOVO: Pega o tipo

            if (!empty($msg) && $destino > 0) {
                $stmt = $pdo_intra->prepare("INSERT INTO chat_mensagens (remetente_id, destino_id, tipo, mensagem) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $destino, $tipo, $msg]);
                
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'sucesso']);
            }
            break;

        case 'marcar_lido':
            $destino = (int)($_POST['destino_id'] ?? 0);
            $tipo = $_POST['tipo'] ?? 'grupo'; // NOVO: Pega o tipo
            
            if ($destino > 0) {
                // Se não existe, CRIA. Se já existe, ATUALIZA para agora!
                $sql = "INSERT INTO chat_leituras (user_id, destino_id, tipo) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE ultima_leitura = CURRENT_TIMESTAMP";
                
                $stmt = $pdo_intra->prepare($sql);
                $stmt->execute([$user_id, $destino, $tipo]);
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'sucesso']);
            break;

        case 'listar_grupos':
            // 1. Traz os Grupos (Canais)
            $sql_grupos = "SELECT g.id, g.nome,
                    (SELECT COUNT(m.id) FROM chat_mensagens m WHERE m.destino_id = g.id AND m.tipo = 'grupo'
                     AND m.data_hora > COALESCE((SELECT ultima_leitura FROM chat_leituras WHERE user_id = ? AND destino_id = g.id AND tipo = 'grupo'), '2000-01-01')
                     AND m.remetente_id != ?) AS nao_lidas
                    FROM chat_grupos g LEFT JOIN chat_grupos_membros cgm ON g.id = cgm.grupo_id
                    WHERE g.id = 1 OR cgm.usuario_id = ? GROUP BY g.id ORDER BY CASE WHEN g.id = 1 THEN 0 ELSE 1 END, g.nome ASC";
            $stmtG = $pdo_intra->prepare($sql_grupos);
            $stmtG->execute([$user_id, $user_id, $user_id]); 
            $grupos = $stmtG->fetchAll(PDO::FETCH_ASSOC);

            $ids_escondidos = "2, 6, 9";

            // 2. Traz os Usuários (Pessoas para o 1x1) com ordenação por Última Mensagem
            $sql_users = "SELECT u.id, CONCAT_WS(' ', u.firstname, u.realname) as nome,
                    (SELECT COUNT(m.id) FROM chat_mensagens m WHERE m.tipo = 'usuario' AND m.remetente_id = u.id AND m.destino_id = ?
                     AND m.data_hora > COALESCE((SELECT ultima_leitura FROM chat_leituras WHERE user_id = ? AND destino_id = u.id AND tipo = 'usuario'), '2000-01-01')
                    ) AS nao_lidas,
                    (SELECT MAX(data_hora) FROM chat_mensagens WHERE tipo = 'usuario' AND ((remetente_id = u.id AND destino_id = ?) OR (remetente_id = ? AND destino_id = u.id))) AS ultima_msg
                    FROM " . DB_GLPI . ".glpi_users u 
                    WHERE u.id != ? 
                    AND u.is_deleted = 0 
                    AND u.is_active = 1 
                    AND u.id NOT IN ($ids_escondidos)
                    ORDER BY ultima_msg DESC, u.firstname ASC";
                    
            $stmtU = $pdo_intra->prepare($sql_users);
            
            // ATENÇÃO: Agora passamos a variável $user_id 5 vezes, pois adicionamos 2 novas perguntas (destino_id e remetente_id) no MAX(data_hora)
            $stmtU->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
            $usuarios = $stmtU->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['grupos' => $grupos, 'usuarios' => $usuarios]);
            break;

        case 'listar_usuarios_glpi':
            // Puxa todo mundo do GLPI para listar no modal
            $stmt = $pdo_intra->query("SELECT id, CONCAT_WS(' ', firstname, realname) as nome FROM " . DB_GLPI . ".glpi_users ORDER BY firstname ASC");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($usuarios);
            break;

        case 'listar_membros_grupo':
            // Descobre quem já faz parte do grupo selecionado
            $grupo_id = (int)($_GET['grupo_id'] ?? 0);
            $stmt = $pdo_intra->prepare("SELECT usuario_id FROM chat_grupos_membros WHERE grupo_id = ?");
            $stmt->execute([$grupo_id]);
            $membros = $stmt->fetchAll(PDO::FETCH_COLUMN); // Retorna só a listinha de IDs: [2, 5, 12...]
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($membros);
            break;

        case 'salvar_membros_grupo':
            // 🛡️ TRAVA DE SEGURANÇA: Só Admin pode salvar membros!
            if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['erro' => 'Acesso negado. Apenas administradores podem gerenciar grupos.']);
                exit;
            }

            // Salva as marcações da telinha no banco de dados
            $grupo_id = (int)($_POST['grupo_id'] ?? 0);
            $membros = $_POST['membros_grupo'] ?? []; 

            if ($grupo_id > 1) { // Proteção: O grupo 1 (GERAL) não pode ser editado!
                // 1. Apaga todo mundo que tava no grupo
                $stmtDel = $pdo_intra->prepare("DELETE FROM chat_grupos_membros WHERE grupo_id = ?");
                $stmtDel->execute([$grupo_id]);

                // 2. Insere os novos selecionados
                if (!empty($membros) && is_array($membros)) {
                    $sqlIns = "INSERT INTO chat_grupos_membros (grupo_id, usuario_id) VALUES (?, ?)";
                    $stmtIns = $pdo_intra->prepare($sqlIns);
                    foreach ($membros as $uid) {
                        $stmtIns->execute([$grupo_id, (int)$uid]);
                    }
                }
            }
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'sucesso']);
            break;

    }


} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['erro' => $e->getMessage()]);
}
exit;