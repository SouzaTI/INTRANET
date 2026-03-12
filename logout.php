<?php
session_start();
session_unset();

if (isset($_SESSION['usuario_id'])) {
    $stmt = $pdo_intra->prepare("UPDATE controle_presenca SET status = 'OFFLINE' WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
}
session_destroy();
header("Location: login.php");
exit;