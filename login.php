<?php
session_start(); // Inicia a sessão caso não esteja rodando globalmente

// Captura a mensagem de erro que vem do processa_login.php[cite: 10]
if (isset($_GET['erro']) && $_GET['erro'] == 'auth') {
    $erro = "Usuário ou senha inválidos.";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Intranet | Souza</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,600" rel="stylesheet">
    
    <!-- Linkando a roupa (CSS) -->
    <link rel="stylesheet" href="assets/login.css?v=1.0">
</head>
<body>

<div id="back">
  <canvas id="canvas" class="canvas-back"></canvas>
  <div class="backRight"></div>
  <div class="backLeft"></div>
</div>

<div id="slideBox">
  <div class="topLayer">
    
    <!-- LADO ESQUERDO: CADASTRAR -->
    <div class="left">
      <div class="content">
        <h2>Cadastrar</h2>
        <form id="form-signup" method="post">
          <div class="form-element form-stack">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email">
          </div>
          <div class="form-element form-stack">
            <label for="username-signup" class="form-label">Usuário</label>
            <input id="username-signup" type="text" name="username">
          </div>
          <div class="form-element form-stack">
            <label for="password-signup" class="form-label">Senha</label>
            <input id="password-signup" type="password" name="password">
          </div>
          <div class="form-element form-submit">
            <button id="signUp" class="signup" type="submit" name="btn_signup">Cadastrar</button>
            <button id="goLeft" type="button" class="signup off">Entrar</button> 
          </div>
        </form>
      </div>
    </div>
    
    <!-- LADO DIREITO: LOGIN -->
    <div class="right">
      <div class="content">
        <h2>Login</h2>

        <!-- EXIBE ERRO DE LOGIN AQUI -->
        <?php if(isset($erro)) echo "<p style='color: #ef4444; font-size: 13px; font-weight: bold; margin-bottom: 10px;'>$erro</p>"; ?>

        <!-- 🔥 AQUI ACONTECE A MÁGICA: O form envia os dados pro arquivo da API -->
        <form id="form-login" method="POST" action="api/processa_login.php">
          <div class="form-element form-stack">
            <label for="username-login" class="form-label">Usuário</label>
            <input id="username-login" type="text" name="user" required>
          </div>
          <div class="form-element form-stack">
            <label for="password-login" class="form-label">Senha</label>
            <div style="position: relative; width: 100%;">
              <input id="password-login" type="password" name="pass" required style="width: 100%; padding-right: 40px;">
              
              <button type="button" id="toggle-password-view" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 5px; opacity: 0.5; transition: opacity 0.2s; box-shadow: none; margin: 0;">
                <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px; fill: none; stroke: #475569; stroke-width: 2;" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </button>
            </div>
          </div>
          <div class="form-element form-submit">
            <button id="logIn" class="login" type="submit" name="btn_login">Entrar</button>
            <button id="goRight" type="button" class="login off">Cadastrar</button>
          </div>
        </form>
      </div>
    </div>
    
  </div>
</div>

<!-- ========================================= *
   * IMPORTAÇÃO DAS BIBLIOTECAS E DO SEU JS    *
   * ========================================= -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/paper.js/0.12.17/paper-full.min.js"></script>

<!-- Linkando os músculos (JS) -->
<script src="assets/login.js?v=1.0"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const btnToggle = document.getElementById('toggle-password-view');
    const inputSenha = document.getElementById('password-login');
    const eyeIcon = document.getElementById('eye-icon');

    if (btnToggle && inputSenha) {
        btnToggle.addEventListener('click', function() {
            // Se for password, muda para text (mostra a senha)
            if (inputSenha.type === 'password') {
                inputSenha.type = 'text';
                btnToggle.style.opacity = "1"; // Destaca o olho
                // Altera o SVG para o olho "riscado" (ocultar)
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                `;
            } else {
                inputSenha.type = 'password';
                btnToggle.style.opacity = "0.5"; // Apaga o olho
                // Restaura o SVG para o olho aberto normal
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                `;
            }
        });
    }
});
</script>

</body>
</html>