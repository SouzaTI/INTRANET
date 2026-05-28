<?php
// 1. PRIMEIRO chamamos o config (que tem as configurações de cookie da sua Intranet)
require_once 'config.php'; 

// 2. SE o config não tiver iniciado a sessão automaticamente, nós iniciamos de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificamos a requisição
if (isset($_POST['acao']) && $_POST['acao'] === 'marcar_visto') {
    
    // Verifica se a sessão do usuário foi recuperada com sucesso
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        
        try {
            // MÁGICA: Se não existir, insere. Se existir, atualiza!
            $sql = "INSERT INTO usuarios_permissoes (usuario_id, tutorial_visto) 
                    VALUES (?, 1) 
                    ON DUPLICATE KEY UPDATE tutorial_visto = 1";
            
            $stmt = $pdo_intra->prepare($sql);
            $stmt->execute([$uid]);
            
            // Atualiza a própria sessão na mesma hora por segurança
            $_SESSION['tutorial_visto'] = 1;
            
            echo "SUCESSO! Gravado para o Usuario ID: " . $uid;
            
        } catch (Exception $e) {
            echo "ERRO_SQL: " . $e->getMessage();
        }
    } else {
        echo "ERRO: Sessão não encontrada. O PHP não reconheceu o seu login no AJAX.";
    }
} else {
    echo "ERRO: Ação inválida ou requisição incorreta.";
}
?>