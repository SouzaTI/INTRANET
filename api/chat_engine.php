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

        case 'marcar_lido':
            $destino = (int)($_POST['destino_id'] ?? 0);
            
            if ($destino > 0) {
                // Se não existe, CRIA. Se já existe, ATUALIZA para agora!
                $sql = "INSERT INTO chat_leituras (user_id, destino_id) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE ultima_leitura = CURRENT_TIMESTAMP";
                
                $stmt = $pdo_intra->prepare($sql);
                $stmt->execute([$user_id, $destino]);
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'sucesso']);
            break;

        case 'listar_grupos':
            // Query de PRIVACIDADE CORRIGIDA: Usa 'usuario_id' para bater certo com a tua tabela
            $sql = "SELECT g.id, g.nome,
                    (SELECT COUNT(m.id) FROM chat_mensagens m
                     WHERE m.destino_id = g.id
                     AND m.data_hora > COALESCE((SELECT ultima_leitura FROM chat_leituras WHERE user_id = ? AND destino_id = g.id), '2000-01-01')
                     AND m.remetente_id != ?) AS nao_lidas
                    FROM chat_grupos g
                    LEFT JOIN chat_grupos_membros cgm ON g.id = cgm.grupo_id
                    WHERE g.id = 1 OR cgm.usuario_id = ?
                    GROUP BY g.id
                    ORDER BY CASE WHEN g.id = 1 THEN 0 ELSE 1 END, g.nome ASC";
            
            $stmt = $pdo_intra->prepare($sql);
            // Passamos o ID do utilizador 3 vezes (2 para as bolinhas, 1 para a verificação de membro)
            $stmt->execute([$user_id, $user_id, $user_id]); 
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($resultado);
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