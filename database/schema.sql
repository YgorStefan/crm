-- ============================================================
-- CRM Empresarial — Schema Completo do Banco de Dados
-- Versão: 1.0 Final (todas as fases consolidadas)
-- Compatível com MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 (suporta emojis e caracteres especiais)
-- ============================================================
--
-- INSTRUÇÕES DE IMPORTAÇÃO:
--   Hospedagem compartilhada (Hostinger, cPanel, etc.):
--     1. Crie o banco de dados pelo painel de controle
--     2. Importe ESTE arquivo com o banco já selecionado
--     3. As linhas CREATE DATABASE / USE foram omitidas propositalmente
--
--   Ambiente local (XAMPP, Laragon, Docker):
--     1. Crie o banco:  CREATE DATABASE crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--     2. Importe:       mysql -u root -p crm < schema.sql
--
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '-03:00';

-- ============================================================
-- TABELA: tenants
-- Organizações multi-tenant. Cada usuário pertence a um tenant.
-- ============================================================
CREATE TABLE IF NOT EXISTS tenants (
    id                  INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    name                VARCHAR(150)        NOT NULL,
    slug                VARCHAR(80)         NOT NULL COMMENT 'Identificador estável por tenant (único)',
    is_system_tenant    TINYINT(1)          NOT NULL DEFAULT 0 COMMENT '1 = tenant da instalação com poderes de plataforma',
    payment_cutoff_day  TINYINT UNSIGNED    NOT NULL DEFAULT 20 COMMENT 'Dia do mês (1-28) para início do ciclo de pagamento (FRAG-04)',
    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenant padrão para instalações novas e realocação de dados legados
INSERT IGNORE INTO tenants (id, name, slug, is_system_tenant) VALUES
  (1, 'Organização Padrão', 'default', 1);

-- ============================================================
-- TABELA: users
-- Usuários do sistema com controle de acesso por papel (role).
--
-- Roles:
--   admin  → acesso total (configurações, relatórios, usuários)
--   seller → gerencia seus próprios clientes e tarefas
--   viewer → somente leitura
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)        NOT NULL,
    email         VARCHAR(150)        NOT NULL,
    -- Senha NUNCA armazenada em texto puro.
    -- PHP usa: password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12])
    -- O hash resultante tem sempre 60 caracteres.
    password_hash VARCHAR(255)        NOT NULL,
    role          ENUM('admin','seller','viewer')
                                      NOT NULL DEFAULT 'seller',
    avatar        VARCHAR(255)        NULL     COMMENT 'Caminho relativo ao /public/uploads/',
    is_active            TINYINT(1)          NOT NULL DEFAULT 1 COMMENT '0 = usuário desativado (soft-delete)',
    password_must_change TINYINT(1)          NOT NULL DEFAULT 0 COMMENT '1 = forçar troca de senha no próximo login',
    is_system_admin      TINYINT(1)          NOT NULL DEFAULT 0 COMMENT '1 = admin da plataforma (acesso a /admin/tenants)',
    tenant_id            INT UNSIGNED        NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant',
    created_at           TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: pipeline_stages
-- Etapas (colunas) do funil de vendas no board Kanban.
-- O campo `position` define a ordem da esquerda para a direita.
-- ============================================================
CREATE TABLE IF NOT EXISTS pipeline_stages (
    id           INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    name         VARCHAR(80)         NOT NULL,
    color        VARCHAR(7)          NOT NULL DEFAULT '#6366f1' COMMENT 'Cor hexadecimal do cabeçalho da coluna',
    position     TINYINT UNSIGNED    NOT NULL DEFAULT 0         COMMENT 'Ordenação crescente = esquerda para direita',
    tenant_id    INT UNSIGNED        NOT NULL DEFAULT 1         COMMENT 'Isolamento multi-tenant',
    is_won_stage TINYINT(1)          NOT NULL DEFAULT 0         COMMENT '1 = etapa de venda fechada (FRAG-03)',
    created_at   TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etapas padrão do funil (o admin pode adicionar/remover depois)
INSERT INTO pipeline_stages (name, color, position, tenant_id) VALUES
  ('Prospecção',       '#6366f1', 1, 1),
  ('Qualificação',     '#f59e0b', 2, 1),
  ('Proposta',         '#3b82f6', 3, 1),
  ('Negociação',       '#8b5cf6', 4, 1),
  ('Fechado - Ganho',  '#10b981', 5, 1),
  ('Fechado - Perdido','#ef4444', 6, 1);

-- ============================================================
-- TABELA: clients
-- Cadastro completo de clientes/leads para o relacionamento
-- comercial. Inclui dados pessoais, endereço, funil e origem.
-- ============================================================
CREATE TABLE IF NOT EXISTS clients (
    id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name              VARCHAR(150)    NOT NULL,
    email             VARCHAR(150)    NULL     COMMENT 'E-mail único por cliente (pode ser NULL)',
    phone             VARCHAR(20)     NULL,
    company           VARCHAR(150)    NULL,
    cnpj_cpf          VARCHAR(20)     NULL     COMMENT 'CPF (11 dígitos) ou CNPJ (14 dígitos) sem máscara',
    address           VARCHAR(255)    NULL,
    city              VARCHAR(100)    NULL,
    state             CHAR(2)         NULL     COMMENT 'UF em maiúsculas: SP, RJ, MG...',
    zip_code          VARCHAR(10)     NULL,
    -- FK: etapa do funil onde o cliente está posicionado
    pipeline_stage_id INT UNSIGNED    NOT NULL,
    -- FK: vendedor responsável (NULL = sem responsável)
    assigned_to       INT UNSIGNED    NULL,
    deal_value        DECIMAL(12,2)   NOT NULL DEFAULT 0.00 COMMENT 'Valor estimado do negócio em R$',
    source            VARCHAR(80)     NULL     COMMENT 'Origem: Google Ads, Indicação, LinkedIn, etc.',
    notes             TEXT            NULL,
    birth_date        DATE            NULL     COMMENT 'Data de nascimento — formato YYYY-MM-DD',
    referido_por      VARCHAR(150)    NULL     COMMENT 'Nome de quem indicou (quando source = Indicação)',
    closed_at         DATE            NULL     COMMENT 'Data de fechamento da venda (preenchido apenas na etapa Venda Fechada)',
    is_active         TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '0 = cliente arquivado (soft-delete)',
    tenant_id         INT UNSIGNED    NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant',
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clients_email (email),
    INDEX idx_clients_tenant (tenant_id),
    -- Ao renomear etapa (UPDATE), a FK atualiza em cascata.
    -- Ao tentar DELETAR etapa com clientes, o banco bloqueia (RESTRICT).
    CONSTRAINT fk_client_stage
        FOREIGN KEY (pipeline_stage_id) REFERENCES pipeline_stages(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    -- Ao remover vendedor, o cliente perde o responsável (SET NULL).
    CONSTRAINT fk_client_user
        FOREIGN KEY (assigned_to) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_clients_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices de apoio (além dos criados automaticamente pelas FKs)
CREATE INDEX idx_clients_stage    ON clients(pipeline_stage_id);
CREATE INDEX idx_clients_assigned ON clients(assigned_to);

-- ============================================================
-- TABELA: client_sales
-- Cotas de consórcio vinculadas a um cliente.
-- Um cliente pode ter múltiplas cotas (multi-venda).
-- Dados pessoais ficam em clients; dados comerciais da cota aqui.
-- ============================================================
CREATE TABLE IF NOT EXISTS client_sales (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    client_id           INT UNSIGNED    NOT NULL,
    grupo               VARCHAR(50)     NULL     COMMENT 'Número/nome do grupo do consórcio',
    cota                VARCHAR(50)     NULL     COMMENT 'Número/nome da cota',
    tipo                ENUM('Imóvel','Veículo','Serviço')
                                        NOT NULL COMMENT 'Tipo do consórcio',
    credito_contratado  DECIMAL(12,2)   NOT NULL DEFAULT 0.00 COMMENT 'Valor do crédito contratado em R$',
    -- NULL = não pago no ciclo vigente.
    -- O status "Pago/Não pago" é calculado em PHP pelo mês de referência;
    -- nunca é armazenado como string aqui.
    paid_at             TIMESTAMP       NULL     DEFAULT NULL
                                                 COMMENT 'Timestamp da última confirmação de pagamento. NULL = não pago.',
    tenant_id           INT UNSIGNED    NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant',
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_client_sales_tenant (tenant_id),
    -- Ao deletar o cliente, todas as suas cotas são removidas (CASCADE)
    CONSTRAINT fk_sale_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_client_sales_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_sales_client ON client_sales(client_id);
CREATE INDEX idx_sales_tipo   ON client_sales(tipo);

-- ============================================================
-- TABELA: interactions
-- Histórico completo de contatos com clientes.
-- Cada linha representa um evento: ligação, e-mail, reunião, etc.
-- ============================================================
CREATE TABLE IF NOT EXISTS interactions (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    client_id   INT UNSIGNED    NOT NULL,
    user_id     INT UNSIGNED    NOT NULL COMMENT 'Usuário que registrou a interação',
    type        ENUM('call','email','meeting','whatsapp','note','other')
                                NOT NULL DEFAULT 'note',
    description TEXT            NOT NULL COMMENT 'Texto livre descrevendo o contato',
    occurred_at DATETIME        NOT NULL COMMENT 'Data/hora em que o contato ocorreu',
    tenant_id   INT UNSIGNED    NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_interactions_tenant (tenant_id),
    -- Deletar cliente remove também suas interações (CASCADE)
    CONSTRAINT fk_inter_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE,
    -- Vendedor não pode ser deletado enquanto tiver interações (RESTRICT)
    CONSTRAINT fk_inter_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_interactions_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acelera a timeline de interações de um cliente ordenada pela mais recente
CREATE INDEX idx_interactions_cli ON interactions(client_id, occurred_at DESC);

-- ============================================================
-- TABELA: tasks
-- Tarefas e follow-ups. Podem estar vinculadas a um cliente
-- específico (client_id) ou ser gerais (client_id = NULL).
-- ============================================================
CREATE TABLE IF NOT EXISTS tasks (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    client_id   INT UNSIGNED    NULL     COMMENT 'NULL = tarefa não vinculada a cliente',
    assigned_to INT UNSIGNED    NOT NULL COMMENT 'Usuário responsável por executar',
    title       VARCHAR(200)    NOT NULL,
    description TEXT            NULL,
    due_date    DATETIME        NOT NULL COMMENT 'Prazo de vencimento',
    priority    ENUM('low','medium','high')
                                NOT NULL DEFAULT 'medium',
    status      ENUM('pending','in_progress','done','cancelled')
                                NOT NULL DEFAULT 'pending',
    created_by  INT UNSIGNED    NOT NULL COMMENT 'Usuário que criou a tarefa',
    tenant_id   INT UNSIGNED    NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_tasks_tenant (tenant_id),
    -- Deletar cliente mantém a tarefa mas remove o vínculo (SET NULL)
    CONSTRAINT fk_task_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_task_assigned
        FOREIGN KEY (assigned_to) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_task_created
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_tasks_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acelera queries de tarefas por prazo (agenda do dia, atrasadas, etc.)
CREATE INDEX idx_tasks_due ON tasks(due_date, status);

-- ============================================================
-- TABELA: cold_contacts
-- Contatos frios importados via CSV para campanhas de prospecção.
-- Completamente isolada de clients — sem FKs externas.
-- ============================================================
CREATE TABLE IF NOT EXISTS cold_contacts (
    id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    phone             VARCHAR(20)     NOT NULL COMMENT 'Celular do contato (coluna A do CSV)',
    name              VARCHAR(150)    NOT NULL COMMENT 'Nome do contato (coluna B do CSV)',
    tipo_lista        VARCHAR(100)    NOT NULL COMMENT 'Nome da lista digitado no momento do import',
    -- Últimos 4 dígitos do celular do vendedor que enviou a mensagem.
    -- NULL = mensagem ainda não foi enviada a este contato.
    telefone_enviado  VARCHAR(4)      NULL     DEFAULT NULL
                                              COMMENT 'Últimos 4 dígitos do celular do vendedor que enviou',
    data_mensagem     DATE            NULL     DEFAULT NULL
                                              COMMENT 'Data em que a mensagem foi enviada ao contato',
    -- Usada para agrupar contatos nos Cards Mensais do painel de acompanhamento.
    -- Valor definido no momento do import (CURRENT_TIMESTAMP) e nunca alterado.
    imported_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  COMMENT 'Data/hora da importação — agrupa Cards Mensais',
    tenant_id            INT UNSIGNED    NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant',
    archived_at          DATETIME        NULL     DEFAULT NULL,
    imported_year_month  CHAR(7) GENERATED ALWAYS AS (DATE_FORMAT(imported_at, '%Y-%m')) STORED,
    created_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_cold_contacts_tenant (tenant_id),
    INDEX idx_cc_year_month (imported_year_month),
    CONSTRAINT fk_cold_contacts_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agrupa por mês de importação nos Cards Mensais
CREATE INDEX idx_cold_imported_at ON cold_contacts(imported_at);
-- Filtro por celular na modal de edição
CREATE INDEX idx_cold_phone ON cold_contacts(phone);

-- ============================================================
-- DADOS INICIAIS: Usuário Administrador Padrão
-- Senha padrão: Admin@1234
-- Hash: password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12])
--
-- IMPORTANTE: Troque a senha no primeiro acesso!
--   Acesse: Configurações → Meu Perfil → Alterar Senha
-- ============================================================
INSERT IGNORE INTO users (id, name, email, password_hash, role, tenant_id, password_must_change) VALUES
(1, 'Administrador', 'admin@crm.local',
 '$2y$12$eImiTXuWVxfM37uY4JANjOe5XtTkLfkwU1h9qMz5h3ZfCqsN8G2HW', 'admin', 1, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- MIGRAÇÕES INCREMENTAIS
-- Execute manualmente em bancos já existentes (não afeta instalações novas)
-- ============================================================

-- v1.1: Data de fechamento de venda na tabela clients
ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS closed_at DATE NULL
    COMMENT 'Data de fechamento da venda (preenchido apenas na etapa Venda Fechada)'
    AFTER referido_por;

-- ============================================================
-- FIM DO SCHEMA
-- Tabelas criadas:
--   1. tenants           → organizações multi-tenant
--   2. users             → autenticação e controle de acesso
--   3. pipeline_stages   → etapas do funil Kanban (6 padrão)
--   4. clients           → cadastro de clientes/leads
--   5. client_sales      → cotas de consórcio por cliente
--   6. interactions      → histórico de contatos
--   7. tasks             → tarefas e follow-ups
--   8. cold_contacts     → contatos frios importados via CSV
-- ============================================================
