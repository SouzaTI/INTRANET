<?php
// Se não houver um ID de usuário na sessão, o usuário não está logado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: login.php?erro=sessao_expirada");
    exit;
}

// 🛡️ VALIDAÇÃO DE IMPRESSÃO DIGITAL
// Verifica se o navegador e o IP ainda são os mesmos do momento do login
$digital_atual = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);

if (!isset($_SESSION['user_fingerprint']) || $_SESSION['user_fingerprint'] !== $digital_atual) {
    // Se a digital mudar (tentativa de roubo de sessão), desloga na hora
    session_unset();
    session_destroy();
    header("Location: login.php?erro=seguranca");
    exit;
}
?>