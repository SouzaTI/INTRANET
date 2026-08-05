<?php
// api/ContratoController.php
require_once '../config.php'; // Voltou uma pasta pra achar a configuração

// 1. Recebe quem tá logado (Contexto)
$user_id_sessao = $_SESSION['user_id'] ?? 0;
$admin_ip       = $_SERVER['REMOTE_ADDR']; 
$setor_usuario  = mb_strtoupper(trim($_SESSION['setor'] ?? ''), 'UTF-8');
$eh_admin       = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$eh_financeiro  = in_array($setor_usuario, ['FINANCEIRO', 'CONTAS A PAGAR']);

// 2. O "Roteador" das Ações (Padrão PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    $acao = $_POST['acao'];

    // A. SALVAR CONTRATO (criação/edição)
    if ($acao === 'salvar_contrato') {
        try {
            $cid        = $_POST['contrato_id'] ?? '';
            $setor_novo = mb_strtoupper(trim($_POST['setor']), 'UTF-8');

            if (!$eh_admin && $setor_novo !== $setor_usuario) {
                header("Location: ../contratos.php?erro=" . urlencode("Você só pode cadastrar contratos do seu próprio setor."));
                exit;
            }

            if (!empty($cid)) {
                $stmt_check = $pdo_intra->prepare("SELECT setor FROM contratos WHERE id = ?");
                $stmt_check->execute([$cid]);
                $setor_dono = $stmt_check->fetchColumn();
                if (!$eh_admin && $setor_dono !== $setor_usuario) {
                    header("Location: ../contratos.php?erro=" . urlencode("Sem permissão para editar contrato de outro setor."));
                    exit;
                }
            }

            // Upload do anexo corrigido para salvar na pasta anterior
            $arquivo_path = $_POST['arquivo_atual'] ?? null;
            if (!empty($_FILES['arquivo_contrato']['name'])) {
                $dir_uploads = __DIR__ . '/../uploads/contratos/';
                if (!is_dir($dir_uploads)) mkdir($dir_uploads, 0775, true);
                $ext          = pathinfo($_FILES['arquivo_contrato']['name'], PATHINFO_EXTENSION);
                $nome_arquivo = 'contrato_' . uniqid() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                if (move_uploaded_file($_FILES['arquivo_contrato']['tmp_name'], $dir_uploads . $nome_arquivo)) {
                    $arquivo_path = 'uploads/contratos/' . $nome_arquivo;
                }
            }

            $valor_bruto = preg_replace('/[^0-9,]/', '', $_POST['valor'] ?? '0');
            $valor       = (float) str_replace(',', '.', $valor_bruto);

            $dados = [
                trim($_POST['fornecedor']),
                trim($_POST['cnpj']),
                trim($_POST['servico_objeto']),
                trim($_POST['numero_contrato']),
                trim($_POST['codigo_sistema'] ?? ''),
                trim($_POST['clausula_tecnica'] ?? ''), 
                trim($_POST['multa_carencia'] ?? ''),   
                $setor_novo,
                trim($_POST['empresa']),
                $_POST['data_inicio'] ?: null,
                $_POST['data_vencimento'],
                isset($_POST['recorrente']) ? 1 : 0,
                $valor,
                $arquivo_path,
                trim($_POST['forma_pagamento']),
                !empty($_POST['quantidade_parcelas']) ? (int)$_POST['quantidade_parcelas'] : null,
                trim($_POST['periodicidade']),
                trim($_POST['indices_reajuste']),
                trim($_POST['centro_custo']),
                trim($_POST['dados_bancarios_fornecedor']),
                trim($_POST['retencoes_tributarias']),
                trim($_POST['condicoes_pagamento']),
                trim($_POST['responsavel_aprovacao_servico']),
                trim($_POST['contato_financeiro_nome']),
                trim($_POST['contato_financeiro_email']),
                trim($_POST['contato_financeiro_telefone']),
            ];

            if (empty($cid)) {
                $sql = "INSERT INTO contratos
                    (fornecedor, cnpj, servico_objeto, numero_contrato, codigo_sistema, clausula_tecnica, multa_carencia, 
                    setor, empresa, data_inicio, data_vencimento, recorrente, valor, arquivo_path, forma_pagamento, quantidade_parcelas, 
                    periodicidade, indices_reajuste, centro_custo, dados_bancarios_fornecedor, retencoes_tributarias, 
                    condicoes_pagamento, responsavel_aprovacao_servico, contato_financeiro_nome, contato_financeiro_email, 
                    contato_financeiro_telefone, gestor_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"; 
                
                $pdo_intra->prepare($sql)->execute(array_merge($dados, [$user_id_sessao]));
                $cid = $pdo_intra->lastInsertId();

                $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, 1, 'Contrato cadastrado (Demanda de Informação)', ?)")
                        ->execute([$cid, $user_id_sessao]);
                registrarLog($pdo_intra, 'CRIOU CONTRATO', "Cadastrou o contrato: {$_POST['fornecedor']}", $user_id_sessao, $admin_ip);
            } else {
                $sql = "UPDATE contratos SET
                    fornecedor=?, cnpj=?, servico_objeto=?, numero_contrato=?, codigo_sistema=?, clausula_tecnica=?, multa_carencia=?, 
                    setor=?, empresa=?, data_inicio=?, data_vencimento=?, recorrente=?, valor=?, arquivo_path=?, forma_pagamento=?, quantidade_parcelas=?, 
                    periodicidade=?, indices_reajuste=?, centro_custo=?, dados_bancarios_fornecedor=?, retencoes_tributarias=?, 
                    condicoes_pagamento=?, responsavel_aprovacao_servico=?, contato_financeiro_nome=?, contato_financeiro_email=?, 
                    contato_financeiro_telefone=?
                    WHERE id = ?";
                
                $pdo_intra->prepare($sql)->execute(array_merge($dados, [$cid]));
                registrarLog($pdo_intra, 'EDITOU CONTRATO', "Editou o contrato ID: $cid", $user_id_sessao, $admin_ip);
            }

            // Se o usuário já marcou para compartilhar direto no wizard
            if (!empty($_POST['compartilhar_agora'])) {
                $pdo_intra->prepare("UPDATE contratos SET etapa_atual = 5, compartilhado_em = NOW() WHERE id = ?")->execute([$cid]);
                $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, 5, 'Resumo financeiro compartilhado com o Contas a Pagar', ?)")
                        ->execute([$cid, $user_id_sessao]);
            }

            // Redireciona voltando uma casa para a interface
            header("Location: ../contratos.php?sucesso=" . urlencode("Contrato salvo com sucesso!"));
            exit;

        } catch (Exception $e) {
            header("Location: ../contratos.php?erro=" . urlencode("Erro no banco de dados ao salvar as informações."));
            exit;
        }
    }

    // B. COMPARTILHAR COM CONTAS A PAGAR
    elseif ($acao === 'compartilhar_contrato') {
        $cid = $_POST['contrato_id'];
        $stmt_check = $pdo_intra->prepare("SELECT setor FROM contratos WHERE id = ?");
        $stmt_check->execute([$cid]);
        $setor_dono = $stmt_check->fetchColumn();

        if (!$eh_admin && $setor_dono !== $setor_usuario) { echo "erro: sem permissão"; exit; }

        $pdo_intra->prepare("UPDATE contratos SET etapa_atual = 5, compartilhado_em = NOW() WHERE id = ?")->execute([$cid]);
        $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, 5, 'Resumo financeiro compartilhado com o Contas a Pagar', ?)")
                ->execute([$cid, $user_id_sessao]);
        registrarLog($pdo_intra, 'COMPARTILHOU CONTRATO', "Compartilhou o resumo financeiro do contrato ID $cid", $user_id_sessao, $admin_ip);
        echo "sucesso"; exit;
    }

    // C. CONFIRMAR USO/RECEBIMENTO
    elseif ($acao === 'confirmar_uso') {
        if (!$eh_admin && !$eh_financeiro) { echo "erro: sem permissão"; exit; }
        $cid = $_POST['contrato_id'];
        $pdo_intra->prepare("UPDATE contratos SET etapa_atual = 6, uso_confirmado_em = NOW() WHERE id = ?")->execute([$cid]);
        $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, 6, 'Informações recebidas e em processamento pelo Contas a Pagar', ?)")
                ->execute([$cid, $user_id_sessao]);
        registrarLog($pdo_intra, 'CONFIRMOU USO', "Confirmou o recebimento do contrato ID $cid", $user_id_sessao, $admin_ip);
        echo "sucesso"; exit;
    }

    // D. REGISTRAR DIVERGÊNCIA DE VALORES
    elseif ($acao === 'registrar_divergencia') {
        if (!$eh_admin && !$eh_financeiro) { echo "erro: sem permissão"; exit; }
        $cid       = $_POST['contrato_id'];
        $descricao = trim($_POST['descricao'] ?? '');
        if (empty($descricao)) { echo "erro: descreva a divergência de valores"; exit; }

        $pdo_intra->prepare("INSERT INTO contratos_divergencias (contrato_id, descricao, usuario_id) VALUES (?, ?, ?)")
                ->execute([$cid, $descricao, $user_id_sessao]);
        $pdo_intra->prepare("UPDATE contratos SET etapa_atual = 7 WHERE id = ? AND etapa_atual < 7")->execute([$cid]);
        $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, observacao, usuario_id) VALUES (?, 7, 'Divergência de valores comunicada à área gestora', ?, ?)")
                ->execute([$cid, $descricao, $user_id_sessao]);
        registrarLog($pdo_intra, 'DIVERGÊNCIA CONTRATO', "Registrou divergência de valores no contrato ID $cid", $user_id_sessao, $admin_ip);
        echo "sucesso"; exit;
    }

    // E. EXCLUIR CONTRATO
    elseif ($acao === 'excluir_contrato') {
        $cid = $_POST['contrato_id'];
        $stmt_check = $pdo_intra->prepare("SELECT setor, fornecedor FROM contratos WHERE id = ?");
        $stmt_check->execute([$cid]);
        $c = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$eh_admin && (!$c || $c['setor'] !== $setor_usuario)) {
            header("Location: ../contratos.php?erro=" . urlencode("Sem permissão para excluir este contrato."));
            exit;
        }

        $pdo_intra->prepare("DELETE FROM contratos WHERE id = ?")->execute([$cid]);
        registrarLog($pdo_intra, 'EXCLUIU CONTRATO', "Excluiu o contrato: " . ($c['fornecedor'] ?? "ID $cid"), $user_id_sessao, $admin_ip);
        header("Location: ../contratos.php?sucesso=" . urlencode("Contrato excluído com sucesso!"));
        exit;
    }
}

// Se alguém tentar acessar o arquivo direto pela URL ou sem enviar POST, chuta de volta pra página principal
header("Location: ../contratos.php");
exit;