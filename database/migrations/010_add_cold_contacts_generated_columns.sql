-- Migração 010: adiciona colunas faltantes em cold_contacts
-- Causa do erro 500: código referencia imported_year_month e archived_at
-- mas essas colunas não existiam no banco de produção.
--
-- Execute no phpMyAdmin ou via linha de comando MySQL.
-- Idempotente: ignora erro se a coluna já existir.

-- 1. archived_at
ALTER TABLE `cold_contacts`
    ADD COLUMN IF NOT EXISTS `archived_at` DATETIME NULL DEFAULT NULL;

-- 2. imported_year_month (coluna gerada a partir de imported_at)
-- DATE_FORMAT() não é permitido em GENERATED ALWAYS AS no MariaDB 11.x
ALTER TABLE `cold_contacts`
    ADD COLUMN IF NOT EXISTS `imported_year_month`
        CHAR(7) GENERATED ALWAYS AS (CONCAT(YEAR(`imported_at`), '-', LPAD(MONTH(`imported_at`), 2, '0'))) STORED;

-- 3. Índice para performance nas listagens mensais
CREATE INDEX IF NOT EXISTS `idx_cc_year_month`
    ON `cold_contacts` (`imported_year_month`);
