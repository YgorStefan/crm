<?php
// ============================================================
// app/Controllers/TaskController.php — Módulo de Tarefas
// ============================================================

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;

class TaskController extends Controller
{
    /**
     * GET /tasks
     * Lista todas as tarefas com filtros.
     */
    public function index(array $params = []): void
    {
        $taskModel = new Task();
        $userModel = new User();

        $filters = [
            'status'      => $_GET['status']      ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? '',
            'priority'    => $_GET['priority']    ?? '',
        ];

        // Vendedores só veem suas próprias tarefas; admins veem tudo
        if (($_SESSION['user']['role'] ?? '') === 'seller') {
            $filters['assigned_to'] = $_SESSION['user']['id'];
        }

        $tasks    = $taskModel->findAllWithRelations($filters);
        $overdue  = $taskModel->findOverdue();
        $users    = $userModel->findAllActive();

        $this->render('tasks/index', [
            'pageTitle'  => 'Tarefas',
            'title'      => 'Tarefas — ' . APP_NAME,
            'tasks'      => $tasks,
            'overdue'    => $overdue,
            'users'      => $users,
            'filters'    => $filters,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * POST /tasks/store
     * Cria uma nova tarefa (pode vir da tela de detalhes do cliente ou da tela de tarefas).
     */
    public function store(array $params = []): void
    {
        $title    = $this->input('title');
        $dueDate  = $this->inputRaw('due_date');
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
            'client_id'   => $clientId ?: null,
            'assigned_to' => $assignedTo,
            'title'       => $title,
            'description' => $this->input('description'),
            'due_date'    => $dueDate,
            'priority'    => $this->inputRaw('priority', 'medium'),
            'created_by'  => $_SESSION['user']['id'],
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
     * POST /tasks/{id}/update  (AJAX ou form normal)
     * Atualiza campos de uma tarefa (principalmente o status).
     */
    public function update(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = [];
        if (isset($_POST['status']))   $data['status']   = $this->inputRaw('status');
        if (isset($_POST['priority'])) $data['priority'] = $this->inputRaw('priority');
        if (isset($_POST['title']))    $data['title']    = $this->input('title');
        if (isset($_POST['due_date'])) $data['due_date'] = str_replace('T', ' ', $this->inputRaw('due_date')) . ':00';

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

    /**
     * POST /tasks/{id}/delete
     */
    public function destroy(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $taskModel = new Task();
        $taskModel->delete($id);

        $this->flash('success', 'Tarefa removida.');
        $this->redirect('/tasks');
    }
}
