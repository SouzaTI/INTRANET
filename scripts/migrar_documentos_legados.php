<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Execução permitida somente pela linha de comando.\n");
}

session_save_path(sys_get_temp_dir());
$_SERVER['REQUEST_URI'] = '/cli/migrar-documentos';
require_once dirname(__DIR__) . '/config.php';

$pdo_intra->exec(
    "UPDATE documentos_central
        SET publicado_em=COALESCE(aprovado_em, atualizado_em, criado_em, NOW()),
            aprovado_em=COALESCE(aprovado_em, publicado_em, atualizado_em, criado_em, NOW())
      WHERE status='PUBLICADO'
        AND (publicado_em IS NULL OR aprovado_em IS NULL)"
);

function migracaoMime(string $arquivo): string
{
    return (string) ((new finfo(FILEINFO_MIME_TYPE))->file($arquivo) ?: 'application/octet-stream');
}

function migracaoInserirVersao(PDO $pdo, int $documentoId, int $numero, string $path, int $usuarioId, string $origem): ?int
{
    $absoluto = dirname(__DIR__) . '/' . str_replace('\\', '/', $path);
    if (!is_file($absoluto) || filesize($absoluto) <= 0) return null;
    $nome = basename($absoluto);
    $pdo->prepare('INSERT INTO documentos_versoes
        (documento_id, numero, nome_original, arquivo_path, mime_type, tamanho_bytes, hash_sha256, criado_por, origem)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$documentoId, $numero, $nome, str_replace('\\', '/', $path), migracaoMime($absoluto), filesize($absoluto), hash_file('sha256', $absoluto), $usuarioId, $origem]);
    return (int) $pdo->lastInsertId();
}

$importadosFluxo = 0;
$importadosPasta = 0;
$ignorados = 0;

$docsLegados = $pdo_intra->query('SELECT * FROM docs_fluxo_simples ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($docsLegados as $legado) {
    $chave = hash('sha256', 'FLUXO_LEGADO:' . $legado['id']);
    $stmtExiste = $pdo_intra->prepare('SELECT id FROM documentos_central WHERE origem_chave=?');
    $stmtExiste->execute([$chave]);
    if ($stmtExiste->fetchColumn()) { $ignorados++; continue; }

    $mapaStatus = [
        'Pendente T.I' => ['EM_VALIDACAO', null, 'TI'],
        'Em Análise' => ['EM_VALIDACAO', null, 'TI'],
        'Aguardando Ajustes' => ['AJUSTES_SOLICITADOS', (int) $legado['usuario_id'], 'CRIADOR'],
        'Aprovado' => ['PUBLICADO', null, 'NENHUM'],
        'APROVADO' => ['PUBLICADO', null, 'NENHUM'],
        'Recusado' => ['REJEITADO', null, 'NENHUM'],
    ];
    [$status, $responsavel, $tipoResponsavel] = $mapaStatus[$legado['status']] ?? ['EM_VALIDACAO', null, 'TI'];

    $pdo_intra->beginTransaction();
    try {
        $pdo_intra->prepare('INSERT INTO documentos_central
            (titulo, setor, tipo, status, criador_id, responsavel_id, responsavel_tipo, aprovado_por,
             aprovado_em, publicado_em, origem, origem_chave, criado_em, atualizado_em)
            VALUES (?, ?, \'PROCESSO\', ?, ?, ?, ?, ?, ?, ?, \'FLUXO_LEGADO\', ?, ?, ?)')
            ->execute([
                $legado['titulo'],
                trim((string) ($legado['setor_origem'] ?? '')) ?: 'GERAL',
                $status,
                (int) $legado['usuario_id'],
                $responsavel,
                $tipoResponsavel,
                $legado['aprovado_por'] ?: null,
                $status === 'PUBLICADO'
                    ? ($legado['publicado_em'] ?: $legado['data_atualizacao'] ?: $legado['data_envio'])
                    : null,
                $status === 'PUBLICADO'
                    ? ($legado['publicado_em'] ?: $legado['data_atualizacao'] ?: $legado['data_envio'])
                    : null,
                $chave,
                $legado['data_envio'] ?: date('Y-m-d H:i:s'),
                $legado['data_atualizacao'] ?: $legado['data_envio'],
            ]);
        $documentoId = (int) $pdo_intra->lastInsertId();

        $stmtHistorico = $pdo_intra->prepare('SELECT * FROM docs_fluxo_historico WHERE doc_id=? ORDER BY criado_em, id');
        $stmtHistorico->execute([$legado['id']]);
        $historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);
        $arquivos = [];
        foreach ($historico as $evento) {
            if (!empty($evento['arquivo_novo'])) $arquivos[] = (string) $evento['arquivo_novo'];
        }
        $arquivos[] = (string) $legado['nome_arquivo'];
        $arquivos = array_values(array_unique(array_filter($arquivos)));

        $versaoAtualId = null;
        $numero = 0;
        foreach ($arquivos as $nomeArquivo) {
            $idVersao = migracaoInserirVersao($pdo_intra, $documentoId, ++$numero, 'uploads_fluxo/' . basename($nomeArquivo), (int) $legado['usuario_id'], 'FLUXO_LEGADO');
            if ($idVersao) $versaoAtualId = $idVersao;
        }
        if (!$versaoAtualId) throw new RuntimeException('Documento legado sem arquivo físico: #' . $legado['id']);

        $pdo_intra->prepare('UPDATE documentos_central SET versao_atual_id=?, versao_publicada_id=? WHERE id=?')
            ->execute([$versaoAtualId, $status === 'PUBLICADO' ? $versaoAtualId : null, $documentoId]);

        foreach ($historico as $evento) {
            $pdo_intra->prepare('INSERT INTO documentos_eventos
                (documento_id, usuario_id, acao, mensagem, dados_json, criado_em)
                VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$documentoId, (int) $evento['usuario_id'], 'LEGADO_' . strtoupper((string) $evento['tipo_acao']), $evento['mensagem'], $evento['dados_extras'], $evento['criado_em']]);
        }
        $pdo_intra->prepare('INSERT INTO documentos_eventos
            (documento_id, versao_id, usuario_id, acao, status_novo, mensagem)
            VALUES (?, ?, 0, \'IMPORTAR_LEGADO\', ?, \'Processo migrado do fluxo anterior.\')')
            ->execute([$documentoId, $versaoAtualId, $status]);

        if ($status === 'EM_VALIDACAO') {
            $pdo_intra->prepare("INSERT INTO documentos_tarefas (documento_id,tipo,grupo_responsavel,instrucoes) VALUES (?,'VALIDAR','TI','Validar processo migrado.')")->execute([$documentoId]);
        } elseif ($status === 'AJUSTES_SOLICITADOS') {
            $pdo_intra->prepare("INSERT INTO documentos_tarefas (documento_id,tipo,usuario_responsavel_id,instrucoes) VALUES (?,'AJUSTAR',?,'Concluir os ajustes solicitados no fluxo anterior.')")->execute([$documentoId, (int) $legado['usuario_id']]);
        }
        $pdo_intra->commit();
        $importadosFluxo++;
    } catch (Throwable $e) {
        if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
        fwrite(STDERR, 'Fluxo #' . $legado['id'] . ': ' . $e->getMessage() . PHP_EOL);
    }
}

$hashesExistentes = array_fill_keys($pdo_intra->query('SELECT hash_sha256 FROM documentos_versoes')->fetchAll(PDO::FETCH_COLUMN), true);
$raizDocs = realpath(dirname(__DIR__) . '/docs');
if ($raizDocs) {
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizDocs, FilesystemIterator::SKIP_DOTS));
    foreach ($iterador as $arquivo) {
        if (!$arquivo->isFile() || strtolower($arquivo->getFilename()) === 'index.md') continue;
        if ($arquivo->getSize() <= 0) { $ignorados++; continue; }
        $hash = hash_file('sha256', $arquivo->getPathname());
        if (isset($hashesExistentes[$hash])) { $ignorados++; continue; }

        $relativoDocs = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($raizDocs) + 1));
        $pathAplicacao = 'docs/' . $relativoDocs;
        $chave = hash('sha256', 'PASTA_LEGADA:' . $relativoDocs);
        $stmtExiste = $pdo_intra->prepare('SELECT id FROM documentos_central WHERE origem_chave=?');
        $stmtExiste->execute([$chave]);
        if ($stmtExiste->fetchColumn()) { $ignorados++; continue; }

        $partes = explode('/', $relativoDocs);
        $setor = mb_strtoupper((string) ($partes[0] ?? 'GERAL'), 'UTF-8');
        $titulo = pathinfo($arquivo->getFilename(), PATHINFO_FILENAME);
        $titulo = trim(str_replace(['_', '-'], ' ', $titulo)) ?: $arquivo->getFilename();

        $pdo_intra->beginTransaction();
        try {
            $pdo_intra->prepare("INSERT INTO documentos_central
                (titulo,setor,tipo,status,criador_id,responsavel_tipo,origem,origem_chave,aprovado_em,publicado_em)
                VALUES (?,?,'DOCUMENTO','PUBLICADO',0,'NENHUM','PASTA_LEGADA',?,NOW(),NOW())")
                ->execute([$titulo, $setor, $chave]);
            $documentoId = (int) $pdo_intra->lastInsertId();
            $versaoId = migracaoInserirVersao($pdo_intra, $documentoId, 1, $pathAplicacao, 0, 'PASTA_LEGADA');
            if (!$versaoId) throw new RuntimeException('Arquivo vazio ou ausente.');
            $pdo_intra->prepare('UPDATE documentos_central SET versao_atual_id=?,versao_publicada_id=? WHERE id=?')
                ->execute([$versaoId, $versaoId, $documentoId]);
            $pdo_intra->prepare("INSERT INTO documentos_eventos
                (documento_id,versao_id,usuario_id,acao,status_novo,mensagem)
                VALUES (?,?,0,'IMPORTAR_LEGADO','PUBLICADO','Documento importado da biblioteca de pastas.')")
                ->execute([$documentoId, $versaoId]);
            $pdo_intra->commit();
            $hashesExistentes[$hash] = true;
            $importadosPasta++;
        } catch (Throwable $e) {
            if ($pdo_intra->inTransaction()) $pdo_intra->rollBack();
            fwrite(STDERR, 'Arquivo ' . $relativoDocs . ': ' . $e->getMessage() . PHP_EOL);
        }
    }
}

echo "Fluxos importados: {$importadosFluxo}\n";
echo "Arquivos da biblioteca importados: {$importadosPasta}\n";
echo "Itens já existentes ou duplicados: {$ignorados}\n";
