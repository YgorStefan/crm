-- ============================================================
-- Migration 002: Phase 2 — Clientes Corporativos e Consórcio
-- Executar no banco de dados do CRM Apollo (Hostinger hPanel)
-- ============================================================

-- 1. Adiciona campos de data de nascimento e indicação em clients (CLI-01, CLI-06)
ALTER TABLE clients
    ADD COLUMN birth_date DATE NULL DEFAULT NULL
    COMMENT 'Data de nascimento do cliente — formato YYYY-MM-DD no banco'
    AFTER notes;

ALTER TABLE clients
    ADD COLUMN referido_por VARCHAR(150) NULL DEFAULT NULL
    COMMENT 'Nome de quem indicou o cliente (preenchido quando source=Indicação)'
    AFTER birth_date;

-- 2. Cria tabela de multi-cotas por cliente (CLI-07, CLI-08)
-- Cada linha é uma venda (cota) de consórcio vinculada a um cliente.
-- Os dados pessoais do cliente ficam em clients; cada cota fica aqui.
CREATE TABLE IF NOT EXISTS client_sales (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    client_id           INT UNSIGNED    NOT NULL,
    grupo               VARCHAR(50)     NULL         COMMENT 'Número/nome do grupo do consórcio',
    cota                VARCHAR(50)     NULL         COMMENT 'Número/nome da cota',
    tipo                ENUM('Imóvel','Veículo','Serviço')
                                        NOT NULL     COMMENT 'Tipo do consórcio',
    credito_contratado  DECIMAL(12,2)   NULL DEFAULT 0.00
                                                     COMMENT 'Valor do crédito contratado em R$',
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

    -- Se o cliente for deletado, todas as suas cotas são removidas também
    CONSTRAINT fk_sale_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para acelerar busca de cotas por cliente
CREATE INDEX idx_sales_client ON client_sales(client_id);

-- Índice para o filtro por tipo (CLI-09)
CREATE INDEX idx_sales_tipo ON client_sales(tipo);
