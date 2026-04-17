# CRM Empresarial — PHP 8 MVC

Sistema de CRM (Customer Relationship Management) empresarial desenvolvido do zero com **PHP 8 puro**, arquitetura **MVC**, **MySQL/PDO** e **Tailwind CSS**.

> Projeto com código comentado em cada camada da aplicação.

---

## Stack

| Camada | Tecnologia |
|--------|------------|
| Back-end | PHP 8.0+ (POO + MVC) |
| Banco de Dados | MySQL 5.7+ / MariaDB 10.3+ |
| ORM / Queries | PDO + Prepared Statements |
| Front-end | HTML5 + Tailwind CSS (CDN) + Vanilla JS |
| Gráficos | Chart.js 4 (CDN) |
| Hospedagem alvo | Hostinger Business (shared hosting) |

---

## Funcionalidades

- **Autenticação segura**: login com `password_hash` (bcrypt), `session_regenerate_id`, timeout de sessão
- **Gestão de Clientes**: CRUD completo com busca, filtros e soft-delete
- **Pipeline Kanban**: funil de vendas com drag & drop (Vanilla JS) e atualização via Fetch API
- **Interações**: timeline de contatos por cliente (ligação, e-mail, reunião, WhatsApp, nota)
- **Tarefas / Follow-ups**: criação, prioridade, status, alerta de tarefas atrasadas, conclusão via AJAX
- **Dashboard**: KPIs, gráfico de barras e rosca (Chart.js), atividade recente e agenda semanal
- **Administração**: gerenciamento de usuários com perfis (admin, vendedor, leitor)

---

## Segurança implementada

| Ameaça | Defesa |
|--------|--------|
| SQL Injection | PDO + Prepared Statements com parâmetros nomeados |
| XSS | `htmlspecialchars()` em todas as saídas, `ENT_QUOTES` |
| CSRF | Token de sessão sincronizado com `hash_equals()` |
| Session Fixation | `session_regenerate_id(true)` no login |
| Clickjacking | Header `X-Frame-Options: SAMEORIGIN` |
| Enumeração de usuários | Mensagem genérica de erro no login |
| Acesso direto aos arquivos | `.htaccess` na raiz bloqueando `/app`, `/core`, `/config` |

---

## Estrutura de Pastas

```
crm/
├── app/
│   ├── Controllers/   ← lógica de negócio
│   ├── Models/        ← queries PDO
│   └── Views/         ← templates PHP + Tailwind
├── config/            ← credenciais e constantes
├── core/              ← Router, Database, Middlewares
│   └── Middleware/
├── database/          ← schema.sql
└── public/            ← único diretório público (front controller)
    └── assets/js/     ← pipeline.js, dashboard.js
```

---

## Instalação

### Pré-requisitos
- PHP 8.0+
- MySQL 5.7+ ou MariaDB 10.3+
- Apache com `mod_rewrite` habilitado

### Passo a passo

**1. Clone o repositório**
```bash
git clone https://github.com/YgorStefan/crm.git
cd crm
```

**2. Crie o banco de dados**
```bash
mysql -u root -p < database/schema.sql
```
Ou importe via phpMyAdmin.

**3. Configure as credenciais**
```bash
cp config/database.php.example config/database.php
```
Edite `config/database.php` com seus dados:
```php
'host'   => 'localhost',
'dbname' => 'crm_db',
'user'   => 'seu_usuario',
'pass'   => 'sua_senha',
```

> **Atenção:** Nunca mantenha arquivos de credenciais (`.env`, `env crm.txt`, etc.) no
> diretório do projeto. Use variáveis de ambiente do painel Hostinger ou coloque o `.env`
> **fora** da pasta pública. Se você rotacionou credenciais após um vazamento, documente
> isso em seus registros internos.

**4. Ajuste a URL base**

Em `config/app.php`, altere:
```php
define('APP_URL', 'http://localhost/crm/public');
```

**5. Configure permissões da pasta de uploads**
```bash
chmod 755 public/uploads
```

**6. Acesse no navegador**
```
http://localhost/crm/public
```

**Login padrão:**
- E-mail: `admin@crm.local`
- Senha: `Admin@1234`

> ⚠️ **Troque a senha no primeiro acesso!** (Administração → Usuários → Editar)

---

## Deploy na Hostinger

1. Faça upload de todos os arquivos via FTP para `public_html/crm/`
2. Importe o `database/schema.sql` no MySQL da Hostinger (via hPanel)
3. Atualize `config/database.php` com as credenciais do banco da Hostinger
4. Altere `APP_URL` para `https://seudominio.com.br/crm/public`

---

## Licença

MIT — livre para uso educativo e comercial.
