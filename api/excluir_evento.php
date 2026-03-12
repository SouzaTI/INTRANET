<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $usuario_id = $_SESSION['usuario_id'];
    $isAdmin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

    try {
        // Se for admin, deleta qualquer um. Se não, deleta só o dele.
        if ($isAdmin) {
            $stmt = $pdo_intra->prepare("DELETE FROM agenda_eventos WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo_intra->prepare("DELETE FROM agenda_eventos WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}