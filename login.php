<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_login = $_POST['user'];
    $user_pass  = $_POST['pass'];

    // 1. Busca o usuário no banco do GLPI (Consulta Segura)
    $stmt = $pdo->prepare("SELECT id, password, firstname, realname FROM glpi_users WHERE name = ?");
    $stmt->execute([$user_login]);
    $user = $stmt->fetch();

    // 2. Verifica a senha
    if ($user && password_verify($user_pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['firstname'] . " " . $user['realname'];
        
        // --- LOGICA DE PRESENÇA (ONLINE) ---
        // Usamos o $pdo_intra para gravar na nossa tabela de controle
        $stmt_presenca = $pdo_intra->prepare("
            INSERT INTO controle_presenca (usuario_id, nome_usuario, status, ultima_atividade) 
            VALUES (?, ?, 'ONLINE', NOW()) 
            ON DUPLICATE KEY UPDATE status = 'ONLINE', ultima_atividade = NOW()
        ");
        $stmt_presenca->execute([$user['id'], $user['firstname']]);
        // ------------------------------------

        header("Location: index.php");
        exit;
    } else {
        $erro = "Usuário ou senha inválidos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login Intranet</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-2xl w-96">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-800">NAVE</h1>
            <p class="text-slate-500 text-sm">Portal de Documentação</p>
        </div>
        
        <?php if(isset($erro)) echo "<p class='text-red-500 text-xs mb-4 text-center'>$erro</p>"; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Usuário</label>
                <input type="text" name="user" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Senha</label>
                <input type="password" name="pass" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 rounded-lg hover:bg-blue-700 transition-all">ENTRAR</button>
        </form>
    </div>
</body>
</html>