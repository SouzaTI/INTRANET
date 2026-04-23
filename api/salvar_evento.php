<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once '../config.php';
header('Content-Type: application/json');

$usuario_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
$isAdmin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

if (!$usuario_id) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_evento = $_POST['id_evento'] ?? null; // ID para edição
    $titulo = htmlspecialchars($_POST['titulo'], ENT_QUOTES, 'UTF-8');
    $data = $_POST['data_evento'];
    $sala = $_POST['local_sala'] ?? 'GERAL';
    $ip_acesso = $_SERVER['REMOTE_ADDR']; // Pega o IP
    
    $abertura = "08:00:00";
    $fechamento = "17:48:00";

    $isDiaInteiro = isset($_POST['dia_inteiro']) && $_POST['dia_inteiro'] == '1';
    
    if ($isDiaInteiro) {
        $h_inicio = $abertura;
        $h_fim = $fechamento;
    } else {
        $h_inicio = !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
        $h_fim = !empty($_POST['hora_fim']) ? $_POST['hora_fim'] : null;
    }

    if ($sala !== 'GERAL' && $h_inicio && $h_fim) {
        if ($h_inicio < $abertura || $h_fim > $fechamento) {
            echo json_encode(['success' => false, 'error' => "Fora do expediente (08:00 - 17:48)."]);
            exit;
        }
    }

    $visibilidade = 'PESSOAL';
    if ($isAdmin) {
        $visibilidade = $_POST['visibilidade'] ?? 'PESSOAL';
    }

    try {
        // TRAVA DE CONFLITO MELHORADA
        if ($sala !== 'GERAL' && $h_inicio && $h_fim) {
            $sqlConflito = "SELECT COUNT(*) FROM agenda_eventos 
                            WHERE data_evento = ? 
                            AND local_sala = ? 
                            AND (hora_inicio < ? AND hora_fim > ?)";
            
            // Se for EDIÇÃO, ignoramos o próprio registro no check de conflito
            if (!empty($id_evento)) {
                $sqlConflito .= " AND id != " . (int)$id_evento;
            }
            
            $stmtCheck = $pdo_intra->prepare($sqlConflito);
            $stmtCheck->execute([$data, $sala, $h_fim, $h_inicio]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'error' => "A $sala já está ocupada neste horário!"]);
                exit;
            }
        }

        if (!empty($id_evento)) {
            // LÓGICA DE UPDATE (Segurança: apenas dono ou admin)
            $sql = "UPDATE agenda_eventos SET titulo = ?, hora_inicio = ?, hora_fim = ?, visibilidade = ?, local_sala = ? 
                    WHERE id = ? AND (usuario_id = ? OR ?)";
            $stmt = $pdo_intra->prepare($sql);
            $stmt->execute([$titulo, $h_inicio, $h_fim, $visibilidade, $sala, $id_evento, $usuario_id, (int)$isAdmin]);
            
            // 🚀 LOG DE EDIÇÃO DA AGENDA
            registrarLog($pdo_intra, 'ALTEROU AGENDA', "Editou a reserva/evento ID $id_evento: $titulo na $sala.", $usuario_id, $ip_acesso);
        } else {
            // LÓGICA DE INSERT
            $sql = "INSERT INTO agenda_eventos (usuario_id, titulo, data_evento, hora_inicio, hora_fim, visibilidade, local_sala, categoria) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'EVENTO')";
            $stmt = $pdo_intra->prepare($sql);
            $stmt->execute([$usuario_id, $titulo, $data, $h_inicio, $h_fim, $visibilidade, $sala]);
            
            // 🚀 LOG DE CRIAÇÃO DA AGENDA
            registrarLog($pdo_intra, 'CRIOU AGENDA', "Criou a reserva/evento: $titulo na $sala para o dia $data.", $usuario_id, $ip_acesso);
        }
        
        echo json_encode(['success' => true]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erro: ' . $e->getMessage()]);
        exit;
    }
}