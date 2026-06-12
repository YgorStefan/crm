<?php
// config/routes.php — Definições de rota da aplicação
// Este arquivo é incluído dentro do escopo de public/index.php após a
// instância de $router ser criada; utiliza $router diretamente (D-05).
//
// Middlewares:
//   - AuthMiddleware é aplicado por padrão a TODAS as rotas; rotas sem
//     login usam public: true.
//   - CspMiddleware é global (toda rota, por último).
//   - Cada rota lista apenas os extras (CsrfMiddleware em todo POST,
//     RateLimitMiddleware no login).
$router->setDefaultMiddlewares(['AuthMiddleware']);
$router->setGlobalMiddlewares(['CspMiddleware']);

// ---- Autenticação (rotas públicas — sem AuthMiddleware) ----
$router->get('/login', 'AuthController', 'loginForm', [], public: true);
$router->post('/login', 'AuthController', 'login', ['RateLimitMiddleware', 'CsrfMiddleware'], public: true);
$router->get('/logout', 'AuthController', 'logout');

// ---- Dashboard ----
$router->get('/dashboard', 'DashboardController', 'index');
$router->get('/', 'DashboardController', 'index');

// ---- Clientes ----
$router->get('/clients', 'ClientController', 'index');
$router->get('/clients/create', 'ClientController', 'create');
$router->post('/clients/store', 'ClientController', 'store', ['CsrfMiddleware']);
$router->get('/clients/{id}', 'ClientController', 'show');
$router->get('/clients/{id}/edit', 'ClientController', 'edit');
$router->post('/clients/{id}/update', 'ClientController', 'update', ['CsrfMiddleware']);
$router->post('/clients/{id}/delete', 'ClientController', 'destroy', ['CsrfMiddleware']);
$router->post('/clients/{id}/update-notes', 'ClientController', 'updateNotes', ['CsrfMiddleware']);

// ---- Cotas de Consórcio (AJAX) ----
$router->post('/clients/{id}/sales', 'ClientController', 'storeSale', ['CsrfMiddleware']);
$router->post('/clients/{id}/sales/{sale_id}/delete', 'ClientController', 'destroySale', ['CsrfMiddleware']);
$router->post('/clients/{id}/sales/{sale_id}/paid', 'ClientController', 'markSalePaid', ['CsrfMiddleware']);

// ---- Pipeline / Kanban ----
$router->get('/pipeline', 'PipelineController', 'index');
$router->post('/pipeline/move', 'PipelineController', 'move', ['CsrfMiddleware']);
$router->get('/pipeline/stages', 'PipelineController', 'stages');
$router->post('/pipeline/stages/store', 'PipelineController', 'storeStage', ['CsrfMiddleware']);
$router->post('/pipeline/stages/{id}/delete', 'PipelineController', 'destroyStage', ['CsrfMiddleware']);
$router->post('/pipeline/stages/{id}/update', 'PipelineController', 'updateStage', ['CsrfMiddleware']);
$router->post('/pipeline/stages/{id}/move', 'PipelineController', 'moveStage', ['CsrfMiddleware']);
$router->post('/pipeline/stages/{id}/toggle-won', 'PipelineController', 'toggleWonStage', ['CsrfMiddleware']);

// ---- Interações ----
$router->post('/interactions/store', 'InteractionController', 'store', ['CsrfMiddleware']);
$router->post('/interactions/{id}/update', 'InteractionController', 'update', ['CsrfMiddleware']);
$router->post('/interactions/{id}/delete', 'InteractionController', 'destroy', ['CsrfMiddleware']);

// ---- Tarefas ----
$router->get('/tasks', 'TaskController', 'index');
$router->post('/tasks/store', 'TaskController', 'store', ['CsrfMiddleware']);
$router->post('/tasks/{id}/update', 'TaskController', 'update', ['CsrfMiddleware']);
$router->post('/tasks/{id}/delete', 'TaskController', 'destroy', ['CsrfMiddleware']);
$router->post('/tasks/{id}/cancel-recurrence', 'TaskController', 'cancelRecurrence', ['CsrfMiddleware']);

// ---- API AJAX — dados para calendário de tarefas ----
$router->get('/api/tasks/upcoming', 'TaskController', 'upcoming');
$router->get('/api/tasks/calendar', 'TaskController', 'calendarFeed');
$router->get('/api/tasks/{id}', 'TaskController', 'getTask');

// ---- API AJAX — dados para gráficos do dashboard ----
$router->get('/api/dashboard/stats', 'DashboardController', 'stats');

// ---- Administração (painel unificado com abas) ----
$router->get('/admin', 'AdminController', 'index');

// ---- Administração de Usuários (somente admin) ----
$router->get('/admin/users', 'UserController', 'index');
$router->get('/admin/users/create', 'UserController', 'create');
$router->post('/admin/users/store', 'UserController', 'store', ['CsrfMiddleware']);
$router->get('/admin/users/{id}/edit', 'UserController', 'edit');
$router->post('/admin/users/{id}/update', 'UserController', 'update', ['CsrfMiddleware']);
$router->post('/admin/users/{id}/delete', 'UserController', 'destroy', ['CsrfMiddleware']);

// ---- Contatos Frios ----
$router->get('/cold-contacts', 'ColdContactController', 'index');
$router->post('/cold-contacts/import', 'ColdContactController', 'import', ['CsrfMiddleware']);
$router->get('/cold-contacts/list', 'ColdContactController', 'listJson');
$router->get('/cold-contacts/export', 'ColdContactController', 'export');
$router->post('/cold-contacts/bulk-update', 'ColdContactController', 'bulkUpdate', ['CsrfMiddleware']);
$router->post('/cold-contacts/month/{year_month}/delete', 'ColdContactController', 'deleteMonth', ['CsrfMiddleware']);
$router->post('/cold-contacts/{id}/update', 'ColdContactController', 'update', ['CsrfMiddleware']);
$router->post('/cold-contacts/{id}/delete', 'ColdContactController', 'destroy', ['CsrfMiddleware']);

// ---- Acompanhamento (Dashboard Lista Fria) ----
$router->get('/acompanhamento', 'AcompanhamentoController', 'index');

// ---- Configurações do Tenant ----
$router->get('/settings', 'SettingsController', 'index');
$router->post('/settings/update', 'SettingsController', 'update', ['CsrfMiddleware']);

// ---- Prospecção de Leads ----
$router->get('/prospecting', 'ProspectingController', 'index');
$router->post('/api/prospecting/search', 'ProspectingController', 'search', ['CsrfMiddleware']);
