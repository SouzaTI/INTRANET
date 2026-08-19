<?php
declare(strict_types=1);

const CONTRATOS_STORAGE_BASE = 'C:\\xampp\\htdocs\\intranet\\contratos';

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
