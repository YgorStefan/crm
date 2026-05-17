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
            'id'    => $t['id'],
            'title' => htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'),
            'start' => $t['due_date'],
            'color' => match ($t['priority']) {
                'high'   => '#ef4444',
                'medium' => '#f59e0b',
                default  => '#6366f1',
            },
            'extendedProps' => [
                'status'      => $t['status'],
                'priority'    => $t['priority'],
                'client_id'   => $t['client_id'] ? (int) $t['client_id'] : null,
                'client_name' => $t['client_name'] ?? null,
            ],
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
     * Retorna alertas para notificacoes: tarefas atrasadas, tarefas das proximas 24h e aniversarios do dia.
     * Consumido pelo polling JS a cada 60 segundos.
     */
    public function upcoming(array $params = []): void
    {
        $taskModel = new Task();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

        $tz   = new \DateTimeZone('America/Sao_Paulo');
        $now  = new \DateTime('now', $tz);
        $in24 = (clone $now)->modify('+24 hours');

        $alerts = [];

        // Tarefas atrasadas (status pending/in_progress, due_date no passado, limite 7 dias)
        $overdue = $taskModel->findOverdue($isAdmin ? null : $userId);
        $cutoff  = (clone $now)->modify('-7 days');
        foreach ($overdue as $t) {
            $due = new \DateTime($t['due_date'], $tz);
            if ($due < $cutoff) continue;
            $alerts[] = [
                'key'      => 'task_overdue_' . $t['id'],
                'type'     => 'task',
                'message'  => 'Tarefa atrasada: ' . $t['title'] . ' (venceu ' . $due->format('d/m H:i') . ')',
                'priority' => $t['priority'] ?? null,
            ];
        }

        // Tarefas com vencimento nas proximas 24h
        $upcoming = $taskModel->findUpcoming(
            $userId,
            $now->format('Y-m-d H:i:s'),
            $in24->format('Y-m-d H:i:s'),
            $isAdmin
        );
        foreach ($upcoming as $t) {
            $dueTime = new \DateTime($t['due_date'], $tz);
            $diffMin = (int) round(($dueTime->getTimestamp() - $now->getTimestamp()) / 60);

            if ($diffMin <= 60) {
                $msg = 'Tarefa em ' . max(0, $diffMin) . ' min: ' . $t['title'];
            } elseif ($dueTime->format('Y-m-d') === $now->format('Y-m-d')) {
                $msg = 'Tarefa hoje as ' . $dueTime->format('H:i') . ': ' . $t['title'];
            } else {
                $msg = 'Tarefa amanha as ' . $dueTime->format('H:i') . ': ' . $t['title'];
            }

            $alerts[] = [
                'key'      => 'task_' . $t['id'],
                'type'     => 'task',
                'message'  => $msg,
                'priority' => $t['priority'],
            ];
        }

        // Busca clientes com aniversário hoje (mesmo dia e mês, qualquer ano)
        $clientModel = new Client();
        $birthdays = $clientModel->findBirthdaysToday();
        foreach ($birthdays as $c) {
            $alerts[] = [
                'key'     => 'birthday_' . $c['id'],
                'type'    => 'birthday',
                'message' => 'Aniversario de ' . $c['name'] . ' hoje!',
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
        $dueDate = $this->inputPost('due_date');
        $clientId = $this->inputPost('client_id');

        if (empty($title) || empty($dueDate)) {
            $this->flash('error', 'Título e prazo são obrigatórios.');
            // Redireciona para a tela de tarefas (Referer header é forjável)
            $clientId = $this->inputPost('client_id');
            if ($clientId) {
                $this->redirect('/clients/' . (int) $clientId);
            } else {
                $this->redirect('/tasks');
            }
            return;
        }

        // Converte datetime-local para MySQL
        $dueDate = str_replace('T', ' ', $dueDate) . ':00';

        $assignedTo = $this->inputPost('assigned_to') ?: $_SESSION['user']['id'];

        $taskModel = new Task();
        $taskModel->create([
            'client_id' => $clientId ?: null,
            'assigned_to' => $assignedTo,
            'title' => $title,
            'description' => $this->input('description'),
            'due_date' => $dueDate,
            'priority' => $this->inputPost('priority', 'medium'),
            'created_by' => $_SESSION['user']['id'],
        ]);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['success' => true, 'csrf_token' => CsrfMiddleware::getToken()]);
            return;
        }

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
        // Check role: viewer and seller authorization
        $role = $_SESSION['user']['role'] ?? '';
        $viewer = ($role === 'viewer');
        $seller = ($role === 'seller');
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $data = [];
        if (isset($_POST['status']))
            $data['status'] = $this->inputPost('status');
        if (isset($_POST['priority']))
            $data['priority'] = $this->inputPost('priority');
        if (isset($_POST['title']))
            $data['title'] = $this->input('title');
        if (isset($_POST['due_date']))
            $data['due_date'] = str_replace('T', ' ', $this->inputPost('due_date')) . ':00';

        $taskModel = new Task();
        $task = $taskModel->findById($id);
        if (!$task) {
            $this->redirect('/tasks');
            return;
        }

        if ($viewer) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
            } else {
                $this->flash('error', 'Acesso negado: leitores não podem editar tarefas.');
                $this->redirect('/tasks');
            }
            return;
        }

        if ($seller && (int) $task['assigned_to'] !== $userId && (int) $task['created_by'] !== $userId) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
            } else {
                $this->flash('error', 'Acesso negado: você só pode editar suas próprias tarefas.');
                $this->redirect('/tasks');
            }
            return;
        }

        $taskModel->update($id, $data);

        // Se for requisição AJAX, retorna JSON; senão, redireciona
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['success' => true, 'csrf_token' => CsrfMiddleware::getToken()]);
        } else {
            $this->flash('success', 'Tarefa atualizada!');
            $this->redirect('/tasks');
        }
    }

    public function destroy(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        // Check role: viewer and seller authorization
        $role = $_SESSION['user']['role'] ?? '';
        $viewer = ($role === 'viewer');
        $seller = ($role === 'seller');
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $taskModel = new Task();
        $task = $taskModel->findById($id);
        if (!$task) {
            $this->redirect('/tasks');
            return;
        }

        if ($viewer) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
            } else {
                $this->flash('error', 'Acesso negado: leitores não podem editar tarefas.');
                $this->redirect('/tasks');
            }
            return;
        }

        if ($seller && (int) $task['assigned_to'] !== $userId && (int) $task['created_by'] !== $userId) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
            } else {
                $this->flash('error', 'Acesso negado: você só pode editar suas próprias tarefas.');
                $this->redirect('/tasks');
            }
            return;
        }

        $taskModel->delete($id);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['success' => true, 'csrf_token' => CsrfMiddleware::getToken()]);
        } else {
            $this->flash('success', 'Tarefa removida.');
            $this->redirect('/tasks');
        }
    }
}
