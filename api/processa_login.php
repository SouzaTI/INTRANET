<?php
// Volta uma pasta para achar o config.php se este arquivo estiver dentro da pasta api/
require_once '../config.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_login = $_POST['user'];
    $user_pass  = $_POST['pass'];
    $ip_acesso = $_SERVER['REMOTE_ADDR'];

    // Busca o usuário no banco do GLPI (Consulta Segura)
    $stmt = $pdo->prepare("SELECT id, password, firstname, realname FROM glpi_users WHERE name = ?");
    $stmt->execute([$user_login]);
    $user = $stmt->fetch();

    // Verifica a senha
    if ($user && password_verify($user_pass, $user['password'])) {
        
        // 🔥 PROTEÇÃO CRÍTICA: Gera um novo ID de sessão e apaga o anterior do servidor
        // Isso impede que a sessão do "usuário A" flutue para o "usuário B"
        session_regenerate_id(true); 

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['firstname'] . " " . $user['realname'];
        
        // 🛡️ DIGITAL DO NAVEGADOR: Cria uma assinatura única (IP + Navegador)
        // Se alguém tentar clonar o ID da sessão em outro PC, o sistema detecta
        $_SESSION['user_fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
        
        // LOGICA DE PRESENÇA (Mantida igual ao seu código)
        $stmt_presenca = $pdo_intra->prepare("
            INSERT INTO controle_presenca (usuario_id, nome_usuario, status, ultima_atividade) 
            VALUES (?, ?, 'ONLINE', NOW()) 
            ON DUPLICATE KEY UPDATE status = 'ONLINE', ultima_atividade = NOW()
        ");
        $stmt_presenca->execute([$user['id'], $user['firstname']]);
        
        registrarLog($pdo_intra, 'LOGIN', "Sessão iniciada com sucesso.", $user['id'], $ip_acesso);

        // 🔥 NOVA VALIDAÇÃO: Verifica se é o primeiro login ou se a senha foi resetada por você
        $stmt_check = $pdo_intra->prepare("SELECT primeiro_login FROM usuarios_permissoes WHERE usuario_id = ?");
        $stmt_check->execute([$user['id']]);
        $check_senha = $stmt_check->fetch();

        if ($check_senha && $check_senha['primeiro_login'] == 1) {
            // Se o admin resetou ou é o primeiro acesso, manda obrigatoriamente para redefinir
            header("Location: ../nova_senha.php");
            exit;
        }

        // Se estiver tudo certo (primeiro_login = 0 ou não registrado), segue para a home normalmente
        header("Location: ../index.php");
        exit;
    }else {
        // REGISTRA LOG DE FALHA (Tentativa de invasão/erro de senha)[cite: 10]
        registrarLog($pdo_intra, 'FALHA DE LOGIN', "Tentativa de acesso negada para o usuário: $user_login", 0, $ip_acesso);
        
        // Redireciona de volta com o alerta de erro na URL
        header("Location: ../login.php?erro=auth");
        exit;
    }
}
?>