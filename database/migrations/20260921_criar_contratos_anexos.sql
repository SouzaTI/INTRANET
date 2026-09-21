CREATE TABLE IF NOT EXISTS contratos_anexos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contrato_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    arquivo_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    tamanho_bytes BIGINT UNSIGNED NOT NULL,
    enviado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contratos_anexos_path (arquivo_path),
    KEY idx_contratos_anexos_contrato (contrato_id, criado_em),
    CONSTRAINT fk_contratos_anexos_contrato
        FOREIGN KEY (contrato_id) REFERENCES contratos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
