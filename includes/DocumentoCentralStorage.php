<?php
declare(strict_types=1);

const DOCUMENTOS_STORAGE_RELATIVO = 'uploads/documentos';
const DOCUMENTOS_MAX_BYTES = 50 * 1024 * 1024;

function documentoCentralValidarUpload(?array $arquivo): array
{
    if (!$arquivo || (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Selecione o arquivo do documento.');
    }
    if ((int) $arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('O arquivo não pôde ser recebido pelo servidor.');
    }

    $tmp = (string) ($arquivo['tmp_name'] ?? '');
    $tamanho = (int) ($arquivo['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp) || $tamanho <= 0) {
        throw new RuntimeException('O arquivo enviado é inválido.');
    }
    if ($tamanho > DOCUMENTOS_MAX_BYTES) {
        throw new RuntimeException('O arquivo deve ter no máximo 50 MB.');
    }

    $nomeOriginal = trim(basename(str_replace('\\', '/', (string) ($arquivo['name'] ?? ''))));
    $extensaoOriginal = mb_strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION), 'UTF-8');
    $permitidos = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'video/mp4' => 'mp4',
    ];
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensao = $permitidos[$mime] ?? null;
    if (in_array($mime, ['text/plain', 'text/markdown'], true)
        && in_array($extensaoOriginal, ['md', 'txt'], true)) {
        $extensao = $extensaoOriginal;
    }
    if ($extensao === null) {
        throw new RuntimeException('Formato não permitido. Use PDF, DOCX, XLSX, Markdown, TXT, JPG, PNG ou MP4.');
    }

    return [
        'tmp_name' => $tmp,
        'tamanho_bytes' => $tamanho,
        'mime_type' => $mime,
        'extensao' => $extensao,
        'nome_original' => $nomeOriginal !== '' ? mb_substr($nomeOriginal, 0, 255) : 'documento.' . $extensao,
    ];
}

function documentoCentralArmazenar(array $arquivo, int $documentoId, int $numeroVersao): array
{
    $diretorioRelativo = DOCUMENTOS_STORAGE_RELATIVO . '/' . $documentoId;
    $diretorioAbsoluto = dirname(__DIR__) . '/' . $diretorioRelativo;
    if (!is_dir($diretorioAbsoluto) && !mkdir($diretorioAbsoluto, 0750, true) && !is_dir($diretorioAbsoluto)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento de documentos.');
    }

    $nomeDisco = sprintf('v%d_%s.%s', $numeroVersao, bin2hex(random_bytes(16)), $arquivo['extensao']);
    $destino = $diretorioAbsoluto . '/' . $nomeDisco;
    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível armazenar o documento enviado.');
    }

    return $arquivo + [
        'arquivo_path' => $diretorioRelativo . '/' . $nomeDisco,
        'caminho_absoluto' => $destino,
        'hash_sha256' => hash_file('sha256', $destino),
    ];
}

function documentoCentralResolverArquivo(string $arquivoPath): string
{
    $relativo = str_replace('\\', '/', trim($arquivoPath));
    if ($relativo === '' || str_contains($relativo, '..') || str_starts_with($relativo, '/')) {
        throw new RuntimeException('Caminho de documento inválido.', 404);
    }

    $raiz = realpath(dirname(__DIR__));
    $permitidas = [
        realpath(dirname(__DIR__) . '/uploads/documentos'),
        realpath(dirname(__DIR__) . '/uploads_fluxo'),
        realpath(dirname(__DIR__) . '/docs'),
        realpath(dirname(__DIR__) . '/uploads/assinaturas'),
    ];
    $real = realpath(dirname(__DIR__) . '/' . $relativo);
    if (!$raiz || !$real || !is_file($real)) {
        throw new RuntimeException('Arquivo não encontrado.', 404);
    }

    foreach (array_filter($permitidas) as $base) {
        $prefixo = rtrim((string) $base, '\\/') . DIRECTORY_SEPARATOR;
        if ($real === $base || strncasecmp($real, $prefixo, strlen($prefixo)) === 0) {
            return $real;
        }
    }

    throw new RuntimeException('Caminho de documento inválido.', 403);
}
