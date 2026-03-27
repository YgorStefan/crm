<?php
// public/index.php — Front Controller (Único Ponto de Entrada)
// TODAS as requisições HTTP passam por aqui, graças ao .htaccess.
// Este arquivo é responsável por:
//   1. Iniciar a sessão PHP de forma segura
//   2. Carregar as configurações globais
//   3. Registrar o autoloader de classes (PSR-4)
//   4. Registrar todas as rotas da aplicação
//   5. Despachar a requisição para o Router

// Configurações de segurança ANTES de session_start():
ini_set('session.cookie_httponly', '1');  // Cookie inacessível por JavaScript (previne XSS)
ini_set('session.cookie_samesite', 'Strict'); // Previne envio em requisições cross-site (CSRF)
ini_set('session.use_strict_mode', '1'); // Rejeita IDs de sessão não inicializados pelo servidor
// Em produção com HTTPS, ativar também:
// ini_set('session.cookie_secure', '1');

session_name('crm_session');
session_start();

// Configurações globais
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

// Autoloader PSR-4
// Em vez de incluir cada arquivo manualmente com require_once,
// o autoloader carrega automaticamente qualquer classe quando ela
// é instanciada pela primeira vez.
//
// Mapeamento de namespaces para diretórios:
//   Core\Database       → core/Database.php
//   App\Controllers\... → app/Controllers/....php
//   App\Models\...      → app/Models/....php
spl_autoload_register(function (string $className): void {
    // Substitui \ por / (ou \ no Windows) para montar o caminho do arquivo
    $relativePath = str_replace('\\', DS, $className) . '.php';

    // Mapeamento: namespace raiz → diretório físico
    $namespaceMap = [
        'Core' . DS => CORE_PATH . DS,
        'App' . DS => APP_PATH . DS,
    ];

    foreach ($namespaceMap as $prefix => $baseDir) {
        if (str_starts_with($relativePath, $prefix)) {
            // Remove o prefixo do namespace e monta o caminho completo
            $file = $baseDir . substr($relativePath, strlen($prefix));
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Registro das Rotas
// Importa o Router e declara todas as URLs da aplicação
use Core\Router;

$router = new Router();

// ---- Autenticação (rotas públicas — sem middleware) ----
$router->get('/login', 'AuthController', 'loginForm');
$router->post('/login', 'AuthController', 'login', ['CsrfMiddleware']);
$router->get('/logout', 'AuthController', 'logout', ['AuthMiddleware']);

// ---- Dashboard ----
$router->get('/dashboard', 'DashboardController', 'index', ['AuthMiddleware']);
$router->get('/', 'DashboardController', 'index', ['AuthMiddleware']);

// ---- Clientes ----
$router->get('/clients', 'ClientController', 'index', ['AuthMiddleware']);
$router->get('/clients/create', 'ClientController', 'create', ['AuthMiddleware']);
$router->post('/clients/store', 'ClientController', 'store', ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/clients/{id}', 'ClientController', 'show', ['AuthMiddleware']);
$router->get('/clients/{id}/edit', 'ClientController', 'edit', ['AuthMiddleware']);
$router->post('/clients/{id}/update', 'ClientController', 'update', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/clients/{id}/delete', 'ClientController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware']);

// ---- Cotas de Consórcio (AJAX) ----
$router->post('/clients/{id}/sales', 'ClientController', 'storeSale', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/clients/{id}/sales/{sale_id}/delete', 'ClientController', 'destroySale', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/clients/{id}/sales/{sale_id}/paid', 'ClientController', 'markSalePaid', ['AuthMiddleware', 'CsrfMiddleware']);

// ---- Pipeline / Kanban ----
$router->get('/pipeline', 'PipelineController', 'index', ['AuthMiddleware']);
$router->post('/pipeline/move', 'PipelineController', 'move', ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/pipeline/stages', 'PipelineController', 'stages', ['AuthMiddleware']);
$router->post('/pipeline/stages/store', 'PipelineController', 'storeStage', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/pipeline/stages/{id}/delete', 'PipelineController', 'destroyStage', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/pipeline/stages/{id}/update', 'PipelineController', 'updateStage', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/pipeline/stages/{id}/move', 'PipelineController', 'moveStage', ['AuthMiddleware', 'CsrfMiddleware']);

// ---- Interações ----
$router->post('/interactions/store', 'InteractionController', 'store', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/interactions/{id}/delete', 'InteractionController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware']);

// ---- Tarefas ----
$router->get('/tasks', 'TaskController', 'index', ['AuthMiddleware']);
$router->post('/tasks/store', 'TaskController', 'store', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/tasks/{id}/update', 'TaskController', 'update', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/tasks/{id}/delete', 'TaskController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware']);

// ---- API AJAX — dados para calendário de tarefas ----
$router->get('/api/tasks/upcoming', 'TaskController', 'upcoming', ['AuthMiddleware']);
$router->get('/api/tasks/calendar', 'TaskController', 'calendarFeed', ['AuthMiddleware']);
$router->get('/api/tasks/{id}', 'TaskController', 'getTask', ['AuthMiddleware']);

// ---- API AJAX — dados para gráficos do dashboard ----
$router->get('/api/dashboard/stats', 'DashboardController', 'stats', ['AuthMiddleware']);

// ---- Administração de Usuários (somente admin) ----
$router->get('/admin/users', 'UserController', 'index', ['AuthMiddleware']);
$router->get('/admin/users/create', 'UserController', 'create', ['AuthMiddleware']);
$router->post('/admin/users/store', 'UserController', 'store', ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/admin/users/{id}/edit', 'UserController', 'edit', ['AuthMiddleware']);
$router->post('/admin/users/{id}/update', 'UserController', 'update', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/admin/users/{id}/delete', 'UserController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware']);

// ---- Contatos Frios ----
$router->get('/cold-contacts', 'ColdContactController', 'index', ['AuthMiddleware']);
$router->post('/cold-contacts/import', 'ColdContactController', 'import', ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/cold-contacts/list', 'ColdContactController', 'listJson', ['AuthMiddleware']);
$router->get('/cold-contacts/export', 'ColdContactController', 'export', ['AuthMiddleware']);
$router->post('/cold-contacts/bulk-update', 'ColdContactController', 'bulkUpdate', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/cold-contacts/month/{yearMonth}/delete', 'ColdContactController', 'deleteMonth', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/cold-contacts/{id}/update', 'ColdContactController', 'update', ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/cold-contacts/{id}/delete', 'ColdContactController', 'destroy', ['AuthMiddleware', 'CsrfMiddleware']);

// ---- Acompanhamento (Dashboard Lista Fria) ----
$router->get('/acompanhamento', 'AcompanhamentoController', 'index', ['AuthMiddleware']);

// Despacha a requisição
// O Router compara a URL atual com os padrões registrados acima
// e executa o Controller/Action correspondente.
$router->dispatch();
