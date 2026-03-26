<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;

class TaskController extends Controller
{
    /**
     * Retorna tarefas em formato JSON para o FullCalendar.
     */
    public function calendarFeed(array $params = []): void
    {
        $taskModel = new Task();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
        $tasks = $taskModel->findForCalendar($userId, $isAdmin);

        $events = array_map(fn($t) => [
            'id' => $t['id'],
            'title' => htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'),
            'start' => $t['due_date'],
            'color' => match ($t['priority']) {
                'high' => '#ef4444',
                'medium' => '#f59e0b',
                default => '#6366f1',
            },
            'extendedProps' => ['status' => $t['status'], 'priority' => $t['priority']],
        ], $tasks);

        $this->json($events);
    }

    /**
     * Retorna dados de uma tarefa em JSON (para modal de edicao).
     */
    public function getTask(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $taskModel = new Task();
        $task = $taskModel->findById($id);

        if (!$task) {
            $this->json(['error' => 'Tarefa nao encontrada'], 404);
            return;
        }

        $this->json($task);
    }

    /**
     * Retorna alertas para notificacoes: tarefas nos proximos 15 min e aniversarios do dia.
     * Consumido pelo polling JS a cada 60 segundos.
     */
    public function upcoming(array $params = []): void
    {
        $taskModel = new Task();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

        // Usa timezone America/Sao_Paulo (consistente com config/app.php)
        $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $in15 = (clone $now)->modify('+15 minutes');

        $upcoming = $taskModel->findUpcoming(
            $userId,
            $now->format('Y-m-d H:i:s'),
            $in15->format('Y-m-d H:i:s'),
            $isAdmin
        );

        $alerts = [];
        foreach ($upcoming as $t) {
            $dueTime = new \DateTime($t['due_date'], new \DateTimeZone('America/Sao_Paulo'));
            $diffMin = (int) round(($dueTime->getTimestamp() - $now->getTimestamp()) / 60);

            $alerts[] = [
                'key' => 'task_' . $t['id'],
                'type' => 'task',
                'message' => 'Tarefa em ' . $diffMin . ' min: ' . $t['title'],
                'priority' => $t['priority'],
            ];
        }

        $this->json($alerts);
    }

    /**
     * Lista todas as tarefas com filtros.
     */
    public function index(array $params = []): void
    {
        $taskModel = new Task();
        $userModel = new User();

        $filters = [
            'status' => $_GET['status'] ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? '',
            'priority' => $_GET['priority'] ?? '',
        ];

        // Vendedores só veem suas próprias tarefas; admins veem tudo
        if (($_SESSION['user']['role'] ?? '') === 'seller') {
            $filters['assigned_to'] = $_SESSION['user']['id'];
        }

        $tasks = $taskModel->findAllWithRelations($filters);
        $overdue = $taskModel->findOverdue();
        $users = $userModel->findAllActive();

        $this->render('tasks/index', [
            'pageTitle' => 'Tarefas',
            'title' => 'Tarefas — ' . APP_NAME,
            'tasks' => $tasks,
            'overdue' => $overdue,
            'users' => $users,
            'filters' => $filters,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Cria uma nova tarefa (pode vir da tela de detalhes do cliente ou da tela de tarefas).
     */
    public function store(array $params = []): void
    {
        $title = $this->input('title');
        $dueDate = $this->inputRaw('due_date');
        $clientId = $this->inputRaw('client_id');

        if (empty($title) || empty($dueDate)) {
            $this->flash('error', 'Título e prazo são obrigatórios.');
            // Redireciona de volta para a origem
            $ref = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/tasks';
            header('Location: ' . $ref);
            exit;
        }

        // Converte datetime-local para MySQL
        $dueDate = str_replace('T', ' ', $dueDate) . ':00';

        $assignedTo = $this->inputRaw('assigned_to') ?: $_SESSION['user']['id'];

        $taskModel = new Task();
        $taskModel->create([
            'client_id' => $clientId ?: null,
            'assigned_to' => $assignedTo,
            'title' => $title,
            'description' => $this->input('description'),
            'due_date' => $dueDate,
            'priority' => $this->inputRaw('priority', 'medium'),
            'created_by' => $_SESSION['user']['id'],
        ]);

        $this->flash('success', 'Tarefa criada com sucesso!');

        // Se veio da tela do cliente, volta para ela
        if ($clientId) {
            $this->redirect('/clients/' . (int) $clientId);
        } else {
            $this->redirect('/tasks');
        }
    }

    /**
     * Atualiza campos de uma tarefa (principalmente o status).
     */
    public function update(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = [];
        if (isset($_POST['status']))
            $data['status'] = $this->inputRaw('status');
        if (isset($_POST['priority']))
            $data['priority'] = $this->inputRaw('priority');
        if (isset($_POST['title']))
            $data['title'] = $this->input('title');
        if (isset($_POST['due_date']))
            $data['due_date'] = str_replace('T', ' ', $this->inputRaw('due_date')) . ':00';

        $taskModel = new Task();
        $taskModel->update($id, $data);

        // Se for requisição AJAX, retorna JSON; senão, redireciona
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['success' => true]);
        } else {
            $this->flash('success', 'Tarefa atualizada!');
            $this->redirect('/tasks');
        }
    }

    public function destroy(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $taskModel = new Task();
        $taskModel->delete($id);

        $this->flash('success', 'Tarefa removida.');
        $this->redirect('/tasks');
    }
}
