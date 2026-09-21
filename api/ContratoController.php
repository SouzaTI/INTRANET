<?php
// api/ContratoController.php
require_once '../config.php'; // Voltou uma pasta pra achar a configuração
require_once __DIR__ . '/ContratoAuth.php';
require_once __DIR__ . '/ContratoStorage.php';

// 1. Recebe quem tá logado (Contexto)
$user_id_sessao = $_SESSION['user_id'] ?? 0;
$admin_ip       = $_SERVER['REMOTE_ADDR']; 
$eh_admin       = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$auth           = new ContratoAuth($pdo_intra, (int) $user_id_sessao, $eh_admin);

function contratoValorDecimal(mixed $valor): float {
    $texto = trim((string) $valor);
    $texto = preg_replace('/[^0-9,.-]/', '', $texto);
    if (str_contains($texto, ',')) {
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
    }
    return is_numeric($texto) ? (float) $texto : 0.0;
}

function contratoValorPost(string $campo): ?float {
    $valor = contratoValorDecimal($_POST[$campo] ?? '');
    return $valor > 0 ? $valor : null;
}

function contratoPendencias(array $c): array {
    $pendencias = [];
    $exigir = static function (string $campo, string $rotulo) use (&$pendencias, $c): void {
        if (!isset($c[$campo]) || trim((string) $c[$campo]) === '') $pendencias[$campo] = $rotulo;
    };

    foreach ([
        'fornecedor' => 'Fornecedor', 'cnpj' => 'CNPJ do fornecedor',
        'servico_objeto' => 'Objeto / serviço', 'setor' => 'Setor responsável',
        'empresa' => 'Empresa contratante', 'cnpj_empresa_contratante' => 'CNPJ da contratante',
        'data_inicio' => 'Início da vigência', 'tipo_prazo' => 'Tipo de prazo',
        'tipo_pagamento' => 'Tipo de pagamento', 'forma_pagamento' => 'Forma de pagamento',
        'arquivo_path' => 'Contrato em PDF',
    ] as $campo => $rotulo) $exigir($campo, $rotulo);

    if (($c['tipo_prazo'] ?? '') === 'DETERMINADO') {
        $exigir('data_vencimento', 'Data final da vigência');
        if (!in_array(($c['renovacao_automatica'] ?? ''), [0, 1, '0', '1'], true)) {
            $pendencias['renovacao_automatica'] = 'Renovação automática';
        }
    }

    if (($c['tipo_pagamento'] ?? '') === 'UNICO') {
        if ((float) ($c['valor'] ?? 0) <= 0) $pendencias['valor'] = 'Valor total';
    } elseif (($c['tipo_pagamento'] ?? '') === 'PARCELADO') {
        if ((float) ($c['valor'] ?? 0) <= 0) $pendencias['valor'] = 'Valor total';
        if ((int) ($c['quantidade_parcelas'] ?? 0) < 2) $pendencias['quantidade_parcelas'] = 'Quantidade de parcelas';
        $exigir('periodicidade', 'Periodicidade das parcelas');
    } elseif (($c['tipo_pagamento'] ?? '') === 'RECORRENTE_MENSAL') {
        if ((float) ($c['valor_parcela'] ?? 0) <= 0) $pendencias['valor_parcela'] = 'Valor mensal atual';
        $dia = (int) ($c['dia_vencimento'] ?? 0);
        if ($dia < 1 || $dia > 31) $pendencias['dia_vencimento'] = 'Dia do vencimento mensal';
    }

    foreach (['possui_reajuste' => 'Informe se possui reajuste', 'possui_aviso_cancelamento' => 'Aviso prévio para cancelamento',
              'possui_multa' => 'Multa contratual', 'possui_carencia' => 'Carência contratual'] as $campo => $rotulo) {
        if (!array_key_exists($campo, $c) || $c[$campo] === null || $c[$campo] === '') $pendencias[$campo] = $rotulo;
    }
    if ((string) ($c['possui_reajuste'] ?? '') === '1') {
        $exigir('indice_reajuste', 'Índice de reajuste');
        $exigir('periodicidade_reajuste', 'Periodicidade do reajuste');
        if ((int) ($c['mes_base_reajuste'] ?? 0) < 1 || (int) ($c['mes_base_reajuste'] ?? 0) > 12) {
            $pendencias['mes_base_reajuste'] = 'Mês-base do reajuste';
        }
        if (($c['indice_reajuste'] ?? '') === 'OUTRO') $exigir('indice_reajuste_outro', 'Nome do índice de reajuste');
    }
    if (($c['possui_aviso_cancelamento'] ?? '') === 'SIM' && ($c['prazo_comunicacao_cancelamento'] ?? '') === '') {
        $pendencias['prazo_comunicacao_cancelamento'] = 'Aviso prévio em dias';
    }
    if (($c['possui_multa'] ?? '') === 'SIM') $exigir('multa_contratual', 'Descrição da multa');
    if (($c['possui_carencia'] ?? '') === 'SIM') $exigir('carencia_contratual', 'Descrição da carência');
    return $pendencias;
}

set_exception_handler(function (Throwable $e): void {
    $codigo = $e instanceof RuntimeException && $e->getCode() >= 400 ? $e->getCode() : 500;
    $mensagem = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.';
    if (($_POST['acao'] ?? '') === 'salvar_contrato') {
        header('Location: ../contratos.php?erro=' . urlencode($mensagem));
        exit;
    }
    http_response_code($codigo);
    exit('erro: ' . $mensagem);
});

if ($user_id_sessao <= 0) {
    http_response_code(401);
    exit('erro: sessão inválida');
}

// 2. O "Roteador" das Ações (Padrão PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    try {
        contratoValidarCsrf();
    } catch (RuntimeException $e) {
        http_response_code($e->getCode() ?: 419);
        exit('erro: ' . $e->getMessage());
    }

    $acao = (string) $_POST['acao'];

    // A. SALVAR CONTRATO (criação/edição)
    if ($acao === 'salvar_contrato') {
        $arquivos_criados = [];
        try {
            $cid        = $_POST['contrato_id'] ?? '';
            $setor_novo = mb_strtoupper(trim((string) ($_POST['setor'] ?? '')), 'UTF-8');
            $arquivo_atual_db = null;

            if (empty($cid)) {
                $auth->exigir('criar');
            } else {
                $auth->exigirNoContrato('editar', (int) $cid);

                $stmt_status_atual = $pdo_intra->prepare("SELECT status, arquivo_path FROM contratos WHERE id = ?");
                $stmt_status_atual->execute([(int) $cid]);
                $contrato_atual = $stmt_status_atual->fetch(PDO::FETCH_ASSOC);
                if (!$contrato_atual) {
                    throw new RuntimeException('Contrato não encontrado.', 404);
                }
                $status_atual = (string) $contrato_atual['status'];
                $arquivo_atual_db = trim((string) ($contrato_atual['arquivo_path'] ?? '')) ?: null;
                if ($status_atual === 'ENCERRADO') {
                    throw new RuntimeException('Contrato encerrado é somente leitura. Use o histórico para consulta.', 409);
                }
            }

            $modo_salvamento = ($_POST['modo_salvamento'] ?? 'RASCUNHO') === 'ENVIAR_FINANCEIRO'
                ? 'ENVIAR_FINANCEIRO' : 'RASCUNHO';
            $obrigatorios = [
                'fornecedor' => 'Fornecedor', 'servico_objeto' => 'Objeto / Serviço',
                'setor' => 'Setor', 'empresa' => 'Empresa contratante',
            ];
            foreach ($obrigatorios as $campo => $rotulo) {
                if (trim((string) ($_POST[$campo] ?? '')) === '') {
                    throw new RuntimeException("Preencha o campo obrigatório: {$rotulo}.");
                }
            }

            $stmt_setor = $pdo_intra->prepare(
                "SELECT TRIM(SETOR)
                   FROM matriz_comunicacao
                  WHERE SETOR IS NOT NULL
                    AND UPPER(TRIM(SETOR)) = UPPER(?)
                  LIMIT 1"
            );
            $stmt_setor->execute([$setor_novo]);
            $setor_matriz = $stmt_setor->fetchColumn();
            if ($setor_matriz === false) {
                throw new RuntimeException('Selecione um setor válido da intranet.');
            }
            $setor_novo = mb_strtoupper(trim((string) $setor_matriz), 'UTF-8');

            $tipo_prazo = in_array(($_POST['tipo_prazo'] ?? ''), ['DETERMINADO', 'INDETERMINADO'], true)
                ? $_POST['tipo_prazo'] : null;
            $tipo_pagamento = in_array(($_POST['tipo_pagamento'] ?? ''), ['UNICO', 'PARCELADO', 'RECORRENTE_MENSAL'], true)
                ? $_POST['tipo_pagamento'] : null;
            if ($tipo_prazo === 'INDETERMINADO') $tipo_pagamento = 'RECORRENTE_MENSAL';
            if ($tipo_prazo === 'DETERMINADO' && $tipo_pagamento === 'RECORRENTE_MENSAL') {
                throw new RuntimeException('Para prazo determinado escolha pagamento único ou parcelado.');
            }
            $prazo_indeterminado = $tipo_prazo === 'INDETERMINADO' ? 1 : 0;
            $valor = contratoValorPost('valor');
            $quantidade_parcelas = ($_POST['quantidade_parcelas'] ?? '') === '' ? null : (int) $_POST['quantidade_parcelas'];
            $valor_parcela = contratoValorPost('valor_parcela');
            if ($tipo_pagamento === 'UNICO') {
                $quantidade_parcelas = 1;
                $valor_parcela = $valor;
            } elseif ($tipo_pagamento === 'PARCELADO' && $valor && $quantidade_parcelas) {
                $valor_parcela = round($valor / $quantidade_parcelas, 2);
            } elseif ($tipo_pagamento === 'RECORRENTE_MENSAL') {
                $valor = null;
                $quantidade_parcelas = null;
            }

            if (!empty($cid)) {
                // A autorização de edição já foi validada por exigirNoContrato().
            }

            // O campo legado continua apontando para o primeiro PDF, enquanto
            // a tabela contratos_anexos mantém todos os documentos do contrato.
            $arquivo_path = $arquivo_atual_db;
            $campo_upload = $_FILES['arquivos_contrato'] ?? $_FILES['arquivo_contrato'] ?? null;
            $uploads = contratosValidarUploads(is_array($campo_upload) ? $campo_upload : null);
            $novos_anexos = contratosArmazenarUploads($uploads, $setor_novo);
            $arquivos_criados = array_column($novos_anexos, 'caminho_absoluto');
            if ($arquivo_path === null && $novos_anexos) {
                $arquivo_path = $novos_anexos[0]['arquivo_path'];
            }
            $possui_reajuste = ($_POST['possui_reajuste'] ?? '') === '' ? null : (int) $_POST['possui_reajuste'];
            $possui_aviso = in_array(($_POST['possui_aviso_cancelamento'] ?? ''), ['SIM','NAO','NAO_INFORMADO'], true) ? $_POST['possui_aviso_cancelamento'] : null;
            $possui_multa = in_array(($_POST['possui_multa'] ?? ''), ['SIM','NAO','NAO_INFORMADO'], true) ? $_POST['possui_multa'] : null;
            $possui_carencia = in_array(($_POST['possui_carencia'] ?? ''), ['SIM','NAO','NAO_INFORMADO'], true) ? $_POST['possui_carencia'] : null;
            $periodicidade = $tipo_pagamento === 'UNICO' ? 'Pagamento único'
                : ($tipo_pagamento === 'RECORRENTE_MENSAL' ? 'Mensal' : trim((string) ($_POST['periodicidade'] ?? '')));
            $renovacao = ($_POST['renovacao_automatica'] ?? '') === '' ? null : (int) $_POST['renovacao_automatica'];
            // Prazo indeterminado não termina nem se renova: o campo não se aplica.
            if ($tipo_prazo === 'INDETERMINADO') $renovacao = null;

            $novo_fluxo = [
                'fornecedor' => trim((string) ($_POST['fornecedor'] ?? '')), 'cnpj' => trim((string) ($_POST['cnpj'] ?? '')),
                'servico_objeto' => trim((string) ($_POST['servico_objeto'] ?? '')), 'setor' => $setor_novo,
                'empresa' => trim((string) ($_POST['empresa'] ?? '')), 'cnpj_empresa_contratante' => trim((string) ($_POST['cnpj_empresa_contratante'] ?? '')),
                'data_inicio' => $_POST['data_inicio'] ?? null, 'data_vencimento' => $prazo_indeterminado ? null : ($_POST['data_vencimento'] ?? null),
                'tipo_prazo' => $tipo_prazo, 'tipo_pagamento' => $tipo_pagamento, 'forma_pagamento' => trim((string) ($_POST['forma_pagamento'] ?? '')),
                'arquivo_path' => $arquivo_path, 'valor' => $valor, 'valor_parcela' => $valor_parcela,
                'quantidade_parcelas' => $quantidade_parcelas, 'periodicidade' => $periodicidade,
                'dia_vencimento' => $_POST['dia_vencimento'] ?? null, 'renovacao_automatica' => $renovacao,
                'possui_reajuste' => $possui_reajuste, 'indice_reajuste' => $_POST['indice_reajuste'] ?? null,
                'periodicidade_reajuste' => $_POST['periodicidade_reajuste'] ?? null, 'mes_base_reajuste' => $_POST['mes_base_reajuste'] ?? null,
                'indice_reajuste_outro' => trim((string) ($_POST['indice_reajuste_outro'] ?? '')),
                'possui_aviso_cancelamento' => $possui_aviso, 'prazo_comunicacao_cancelamento' => $_POST['prazo_comunicacao_cancelamento'] ?? null,
                'possui_multa' => $possui_multa, 'multa_contratual' => trim((string) ($_POST['multa_contratual'] ?? '')),
                'possui_carencia' => $possui_carencia, 'carencia_contratual' => trim((string) ($_POST['carencia_contratual'] ?? '')),
            ];
            $pendencias_envio = contratoPendencias($novo_fluxo);
            if ($modo_salvamento === 'ENVIAR_FINANCEIRO' && $pendencias_envio) {
                throw new RuntimeException('Complete o cadastro antes de enviar. Pendências: ' . implode(', ', $pendencias_envio) . '.');
            }

            $dados = [
                trim($_POST['fornecedor']),
                trim($_POST['nome_fantasia'] ?? ''),
                trim($_POST['cnpj']),
                trim($_POST['contato_fornecedor_nome'] ?? ''),
                trim($_POST['contato_fornecedor_telefone'] ?? ''),
                trim($_POST['servico_objeto']),
                trim($_POST['numero_contrato'] ?? ''),
                trim($_POST['codigo_sistema'] ?? ''),
                trim($_POST['clausula_tecnica'] ?? ''), 
                trim($_POST['multa_carencia'] ?? ''),
                ($_POST['prazo_comunicacao_cancelamento'] ?? '') === '' ? null : (int) $_POST['prazo_comunicacao_cancelamento'],
                $renovacao,
                trim($_POST['aviso_previo'] ?? ''),
                trim((string) ($_POST['multa_contratual'] ?? '')),
                trim((string) ($_POST['carencia_contratual'] ?? '')),
                $setor_novo,
                trim($_POST['empresa']),
                trim($_POST['cnpj_empresa_contratante']),
                $_POST['data_inicio'] ?: null,
                $prazo_indeterminado ? null : ($_POST['data_vencimento'] ?: null),
                $prazo_indeterminado,
                $tipo_pagamento === 'RECORRENTE_MENSAL' ? 1 : 0,
                $valor,
                $valor_parcela,
                $arquivo_path,
                trim($_POST['forma_pagamento']),
                $quantidade_parcelas,
                $periodicidade,
                trim($_POST['indices_reajuste'] ?? ''),
                trim($_POST['centro_custo'] ?? ''),
                trim($_POST['dados_bancarios_fornecedor'] ?? ''),
                trim($_POST['retencoes_tributarias'] ?? ''),
                trim($_POST['condicoes_pagamento'] ?? ''),
                trim($_POST['responsavel_aprovacao_servico'] ?? ''),
                trim($_POST['contato_financeiro_nome'] ?? ''),
                trim($_POST['contato_financeiro_email'] ?? ''),
                trim($_POST['contato_financeiro_telefone'] ?? ''),
            ];

            $pdo_intra->beginTransaction();

            if (empty($cid)) {
                $sql = "INSERT INTO contratos
                    (fornecedor, nome_fantasia, cnpj, contato_fornecedor_nome, contato_fornecedor_telefone, servico_objeto, numero_contrato, codigo_sistema, clausula_tecnica, multa_carencia,
                    prazo_comunicacao_cancelamento, renovacao_automatica, aviso_previo, multa_contratual, carencia_contratual,
                    setor, empresa, cnpj_empresa_contratante, data_inicio, data_vencimento, prazo_indeterminado, recorrente, valor, valor_parcela, arquivo_path, forma_pagamento, quantidade_parcelas, 
                    periodicidade, indices_reajuste, centro_custo, dados_bancarios_fornecedor, retencoes_tributarias, 
                    condicoes_pagamento, responsavel_aprovacao_servico, contato_financeiro_nome, contato_financeiro_email, 
                    contato_financeiro_telefone, gestor_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                
                $pdo_intra->prepare($sql)->execute(array_merge($dados, [$user_id_sessao]));
                $cid = $pdo_intra->lastInsertId();

                $pdo_intra->prepare("INSERT IGNORE INTO contratos_acessos_usuarios (contrato_id, usuario_id, concedido_por) VALUES (?, ?, ?)")
                          ->execute([$cid, $user_id_sessao, $user_id_sessao]);

                $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, 1, 'Contrato cadastrado (Demanda de Informação)', ?)")
                        ->execute([$cid, $user_id_sessao]);
                registrarLog($pdo_intra, 'CRIOU CONTRATO', "Cadastrou o contrato: {$_POST['fornecedor']}", $user_id_sessao, $admin_ip);
            } else {
                $sql = "UPDATE contratos SET
                    fornecedor=?, nome_fantasia=?, cnpj=?, contato_fornecedor_nome=?, contato_fornecedor_telefone=?, servico_objeto=?, numero_contrato=?, codigo_sistema=?, clausula_tecnica=?, multa_carencia=?,
                    prazo_comunicacao_cancelamento=?, renovacao_automatica=?, aviso_previo=?, multa_contratual=?, carencia_contratual=?,
                    setor=?, empresa=?, cnpj_empresa_contratante=?, data_inicio=?, data_vencimento=?, prazo_indeterminado=?, recorrente=?, valor=?, valor_parcela=?, arquivo_path=?, forma_pagamento=?, quantidade_parcelas=?, 
                    periodicidade=?, indices_reajuste=?, centro_custo=?, dados_bancarios_fornecedor=?, retencoes_tributarias=?, 
                    condicoes_pagamento=?, responsavel_aprovacao_servico=?, contato_financeiro_nome=?, contato_financeiro_email=?, 
                    contato_financeiro_telefone=?
                    WHERE id = ?";
                
                $pdo_intra->prepare($sql)->execute(array_merge($dados, [$cid]));
                registrarLog($pdo_intra, 'EDITOU CONTRATO', "Editou o contrato ID: $cid", $user_id_sessao, $admin_ip);
            }

            if ($novos_anexos) {
                $stmt_anexo = $pdo_intra->prepare(
                    "INSERT INTO contratos_anexos
                        (contrato_id, nome_original, arquivo_path, mime_type, tamanho_bytes, enviado_por)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                foreach ($novos_anexos as $anexo) {
                    $stmt_anexo->execute([
                        (int) $cid,
                        $anexo['name'],
                        $anexo['arquivo_path'],
                        $anexo['mime_type'],
                        $anexo['size'],
                        $user_id_sessao,
                    ]);
                }
            }

            $status_fluxo = $modo_salvamento === 'ENVIAR_FINANCEIRO' ? 'AGUARDANDO_FINANCEIRO' : 'RASCUNHO';
            $pdo_intra->prepare(
                "UPDATE contratos SET status_fluxo=?, cadastro_atualizado=1, tipo_prazo=?, tipo_pagamento=?,
                    dia_vencimento=?, possui_reajuste=?, indice_reajuste=?, indice_reajuste_outro=?,
                    periodicidade_reajuste=?, periodicidade_reajuste_outro=?, mes_base_reajuste=?,
                    possui_aviso_cancelamento=?, possui_multa=?, possui_carencia=? WHERE id=?"
            )->execute([
                $status_fluxo, $tipo_prazo, $tipo_pagamento,
                ($_POST['dia_vencimento'] ?? '') === '' ? null : (int) $_POST['dia_vencimento'],
                $possui_reajuste,
                $possui_reajuste ? ($_POST['indice_reajuste'] ?? null) : 'SEM_REAJUSTE',
                $possui_reajuste ? trim((string) ($_POST['indice_reajuste_outro'] ?? '')) : null,
                $possui_reajuste ? ($_POST['periodicidade_reajuste'] ?? null) : null,
                $possui_reajuste ? trim((string) ($_POST['periodicidade_reajuste_outro'] ?? '')) : null,
                $possui_reajuste && ($_POST['mes_base_reajuste'] ?? '') !== '' ? (int) $_POST['mes_base_reajuste'] : null,
                $possui_aviso, $possui_multa, $possui_carencia, $cid,
            ]);

            // Envio explícito ao Financeiro, somente depois da validação completa.
            if ($modo_salvamento === 'ENVIAR_FINANCEIRO') {
                $auth->exigirNoContrato('compartilhar', (int) $cid);
                $pdo_intra->prepare("UPDATE contratos SET etapa_atual = 5, status_fluxo='AGUARDANDO_FINANCEIRO', compartilhado_em = NOW() WHERE id = ?")->execute([$cid]);
                $pdo_intra->prepare("INSERT INTO contratos_acessos_grupos (contrato_id, grupo_id, concedido_por) VALUES (?, 17, ?) ON DUPLICATE KEY UPDATE concedido_por = VALUES(concedido_por), concedido_em = CURRENT_TIMESTAMP")
                          ->execute([$cid, $user_id_sessao]);
                $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, 5, 'Resumo financeiro compartilhado com o Contas a Pagar', ?)")
                        ->execute([$cid, $user_id_sessao]);
            }

            $pdo_intra->commit();

            // Redireciona voltando uma casa para a interface
            $mensagem_sucesso = $modo_salvamento === 'ENVIAR_FINANCEIRO'
                ? 'Contrato enviado ao Contas a Pagar com sucesso!'
                : 'Rascunho salvo com sucesso!';
            header("Location: ../contratos.php?sucesso=" . urlencode($mensagem_sucesso));
            exit;

        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }
            foreach ($arquivos_criados as $arquivo_criado) {
                if (is_file($arquivo_criado)) {
                    @unlink($arquivo_criado);
                }
            }
            $mensagem = $e instanceof RuntimeException ? $e->getMessage() : 'Erro no banco de dados ao salvar as informações.';
            header("Location: ../contratos.php?erro=" . urlencode($mensagem));
            exit;
        }
    }

    // A.1 ATUALIZAÇÃO PONTUAL PELO CONTAS A PAGAR OU RESPONSÁVEL
    // Permite completar somente os dados contratuais exibidos no resumo,
    // sem conceder acesso aos demais campos administrativos.
    elseif ($acao === 'atualizar_dados_contas_pagar') {
        $cid = (int) ($_POST['contrato_id'] ?? 0);
        // Diretoria em contrato de outro dono é SOMENTE LEITURA.
        // Contas a Pagar continua podendo atualizar quando possuir a permissão financeira.
        $pode_atualizar_financeiro =
            !$auth->isDiretoria()
            && $auth->pode('ver_financeiro')
            && $auth->podeAcessarContrato($cid);

        $pode_atualizar_responsavel = $auth->podeNoContrato('editar', $cid);

        if (!$pode_atualizar_financeiro && !$pode_atualizar_responsavel) {
            throw new RuntimeException('Você possui somente visualização deste contrato.', 403);
        }

        $stmt = $pdo_intra->prepare("SELECT etapa_atual, valor, quantidade_parcelas, prazo_indeterminado FROM contratos WHERE id = ?");
        $stmt->execute([$cid]);
        $contrato_atual = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contrato_atual) {
            throw new RuntimeException('Contrato não encontrado.', 404);
        }
        $etapa_atual = (int) $contrato_atual['etapa_atual'];
        if ($pode_atualizar_financeiro && !$pode_atualizar_responsavel && (int) $etapa_atual < 5) {
            throw new RuntimeException('O contrato ainda não foi compartilhado com o Contas a Pagar.', 409);
        }

        $renovacao = $_POST['renovacao_automatica'] ?? '';
        if ($renovacao !== '' && !in_array((string) $renovacao, ['0', '1'], true)) {
            throw new RuntimeException('Informe corretamente se existe renovação automática.', 422);
        }
        $prazo = trim((string) ($_POST['prazo_comunicacao_cancelamento'] ?? ''));
        if ($prazo !== '' && (!ctype_digit($prazo) || (int) $prazo < 0)) {
            throw new RuntimeException('O prazo para cancelamento deve ser informado em dias.', 422);
        }

        $obrigatorios_resumo = [
            'nome_fantasia' => 'Nome fantasia', 'codigo_sistema' => 'Código do sistema',
            'contato_fornecedor_nome' => 'Contato do fornecedor',
            'contato_fornecedor_telefone' => 'Telefone do fornecedor',
            'valor_parcela' => 'Valor por pagamento/parcela',
            'prazo_comunicacao_cancelamento' => 'Prazo para comunicar o cancelamento',
            'renovacao_automatica' => 'Renovação automática',
            'multa_contratual' => 'Multa contratual', 'carencia_contratual' => 'Carência contratual',
            'clausula_tecnica' => 'Cláusula técnica / regra de cancelamento',
        ];
        foreach ($obrigatorios_resumo as $campo => $rotulo) {
            if (trim((string) ($_POST[$campo] ?? '')) === '') {
                throw new RuntimeException("Preencha o campo obrigatório: {$rotulo}.", 422);
            }
        }

        $valor_parcela = contratoValorDecimal($_POST['valor_parcela'] ?? 0);
        if (empty($contrato_atual['prazo_indeterminado']) && (int) $contrato_atual['quantidade_parcelas'] > 0) {
            $valor_parcela = round((float) $contrato_atual['valor'] / (int) $contrato_atual['quantidade_parcelas'], 2);
        }

        $dados = [
            trim((string) ($_POST['nome_fantasia'] ?? '')),
            trim((string) ($_POST['contato_fornecedor_nome'] ?? '')),
            trim((string) ($_POST['contato_fornecedor_telefone'] ?? '')),
            mb_strtoupper(trim((string) ($_POST['codigo_sistema'] ?? '')), 'UTF-8'),
            $valor_parcela,
            $prazo === '' ? null : (int) $prazo,
            $renovacao === '' ? null : (int) $renovacao,
            trim((string) ($_POST['multa_contratual'] ?? '')),
            trim((string) ($_POST['carencia_contratual'] ?? '')),
            trim((string) ($_POST['clausula_tecnica'] ?? '')),
            $cid,
        ];

        $pdo_intra->beginTransaction();
        try {
            $pdo_intra->prepare(
                "UPDATE contratos
                    SET nome_fantasia=?, contato_fornecedor_nome=?, contato_fornecedor_telefone=?,
                        codigo_sistema=?, valor_parcela=?, prazo_comunicacao_cancelamento=?, renovacao_automatica=?,
                        multa_contratual=?, carencia_contratual=?, clausula_tecnica=?
                  WHERE id=?"
            )->execute($dados);
            $origem_atualizacao = $pode_atualizar_financeiro && !$pode_atualizar_responsavel
                ? 'Dados contratuais atualizados pelo Contas a Pagar'
                : 'Dados contratuais atualizados pelo responsável';
            $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, ?, ?, ?)")
                      ->execute([$cid, (int) $etapa_atual, $origem_atualizacao, $user_id_sessao]);
            $pdo_intra->commit();
        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
            throw $e;
        }

        registrarLog($pdo_intra, 'ATUALIZOU DADOS CONTRATO', "Usuário atualizou dados pontuais do contrato ID $cid", $user_id_sessao, $admin_ip);
        echo 'sucesso';
        exit;
    }

    // B. COMPARTILHAR COM CONTAS A PAGAR
    elseif ($acao === 'compartilhar_contrato') {
        $cid = (int) ($_POST['contrato_id'] ?? 0);
        $auth->exigirNoContrato('compartilhar', $cid);

        $stmt_contrato = $pdo_intra->prepare("SELECT * FROM contratos WHERE id = ?");
        $stmt_contrato->execute([$cid]);
        $contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
        if (!$contrato) {
            throw new RuntimeException('Contrato não encontrado.', 404);
        }
        $pendentes = contratoPendencias($contrato);
        if ($pendentes) {
            throw new RuntimeException('Complete o cadastro antes de compartilhar. Pendências: ' . implode(', ', $pendentes) . '.', 422);
        }

        $pdo_intra->prepare("UPDATE contratos SET etapa_atual = 5, status_fluxo='AGUARDANDO_FINANCEIRO', cadastro_atualizado=1, compartilhado_em = NOW() WHERE id = ?")->execute([$cid]);
        $pdo_intra->prepare("INSERT INTO contratos_acessos_grupos (contrato_id, grupo_id, concedido_por) VALUES (?, 17, ?) ON DUPLICATE KEY UPDATE concedido_por = VALUES(concedido_por), concedido_em = CURRENT_TIMESTAMP")
                  ->execute([$cid, $user_id_sessao]);
        $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, 5, 'Resumo financeiro compartilhado com o Contas a Pagar', ?)")
                ->execute([$cid, $user_id_sessao]);
        registrarLog($pdo_intra, 'COMPARTILHOU CONTRATO', "Compartilhou o resumo financeiro do contrato ID $cid", $user_id_sessao, $admin_ip);
        echo "sucesso"; exit;
    }

    // C. CONFIRMAR USO/RECEBIMENTO
    elseif ($acao === 'confirmar_uso') {
        $cid = (int) ($_POST['contrato_id'] ?? 0);
        $auth->exigirNoContrato('confirmar_uso', $cid);
        $stmt = $pdo_intra->prepare("SELECT etapa_atual FROM contratos WHERE id = ?");
        $stmt->execute([$cid]);
        $etapa_atual = (int) $stmt->fetchColumn();
        if ($etapa_atual < 5) {
            throw new RuntimeException('O contrato ainda não foi compartilhado com o Contas a Pagar.', 409);
        }
        $pdo_intra->beginTransaction();
        try {
            $stmt = $pdo_intra->prepare("SELECT COUNT(*) FROM contratos_divergencias WHERE contrato_id = ? AND status = 'ABERTA'");
            $stmt->execute([$cid]);
            $tinha_divergencia = (int) $stmt->fetchColumn() > 0;

            if ($tinha_divergencia) {
                $pdo_intra->prepare("UPDATE contratos_divergencias SET status = 'RESOLVIDA' WHERE contrato_id = ? AND status = 'ABERTA'")
                          ->execute([$cid]);
            }

            $pdo_intra->prepare("UPDATE contratos SET etapa_atual = 6, status_fluxo='CONFIRMADO', uso_confirmado_em = NOW() WHERE id = ?")->execute([$cid]);
            $acao_historico = $tinha_divergencia
                ? 'Correção validada pelo Contas a Pagar; divergência resolvida'
                : 'Informações recebidas e em processamento pelo Contas a Pagar';
            $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, usuario_id) VALUES (?, 6, ?, ?)")
                      ->execute([$cid, $acao_historico, $user_id_sessao]);
            $pdo_intra->commit();
        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
            throw $e;
        }
        registrarLog($pdo_intra, 'CONFIRMOU USO', $tinha_divergencia ? "Resolveu a divergência do contrato ID $cid" : "Confirmou o recebimento do contrato ID $cid", $user_id_sessao, $admin_ip);
        echo "sucesso"; exit;
    }

    // D. REGISTRAR DIVERGÊNCIA DE VALORES
    elseif ($acao === 'registrar_divergencia') {
        $cid       = (int) ($_POST['contrato_id'] ?? 0);
        $auth->exigirNoContrato('registrar_divergencia', $cid);
        $descricao = trim($_POST['descricao'] ?? '');
        if (empty($descricao)) { echo "erro: descreva a divergência de valores"; exit; }

        $stmt = $pdo_intra->prepare("SELECT etapa_atual FROM contratos WHERE id = ?");
        $stmt->execute([$cid]);
        if ((int) $stmt->fetchColumn() < 5) {
            throw new RuntimeException('O contrato ainda não foi compartilhado com o Contas a Pagar.', 409);
        }
        $stmt = $pdo_intra->prepare("SELECT COUNT(*) FROM contratos_divergencias WHERE contrato_id = ? AND status = 'ABERTA'");
        $stmt->execute([$cid]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Este contrato já possui uma divergência aberta.', 409);
        }

        $pdo_intra->prepare("INSERT INTO contratos_divergencias (contrato_id, descricao, usuario_id) VALUES (?, ?, ?)")
                ->execute([$cid, $descricao, $user_id_sessao]);
        $pdo_intra->prepare("UPDATE contratos SET etapa_atual = 7, status_fluxo='COM_DIVERGENCIA' WHERE id = ?")->execute([$cid]);
        $pdo_intra->prepare("INSERT INTO contratos_historico (contrato_id, etapa, acao, observacao, usuario_id) VALUES (?, 7, 'Divergência de valores comunicada à área gestora', ?, ?)")
                ->execute([$cid, $descricao, $user_id_sessao]);
        registrarLog($pdo_intra, 'DIVERGÊNCIA CONTRATO', "Registrou divergência de valores no contrato ID $cid", $user_id_sessao, $admin_ip);
        echo "sucesso"; exit;
    }

    // E. RENOVAR CONTRATO VENCIDO
    elseif ($acao === 'renovar_contrato') {
        $cid = (int) ($_POST['contrato_id'] ?? 0);
        $auth->exigirNoContrato('editar', $cid);

        $stmt = $pdo_intra->prepare("
            SELECT id, fornecedor, etapa_atual, status, tipo_prazo,
                   data_inicio, data_vencimento
              FROM contratos
             WHERE id = ?
             LIMIT 1
        ");
        $stmt->execute([$cid]);
        $contrato = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contrato) {
            throw new RuntimeException('Contrato não encontrado.', 404);
        }
        if (($contrato['status'] ?? '') === 'ENCERRADO') {
            throw new RuntimeException('Contrato encerrado não pode ser renovado diretamente.', 409);
        }
        if (($contrato['tipo_prazo'] ?? '') !== 'DETERMINADO' || empty($contrato['data_vencimento'])) {
            throw new RuntimeException('A renovação rápida é destinada a contratos de prazo determinado.', 422);
        }

        $hoje = new DateTimeImmutable('today');
        $vencimento_atual = DateTimeImmutable::createFromFormat('Y-m-d', (string) $contrato['data_vencimento']);
        if (!$vencimento_atual) {
            throw new RuntimeException('A data de vencimento atual do contrato é inválida.', 422);
        }
        if ($vencimento_atual >= $hoje) {
            throw new RuntimeException('A renovação rápida fica disponível quando o contrato estiver vencido.', 409);
        }

        $tipo_novo = strtoupper(trim((string) ($_POST['tipo_prazo_novo'] ?? '')));
        if (!in_array($tipo_novo, ['DETERMINADO', 'INDETERMINADO'], true)) {
            throw new RuntimeException('Informe se a nova vigência possui prazo determinado ou indeterminado.', 422);
        }

        $data_inicio_nova = trim((string) ($_POST['data_inicio_nova'] ?? ''));
        $inicio_novo = DateTimeImmutable::createFromFormat('Y-m-d', $data_inicio_nova);
        if (!$inicio_novo || $inicio_novo->format('Y-m-d') !== $data_inicio_nova) {
            throw new RuntimeException('Informe uma data válida para o início da nova vigência.', 422);
        }

        $data_vencimento_nova = null;
        if ($tipo_novo === 'DETERMINADO') {
            $data_vencimento_nova = trim((string) ($_POST['data_vencimento_nova'] ?? ''));
            $fim_novo = DateTimeImmutable::createFromFormat('Y-m-d', $data_vencimento_nova);
            if (!$fim_novo || $fim_novo->format('Y-m-d') !== $data_vencimento_nova) {
                throw new RuntimeException('Informe a nova data final do contrato.', 422);
            }
            if ($fim_novo <= $inicio_novo) {
                throw new RuntimeException('A nova data final deve ser posterior ao início da nova vigência.', 422);
            }
        }

        $observacao = trim((string) ($_POST['observacao'] ?? ''));
        if (mb_strlen($observacao, 'UTF-8') > 1000) {
            throw new RuntimeException('A observação da renovação deve ter no máximo 1000 caracteres.', 422);
        }

        $pdo_intra->beginTransaction();
        try {
            $pdo_intra->prepare("
                INSERT INTO contratos_renovacoes (
                    contrato_id,
                    tipo_prazo_anterior,
                    data_inicio_anterior,
                    data_vencimento_anterior,
                    tipo_prazo_novo,
                    data_inicio_nova,
                    data_vencimento_nova,
                    observacao,
                    usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $cid,
                $contrato['tipo_prazo'] ?? null,
                $contrato['data_inicio'] ?: null,
                $contrato['data_vencimento'] ?: null,
                $tipo_novo,
                $data_inicio_nova,
                $data_vencimento_nova,
                $observacao !== '' ? $observacao : null,
                $user_id_sessao,
            ]);

            $pdo_intra->prepare("
                UPDATE contratos
                   SET tipo_prazo = ?,
                       prazo_indeterminado = ?,
                       data_inicio = ?,
                       data_vencimento = ?,
                       status = 'ATIVO',
                       data_encerramento = NULL,
                       motivo_encerramento = NULL,
                       encerrado_por = NULL,
                       renovacao_automatica = CASE WHEN ? = 'INDETERMINADO' THEN NULL ELSE renovacao_automatica END
                 WHERE id = ?
            ")->execute([
                $tipo_novo,
                $tipo_novo === 'INDETERMINADO' ? 1 : 0,
                $data_inicio_nova,
                $data_vencimento_nova,
                $tipo_novo,
                $cid,
            ]);

            $descricao_renovacao = $tipo_novo === 'INDETERMINADO'
                ? 'Contrato renovado para prazo indeterminado'
                : 'Contrato renovado até ' . (new DateTimeImmutable($data_vencimento_nova))->format('d/m/Y');

            $pdo_intra->prepare("
                INSERT INTO contratos_historico (contrato_id, etapa, acao, observacao, usuario_id)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $cid,
                (int) ($contrato['etapa_atual'] ?? 0),
                $descricao_renovacao,
                $observacao !== '' ? $observacao : null,
                $user_id_sessao,
            ]);

            $pdo_intra->commit();
        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }
            throw $e;
        }

        registrarLog(
            $pdo_intra,
            'RENOVOU CONTRATO',
            "Renovou o contrato ID {$cid} - " . ($contrato['fornecedor'] ?? ''),
            $user_id_sessao,
            $admin_ip
        );

        echo 'sucesso';
        exit;
    }

    // F. ENCERRAR CONTRATO (ARQUIVAMENTO LÓGICO)
    elseif ($acao === 'encerrar_contrato') {
        $cid = (int) ($_POST['contrato_id'] ?? 0);
        $auth->exigirNoContrato('editar', $cid);

        $stmt = $pdo_intra->prepare("
            SELECT id, fornecedor, etapa_atual, status
              FROM contratos
             WHERE id = ?
             LIMIT 1
        ");
        $stmt->execute([$cid]);
        $contrato = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contrato) {
            throw new RuntimeException('Contrato não encontrado.', 404);
        }
        if (($contrato['status'] ?? '') === 'ENCERRADO') {
            throw new RuntimeException('Este contrato já está encerrado.', 409);
        }

        $data_encerramento = trim((string) ($_POST['data_encerramento'] ?? ''));
        $data_enc = DateTimeImmutable::createFromFormat('Y-m-d', $data_encerramento);
        if (!$data_enc || $data_enc->format('Y-m-d') !== $data_encerramento) {
            throw new RuntimeException('Informe uma data válida para o encerramento.', 422);
        }
        if ($data_enc > new DateTimeImmutable('today')) {
            throw new RuntimeException('A data de encerramento não pode ser futura.', 422);
        }

        $motivo = trim((string) ($_POST['motivo_encerramento'] ?? ''));
        if ($motivo === '') {
            throw new RuntimeException('Informe o motivo do encerramento.', 422);
        }
        if (mb_strlen($motivo, 'UTF-8') > 500) {
            throw new RuntimeException('O motivo do encerramento deve ter no máximo 500 caracteres.', 422);
        }

        $pdo_intra->beginTransaction();
        try {
            $pdo_intra->prepare("
                UPDATE contratos
                   SET status = 'ENCERRADO',
                       data_encerramento = ?,
                       motivo_encerramento = ?,
                       encerrado_por = ?
                 WHERE id = ?
            ")->execute([
                $data_encerramento,
                $motivo,
                $user_id_sessao,
                $cid,
            ]);

            $pdo_intra->prepare("
                INSERT INTO contratos_historico (contrato_id, etapa, acao, observacao, usuario_id)
                VALUES (?, ?, 'Contrato encerrado', ?, ?)
            ")->execute([
                $cid,
                (int) ($contrato['etapa_atual'] ?? 0),
                $motivo,
                $user_id_sessao,
            ]);

            $pdo_intra->commit();
        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) {
                $pdo_intra->rollBack();
            }
            throw $e;
        }

        registrarLog(
            $pdo_intra,
            'ENCERROU CONTRATO',
            "Encerrou o contrato ID {$cid} - " . ($contrato['fornecedor'] ?? ''),
            $user_id_sessao,
            $admin_ip
        );

        echo 'sucesso';
        exit;
    }

    // G. EXCLUIR CONTRATO
    elseif ($acao === 'excluir_contrato') {
        $cid = (int) ($_POST['contrato_id'] ?? 0);
        $auth->exigirNoContrato('excluir', $cid);
        $stmt_check = $pdo_intra->prepare("SELECT setor, fornecedor FROM contratos WHERE id = ?");
        $stmt_check->execute([$cid]);
        $c = $stmt_check->fetch(PDO::FETCH_ASSOC);

        $pdo_intra->prepare("DELETE FROM contratos WHERE id = ?")->execute([$cid]);
        registrarLog($pdo_intra, 'EXCLUIU CONTRATO', "Excluiu o contrato: " . ($c['fornecedor'] ?? "ID $cid"), $user_id_sessao, $admin_ip);
        header("Location: ../contratos.php?sucesso=" . urlencode("Contrato excluído com sucesso!"));
        exit;
    }
}

// Se alguém tentar acessar o arquivo direto pela URL ou sem enviar POST, chuta de volta pra página principal
header("Location: ../contratos.php");
exit;
