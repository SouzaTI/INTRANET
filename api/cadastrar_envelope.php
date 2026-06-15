<?php
// api/cadastrar_envelope.php

require_once '../config.php';

// ── Guarda de sessão ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

// ── Constantes ────────────────────────────────────────────────────────────
const UPLOAD_DIR  = __DIR__ . '/../uploads/assinaturas/';
const MAX_SIZE    = 10_485_760; // 10 MB
const PIN_PADRAO  = '1234';
const REDIRECT_OK = '../minhas_assinaturas.php?sucesso=1';
const REDIRECT_ER = '../criar_envelope.php?erro=';

// ── Helpers ───────────────────────────────────────────────────────────────
function redirecionar(string $url): never {
    header('Location: ' . $url);
    exit;
}

function abortar(string $motivo, ?string $arquivoParaRemover = null): never {
    if ($arquivoParaRemover && file_exists($arquivoParaRemover)) {
        unlink($arquivoParaRemover);
    }
    redirecionar(REDIRECT_ER . urlencode($motivo));
}

// ── 1. Validação dos campos de texto ─────────────────────────────────────
$titulo     = trim($_POST['titulo']     ?? '');
$tipo_fluxo = trim($_POST['tipo_fluxo'] ?? '');
$assinantes = array_map('intval', (array) ($_POST['assinantes'] ?? []));
$ordens     = array_map('intval', (array) ($_POST['ordem']      ?? []));

if ($titulo === '')                                        abortar('Título obrigatório.');
if (mb_strlen($titulo) > 255)                             abortar('Título excede 255 caracteres.');
if (!in_array($tipo_fluxo, ['paralelo','sequencial'], true)) abortar('Tipo de fluxo inválido.');

$assinantes = array_filter($assinantes); // remove zeros
if (empty($assinantes))                                   abortar('Informe ao menos um assinante.');
if (count($assinantes) !== count(array_unique($assinantes))) abortar('Assinantes duplicados.');
if ($tipo_fluxo === 'sequencial' && count($ordens) !== count($assinantes))
                                                          abortar('Ordem deve ser definida para todos os assinantes.');

// ── 2. Validação do arquivo ───────────────────────────────────────────────
$arquivo = $_FILES['pdf'] ?? null;

if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $erros = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulário.',
        UPLOAD_ERR_PARTIAL    => 'Upload incompleto. Tente novamente.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo temporário.',
        UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão do servidor.',
    ];
    abortar($erros[$arquivo['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Erro desconhecido no upload.');
}

if ($arquivo['size'] > MAX_SIZE) {
    abortar('O arquivo excede o limite de 10 MB.');
}

// MIME real — não confia em $_FILES['type']
$finfo     = new finfo(FILEINFO_MIME_TYPE);
$mime_real = $finfo->file($arquivo['tmp_name']);
if ($mime_real !== 'application/pdf') {
    abortar('Apenas arquivos PDF são aceitos.');
}

// Hash do conteúdo original (antes de mover)
$arquivo_hash = hash_file('sha256', $arquivo['tmp_name']);

// ── 3. Prepara diretório e move o arquivo ────────────────────────────────
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0750, true);
    file_put_contents(UPLOAD_DIR . '.htaccess', "Deny from all\n");
}

$uuid = sprintf('%s-%s-%s-%s-%s',
    bin2hex(random_bytes(4)), bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)), bin2hex(random_bytes(2)),
    bin2hex(random_bytes(6))
);
$nome_disco    = $uuid . '.pdf';
$destino_disco = UPLOAD_DIR . $nome_disco;
$path_relativo = 'uploads/assinaturas/' . $nome_disco;

if (!move_uploaded_file($arquivo['tmp_name'], $destino_disco)) {
    abortar('Falha ao mover o arquivo para o diretório seguro.');
}

// ── 4–7. Transação atômica ────────────────────────────────────────────────
$pdo_intra->beginTransaction();
try {
    // ── 5. Insere o envelope ─────────────────────────────────────────────
    $stmt_env = $pdo_intra->prepare("
        INSERT INTO sistemas_assinaturas
            (titulo, arquivo_path, arquivo_hash, tipo_fluxo, criado_por, status)
        VALUES
            (:titulo, :path, :hash, :tipo_fluxo, :criado_por, 'aguardando')
    ");
    $stmt_env->execute([
        ':titulo'      => $titulo,
        ':path'        => $path_relativo,
        ':hash'        => $arquivo_hash,
        ':tipo_fluxo'  => $tipo_fluxo,
        ':criado_por'  => (int) $_SESSION['user_id'],
    ]);
    $envelope_id = (int) $pdo_intra->lastInsertId();

    // ── 6. Insere assinantes ─────────────────────────────────────────────
    $stmt_flu = $pdo_intra->prepare("
        INSERT INTO assinaturas_fluxo
            (fk_assinatura, glpi_user_id, ordem, pin_hash, pin_salt, status)
        VALUES
            (:fk, :uid, :ordem, :pin_hash, :salt, 'pendente')
    ");

    $assinantes = array_values($assinantes); // reindexação após array_filter
    foreach ($assinantes as $idx => $glpi_user_id) {
        $salt     = bin2hex(random_bytes(16));
        $pin_hash = hash('sha256', PIN_PADRAO . $salt);

        // Paralelo → ordem fixa 1; Sequencial → usa o valor enviado
        $ordem = ($tipo_fluxo === 'sequencial')
            ? max(1, (int) ($ordens[$idx] ?? ($idx + 1)))
            : 1;

        $stmt_flu->execute([
            ':fk'       => $envelope_id,
            ':uid'      => $glpi_user_id,
            ':ordem'    => $ordem,
            ':pin_hash' => $pin_hash,
            ':salt'     => $salt,
        ]);
    }

    $pdo_intra->commit();

    // ── Log e redirecionamento ────────────────────────────────────────────
    registrarLog(
        $pdo_intra,
        'CRIAR ENVELOPE',
        "Envelope #{$envelope_id} — \"{$titulo}\" criado com " . count($assinantes) . " assinante(s). Fluxo: {$tipo_fluxo}."
    );

    redirecionar(REDIRECT_OK);

} catch (Throwable $e) {
    $pdo_intra->rollBack();
    // Remove o PDF do disco para não deixar órfão
    abortar('Falha interna ao salvar. Tente novamente.', $destino_disco);
}