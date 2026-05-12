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
            <input id="password-login" type="password" name="pass" required>
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

</body>
</html>