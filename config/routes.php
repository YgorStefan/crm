<?php
// config/routes.php — Definições de rota da aplicação
// Este arquivo é incluído dentro do escopo de public/index.php após a
// instância de $router ser criada; utiliza $router diretamente (D-05).

// ---- Autenticação (rotas públicas — sem middleware) ----
$router->get('/login', 'AuthController', 'loginForm', ['CspMiddleware']);
$router->post('/login', 'AuthController', 'login', ['RateLimitMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->get('/logout', 'AuthController', 'logout', ['AuthMiddleware', 'CspMiddleware']);

// ---- Dashboard ----
$router->get('/dashboard', 'DashboardController', 'index', ['AuthMiddleware', 'CspMiddleware']);
$router->get('/', 'DashboardController', 'index', ['AuthMiddleware', 'CspMiddleware']);

// ---- Clientes ----
$router->get('/clients', 'ClientController', 'index', ['AuthMiddleware', 'CspMiddleware']);
$router->get('/clients/create', 'ClientController', 'create', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/clients/store', 'ClientController', 'store', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->get('/clients/{id}', 'ClientController', 'show', ['AuthMiddleware', 'CspMiddleware']);
$router->get('/clients/{id}/edit', 'ClientController', 'edit', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/clients/{id}/update', 'ClientController', 'update', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/clients/{id}/delete', 'ClientController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/clients/{id}/update-notes', 'ClientController', 'updateNotes', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);

// ---- Cotas de Consórcio (AJAX) ----
$router->post('/clients/{id}/sales', 'ClientController', 'storeSale', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/clients/{id}/sales/{sale_id}/delete', 'ClientController', 'destroySale', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/clients/{id}/sales/{sale_id}/paid', 'ClientController', 'markSalePaid', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);

// ---- Pipeline / Kanban ----
$router->get('/pipeline', 'PipelineController', 'index', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/pipeline/move', 'PipelineController', 'move', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->get('/pipeline/stages', 'PipelineController', 'stages', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/pipeline/stages/store', 'PipelineController', 'storeStage', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/pipeline/stages/{id}/delete', 'PipelineController', 'destroyStage', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/pipeline/stages/{id}/update', 'PipelineController', 'updateStage', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/pipeline/stages/{id}/move', 'PipelineController', 'moveStage', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/pipeline/stages/{id}/toggle-won', 'PipelineController', 'toggleWonStage', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);

// ---- Interações ----
$router->post('/interactions/store', 'InteractionController', 'store', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/interactions/{id}/update', 'InteractionController', 'update', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/interactions/{id}/delete', 'InteractionController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);

// ---- Tarefas ----
$router->get('/tasks', 'TaskController', 'index', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/tasks/store', 'TaskController', 'store', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/tasks/{id}/update', 'TaskController', 'update', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/tasks/{id}/delete', 'TaskController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);

// ---- API AJAX — dados para calendário de tarefas ----
$router->get('/api/tasks/upcoming', 'TaskController', 'upcoming', ['AuthMiddleware', 'CspMiddleware']);
$router->get('/api/tasks/calendar', 'TaskController', 'calendarFeed', ['AuthMiddleware', 'CspMiddleware']);
$router->get('/api/tasks/{id}', 'TaskController', 'getTask', ['AuthMiddleware', 'CspMiddleware']);

// ---- API AJAX — dados para gráficos do dashboard ----
$router->get('/api/dashboard/stats', 'DashboardController', 'stats', ['AuthMiddleware', 'CspMiddleware']);

// ---- Administração de Usuários (somente admin) ----
$router->get('/admin/users', 'UserController', 'index', ['AuthMiddleware', 'CspMiddleware']);
$router->get('/admin/users/create', 'UserController', 'create', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/admin/users/store', 'UserController', 'store', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->get('/admin/users/{id}/edit', 'UserController', 'edit', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/admin/users/{id}/update', 'UserController', 'update', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/admin/users/{id}/delete', 'UserController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);

// ---- Contatos Frios ----
$router->get('/cold-contacts', 'ColdContactController', 'index', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/cold-contacts/import', 'ColdContactController', 'import', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->get('/cold-contacts/list', 'ColdContactController', 'listJson', ['AuthMiddleware', 'CspMiddleware']);
$router->get('/cold-contacts/export', 'ColdContactController', 'export', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/cold-contacts/bulk-update', 'ColdContactController', 'bulkUpdate', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/cold-contacts/month/{year_month}/delete', 'ColdContactController', 'deleteMonth', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/cold-contacts/{id}/update', 'ColdContactController', 'update', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/cold-contacts/{id}/delete', 'ColdContactController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);

// ---- Acompanhamento (Dashboard Lista Fria) ----
$router->get('/acompanhamento', 'AcompanhamentoController', 'index', ['AuthMiddleware', 'CspMiddleware']);

// ---- Configurações do Tenant ----
$router->get('/settings', 'SettingsController', 'index', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/settings/update', 'SettingsController', 'update', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
