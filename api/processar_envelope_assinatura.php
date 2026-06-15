<?php
// api/processar_envelope_assinatura.php

require_once '../config.php';

// -----------------------------------------------------------------
// Guarda de sessão/autenticação (padrão da intranet)
// -----------------------------------------------------------------
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'msg' => 'Acesso negado.']));
}

header('Content-Type: application/json');

// -----------------------------------------------------------------
// Classe principal
// -----------------------------------------------------------------
class EnvelopeAssinatura
{
    private PDO    $pdo;
    private string $uploadDir;
    private const  PIN_PADRAO    = '1234';
    private const  MAX_FILE_SIZE = 10_485_760; // 10 MB
    private const  MIME_ALLOWED  = ['application/pdf'];

    public function __construct(PDO $pdo, string $uploadDir)
    {
        $this->pdo       = $pdo;
        $this->uploadDir = rtrim($uploadDir, '/');
    }

    // -----------------------------------------------------------------
    // Ponto de entrada público
    // -----------------------------------------------------------------
    public function criar(array $post, array $file): array
    {
        // 1. Validações básicas de input
        $titulo     = trim($post['titulo'] ?? '');
        $tipo_fluxo = $post['tipo_fluxo'] ?? '';
        $assinantes = array_filter(array_map('intval', (array)($post['assinantes'] ?? [])));

        if ($titulo === '')                              return $this->erro('Título obrigatório.');
        if (!in_array($tipo_fluxo, ['paralelo','sequencial'], true))
                                                         return $this->erro('Tipo de fluxo inválido.');
        if (empty($assinantes))                          return $this->erro('Informe ao menos um assinante.');

        // 2. Validação e processamento do arquivo
        [$arquivo_path, $arquivo_hash] = $this->processarPDF($file);

        // 3. Transação atômica
        $this->pdo->beginTransaction();
        try {
            $envelope_id = $this->inserirEnvelope($titulo, $arquivo_path, $arquivo_hash, $tipo_fluxo);
            $this->inserirAssinantes($envelope_id, $assinantes, $tipo_fluxo);

            $this->pdo->commit();

            registrarLog($this->pdo, 'CRIAR ENVELOPE', "Envelope #{$envelope_id} — '{$titulo}' criado com " . count($assinantes) . " assinante(s).");

            return ['ok' => true, 'envelope_id' => $envelope_id, 'msg' => 'Envelope criado com sucesso.'];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            // Remove o PDF salvo em disco se a transação falhar
            if (!empty($arquivo_path) && file_exists($this->uploadDir . '/' . basename($arquivo_path))) {
                unlink($this->uploadDir . '/' . basename($arquivo_path));
            }
            // Em produção: logar $e->getMessage() em arquivo; não expor ao cliente
            return $this->erro('Falha interna. Operação revertida.');
        }
    }

    // -----------------------------------------------------------------
    // Valida, move e retorna [path_relativo, sha256]
    // -----------------------------------------------------------------
    private function processarPDF(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erro no upload do arquivo.');
        }
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Arquivo excede 10 MB.');
        }

        // Verifica MIME real (não confia no $_FILES['type'])
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::MIME_ALLOWED, true)) {
            throw new \RuntimeException('Apenas PDFs são aceitos.');
        }

        // Calcula hash ANTES de mover (arquivo ainda íntegro no tmp)
        $arquivo_hash = hash_file('sha256', $file['tmp_name']);

        // Nome seguro: UUID v4 simples via random_bytes
        $uuid      = sprintf('%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)), bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)), bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
        $nome_final   = $uuid . '.pdf';
        $destino      = $this->uploadDir . '/' . $nome_final;
        $path_relativo = 'uploads/assinaturas/' . $nome_final;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            throw new \RuntimeException('Falha ao mover o arquivo para o diretório seguro.');
        }

        return [$path_relativo, $arquivo_hash];
    }

    // -----------------------------------------------------------------
    // INSERT em sistemas_assinaturas → retorna ID
    // -----------------------------------------------------------------
    private function inserirEnvelope(
        string $titulo, string $path, string $hash, string $tipo_fluxo
    ): int {
        $criado_por = (int) ($_SESSION['user_id'] ?? 0);

        $stmt = $this->pdo->prepare("
            INSERT INTO sistemas_assinaturas
                (titulo, arquivo_path, arquivo_hash, tipo_fluxo, criado_por, status)
            VALUES
                (:titulo, :path, :hash, :tipo_fluxo, :criado_por, 'aguardando')
        ");
        $stmt->execute([
            ':titulo'      => $titulo,
            ':path'        => $path,
            ':hash'        => $hash,
            ':tipo_fluxo'  => $tipo_fluxo,
            ':criado_por'  => $criado_por,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // -----------------------------------------------------------------
    // INSERT em assinaturas_fluxo para cada assinante
    // -----------------------------------------------------------------
    private function inserirAssinantes(int $envelope_id, array $assinantes, string $tipo_fluxo): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO assinaturas_fluxo
                (fk_assinatura, glpi_user_id, ordem, pin_hash, pin_salt, status)
            VALUES
                (:fk, :uid, :ordem, :pin_hash, :salt, 'pendente')
        ");

        foreach (array_values($assinantes) as $idx => $glpi_user_id) {
            $salt     = bin2hex(random_bytes(16));            // 32 chars, único por registro
            $pin_hash = hash('sha256', self::PIN_PADRAO . $salt); // PIN padrão '1234' — assinante deve alterar

            // Em fluxo paralelo a ordem não tem relevância funcional; mantemos 1 para consistência
            $ordem = ($tipo_fluxo === 'sequencial') ? ($idx + 1) : 1;

            $stmt->execute([
                ':fk'       => $envelope_id,
                ':uid'      => $glpi_user_id,
                ':ordem'    => $ordem,
                ':pin_hash' => $pin_hash,
                ':salt'     => $salt,
            ]);
        }
    }

    // -----------------------------------------------------------------
    private function erro(string $msg): array
    {
        return ['ok' => false, 'msg' => $msg];
    }
}

// -----------------------------------------------------------------
// Instância e execução — diretório fora do webroot é o ideal;
// ajuste o caminho conforme seu servidor
// -----------------------------------------------------------------
$uploadDir = dirname(__DIR__) . '/uploads/assinaturas';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0750, true);
    // Bloqueia acesso direto via HTTP ao diretório de PDFs
    file_put_contents($uploadDir . '/.htaccess', "Deny from all\n");
}

$envelope = new EnvelopeAssinatura($pdo_intra, $uploadDir);
echo json_encode($envelope->criar($_POST, $_FILES['pdf'] ?? []));