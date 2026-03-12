<?php
// api/registrar_log.php
require_once __DIR__ . '/../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? 'ACAO_DESCONHECIDA';
    $detalhe = $_POST['detalhe'] ?? '';
    
    // Usa a função global que criamos no config.php
    registrarLog($pdo_intra, $acao, $detalhe);
    
    echo json_encode(['status' => 'success']);
}