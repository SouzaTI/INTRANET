<?php
// ==========================================
// TESTE DE VALIDAÇÃO DE SENHA DIRETA
// ==========================================
$ad_ip = ""; 
$dominio = ""; // O prefixo do seu domínio

// Simulando as variáveis que viriam do seu formulário HTML via $_POST
$usuario_digitado = ""; 
$senha_digitada   = ''; 

echo "<h3>🔐 Testando Autenticação LDAP...</h3>";

// 1. O PHP bate na porta do servidor
$ldap_conn = ldap_connect($ad_ip);

if ($ldap_conn) {
    // Configurações padrão do protocolo
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // 2. A TENTATIVA DE LOGIN:
    // Montamos a string igual o Windows pede: "dominio\usuario"
    $usuario_completo = $dominio . $usuario_digitado;

    // Tentamos fazer o BIND usando as credenciais que vieram da tela
    // O '@' está aqui de propósito para esconder mensagens de erro nativas do PHP caso a senha esteja errada
    $autenticado = @ldap_bind($ldap_conn, $usuario_completo, $senha_digitada);

    if ($autenticado) {
        // Se entrou aqui, o AD disse: "A senha confere!"
        echo "<h3 style='color: green;'>✅ ACESSO CONCEDIDO!</h3>";
        echo "A senha do <strong>" . $usuario_digitado . "</strong> está 100% correta no Active Directory!<br>";
        
        // Dica: Como ele autenticou, você poderia fazer um ldap_search AQUI dentro
        // para puxar o e-mail ou nome completo do cara no AD, se quisesse.
        
    } else {
        // Se caiu aqui, a senha ou o login estão errados
        echo "<h3 style='color: red;'>❌ ACESSO NEGADO!</h3>";
        echo "Credenciais inválidas. O AD barrou a entrada.<br>";
    }

    // Fecha a conexão
    ldap_unbind($ldap_conn);
} else {
    echo "Falha ao encontrar o servidor no IP especificado.";
}
?>