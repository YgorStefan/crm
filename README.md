# CRM Empresarial — PHP 8 MVC

Sistema de CRM (Customer Relationship Management) empresarial desenvolvido do zero com **PHP 8 puro**, arquitetura **MVC**, **MySQL/PDO**, **multi-tenant** e **Tailwind CSS** compilado localmente.

> Projeto com código comentado em cada camada da aplicação.

---

## Stack

| Camada | Tecnologia |
|--------|------------|
| Back-end | PHP 8.0+ (POO + MVC, sem framework) |
| Banco de Dados | MySQL 5.7+ / MariaDB 10.3+ |
| ORM / Queries | PDO + Prepared Statements |
| Front-end | HTML5 + **Tailwind CSS v3** (compilado) + Vanilla JS |
| Tema | Light / **Dark Mode** com persistência em `localStorage` |
| Gráficos | Chart.js 4 (CDN) |
| Calendário | FullCalendar 6 (CDN) |
| Ícones | Lucide (inline SVG, sem CDN runtime) |
| Multi-tenant | Coluna `tenant_id` em todas as tabelas + isolamento no Model |
| Hospedagem alvo | Hostinger Business (shared hosting) |

---

## Funcionalidades

### Núcleo CRM
- **Autenticação segura**: login com `password_hash` (bcrypt), `session_regenerate_id`, timeout de sessão, **rate-limit** (5 tentativas / 10 min por IP), bloqueio de conta com `password_must_change`
- **Multi-tenant**: cada usuário pertence a uma organização (`tenant`) e só enxerga os dados dela
- **Gestão de Clientes**: CRUD com busca, filtros, paginação, **endereço completo** (CEP, número, complemento, bairro, cidade, UF), data de nascimento, fonte e indicado por
- **Pipeline Kanban**: funil de vendas com drag & drop (Vanilla JS), atualização via Fetch API, **etapas customizáveis** por tenant (criar, renomear, mover, marcar como Won, deletar)
- **Interações**: timeline de contatos por cliente (ligação, e-mail, reunião, WhatsApp, nota) com edição inline
- **Tarefas / Calendário**: visão calendário (FullCalendar) com mês/semana, filtros rápidos (status × prioridade), tooltips, banner enxuto de tarefas atrasadas, conclusão via AJAX
- **Cotas de Consórcio**: clientes em estágios marcados como Won ganham registro de venda (grupo, cota, tipo, crédito contratado) com máscara monetária BRL e ciclo mensal de pagamento

### Listas Frias
- **Contatos Frios**: importação CSV/manual, edição inline em modal, bulk-update, deleção por mês, exportação
- **Acompanhamento**: dashboard analítico da lista fria (estatísticas mensais)

### Painel Admin
- **Administração unificada** com 3 abas (`/admin?tab=...`):
  - **Usuários**: perfis `admin` / `seller` / `viewer`, ativo/inativo
  - **Organização**: nome do tenant
  - **Ciclo de pagamento**: dia de corte (1–28) para cotas
- **Dashboard**: KPIs, gráfico de barras (vendas/mês) e rosca (pipeline), tarefas próximas e atividade recente, com cards compactos e links de drill-down

### UX
- **Notificações** em polling 60s (tarefas atrasadas, vencendo em 24h, aniversários do dia) com **dismiss individual + dismiss-all persistente** em `localStorage`
- **Custom Select** vanilla JS substituindo `<select>` nativo (ARIA, navegação por teclado, type-ahead)
- **Tooltips** customizados via `data-tooltip` (CSS + pseudo-elementos)
- **Máscaras de input** reutilizáveis: `data-mask="currency"` (BRL) e `data-mask="digits"` (apenas números)
- **Sidebar colapsável** com estado persistido em `localStorage`
- **Cache-buster** automático em CSS/JS via `?v=<filemtime>` (sem precisar Ctrl+F5 após rebuild)

---

## Segurança implementada

| Ameaça | Defesa |
|--------|--------|
| SQL Injection | PDO + Prepared Statements com parâmetros nomeados |
| XSS | `htmlspecialchars()` em todas as saídas, `ENT_QUOTES` |
| CSRF | Token de sessão sincronizado com `hash_equals()` em todo POST |
| Session Fixation | `session_regenerate_id(true)` no login |
| Clickjacking | Header `X-Frame-Options: SAMEORIGIN` |
| Enumeração de usuários | Mensagem genérica de erro no login |
| Brute-force no login | `RateLimitMiddleware` — 5 tentativas / 10 min por IP, tabela `login_attempts` |
| Inline script/style injection | CSP `script-src 'self' 'nonce-…' 'strict-dynamic'` em produção |
| Path traversal / acesso direto | `.htaccess` na raiz + em `/app`, `/core`, `/config`, `/database`, `/storage` |
| Senha vazada por padrão | Flag `password_must_change` no usuário-semente |
| Multi-tenant data leak | Toda query filtrada por `tenant_id` no Model |

---

## Estrutura de Pastas

```
crm/
├── app/
│   ├── Controllers/          ← lógica de negócio (incluindo AdminController unificado)
│   ├── Models/               ← queries PDO
│   └── Views/                ← templates PHP + Tailwind
│       ├── layouts/          ← main.php (com sidebar) e blank.php (login)
│       ├── components/       ← partials reutilizáveis (paginação)
│       └── errors/           ← 404, etc.
├── config/
│   ├── app.php               ← carrega .env e define constantes
│   ├── routes.php            ← definição das rotas
│   ├── database.php          ← (opcional) credenciais alternativas
│   └── database.php.example  ← template
├── core/
│   ├── Router.php
│   ├── Controller.php
│   ├── Model.php
│   ├── Database.php
│   ├── Logger.php
│   ├── bootstrap.php
│   ├── helpers.php
│   └── Middleware/           ← Auth, Csrf, Csp, RateLimit
├── database/
│   ├── schema.sql            ← schema completo consolidado
│   ├── migrations/           ← migrações incrementais (001-012)
│   └── seeders/              ← seeds de pipeline_stages padrão
├── public/                   ← único diretório público (front controller)
│   ├── index.php
│   ├── .htaccess             ← mod_rewrite → index.php
│   ├── assets/
│   │   ├── css/tailwind.css  ← saída do build (versionada / não-minificada por padrão)
│   │   └── js/               ← pipeline.js, dashboard.js, acompanhamento.js,
│   │                           masks.js, custom-select.js
│   └── uploads/              ← uploads de usuários (gitkeep)
├── resources/
│   └── css/input.css         ← fonte do Tailwind (componentes customizados + scrollbar + tooltip + custom-select + FullCalendar)
├── scripts/
│   ├── setup_tailwind.php    ← baixa o binário standalone do Tailwind para .bin/
│   └── build_css.php         ← compila resources/css/input.css → public/assets/css/tailwind.css
├── storage/
│   └── logs/                 ← logs de runtime (não versionado)
├── .env / .env.example       ← variáveis de ambiente
├── router.php                ← roteador para php -S (dev local)
├── tailwind.config.js        ← config v3 (content + safelist + dark)
└── package.json              ← (opcional) Tailwind v3 via npm
```

---

## Instalação

### Pré-requisitos
- **PHP 8.0+** (CLI + módulo Apache)
- **MySQL 5.7+** ou **MariaDB 10.3+**
- **Apache** com `mod_rewrite` (produção) **OU** PHP built-in server (dev)
- **Node.js 18+ OU PHP CLI com `exec` habilitado** (para compilar o Tailwind)

### Passo a passo

**1. Clone o repositório**
```bash
git clone https://github.com/YgorStefan/crm.git
cd crm
```

**2. Configure as variáveis de ambiente**
```bash
cp .env.example .env
```
Edite `.env`:
```ini
DB_HOST=localhost
DB_NAME=crm_db
DB_USER=root
DB_PASS=

APP_URL=http://localhost:8000
APP_ENV=development
```

**3. Importe o schema do banco**
```bash
# Crie o banco primeiro
mysql -u root -p -e "CREATE DATABASE crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importe o schema completo
mysql -u root -p crm < database/schema.sql
```

Para atualizações posteriores, use o **runner de migrações** (idempotente e rastreado em `_migrations`):
```bash
php bin/migrate.php             # aplica pendentes
php bin/migrate.php --status    # mostra aplicadas vs pendentes
php bin/migrate.php --baseline  # registra TODAS como aplicadas sem rodar (1ª vez por env)
```
> **Em ambiente novo cujo schema já vem pronto** (clone + `mysql < schema.sql`),
> rode `php bin/migrate.php --baseline` UMA vez para que o runner saiba que
> tudo já está aplicado. Depois, qualquer migration nova é aplicada com `php bin/migrate.php`.

**4. Compile o CSS do Tailwind**

Opção A — via **npm** (recomendado para dev):
```bash
npm install
npm run build          # build legível (dev)
npm run build:prod     # build minificado (produção)
npm run watch          # recompila ao salvar
```

Opção B — via **binário standalone** (sem Node):
```bash
php scripts/setup_tailwind.php           # baixa o binário para .bin/
php scripts/build_css.php                # build legível (dev)
php scripts/build_css.php --minify       # build minificado (produção)
```

> Reexecute o build sempre que mudar arquivos em `app/Views/**/*.php` ou `resources/css/input.css`.
> O cache-buster `?v=<filemtime>` faz o browser baixar a versão nova automaticamente.

**5. Suba o servidor (dev local)**
```bash
php -S localhost:8000 -t public router.php
```

**6. Acesse no navegador**
```
http://localhost:8000
```

**Login padrão (schema.sql):**
- E-mail: `admin@crm.local`
- Senha: `Admin@1234`
- O usuário tem `password_must_change = 1` — o sistema pedirá troca no primeiro login.

> ⚠️ **Sempre troque a senha após o primeiro acesso.**

---

## Comandos úteis

```bash
# Iniciar servidor dev
php -S localhost:8000 -t public router.php

# Rebuild do CSS (npm) — legível
npm run build

# Rebuild do CSS (npm) — minificado para produção
npm run build:prod

# Rebuild do CSS (sem npm)
php scripts/build_css.php            # dev (legível)
php scripts/build_css.php --minify   # produção

# Watch mode (npm) — recompila ao salvar
npm run watch
```

---

## Deploy na Hostinger

### Setup inicial (uma vez por ambiente)
1. Faça upload de todos os arquivos via FTP/SSH para `public_html/crm/` (ou onde a hospedagem aponta).
2. Importe `database/schema.sql` pelo phpMyAdmin / hPanel.
3. Crie um `.env` na raiz com as credenciais reais (use `.env.example` como base).
4. Ajuste `APP_URL=https://seudominio.com.br/crm` e `APP_ENV=production`.
5. Garanta que `public/` é o **DocumentRoot** — nunca exponha `/app`, `/core`, `/config`.
6. Rode `php bin/migrate.php --baseline` UMA vez (registra que o schema atual já está aplicado).

### Deploy contínuo (toda mudança)
Use o pipeline automatizado, que evita os erros mais comuns (CSS desatualizado, migration esquecida, arquivo solto via SCP):
```powershell
# Local (Windows)
powershell -ExecutionPolicy Bypass -File bin/deploy.ps1
```
O script executa em ordem: verifica git status limpo → `npm run build` → commit do CSS se mudou → `git push` → SSH em prod (`git pull` + `php bin/migrate.php`) → mostra status final das migrations.

Flags úteis:
```powershell
powershell -File bin/deploy.ps1 -DryRun      # mostra o que faria, não executa
powershell -File bin/deploy.ps1 -SkipBuild   # pula rebuild do Tailwind
```

> Em produção a CSP usa `script-src 'self' 'nonce-…' 'strict-dynamic'`, então qualquer script/style novo precisa do atributo `nonce="<?= CSP_NONCE ?>"`.

---

## Espelhamento local ↔ produção

Para evitar bugs que só aparecem em produção, mantenha os ambientes o mais idênticos possível:

| Item | Local (recomendado) | Produção |
|---|---|---|
| URL base | `http://localhost/crm` | `https://seudominio.com.br/crm` |
| `APP_URL` no `.env` | `http://localhost/crm` (sem `/public` no fim) | `https://seudominio.com.br/crm` |
| Servidor web | Apache (XAMPP/Laragon) com DocumentRoot em `crm/public` | Apache da Hostinger com DocumentRoot em `crm/public` |
| `APP_ENV` | `development` (mostra erros na tela) | `production` (oculta erros) |
| Migrations | `php bin/migrate.php` após cada pull | mesmo comando, automatizado pelo `bin/deploy.ps1` |
| Tailwind CSS | `npm run watch` enquanto desenvolve | gerado pelo `bin/deploy.ps1`, versionado |

### Por que isso importa
Bugs que ocorreram na prática por divergência:
- `APP_URL` local sem prefixo de subdir → links de paginação iam pra `/clients?page=2` (404 em prod, ok local).
- Migration nova versionada mas nunca rodada em prod → coluna `interactions.tenant_id` faltando → HTTP 500 em qualquer interação nova.
- CSS editado mas não rebuildado antes do commit → produção carregava versão velha.

### Truque para testar localmente em modo "produção"
Quando um bug só aparece em prod, edite seu `.env` local temporariamente:
```ini
APP_ENV=production
```
Isso desliga `display_errors`. Bugs de PHP que estavam aparecendo na tela passam a ser silenciosos (= erro 500), reproduzindo o comportamento de prod e te forçando a olhar o `error_log` em vez de adivinhar.

---

## Convenções de código

- **Idioma**: comentários e UI em **pt-BR**, código (variáveis/classes/métodos) em **camelCase inglês**
- **Sem framework**: tudo escrito do zero (Router, Controller, Model, Middleware)
- **Sem bundler JS**: vanilla JS apenas, scripts inline com CSP nonce
- **Sem CDN de JS runtime para ícones**: SVG Lucide inline (zero JS overhead)
- **Atributos `data-*`** para hooks de comportamento (`data-mask`, `data-tooltip`, `data-no-custom`)

---

## Licença

MIT — livre para uso educativo e comercial.
