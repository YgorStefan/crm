-- ============================================================
-- CRM Empresarial — Schema do Banco de Dados
-- Compatível com MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 (suporta emojis e caracteres especiais)
-- ============================================================

-- ATENÇÃO: Em hospedagem compartilhada (Hostinger, etc.), crie o banco
-- manualmente pelo painel de controle (hPanel) e importe este arquivo
-- com o banco já selecionado. As linhas CREATE DATABASE e USE foram
-- removidas pois o usuário de hospedagem não tem essa permissão.

-- ------------------------------------------------------------
-- TABELA: users
-- Armazena os usuários do sistema com seus níveis de acesso.
-- Roles disponíveis:
--   admin  → acesso total (configurações, relatórios, usuários)
--   seller → gerencia seus próprios clientes e tarefas
--   viewer → somente leitura
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)        NOT NULL,
    email         VARCHAR(150)        NOT NULL UNIQUE,
    -- A senha NUNCA é armazenada em texto puro.
    -- Usamos password_hash($senha, PASSWORD_BCRYPT) no PHP,
    -- que gera um hash seguro de 60 caracteres.
    password_hash VARCHAR(255)        NOT NULL,
    role          ENUM('admin','seller','viewer')
                                      NOT NULL DEFAULT 'seller',
    avatar        VARCHAR(255)        NULL,               -- caminho relativo ao /public/uploads/
    is_active     TINYINT(1)          NOT NULL DEFAULT 1, -- soft-delete: 0 = inativo
    created_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: pipeline_stages
-- Define as etapas (colunas) do funil de vendas Kanban.
-- O campo `position` controla a ordem de exibição da esquerda
-- para a direita no board.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pipeline_stages (
    id            INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(80)         NOT NULL,
    color         VARCHAR(7)          NOT NULL DEFAULT '#6366f1', -- cor hex para cabeçalho da coluna
    position      TINYINT UNSIGNED    NOT NULL DEFAULT 0,         -- ordem crescente = da esquerda p/ direita
    created_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etapas padrão do funil de vendas
-- (o administrador pode adicionar/remover etapas no futuro)
INSERT INTO pipeline_stages (name, color, position) VALUES
  ('Prospecção',      '#6366f1', 1),
  ('Qualificação',    '#f59e0b', 2),
  ('Proposta',        '#3b82f6', 3),
  ('Negociação',      '#8b5cf6', 4),
  ('Fechado - Ganho', '#10b981', 5),
  ('Fechado - Perdido','#ef4444', 6);

-- ------------------------------------------------------------
-- TABELA: clients
-- Cadastro completo de clientes/leads com todos os dados
-- necessários para o relacionamento comercial.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(150)    NOT NULL,
    email             VARCHAR(150)    NULL UNIQUE,
    phone             VARCHAR(20)     NULL,
    company           VARCHAR(150)    NULL,
    cnpj_cpf          VARCHAR(20)     NULL,               -- CPF (11 dígitos) ou CNPJ (14 dígitos) sem máscara
    address           VARCHAR(255)    NULL,
    city              VARCHAR(100)    NULL,
    state             CHAR(2)         NULL,                -- UF: SP, RJ, MG...
    zip_code          VARCHAR(10)     NULL,
    -- FK para pipeline_stages: define em qual etapa do funil o cliente está
    pipeline_stage_id INT UNSIGNED    NOT NULL,
    -- FK para users: vendedor responsável por este cliente
    assigned_to       INT UNSIGNED    NULL,
    deal_value        DECIMAL(12,2)   NULL DEFAULT 0.00,   -- valor estimado do negócio em R$
    source            VARCHAR(80)     NULL,                -- origem: Google Ads, Indicação, LinkedIn...
    notes             TEXT            NULL,
    is_active         TINYINT(1)      NOT NULL DEFAULT 1,  -- 0 = cliente arquivado
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,

    -- Se a etapa do funil for renomeada (UPDATE), a FK se atualiza em cascata.
    -- Se tentarmos DELETAR uma etapa que possui clientes, o banco bloqueia (RESTRICT).
    CONSTRAINT fk_client_stage
        FOREIGN KEY (pipeline_stage_id) REFERENCES pipeline_stages(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    -- Se o vendedor for removido, o cliente fica sem responsável (SET NULL)
    -- em vez de ser deletado junto.
    CONSTRAINT fk_client_user
        FOREIGN KEY (assigned_to) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: interactions
-- Registra todo o histórico de contato com um cliente.
-- Cada linha é um evento: ligação, e-mail, reunião, nota, etc.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS interactions (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED    NOT NULL,
    user_id     INT UNSIGNED    NOT NULL,                  -- usuário que registrou a interação
    type        ENUM('call','email','meeting','whatsapp','note','other')
                                NOT NULL DEFAULT 'note',
    description TEXT            NOT NULL,                  -- texto livre descrevendo o contato
    occurred_at DATETIME        NOT NULL,                  -- data/hora em que o contato ocorreu
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Se o cliente for deletado, todas as suas interações também são removidas (CASCADE)
    CONSTRAINT fk_inter_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE,

    -- O usuário não pode ser deletado enquanto tiver interações registradas (RESTRICT)
    CONSTRAINT fk_inter_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELA: tasks
-- Tarefas e follow-ups. Podem estar vinculadas a um cliente
-- específico (client_id) ou ser uma tarefa geral (client_id NULL).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED    NULL,                      -- NULL = tarefa não vinculada a cliente
    assigned_to INT UNSIGNED    NOT NULL,                  -- responsável por executar a tarefa
    title       VARCHAR(200)    NOT NULL,
    description TEXT            NULL,
    due_date    DATETIME        NOT NULL,                   -- prazo de vencimento
    priority    ENUM('low','medium','high')
                                NOT NULL DEFAULT 'medium',
    status      ENUM('pending','in_progress','done','cancelled')
                                NOT NULL DEFAULT 'pending',
    created_by  INT UNSIGNED    NOT NULL,                  -- quem criou a tarefa
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,

    -- Se o cliente for deletado, a tarefa permanece mas perde o vínculo (SET NULL)
    CONSTRAINT fk_task_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_task_assigned
        FOREIGN KEY (assigned_to) REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_task_created
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Índices adicionais para otimizar as queries mais frequentes
-- (além dos índices criados automaticamente pelas FKs)
-- ------------------------------------------------------------

-- Acelera filtros e ordenação por etapa do funil na view Kanban
CREATE INDEX idx_clients_stage    ON clients(pipeline_stage_id);

-- Acelera a busca de clientes por vendedor responsável
CREATE INDEX idx_clients_assigned ON clients(assigned_to);

-- Acelera queries de tarefas por prazo (agenda do dia, tarefas atrasadas, etc.)
CREATE INDEX idx_tasks_due        ON tasks(due_date, status);

-- Acelera a timeline de interações de um cliente, ordenada pela mais recente
CREATE INDEX idx_interactions_cli ON interactions(client_id, occurred_at DESC);

-- ------------------------------------------------------------
-- Usuário administrador padrão
-- Senha: Admin@1234  (TROQUE no primeiro acesso!)
-- Hash gerado com: password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12])
-- ------------------------------------------------------------
INSERT INTO users (name, email, password_hash, role) VALUES (
  'Administrador',
  'admin@crm.local',
  '$2y$12$eImiTXuWVxfM37uY4JANjOe5XtTkLfkwU1h9qMz5h3ZfCqsN8G2HW',
  'admin'
);
