<?php
require_once 'config.php';

// Proteção: Se não tiver sessão ativa, volta pro login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';
    $user_id = $_SESSION['user_id'];
    $ip_acesso = $_SERVER['REMOTE_ADDR'];

    if (empty($nova_senha) || strlen($nova_senha) < 6) {
        $erro = "A senha deve conter pelo menos 6 caracteres.";
    } elseif ($nova_senha !== $confirma_senha) {
        $erro = "As senhas digitadas não coincidem.";
    } elseif ($nova_senha === 'Souza@123') {
        $erro = "Você não pode escolher a senha padrão corporativa. Escolha uma senha pessoal!";
    } else {
        try {
            // 1. Gera o hash BCRYPT idêntico ao que o GLPI espera
            $novo_hash = password_hash($nova_senha, PASSWORD_BCRYPT); //

            // 2. 🔥 ALTERADO PARA $pdo_glpi PARA GARANTIR O BANCO DO GLPI GLOBALMENTE
            $stmt_glpi = $pdo_glpi->prepare("UPDATE glpi_users SET password = ? WHERE id = ?");
            $stmt_glpi->execute([$novo_hash, $user_id]); //

            // 3. Altera o estado de primeiro login para 0 na Intranet
            $stmt_intra = $pdo_intra->prepare("UPDATE usuarios_permissoes SET primeiro_login = 0 WHERE usuario_id = ?"); //
            $stmt_intra->execute([$user_id]); //

            // 3. Altera o estado de primeiro login para 0 na Intranet
            $stmt_intra = $pdo_intra->prepare("UPDATE usuarios_permissoes SET primeiro_login = 0 WHERE usuario_id = ?");
            $stmt_intra->execute([$user_id]);

            // Grava a auditoria
            registrarLog($pdo_intra, 'ALTEROU SENHA', "Usuário redefiniu a senha pessoal com sucesso no primeiro acesso.", $user_id, $ip_acesso);

            // Sucesso! Vai para a home liberado
            header("Location: index.php?sucesso=" . urlencode("Senha atualizada com sucesso!"));
            exit;

        } catch (Exception $e) {
            $erro = "Erro ao atualizar senha no banco: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Definir Nova Senha Pessoal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen text-slate-100">
    <div class="bg-slate-800 p-8 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-md">
        <div class="text-center mb-6">
            <span class="text-4xl">🔒</span>
            <h2 class="text-xl font-black mt-2 uppercase tracking-wide text-white">Nova Senha Obrigatória</h2>
            <p class="text-xs text-slate-400 mt-1">Sua senha foi resetada pela gerência. Por segurança, defina uma senha pessoal para continuar.</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-3 rounded-xl text-xs font-bold mb-4">
                ⚠️ <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[11px] font-black uppercase text-slate-400 tracking-wider mb-1">Nova Senha</label>
                <input type="password" name="nova_senha" required placeholder="Digite sua nova senha"
                       class="w-full bg-slate-950 border border-slate-700 focus:border-blue-500 text-sm rounded-xl px-4 py-3 outline-none text-white transition-all">
            </div>

            <div>
                <label class="block text-[11px] font-black uppercase text-slate-400 tracking-wider mb-1">Confirme a Nova Senha</label>
                <input type="password" name="confirma_senha" required placeholder="Repita a nova senha"
                       class="w-full bg-slate-950 border border-slate-700 focus:border-blue-500 text-sm rounded-xl px-4 py-3 outline-none text-white transition-all">
            </div>

            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black uppercase text-xs tracking-wider py-3.5 rounded-xl transition-all shadow-lg shadow-blue-500/20">
                Salvar Nova Senha e Entrar
            </button>
        </form>
    </div>
</body>
</html>