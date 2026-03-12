<?php
require_once '../config.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // 1. Busca o caminho da imagem para apagar o arquivo do servidor
    $stmt = $pdo_intra->prepare("SELECT imagem_path FROM banners_marketing WHERE id = ?");
    $stmt->execute([$id]);
    $banner = $stmt->fetch();

    if ($banner) {
        // Apaga o arquivo físico (usa ../ porque o script está na pasta api)
        if (file_exists("../" . $banner['imagem_path'])) {
            unlink("../" . $banner['imagem_path']);
        }

        // 2. Remove o registro do banco de dados
        $delete = $pdo_intra->prepare("DELETE FROM banners_marketing WHERE id = ?");
        $delete->execute([$id]);
    }
}

// Retorna com alerta de sucesso
echo "<script>
        alert('🗑️ Campanha removida com sucesso!');
        window.location.href = '../admin_campanhas.php';
      </script>";
exit;