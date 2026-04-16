# Auditoria Completa do CRM — Design Spec
**Data:** 2026-04-16  
**Abordagem:** C — ciclo auditoria→correção→teste por camada MVC

---

## Visão geral

Auditoria completa do projeto CRM Empresarial (PHP 8 MVC) cobrindo: bugs, falhas de segurança, código morto, lógica inválida, responsividade mobile, `.gitignore` e arquivos não utilizados. Cada camada é auditada, corrigida e testada antes de avançar para a próxima.

---

## Camada 1 — core/ + config/

**Escopo de auditoria:**
- `core/bootstrap.php` — inicialização de sessão, `session.cookie_secure` condicional por ambiente
- `core/Router.php` — despacho de middlewares, tratamento de rotas não encontradas
- `core/Database.php` — singleton PDO, tratamento de erros de conexão
- `core/Middleware/AuthMiddleware.php` — verificação de sessão, timeout
- `core/Middleware/CsrfMiddleware.php` — geração e validação de token CSRF
- `core/Middleware/CspMiddleware.php` — headers CSP corretos
- `core/Controller.php` — métodos base (render, redirect, flash, input, requireRole)
- `core/Model.php` — base model PDO
- `core/helpers.php` — `navLink()`, `format_currency()`
- `core/Logger.php` — log injection, permissões de diretório
- `config/app.php` — constantes de ambiente
- `config/database.php` — leitura segura de credenciais

**Problemas conhecidos a confirmar:**
- `session.cookie_secure` comentado em bootstrap — deve ser ativado condicionalmente quando `APP_ENV=production`
- Verificar se o timeout de sessão é validado corretamente no AuthMiddleware
- Verificar se o Router trata método HTTP inválido sem vazar stack trace

---

## Camada 2 — Models/

**Escopo de auditoria:**
- `app/Models/Client.php`
- `app/Models/ColdContact.php`
- `app/Models/Interaction.php`
- `app/Models/PipelineStage.php`
- `app/Models/Task.php`
- `app/Models/User.php`

**O que verificar:**
- Todos os parâmetros usam prepared statements (sem concatenação de variáveis em SQL)
- Métodos públicos que não são chamados por nenhum Controller (código morto)
- Lógica de soft-delete: `is_active = 0` aplicada consistentemente
- Isolamento multi-tenant: todas as queries filtram por `tenant_id` onde necessário
- Retorno de dados sensíveis desnecessários (ex: `password_hash` em queries de listagem)

---

## Camada 3 — Controllers/

**Escopo de auditoria:**
- `AcompanhamentoController`, `AuthController`, `ClientController`, `ColdContactController`
- `DashboardController`, `InteractionController`, `PipelineController`
- `SettingsController`, `TaskController`, `UserController`

**O que verificar:**
- Validação de input em todos os métodos POST (tipo, tamanho, formato)
- Autorização por role antes de cada operação sensível (`requireRole`)
- Redirecionamentos após POST (padrão PRG — Post/Redirect/Get)
- Métodos órfãos (declarados mas sem rota correspondente em `config/routes.php`)
- Upload de avatar: tipo MIME validado, extensão segura, tamanho limitado
- Verificação de ownership: vendedor só vê seus próprios clientes/tarefas (ou é intencional que veja todos?)

---

## Camada 4 — Views/ + assets/JS

**Escopo de auditoria:**
- Todas as views em `app/Views/` (layouts, auth, clients, pipeline, tasks, dashboard, etc.)
- `public/assets/js/pipeline.js`, `dashboard.js`, `acompanhamento.js`

**O que verificar:**
- XSS: toda saída de variáveis PHP usa `htmlspecialchars()` com `ENT_QUOTES`
- Responsividade mobile: breakpoints Tailwind (`sm:`, `md:`), menus colapsáveis, tabelas com `overflow-x-auto`, modais em tela pequena
- JS: fetch/AJAX com tratamento de erro (catch), referências a elementos DOM que podem não existir, `console.log` de debug deixados no código
- CSRF token presente em todos os formulários POST
- Links e botões de ação destrutiva com confirmação antes de executar

---

## Camada 5 — Limpeza geral

### .gitignore
Adicionar entradas faltantes:
- `"env crm.txt"` — contém credenciais reais de produção (CRÍTICO)
- `config/database.php` — credenciais do banco
- `scripts/smoke/` — scripts de desenvolvimento descartáveis
- `docs/superpowers/` — artefatos de planejamento interno
- `storage/` — logs gerados em runtime
- `composer.lock` e `vendor/` — se Composer for introduzido

### Arquivos a remover do repositório
- `"env crm.txt"` — credenciais expostas, remover do índice git e do disco após confirmar com usuário

### Arquivos a avaliar
- `scripts/migrations/*.php` — scripts de migração já executados, podem ir para `.gitignore` ou ser mantidos como documentação
- `scripts/smoke/*.php` — scripts ad-hoc de verificação de fases anteriores; candidatos a remoção ou arquivamento
- `public/.user.ini` — configuração de servidor; já está no `.gitignore`, mas verificar se está rastreado

---

## Camada 6 — Testes

**Padrão:** manter o micro-runner customizado já existente no projeto (`php tests/PhaseXXTest.php`), sem introduzir PHPUnit.

**Arquivo a criar:** `tests/Phase10Test.php` (auditoria de correções)

**O que testar por categoria de correção:**
- Segurança: `session.cookie_secure` ativo em produção, CSRF token validado, headers CSP presentes
- Helpers: `format_currency()` com valores edge (zero, negativo, string inválida), `navLink()` com paths especiais
- Logger: log injection bloqueado (newlines sanitizados), diretório criado corretamente
- Models: prepared statements usados (não há concatenação SQL detectável por análise estática simplificada)
- Controllers: validação de role (`requireRole`) bloqueia acesso não autorizado
- Views: presença de `htmlspecialchars` nas saídas — verificação de padrão via file_get_contents + regex

---

## Prioridade de execução

| Prioridade | Item |
|------------|------|
| CRÍTICO | Remover/gitignore `"env crm.txt"` com credenciais reais |
| CRÍTICO | `session.cookie_secure` em produção |
| ALTA | XSS em views |
| ALTA | Validação de input em Controllers |
| ALTA | Isolamento multi-tenant nas queries |
| MÉDIA | Código morto (métodos não usados) |
| MÉDIA | Responsividade mobile |
| BAIXA | Scripts smoke/migrations no .gitignore |
| BAIXA | `console.log` de debug no JS |

---

## Fora do escopo

- Refatoração arquitetural (ex: trocar para framework externo)
- Novas funcionalidades
- Testes de browser/UI headless
- Performance de banco de dados além do que já tem índices
