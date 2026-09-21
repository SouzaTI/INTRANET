<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/DocumentoCentralAuth.php';
require_once dirname(__DIR__) . '/includes/DocumentoCentralStorage.php';
require_once dirname(__DIR__) . '/services/EmailAssinaturaService.php';

$usuarioId = (int) ($_SESSION['user_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $usuarioId <= 0) {
    http_response_code(403);
    exit('Acesso negado.');
}

$auth = new DocumentoCentralAuth(
    $pdo_intra,
    $usuarioId,
    isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true,
    $_SESSION
);

function docRedirecionar(bool $ok, string $mensagem, string $aba = 'meus'): never
{
    $chave = $ok ? 'sucesso' : 'erro';
    header('Location: ../documentos.php?aba=' . rawurlencode($aba) . '&' . $chave . '=' . rawurlencode($mensagem));
    exit;
}

function docBuscar(PDO $pdo, int $id, bool $travar = false): array
{
    $sql = 'SELECT * FROM documentos_central WHERE id = ?' . ($travar ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        throw new RuntimeException('Documento não encontrado.', 404);
    }
    return $doc;
}

function docConcluirTarefas(PDO $pdo, int $documentoId, int $usuarioId): void
{
    $pdo->prepare(
        "UPDATE documentos_tarefas
            SET status='CONCLUIDA', concluido_em=NOW(), concluido_por=?
          WHERE documento_id=? AND status='PENDENTE'"
    )->execute([$usuarioId, $documentoId]);
}

function docCriarTarefa(
    PDO $pdo,
    int $documentoId,
    string $tipo,
    ?int $usuarioResponsavel,
    ?string $grupoResponsavel,
    string $instrucoes = ''
): void {
    $pdo->prepare(
        'INSERT INTO documentos_tarefas
            (documento_id, tipo, usuario_responsavel_id, grupo_responsavel, instrucoes)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$documentoId, $tipo, $usuarioResponsavel, $grupoResponsavel, $instrucoes ?: null]);
}

function docEvento(
    PDO $pdo,
    int $documentoId,
    ?int $versaoId,
    int $usuarioId,
    string $acao,
    ?string $anterior,
    ?string $novo,
    string $mensagem = '',
    array $dados = []
): void {
    $pdo->prepare(
        'INSERT INTO documentos_eventos
            (documento_id, versao_id, usuario_id, acao, status_anterior, status_novo, mensagem, dados_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $documentoId,
        $versaoId,
        $usuarioId,
        $acao,
        $anterior,
        $novo,
        $mensagem ?: null,
        $dados ? json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);
}

function docRegistrarVersao(PDO $pdo, int $documentoId, int $numero, int $usuarioId, array $armazenado): int
{
    $pdo->prepare(
        'INSERT INTO documentos_versoes
            (documento_id, numero, nome_original, arquivo_path, mime_type, tamanho_bytes, hash_sha256, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $documentoId,
        $numero,
        $armazenado['nome_original'],
        $armazenado['arquivo_path'],
        $armazenado['mime_type'],
        $armazenado['tamanho_bytes'],
        $armazenado['hash_sha256'],
        $usuarioId,
    ]);
    return (int) $pdo->lastInsertId();
}

function docCriarEnvelopeAssinatura(
    PDO $pdo,
    array $documento,
    array $versao,
    array $assinantes,
    int $usuarioId,
    array &$arquivosCriados
): int {
    if ($versao['mime_type'] !== 'application/pdf') {
        throw new RuntimeException('Somente documentos PDF podem seguir para assinatura.');
    }

    $assinantes = array_values(array_unique(array_filter(array_map('intval', $assinantes))));
    if (!$assinantes) {
        throw new RuntimeException('Selecione pelo menos um assinante.');
    }

    $marcadores = implode(',', array_fill(0, count($assinantes), '?'));
    $stmtUsuarios = $GLOBALS['pdo_glpi']->prepare(
        "SELECT id FROM glpi_users WHERE id IN ($marcadores) AND is_active=1 AND is_deleted=0"
    );
    $stmtUsuarios->execute($assinantes);
    $validosEncontrados = array_map('intval', $stmtUsuarios->fetchAll(PDO::FETCH_COLUMN));
    $esperadosOrdenados = $assinantes;
    sort($esperadosOrdenados);
    sort($validosEncontrados);
    if ($validosEncontrados !== $esperadosOrdenados) {
        throw new RuntimeException('Um ou mais assinantes selecionados são inválidos.');
    }

    $origem = documentoCentralResolverArquivo((string) $versao['arquivo_path']);
    if (!hash_equals((string) $versao['hash_sha256'], hash_file('sha256', $origem))) {
        throw new RuntimeException('A versão aprovada falhou na verificação de integridade.');
    }

    $pdo->prepare(
        "INSERT INTO sistemas_assinaturas
            (titulo, arquivo_path, arquivo_hash, tipo_fluxo, criado_por, status)
         VALUES (?, '', ?, 'sequencial', ?, 'em_andamento')"
    )->execute([(string) $documento['titulo'], (string) $versao['hash_sha256'], $usuarioId]);
    $envelopeId = (int) $pdo->lastInsertId();

    $diretorio = dirname(__DIR__) . '/uploads/assinaturas/' . $envelopeId;
    if (!mkdir($diretorio, 0750, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Não foi possível preparar o envelope de assinatura.');
    }
    $destino = $diretorio . '/01_' . bin2hex(random_bytes(12)) . '.pdf';
    if (!copy($origem, $destino)) {
        throw new RuntimeException('Não foi possível copiar o documento para assinatura.');
    }
    $arquivosCriados[] = $destino;
    $relativo = 'uploads/assinaturas/' . $envelopeId . '/' . basename($destino);
    $hash = hash_file('sha256', $destino);

    $pdo->prepare(
        "INSERT INTO assinatura_documentos
            (envelope_id, nome_original, arquivo_original_path, arquivo_atual_path, hash_original, hash_atual, status)
         VALUES (?, ?, ?, ?, ?, ?, 'em_assinatura')"
    )->execute([$envelopeId, $versao['nome_original'], $relativo, $relativo, $hash, $hash]);
    $pdo->prepare('UPDATE sistemas_assinaturas SET arquivo_path=?, arquivo_hash=? WHERE id=?')
        ->execute([$relativo, $hash, $envelopeId]);

    $stmtFluxo = $pdo->prepare(
        'INSERT INTO assinaturas_fluxo (fk_assinatura, glpi_user_id, ordem, status) VALUES (?, ?, ?, ?)'
    );
    foreach ($assinantes as $indice => $assinanteId) {
        $stmtFluxo->execute([$envelopeId, $assinanteId, $indice + 1, $indice === 0 ? 'pendente' : 'aguardando']);
    }

    $pdo->prepare(
        "INSERT INTO assinatura_eventos
            (envelope_id, glpi_user_id, evento, descricao, ip_origem, user_agent)
         VALUES (?, ?, 'CRIADO', ?, ?, ?)"
    )->execute([
        $envelopeId,
        $usuarioId,
        'Envelope criado pela Central de Documentos.',
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    return $envelopeId;
}

try {
    documentoCentralValidarCsrf();
    $acao = (string) ($_POST['acao'] ?? '');
    $documentoId = (int) ($_POST['documento_id'] ?? 0);
    $arquivoCriado = null;
    $arquivosAssinaturaCriados = [];
    $notificarAssinantes = [];
    $envelopeCriado = null;

    $pdo_intra->beginTransaction();

    if ($acao === 'novo') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $tipo = in_array(($_POST['tipo'] ?? ''), ['DOCUMENTO', 'PROCESSO'], true) ? $_POST['tipo'] : 'PROCESSO';
        if ($titulo === '' || mb_strlen($titulo) > 255) {
            throw new RuntimeException('Informe um título válido.');
        }

        $setoresPermitidos = $auth->setores();
        $setor = mb_strtoupper(trim((string) ($_POST['setor'] ?? $auth->setorPrincipal())), 'UTF-8');
        if (!$auth->isValidador() && !in_array($setor, $setoresPermitidos, true)) {
            $setor = $auth->setorPrincipal();
        }
        if ($setor === '') {
            throw new RuntimeException('Seu usuário não possui setor definido.');
        }

        $upload = documentoCentralValidarUpload($_FILES['documento'] ?? null);
        $pdo_intra->prepare(
            "INSERT INTO documentos_central
                (titulo, descricao, setor, tipo, status, criador_id, responsavel_tipo)
             VALUES (?, ?, ?, ?, 'EM_VALIDACAO', ?, 'TI')"
        )->execute([$titulo, $descricao ?: null, $setor, $tipo, $usuarioId]);
        $documentoId = (int) $pdo_intra->lastInsertId();

        $armazenado = documentoCentralArmazenar($upload, $documentoId, 1);
        $arquivoCriado = $armazenado['caminho_absoluto'];
        $versaoId = docRegistrarVersao($pdo_intra, $documentoId, 1, $usuarioId, $armazenado);
        $pdo_intra->prepare('UPDATE documentos_central SET versao_atual_id=? WHERE id=?')
            ->execute([$versaoId, $documentoId]);
        docCriarTarefa($pdo_intra, $documentoId, 'VALIDAR', null, 'TI', 'Validar a versão V1.');
        docEvento($pdo_intra, $documentoId, $versaoId, $usuarioId, 'ENVIAR', 'RASCUNHO', 'EM_VALIDACAO', 'Documento enviado para validação do T.I.');
        $mensagemFinal = 'Documento enviado para validação do T.I.';
        $abaFinal = 'meus';
    } elseif ($acao === 'reenviar') {
        $doc = docBuscar($pdo_intra, $documentoId, true);
        $auth->exigirCriador($doc);
        if ($doc['status'] !== 'AJUSTES_SOLICITADOS') {
            throw new RuntimeException('Este documento não está aguardando correções.');
        }
        $upload = documentoCentralValidarUpload($_FILES['documento'] ?? null);
        $numero = (int) $pdo_intra->query(
            'SELECT COALESCE(MAX(numero),0)+1 FROM documentos_versoes WHERE documento_id=' . (int) $documentoId
        )->fetchColumn();
        $armazenado = documentoCentralArmazenar($upload, $documentoId, $numero);
        $arquivoCriado = $armazenado['caminho_absoluto'];
        $versaoId = docRegistrarVersao($pdo_intra, $documentoId, $numero, $usuarioId, $armazenado);
        docConcluirTarefas($pdo_intra, $documentoId, $usuarioId);
        $pdo_intra->prepare(
            "UPDATE documentos_central
                SET status='EM_VALIDACAO', responsavel_id=NULL, responsavel_tipo='TI', versao_atual_id=?
              WHERE id=?"
        )->execute([$versaoId, $documentoId]);
        docCriarTarefa($pdo_intra, $documentoId, 'VALIDAR', null, 'TI', 'Validar a versão V' . $numero . '.');
        docEvento($pdo_intra, $documentoId, $versaoId, $usuarioId, 'REENVIAR', $doc['status'], 'EM_VALIDACAO', trim((string) ($_POST['mensagem'] ?? 'Nova versão enviada.')));
        $mensagemFinal = 'Nova versão enviada ao T.I.';
        $abaFinal = 'meus';
    } elseif ($acao === 'assumir') {
        $auth->exigirValidador();
        $doc = docBuscar($pdo_intra, $documentoId, true);
        if ($doc['status'] !== 'EM_VALIDACAO') {
            throw new RuntimeException('Este documento não está disponível para assumir.');
        }
        $pdo_intra->prepare(
            "UPDATE documentos_central SET status='EM_ANALISE', responsavel_id=?, responsavel_tipo='VALIDADOR' WHERE id=?"
        )->execute([$usuarioId, $documentoId]);
        $pdo_intra->prepare(
            "UPDATE documentos_tarefas SET usuario_responsavel_id=?
              WHERE documento_id=? AND status='PENDENTE' AND tipo='VALIDAR'"
        )->execute([$usuarioId, $documentoId]);
        docEvento($pdo_intra, $documentoId, (int) $doc['versao_atual_id'], $usuarioId, 'ASSUMIR', $doc['status'], 'EM_ANALISE', 'Validação assumida pelo T.I.');
        $mensagemFinal = 'Documento atribuído a você.';
        $abaFinal = 'fila';
    } elseif ($acao === 'solicitar_ajustes') {
        $auth->exigirValidador();
        $doc = docBuscar($pdo_intra, $documentoId, true);
        if ($doc['status'] !== 'EM_ANALISE' || (!$auth->isAdmin() && (int) $doc['responsavel_id'] !== $usuarioId)) {
            throw new RuntimeException('Assuma a validação antes de solicitar ajustes.');
        }
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if (mb_strlen($mensagem) < 5) {
            throw new RuntimeException('Descreva claramente os ajustes necessários.');
        }
        docConcluirTarefas($pdo_intra, $documentoId, $usuarioId);
        $pdo_intra->prepare(
            "UPDATE documentos_central SET status='AJUSTES_SOLICITADOS', responsavel_id=criador_id, responsavel_tipo='CRIADOR' WHERE id=?"
        )->execute([$documentoId]);
        docCriarTarefa($pdo_intra, $documentoId, 'AJUSTAR', (int) $doc['criador_id'], null, $mensagem);
        docEvento($pdo_intra, $documentoId, (int) $doc['versao_atual_id'], $usuarioId, 'SOLICITAR_AJUSTES', $doc['status'], 'AJUSTES_SOLICITADOS', $mensagem);
        $mensagemFinal = 'Ajustes enviados ao responsável do documento.';
        $abaFinal = 'fila';
    } elseif ($acao === 'rejeitar') {
        $auth->exigirValidador();
        $doc = docBuscar($pdo_intra, $documentoId, true);
        if (!in_array($doc['status'], ['EM_VALIDACAO', 'EM_ANALISE'], true)) {
            throw new RuntimeException('Este documento não pode ser rejeitado neste estado.');
        }
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if (mb_strlen($mensagem) < 5) {
            throw new RuntimeException('Informe o motivo da rejeição.');
        }
        docConcluirTarefas($pdo_intra, $documentoId, $usuarioId);
        $pdo_intra->prepare(
            "UPDATE documentos_central SET status='REJEITADO', responsavel_id=NULL, responsavel_tipo='NENHUM' WHERE id=?"
        )->execute([$documentoId]);
        docEvento($pdo_intra, $documentoId, (int) $doc['versao_atual_id'], $usuarioId, 'REJEITAR', $doc['status'], 'REJEITADO', $mensagem);
        $mensagemFinal = 'Documento rejeitado.';
        $abaFinal = 'fila';
    } elseif ($acao === 'aprovar') {
        $auth->exigirValidador();
        $doc = docBuscar($pdo_intra, $documentoId, true);
        if ($doc['status'] !== 'EM_ANALISE' || (!$auth->isAdmin() && (int) $doc['responsavel_id'] !== $usuarioId)) {
            throw new RuntimeException('Assuma a validação antes de aprovar.');
        }
        $stmtVersao = $pdo_intra->prepare('SELECT * FROM documentos_versoes WHERE id=? AND documento_id=?');
        $stmtVersao->execute([(int) $doc['versao_atual_id'], $documentoId]);
        $versao = $stmtVersao->fetch(PDO::FETCH_ASSOC);
        if (!$versao) {
            throw new RuntimeException('A versão atual não foi encontrada.');
        }
        $arquivo = documentoCentralResolverArquivo((string) $versao['arquivo_path']);
        if (filesize($arquivo) <= 0 || !hash_equals((string) $versao['hash_sha256'], hash_file('sha256', $arquivo))) {
            throw new RuntimeException('A versão atual está vazia ou falhou na verificação de integridade.');
        }

        $exigeAssinatura = !empty($_POST['exige_assinatura']);
        docConcluirTarefas($pdo_intra, $documentoId, $usuarioId);
        if ($exigeAssinatura) {
            $notificarAssinantes = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['assinantes'] ?? [])))));
            $envelopeCriado = docCriarEnvelopeAssinatura(
                $pdo_intra,
                $doc,
                $versao,
                $notificarAssinantes,
                $usuarioId,
                $arquivosAssinaturaCriados
            );
            $pdo_intra->prepare(
                "UPDATE documentos_central
                    SET status='AGUARDANDO_ASSINATURAS', responsavel_id=NULL, responsavel_tipo='ASSINATURAS',
                        exige_assinatura=1, assinatura_envelope_id=?, aprovado_por=?, aprovado_em=NOW()
                  WHERE id=?"
            )->execute([$envelopeCriado, $usuarioId, $documentoId]);
            docCriarTarefa($pdo_intra, $documentoId, 'ASSINAR', null, 'ASSINANTES', 'Aguardar a conclusão do envelope #' . $envelopeCriado . '.');
            docEvento($pdo_intra, $documentoId, (int) $versao['id'], $usuarioId, 'APROVAR_PARA_ASSINATURA', $doc['status'], 'AGUARDANDO_ASSINATURAS', 'Documento aprovado tecnicamente e enviado para assinatura.', ['envelope_id' => $envelopeCriado]);
            $mensagemFinal = 'Documento aprovado e enviado para assinatura.';
        } else {
            $pdo_intra->prepare(
                "UPDATE documentos_central
                    SET status='PUBLICADO', responsavel_id=NULL, responsavel_tipo='NENHUM', exige_assinatura=0,
                        versao_publicada_id=versao_atual_id, aprovado_por=?, aprovado_em=NOW(), publicado_em=NOW()
                  WHERE id=?"
            )->execute([$usuarioId, $documentoId]);
            docEvento($pdo_intra, $documentoId, (int) $versao['id'], $usuarioId, 'APROVAR_PUBLICAR', $doc['status'], 'PUBLICADO', 'Documento aprovado tecnicamente e publicado.');
            $mensagemFinal = 'Documento aprovado e publicado.';
        }
        $abaFinal = 'fila';
    } else {
        throw new RuntimeException('Ação inválida.');
    }

    $pdo_intra->commit();
    registrarLog($pdo_intra, 'CENTRAL DOCUMENTOS', 'Ação ' . $acao . ' no documento #' . $documentoId . '.');

    if ($envelopeCriado && $notificarAssinantes) {
        try {
            $email = new EmailAssinaturaService();
            $email->enviarNovoPendente($pdo_intra, $pdo_glpi, $envelopeCriado, (int) $notificarAssinantes[0]);
        } catch (Throwable $emailErro) {
            error_log('CentralDocumentos/notificacao-assinatura: ' . $emailErro->getMessage());
        }
    }

    docRedirecionar(true, $mensagemFinal, $abaFinal);
} catch (Throwable $e) {
    if ($pdo_intra->inTransaction()) {
        $pdo_intra->rollBack();
    }
    if (!empty($arquivoCriado) && is_file($arquivoCriado)) {
        @unlink($arquivoCriado);
    }
    foreach ($arquivosAssinaturaCriados ?? [] as $arquivoAssinatura) {
        if (is_file($arquivoAssinatura)) {
            @unlink($arquivoAssinatura);
        }
        $diretorioAssinatura = dirname($arquivoAssinatura);
        if (is_dir($diretorioAssinatura)) {
            @rmdir($diretorioAssinatura);
        }
    }
    $mensagem = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.';
    error_log('CentralDocumentos/' . ($_POST['acao'] ?? 'acao') . ': ' . $e->getMessage());
    docRedirecionar(false, $mensagem, $auth->isValidador() ? 'fila' : 'meus');
}
