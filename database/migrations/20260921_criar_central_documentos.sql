CREATE TABLE IF NOT EXISTS documentos_central (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    setor VARCHAR(100) NOT NULL,
    tipo VARCHAR(30) NOT NULL DEFAULT 'PROCESSO',
    status VARCHAR(40) NOT NULL DEFAULT 'RASCUNHO',
    criador_id INT NOT NULL,
    responsavel_id INT NULL,
    responsavel_tipo VARCHAR(30) NOT NULL DEFAULT 'CRIADOR',
    versao_atual_id BIGINT UNSIGNED NULL,
    versao_publicada_id BIGINT UNSIGNED NULL,
    exige_assinatura TINYINT(1) NOT NULL DEFAULT 0,
    assinatura_envelope_id INT NULL,
    aprovado_por INT NULL,
    aprovado_em DATETIME NULL,
    publicado_em DATETIME NULL,
    origem VARCHAR(30) NOT NULL DEFAULT 'NOVO',
    origem_chave CHAR(64) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documentos_origem (origem_chave),
    KEY idx_documentos_biblioteca (status, setor, publicado_em),
    KEY idx_documentos_criador (criador_id, atualizado_em),
    KEY idx_documentos_responsavel (responsavel_id, responsavel_tipo, status),
    KEY idx_documentos_envelope (assinatura_envelope_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE documentos_central
   SET publicado_em = COALESCE(aprovado_em, atualizado_em, criado_em, NOW()),
       aprovado_em = COALESCE(aprovado_em, publicado_em, atualizado_em, criado_em, NOW())
 WHERE status = 'PUBLICADO'
   AND (publicado_em IS NULL OR aprovado_em IS NULL);

CREATE TABLE IF NOT EXISTS documentos_versoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    documento_id BIGINT UNSIGNED NOT NULL,
    numero INT UNSIGNED NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    arquivo_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    tamanho_bytes BIGINT UNSIGNED NOT NULL,
    hash_sha256 CHAR(64) NOT NULL,
    criado_por INT NOT NULL,
    origem VARCHAR(30) NOT NULL DEFAULT 'UPLOAD',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documentos_versao (documento_id, numero),
    UNIQUE KEY uq_documentos_arquivo (arquivo_path),
    CONSTRAINT fk_documentos_versoes_documento
        FOREIGN KEY (documento_id) REFERENCES documentos_central (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS documentos_tarefas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    documento_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
    usuario_responsavel_id INT NULL,
    grupo_responsavel VARCHAR(100) NULL,
    instrucoes TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    concluido_em DATETIME NULL,
    concluido_por INT NULL,
    PRIMARY KEY (id),
    KEY idx_documentos_tarefas_usuario (usuario_responsavel_id, status, criado_em),
    KEY idx_documentos_tarefas_grupo (grupo_responsavel, status, criado_em),
    KEY idx_documentos_tarefas_documento (documento_id, status),
    CONSTRAINT fk_documentos_tarefas_documento
        FOREIGN KEY (documento_id) REFERENCES documentos_central (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS documentos_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    documento_id BIGINT UNSIGNED NOT NULL,
    versao_id BIGINT UNSIGNED NULL,
    usuario_id INT NOT NULL,
    acao VARCHAR(40) NOT NULL,
    status_anterior VARCHAR(40) NULL,
    status_novo VARCHAR(40) NULL,
    mensagem TEXT NULL,
    dados_json LONGTEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documentos_eventos_documento (documento_id, criado_em),
    CONSTRAINT fk_documentos_eventos_documento
        FOREIGN KEY (documento_id) REFERENCES documentos_central (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_documentos_eventos_versao
        FOREIGN KEY (versao_id) REFERENCES documentos_versoes (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
