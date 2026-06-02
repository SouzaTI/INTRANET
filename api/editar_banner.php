<?php
require_once '../config.php';
session_start();

// Aqui é sempre bom validar se veio via POST para evitar acesso direto pela URL
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? '';
    $data_fim = $_POST['data_fim'] ?? '';

    if ($id && $titulo && $data_inicio && $data_fim) {
        try {
            // A gente usa prepare() e não concatena string direto no SQL para evitar SQL Injection. Regra de ouro da faculdade!
            $stmt = $pdo_intra->prepare("UPDATE banners_marketing SET titulo = ?, data_inicio = ?, data_fim = ? WHERE id = ?");
            $stmt->execute([$titulo, $data_inicio, $data_fim, $id]);

            // Se você quiser registrar no log quem mexeu no banner, seria aqui!
            // registrarLog($pdo_intra, 'EDITOU BANNER', "Editou datas do banner: $titulo", $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']);

            header("Location: ../admin_campanhas.php?sucesso=" . urlencode("Campanha atualizada com sucesso!"));
            exit;
        } catch (PDOException $e) {
            header("Location: ../admin_campanhas.php?erro=" . urlencode("Erro no banco: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: ../admin_campanhas.php?erro=" . urlencode("Dados incompletos."));
        exit;
    }
} else {
    header("Location: ../admin_campanhas.php");
    exit;
}