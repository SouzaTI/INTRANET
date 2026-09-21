<?php
declare(strict_types=1);

const CONTRATOS_STORAGE_BASE = 'C:\\xampp\\htdocs\\intranet\\contratos';
const CONTRATOS_MAX_ANEXOS_POR_ENVIO = 10;
const CONTRATOS_MAX_BYTES_POR_ANEXO = 10 * 1024 * 1024;
const CONTRATOS_MAX_BYTES_POR_ENVIO = 50 * 1024 * 1024;

function contratosNomeBaseGrupo(string $nomeGrupo): string
{
    $nome = trim($nomeGrupo);
    $semParenteses = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $nome);
    $semParenteses = is_string($semParenteses) ? trim($semParenteses) : $nome;

    // Remove também parênteses soltos, como em "FINANCEIRO III (TESOURARIA) )".
    $semParenteses = str_replace(['(', ')'], ' ', $semParenteses);
    $semParenteses = preg_replace('/\s+/u', ' ', trim($semParenteses)) ?: '';

    return $semParenteses !== '' ? $semParenteses : 'SETOR SEM NOME';
}

function contratosPastaGrupo(string $nomeGrupo): string
{
    $nome = contratosNomeBaseGrupo($nomeGrupo);

    if (class_exists('Transliterator')) {
        $convertido = transliterator_transliterate('Any-Latin; Latin-ASCII', $nome);
        if (is_string($convertido)) {
            $nome = $convertido;
        }
    } else {
        $convertido = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        if ($convertido !== false) {
            $nome = $convertido;
        }
    }

    // FACILITIES & T.I -> FACILITIESTI; FINANCEIRO III (TESOURARIA) -> FINANCEIROIII.
    $pasta = preg_replace('/[^A-Za-z0-9]+/', '', $nome) ?: '';
    return $pasta !== '' ? $pasta : 'SetorSemNome';
}

function contratosDiretorioGrupo(string $nomeGrupo): string
{
    return rtrim(CONTRATOS_STORAGE_BASE, '\\/')
        . DIRECTORY_SEPARATOR
        . contratosPastaGrupo($nomeGrupo);
}

function contratosGarantirDiretorio(string $diretorio): void
{
    if (is_dir($diretorio)) {
        if (!is_writable($diretorio)) {
            throw new RuntimeException('A pasta do setor existe, mas o Apache não possui permissão de gravação.');
        }
        return;
    }

    if (!@mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
        throw new RuntimeException('O Apache não conseguiu criar a pasta local de contratos. Verifique as permissões em C:\\xampp\\htdocs\\intranet\\contratos.');
    }
}

function contratosResolverArquivo(string $arquivoRelativo): string
{
    $relativo = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($arquivoRelativo));
    if ($relativo === '' || str_contains($relativo, '..') || str_starts_with($relativo, DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Caminho de anexo inválido.', 404);
    }

    $baseReal = realpath(CONTRATOS_STORAGE_BASE);
    $arquivoReal = realpath(rtrim(CONTRATOS_STORAGE_BASE, '\\/') . DIRECTORY_SEPARATOR . $relativo);

    if ($baseReal === false || $arquivoReal === false || !is_file($arquivoReal)) {
        throw new RuntimeException('Arquivo não encontrado.', 404);
    }

    $prefixo = rtrim($baseReal, '\\/') . DIRECTORY_SEPARATOR;
    if (strncasecmp($arquivoReal, $prefixo, strlen($prefixo)) !== 0) {
        throw new RuntimeException('Caminho de anexo inválido.', 404);
    }

    return $arquivoReal;
}

/**
 * Normaliza o formato de $_FILES para um ou vários anexos e valida o lote
 * antes que qualquer arquivo seja movido para o armazenamento definitivo.
 */
function contratosValidarUploads(?array $campoArquivos): array
{
    if (!$campoArquivos || !isset($campoArquivos['name'])) {
        return [];
    }

    $nomes = is_array($campoArquivos['name'])
        ? $campoArquivos['name']
        : [$campoArquivos['name']];
    $temporarios = is_array($campoArquivos['tmp_name'] ?? null)
        ? $campoArquivos['tmp_name']
        : [$campoArquivos['tmp_name'] ?? ''];
    $erros = is_array($campoArquivos['error'] ?? null)
        ? $campoArquivos['error']
        : [$campoArquivos['error'] ?? UPLOAD_ERR_NO_FILE];
    $tamanhos = is_array($campoArquivos['size'] ?? null)
        ? $campoArquivos['size']
        : [$campoArquivos['size'] ?? 0];

    $arquivos = [];
    $totalBytes = 0;
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($nomes as $indice => $nomeInformado) {
        $erro = (int) ($erros[$indice] ?? UPLOAD_ERR_NO_FILE);
        if ($erro === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($erro !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Um dos anexos não pôde ser recebido pelo servidor.');
        }

        $tmp = (string) ($temporarios[$indice] ?? '');
        $tamanho = (int) ($tamanhos[$indice] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $tamanho <= 0) {
            throw new RuntimeException('Um dos anexos enviados é inválido.');
        }
        if ($tamanho > CONTRATOS_MAX_BYTES_POR_ANEXO) {
            throw new RuntimeException('Cada anexo deve ter no máximo 10 MB.');
        }
        if ($finfo->file($tmp) !== 'application/pdf' || file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('Somente arquivos PDF válidos são permitidos.');
        }

        $totalBytes += $tamanho;
        $nomeOriginal = trim(basename(str_replace('\\', '/', (string) $nomeInformado)));
        $arquivos[] = [
            'tmp_name' => $tmp,
            'size' => $tamanho,
            'name' => $nomeOriginal !== '' ? mb_substr($nomeOriginal, 0, 255) : 'documento.pdf',
            'mime_type' => 'application/pdf',
        ];
    }

    if (count($arquivos) > CONTRATOS_MAX_ANEXOS_POR_ENVIO) {
        throw new RuntimeException('Envie no máximo 10 anexos por vez.');
    }
    if ($totalBytes > CONTRATOS_MAX_BYTES_POR_ENVIO) {
        throw new RuntimeException('O conjunto de anexos deve ter no máximo 50 MB.');
    }

    return $arquivos;
}

function contratosArmazenarUploads(array $arquivos, string $setor): array
{
    if (!$arquivos) {
        return [];
    }

    $pastaGrupo = contratosPastaGrupo($setor);
    $diretorio = contratosDiretorioGrupo($setor);
    contratosGarantirDiretorio($diretorio);

    $armazenados = [];
    try {
        foreach ($arquivos as $arquivo) {
            $nomeDisco = 'contrato_' . bin2hex(random_bytes(16)) . '.pdf';
            $destino = $diretorio . DIRECTORY_SEPARATOR . $nomeDisco;
            if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
                throw new RuntimeException('O Apache não conseguiu gravar um dos anexos na pasta de contratos.');
            }
            $armazenados[] = $arquivo + [
                'arquivo_path' => $pastaGrupo . '/' . $nomeDisco,
                'caminho_absoluto' => $destino,
            ];
        }
    } catch (Throwable $e) {
        foreach ($armazenados as $armazenado) {
            if (is_file($armazenado['caminho_absoluto'])) {
                @unlink($armazenado['caminho_absoluto']);
            }
        }
        throw $e;
    }

    return $armazenados;
}
