<!-- GSD:project-start source:PROJECT.md -->
## Project

**CRM Consórcio — Project Context**

CRM web SaaS para vendedores e equipes de consórcio. Permite que gestores criem uma conta de empresa, convidem seus vendedores e gerenciem todo o ciclo de vendas de consórcio: cadastro de clientes, pipeline de vendas, acompanhamento de pagamentos, contatos frios, tarefas e interações.

**Objetivo de negócio:** Comercializar o produto com assinatura mensal (trial grátis → plano pago), usando Mercado Pago / Pix para cobrança recorrente.

**Core Value:** **Onboarding funcional de ponta a ponta** — qualquer gestor ou vendedor pode criar uma conta, convidar sua equipe e começar a usar imediatamente, com dados completamente isolados de outros tenants.
<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->
## Technology Stack

## Languages
- PHP 8.1+ — All backend logic, routing, controllers, models, views
- SQL — Database schema and queries (`database/schema.sql`)
- JavaScript (vanilla, ES2020+) — Frontend interactivity (`public/assets/js/`)
- HTML/PHP templates — Server-side rendered views (`app/Views/`)
- CSS (via Tailwind CDN) — Styling, no local CSS build step
## Runtime
- PHP 8.1+ (uses `str_starts_with`, `match`, union types, readonly-compatible patterns)
- Apache with `mod_rewrite` — URL rewriting via `.htaccess`
- MySQL 5.7+ / MariaDB 10.3+ — Database engine (per `database/schema.sql` header)
- `America/Sao_Paulo` (set in `config/app.php`)
- None — No Composer, no npm. Zero external PHP or JS dependencies installed locally.
- Lockfile: Not applicable
## Frameworks
- Custom MVC framework (hand-rolled, no Composer dependency)
- `core/Middleware/AuthMiddleware.php` — Session-based authentication guard
- `core/Middleware/CsrfMiddleware.php` — CSRF token validation for POST routes
- PSR-4 manual autoloader registered in `public/index.php`
- Not detected
- None — No build tools, bundlers, or transpilers
## Key Dependencies (CDN-loaded, no local install)
- Tailwind CSS v3 (CDN) — `https://cdn.tailwindcss.com` — Utility-first CSS framework
- Chart.js 4.4.0 (CDN) — `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js` — Dashboard charts
- FullCalendar 6.1.20 (CDN) — `https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js` — Task calendar view
- FullCalendar Locales (CDN) — `https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.20/locales-all.global.min.js`
## Configuration
- Loaded from `.env` file at project root via custom parser in `config/app.php`
- Falls back to `$_ENV` / `$_SERVER` (for Hostinger compatibility with blocked `putenv()`)
- Template: `.env.example`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- `APP_URL`, `APP_NAME`, `APP_ENV`
- `SESSION_NAME`, `SESSION_LIFETIME`
- No build config. Deploy is `git pull` on server via `deploy.sh`
## Platform Requirements
- PHP 8.1+ with PDO + PDO_MySQL extensions
- Apache with `mod_rewrite` (or XAMPP/Laragon)
- MySQL 5.7+ or MariaDB 10.3+
- Shared hosting on Hostinger (`ygorstefan.com/crm`)
- Apache with `.htaccess` support
- MySQL via Hostinger control panel
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

## Naming Patterns
- PHP classes: `PascalCase.php` matching the class name — `ClientController.php`, `CsrfMiddleware.php`
- PHP views: `snake_case.php` or `kebab-case.php` in subdirectories — `index.php`, `create.php`, `cold-contacts/index.php`
- JS assets: `kebab-case.js` — `pipeline.js`, `dashboard.js`, `acompanhamento.js`
- Config files: `snake_case.php` — `app.php`, `database.php`
- `PascalCase` for all PHP classes — `ClientController`, `AuthMiddleware`, `PipelineStage`
- Controllers suffixed with `Controller` — `ClientController`, `DashboardController`
- Middlewares suffixed with `Middleware` — `AuthMiddleware`, `CsrfMiddleware`
- `camelCase` for PHP methods — `findById()`, `softDelete()`, `computeRefMonth()`, `findAllWithRelations()`
- `camelCase` for JavaScript functions — `bindCardEvents()`, `onDragStart()`, `moveClient()`, `showToast()`
- Action method names follow Rails-style CRUD verbs: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`
- `camelCase` in PHP — `$clientModel`, `$stageId`, `$paidFormatted`
- `camelCase` in JS — `draggedCard`, `newStageId`, `csrfToken`
- DB column references in SQL: `snake_case` — `pipeline_stage_id`, `assigned_to`, `is_active`
- `Core\` for framework classes — `Core\Controller`, `Core\Router`, `Core\Middleware\AuthMiddleware`
- `App\Controllers\` for controllers — `App\Controllers\ClientController`
- `App\Models\` for models — `App\Models\Client`
## Code Style
- No automated formatting tool detected (no `.prettierrc`, `.editorconfig`, or PHP-CS-Fixer config)
- 4-space indentation throughout PHP files
- Opening braces on same line for methods and control structures
- Single blank line between class methods
- No ESLint or PHP_CodeSniffer config detected
- JS files use `'use strict'` and are wrapped in IIFEs — `(function () { 'use strict'; ... })()`
- Strict type hints on method parameters and return types — `string $view`, `array $data`, `: void`, `: bool`, `: int`
- Union return types used — `array|bool`, `string|array`
- `mixed` type used for flexible inputs — `mixed $data`, `mixed $default`
## Patterns Used
- Strict separation: `core/` for framework base classes, `app/Controllers/`, `app/Models/`, `app/Views/`
- `Core\Controller` is `abstract` — all controllers extend it
- `Core\Model` is `abstract` — all models extend it
- Views rendered via `$this->render('path/view', $data, $layout)` in `core/Controller.php`
- Single entry point at `public/index.php` — all HTTP requests routed here via `.htaccess`
- Routes registered explicitly using `$router->get()` and `$router->post()` in `public/index.php`
- Each model class owns all SQL for its domain table
- Named methods per query type: `findById()`, `findAllWithRelations()`, `findByEmail()`, `countByStage()`
- PDO prepared statements with named placeholders (`:id`, `:name`) everywhere
- Soft-delete pattern: `is_active = 0` instead of physical DELETE — `softDelete()` in `app/Models/Client.php`
- Middlewares declared per route as string array: `['AuthMiddleware', 'CsrfMiddleware']`
- Executed in sequence before controller dispatch in `core/Router.php`
- Each middleware implements a single `handle(): void` method
- `$this->flash('success'|'error'|'warning'|'info', $message)` sets `$_SESSION['flash']`
- Consumed in the layout view on next request
- Controller methods ending in AJAX calls use `$this->json($data, $status)` from base controller
- Some older AJAX methods manually set `Content-Type: application/json` header and call `json_encode()` directly (inconsistency in `ClientController.php`)
- CSRF token returned in every JSON response to rotate the token client-side
- All JS files are IIFEs with `'use strict'`
- DOM data passed via `data-*` attributes on HTML elements — `data-move-url`, `data-csrf`, `data-client-id`
- Async/await used for Fetch API calls
- JSDoc-style block comments on all functions
## File Organization
- New feature controller: `app/Controllers/FeatureController.php` extending `Core\Controller`
- New model: `app/Models/ModelName.php` extending `Core\Model`
- New views: `app/Views/feature-name/` subdirectory with `index.php`, `create.php`, etc.
- New routes: register in `public/index.php` using `$router->get()` / `$router->post()`
- New middleware: `core/Middleware/NameMiddleware.php` with `handle(): void` method
- New JS for a page: `public/assets/js/feature-name.js` as IIFE
## Comments & Docs
- File-level comment at top of `core/` files explaining the class purpose (block style)
- PHPDoc `/** ... */` blocks on all public and protected methods
- `@param` and `@return` tags used consistently
- Inline comments explain non-obvious logic (SQL filter blocks, session handling, ref month calculation)
- Portuguese used for user-facing messages; English for code identifiers and technical comments (mixed language in comments)
- JSDoc `/** ... */` blocks on named functions
- `@param` with type annotations: `@param {number} clientId`
- Inline comments explain browser quirks and API design decisions
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture

## Overview
## Pattern
- `core/` — Framework layer (Router, Controller base, Model base, Database singleton, Middleware)
- `app/Controllers/` — Application controllers extending `Core\Controller`
- `app/Models/` — Application models extending `Core\Model`
- `app/Views/` — PHP view templates wrapped in layouts
- `config/` — Environment configuration and database credentials
## Data Flow
- Same flow through steps 1–6
- Controller calls `$this->json($data, $status)` instead of `render()`, returning a JSON response and calling `exit`
## Key Components
- `public/index.php`: Front controller — session init, autoloader registration, route registration, dispatch trigger
- `core/Router.php`: Regex-based HTTP router supporting GET/POST, URL parameter extraction (`{id}`, `{slug}`), and middleware chain execution
- `core/Controller.php`: Abstract base class providing `render()`, `redirect()`, `json()`, `flash()`, `requireRole()`, `input()`, `inputRaw()`
- `core/Model.php`: Abstract base class providing `findById()`, `findAll()`, `delete()`, `lastInsertId()` via shared PDO
- `core/Database.php`: Singleton PDO factory — reads `config/database.php`, creates connection once, reuses across all models
- `core/Middleware/AuthMiddleware.php`: Session-based authentication gate — redirects to `/login` if not authenticated
- `core/Middleware/CsrfMiddleware.php`: CSRF token validation for POST requests
- `app/Controllers/`: Nine feature controllers (Auth, Dashboard, Client, ColdContact, Interaction, Pipeline, Task, User, Acompanhamento)
- `app/Models/`: Six domain models (User, Client, ColdContact, Interaction, PipelineStage, Task)
- `app/Views/layouts/main.php`: Primary layout wrapping all authenticated views (header, sidebar, content slot via `$content`)
- `app/Views/layouts/blank.php`: Minimal layout used for auth pages
- `config/app.php`: Defines all path constants (`ROOT_PATH`, `APP_PATH`, `VIEW_PATH`, etc.), loads `.env`, sets `APP_URL`, `APP_ENV`, session and error config
- `config/database.php`: Returns database connection array (host, port, dbname, user, pass) — reads from env vars
- `database/schema.sql`: MySQL schema definition (275 lines) for all tables
## State Management
## Routing
<!-- GSD:architecture-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd:quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd:debug` for investigation and bug fixing
- `/gsd:execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->



<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd:profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
