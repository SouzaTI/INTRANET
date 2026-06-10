<?php
// includes/validar_upload.php

function validarArquivoFluxo(array $arquivo): array {
    // 1. Erros nativos do PHP (Tamanho excedido no php.ini, etc)[cite: 11]
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros_php = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto. Tente novamente.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        ];
        return ['ok' => false, 'erro' => $erros_php[$arquivo['error']] ?? 'Erro desconhecido no upload.'];
    }

    // 2. Mapeamento de MIME types reais de forma segura[cite: 7, 8]
    $tipos_permitidos = [
        'application/pdf'  => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'video/mp4'        => 'mp4',
        'video/quicktime'  => 'mov',
        'video/x-msvideo'  => 'avi',
    ];

    $tamanho_max = [
        'pdf'  => 50  * 1024 * 1024,  // 50 MB[cite: 9]
        'jpg'  => 10  * 1024 * 1024,  // 10 MB[cite: 9]
        'png'  => 10  * 1024 * 1024,  // 10 MB[cite: 9]
        'mp4'  => 500 * 1024 * 1024,  // 500 MB (Ideal para os vídeos da galera!)[cite: 9]
        'mov'  => 500 * 1024 * 1024,  // 500 MB[cite: 9]
        'avi'  => 500 * 1024 * 1024,  // 500 MB[cite: 10]
    ];

    // Detecta o tipo real do arquivo lendo os bytes internos dele (ignora o nome do cliente)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_real = $finfo->file($arquivo['tmp_name']);

    if (!array_key_exists($mime_real, $tipos_permitidos)) {
        return ['ok' => false, 'erro' => "Tipo de arquivo não permitido: $mime_real"];
    }

    $extensao = $tipos_permitidos[$mime_real];

    // 3. Validação de tamanho por tipo de extensão[cite: 16]
    if ($arquivo['size'] > $tamanho_max[$extensao]) {
        $limite_mb = $tamanho_max[$extensao] / 1024 / 1024;
        return ['ok' => false, 'erro' => "Arquivo excede o limite de {$limite_mb}MB para o tipo .$extensao"];
    }

    // 4. Token aleatório seguro para evitar substituição de nomes antigos[cite: 18]
    $nome_seguro = bin2hex(random_bytes(16)) . '.' . $extensao;

    return [
        'ok'        => true,
        'nome'      => $nome_seguro,
        'extensao'  => $extensao,
        'eh_video'  => in_array($extensao, ['mp4', 'mov', 'avi']),
    ];
}