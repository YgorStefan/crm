-- ============================================================
-- Migration 004: Phase 6 — Módulo de Contatos Frios
-- Executar no banco de dados do CRM Apollo (Hostinger hPanel)
-- ============================================================

-- Tabela completamente isolada de `clients` — nenhum FK externo (D-16)
CREATE TABLE IF NOT EXISTS cold_contacts (
    id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    phone             VARCHAR(20)     NOT NULL        COMMENT 'Celular do contato (coluna A do CSV)',
    name              VARCHAR(150)    NOT NULL        COMMENT 'Nome do contato (coluna B do CSV)',
    tipo_lista        VARCHAR(100)    NOT NULL        COMMENT 'Nome da lista digitado no import — campo obrigatório (D-01, D-02)',
    telefone_enviado  VARCHAR(4)      NULL DEFAULT NULL
                                                      COMMENT 'Últimos 4 dígitos do celular do vendedor que enviou (D-06, D-07)',
    data_mensagem     DATE            NULL DEFAULT NULL
                                                      COMMENT 'Data em que a mensagem foi enviada ao contato (D-08)',
    imported_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                      COMMENT 'Data/hora da importação — usada para agrupar nos Cards Mensais (D-03)',
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para agrupar por mês de importação nos Cards Mensais
CREATE INDEX idx_cold_imported_at ON cold_contacts(imported_at);

-- Índice para filtro por celular na modal (D-11)
CREATE INDEX idx_cold_phone ON cold_contacts(phone);
