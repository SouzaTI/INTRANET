<?php
require_once 'config.php';
session_start();

$meu_id = $_SESSION['user_id'] ?? 0;

// Procura apenas registos CRIADOS nos últimos 15 segundos
// Ignora atualizações de cliques (ultima_atividade)
$stmt = $pdo_intra->prepare("
    SELECT nome_usuario 
    FROM controle_presenca 
    WHERE criado_em > DATE_SUB(NOW(), INTERVAL 15 SECOND)
    AND usuario_id != ?
");
$stmt->execute([$meu_id]);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($usuarios);