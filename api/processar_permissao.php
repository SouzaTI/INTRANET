<?php
require_once __DIR__ . '/../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['is_admin']) {
    $user_id = $_POST['user_id'];
    $pastas = $_POST['pastas'] ?? [];
    $nome_colaborador = $_POST['user_name_hidden'] ?? "ID $user_id"; // Caso tenha passado o nome

    // 1. Limpa e Insere (Sua lógica que já funciona)
    $stmt = $pdo_intra->prepare("DELETE FROM permissoes_pastas WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $stmt_ins = $pdo_intra->prepare("INSERT INTO permissoes_pastas (user_id, pasta_nome) VALUES (?, ?)");
    foreach ($pastas as $pasta) {
        $stmt_ins->execute([$user_id, $pasta]);
    }

    // 2. O REGISTRO DO LOG (O que faltava)
    $lista_pastas = !empty($pastas) ? implode(', ', $pastas) : 'Nenhuma (Acesso removido)';
    registrarLog($pdo_intra, "ALTEROU PERMISSÃO", "Usuário $nome_colaborador recebeu acesso às pastas: $lista_pastas");

    header("Location: ../admin_gestao.php?sucesso=1");
    exit;
}