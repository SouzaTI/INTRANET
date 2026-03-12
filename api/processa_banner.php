<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = trim($_POST['titulo'] ?? 'Sem Título');
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    
    // IMPORTANTE: Como estamos dentro da pasta /api, precisamos subir um nível (../)
    // para salvar na pasta correta, mas salvar no BANCO sem o ../
    $diretorio_fisico = "../img/comunicacao/campanhas/";
    $diretorio_banco = "img/comunicacao/campanhas/";

    if (!is_dir($diretorio_fisico)) {
        mkdir($diretorio_fisico, 0777, true);
    }

    if (isset($_FILES['banner_arquivo']) && $_FILES['banner_arquivo']['error'] === 0) {
        
        $arquivo_tmp = $_FILES['banner_arquivo']['tmp_name'];
        $nome_original = $_FILES['banner_arquivo']['name'];
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));

        $novo_nome = "campanha_" . date('Ymd_His') . "_" . uniqid() . "." . $extensao;
        
        $caminho_salvamento = $diretorio_fisico . $novo_nome;
        $caminho_banco = $diretorio_banco . $novo_nome;

        if (move_uploaded_file($arquivo_tmp, $caminho_salvamento)) {
            
            try {
                $sql = "INSERT INTO banners_marketing (titulo, imagem_path, data_inicio, data_fim, ativo) 
                        VALUES (:titulo, :path, :inicio, :fim, 1)";
                
                $stmt = $pdo_intra->prepare($sql);
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':path'   => $caminho_banco, 
                    ':inicio' => $data_inicio,
                    ':fim'    => $data_fim
                ]);

                // AQUI ESTÁ O SEGREDO: Mensagem de sucesso via JS e redirecionamento
                echo "<script>
                        alert('✅ Campanha agendada com sucesso!');
                        window.location.href = '../admin_campanhas.php';
                      </script>";
                exit;

            } catch (PDOException $e) {
                unlink($caminho_salvamento);
                die("Erro ao salvar no banco: " . $e->getMessage());
            }

        } else {
            die("Erro ao mover o arquivo.");
        }
    } else {
        die("Nenhum arquivo enviado.");
    }
} else {
    header("Location: ../admin_campanhas.php");
    exit;
}