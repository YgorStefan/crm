-- ============================================================
-- Migration 003: Phase 3 — Controle Financeiro (Pagamentos)
-- Executar no banco de dados do CRM Apollo (Hostinger hPanel)
-- ============================================================

-- Adiciona campo paid_at em client_sales (PAG-01, PAG-02, PAG-03)
-- NULL = não pago no ciclo vigente. Timestamp da última confirmação de pagamento.
-- O status "Não" é calculado em PHP, nunca armazenado explicitamente.
ALTER TABLE client_sales
    ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL
    COMMENT 'Timestamp da última confirmação de pagamento desta cota. NULL = não pago.'
    AFTER credito_contratado;
