<?php

namespace App\Controllers;

use Core\Controller;
use Core\Http\ApiResponse;
use Core\Middleware\CsrfMiddleware;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;

class TaskController extends Controller
{
    public function __construct(
        private Task   $tasks   = new Task(),
        private Client $clients = new Client(),
        private User   $users   = new User(),
    ) {}

    /**
     * Retorna tarefas em formato JSON para o FullCalendar.
     */
    public function calendarFeed(array $params = []): void
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
        // FullCalendar envia start/end (janela visível) automaticamente
        // quando "events" é configurado como URL — antes ignorados, o que
        // fazia esse endpoint carregar TODAS as tarefas do tenant a cada abertura.
        $start = $this->parseCalendarDate($_GET['start'] ?? null);
        $end   = $this->parseCalendarDate($_GET['end'] ?? null);
        $tasks = $this->tasks->findForCalendar($userId, $isAdmin, $start, $end);

        $events = array_map(fn($t) => [
            'id'    => $t['id'],
            'title' => (($t['recurrence_type'] !== 'none' || $t['recurrence_parent_id'] !== null) ? '↻ ' : '')
                       . htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'),
            'start' => $t['due_date'],
            'color' => match ($t['priority']) {
                'high'   => '#ef4444',
                'medium' => '#f59e0b',
                default  => '#6366f1',
            },
            'extendedProps' => [
                'status'               => $t['status'],
                'priority'             => $t['priority'],
                'client_id'            => $t['client_id'] ? (int) $t['client_id'] : null,
                'client_name'          => $t['client_name'] ?? null,
                'recurrence_type'      => $t['recurrence_type'],
                'recurrence_parent_id' => $t['recurrence_parent_id'] !== null ? (int) $t['recurrence_parent_id'] : null,
            ],
        ], $tasks);

        $this->json($events);
    }

    /**
     * Converte a data ISO enviada pelo FullCalendar (start/end da janela
     * visível) para o formato DATETIME do MySQL. Retorna null se ausente
     * ou inválida (nesse caso a consulta simplesmente não filtra por data).
     */
    private function parseCalendarDate(?string $raw): ?string
    {
        if (empty($raw)) return null;
        try {
            return (new \DateTime($raw))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retorna dados de uma tarefa em JSON (para modal de edicao).
     */
    public function getTask(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $task = $this->tasks->findById($id);

        if (!$task) {
            $this->json(ApiResponse::error('Tarefa não encontrada.'), 404);
            return;
        }

        // Seller só pode ver detalhes das próprias tarefas (mesma regra usada
        // para editar) — sem isso, GET /api/tasks/{id} vazava tarefas de colegas.
        $role = $_SESSION['user']['role'] ?? '';
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($role === 'seller' && (int) $task['assigned_to'] !== $userId && (int) $task['created_by'] !== $userId) {
            $this->json(ApiResponse::error('Acesso negado.'), 403);
            return;
        }

        $this->json($task);
    }

    /**
     * Autoriza mutação de uma tarefa: viewers nunca podem; sellers só nas
     * próprias (assigned_to ou created_by); admins sempre podem.
     * Em caso de negação já emite a resposta (JSON 403 ou flash+redirect)
     * e retorna false — o caller deve apenas dar return.
     *
     * @param  array  $task  Registro da tarefa
     * @param  bool   $json  Força resposta JSON (endpoints só-AJAX)
     */
    private function authorizeTaskMutation(array $task, bool $json = false): bool
    {
        $role   = $_SESSION['user']['role'] ?? '';
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $denyMessage = null;
        if ($role === 'viewer') {
            $denyMessage = 'Acesso negado: leitores não podem editar tarefas.';
        } elseif ($role === 'seller'
            && (int) $task['assigned_to'] !== $userId
            && (int) $task['created_by'] !== $userId
        ) {
            $denyMessage = 'Acesso negado: você só pode editar suas próprias tarefas.';
        }

        if ($denyMessage === null) {
            return true;
        }

        if ($json || $this->isAjax()) {
            $this->json(ApiResponse::error('Acesso negado.'), 403);
        } else {
            $this->flash('error', $denyMessage);
            $this->redirect('/tasks');
        }
        return false;
    }

    /**
     * Retorna alertas para notificacoes: tarefas atrasadas, tarefas das proximas 24h e aniversarios do dia.
     * Consumido pelo polling JS a cada 60 segundos.
     */
    public function upcoming(array $params = []): void
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

        $tz   = new \DateTimeZone('America/Sao_Paulo');
        $now  = new \DateTime('now', $tz);
        $in15 = (clone $now)->modify('+15 minutes');

        $alerts = [];

        // Tarefas atrasadas (limitadas — endpoint chamado a cada 60s por
        // usuário logado; sem LIMIT, cresce sem controle com a base de dados).
        $overdue = $this->tasks->findOverdue($isAdmin ? null : $userId, 20);
        foreach ($overdue as $t) {
            $due = new \DateTime($t['due_date'], $tz);
            $alerts[] = [
                'key'      => 'task_overdue_' . $t['id'],
                'type'     => 'task',
                'message'  => 'Tarefa atrasada: ' . $t['title'] . ' (venceu ' . $due->format('d/m H:i') . ')',
                'priority' => $t['priority'] ?? null,
            ];
        }

        // Tarefas urgentes (high) com vencimento nos proximos 15 min
        $upcoming = $this->tasks->findUpcoming(
            $userId,
            $now->format('Y-m-d H:i:s'),
            $in15->format('Y-m-d H:i:s'),
            $isAdmin
        );
        foreach ($upcoming as $t) {
            if (($t['priority'] ?? '') !== 'high') continue;
            $dueTime = new \DateTime($t['due_date'], $tz);
            $diffMin = max(0, (int) round(($dueTime->getTimestamp() - $now->getTimestamp()) / 60));
            $alerts[] = [
                'key'      => 'task_' . $t['id'],
                'type'     => 'task',
                'message'  => 'Tarefa urgente em ' . $diffMin . ' min: ' . $t['title'],
                'priority' => $t['priority'],
            ];
        }

        // Busca clientes com aniversário hoje (mesmo dia e mês, qualquer ano)
        $birthdays = $this->clients->findBirthdaysToday();
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
        $filters = [
            'status' => $_GET['status'] ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? '',
            'priority' => $_GET['priority'] ?? '',
        ];

        // Vendedores só veem suas próprias tarefas; admins veem tudo
        $isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $filters['assigned_to'] = $_SESSION['user']['id'];
        }

        $tasks = $this->tasks->findAllWithRelations($filters);
        // Mesma regra do filtro acima — sem isso, o banner de "atrasadas"
        // mostrava tarefas de todos os usuários do tenant para um seller.
        $overdue = $this->tasks->findOverdue($isAdmin ? null : (int) $_SESSION['user']['id']);
        $users = $this->users->findAllActive();

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
        $this->requireRole(['admin', 'seller']);

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

        // client_id é opcional, mas se enviado precisa pertencer ao tenant
        // atual (Client::findById() já é escopado) — sem isso, uma tarefa
        // podia ser criada apontando para o cliente de outro tenant.
        if (!empty($clientId) && !$this->clients->findById((int) $clientId)) {
            $this->flash('error', 'Cliente inválido.');
            $this->redirect('/tasks');
            return;
        }

        // Converte datetime-local para MySQL
        $dueDate = str_replace('T', ' ', $dueDate) . ':00';

        $assignedTo = $this->inputPost('assigned_to') ?: $_SESSION['user']['id'];

        $recurrenceType = $this->inputPost('recurrence_type', 'none');
        if (!in_array($recurrenceType, ['none', 'weekly', 'monthly', 'yearly'], true)) {
            $recurrenceType = 'none';
        }

        $this->tasks->create([
            'client_id'       => $clientId ?: null,
            'assigned_to'     => $assignedTo,
            'title'           => $title,
            'description'     => $this->input('description'),
            'due_date'        => $dueDate,
            'priority'        => $this->inputPost('priority', 'medium'),
            'recurrence_type' => $recurrenceType,
            'created_by'      => $_SESSION['user']['id'],
        ]);

        if ($this->isAjax()) {
            $this->json(ApiResponse::success(token: true));
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
     * Responde a uma validação inválida em update(): JSON para AJAX,
     * flash + redirect para submissão de formulário normal.
     */
    private function rejectTaskUpdate(string $message): void
    {
        if ($this->isAjax()) {
            $this->json(ApiResponse::error($message), 422);
        } else {
            $this->flash('error', $message);
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
        if (isset($_POST['status'])) {
            $status = $this->inputPost('status');
            if (!in_array($status, ['pending', 'in_progress', 'done', 'cancelled'], true)) {
                $this->rejectTaskUpdate('Status inválido.');
                return;
            }
            $data['status'] = $status;
        }
        if (isset($_POST['priority'])) {
            $priority = $this->inputPost('priority');
            if (!in_array($priority, ['low', 'medium', 'high'], true)) {
                $this->rejectTaskUpdate('Prioridade inválida.');
                return;
            }
            $data['priority'] = $priority;
        }
        if (isset($_POST['title']))
            $data['title'] = $this->input('title');
        if (isset($_POST['due_date']))
            $data['due_date'] = str_replace('T', ' ', $this->inputPost('due_date')) . ':00';
        if (isset($_POST['description']))
            $data['description'] = $this->input('description');

        $task = $this->tasks->findById($id);
        if (!$task) {
            $this->redirect('/tasks');
            return;
        }

        if (!$this->authorizeTaskMutation($task)) {
            return;
        }

        $this->tasks->update($id, $data);

        // Se for requisição AJAX, retorna JSON; senão, redireciona
        if ($this->isAjax()) {
            $this->json(ApiResponse::success(token: true));
        } else {
            $this->flash('success', 'Tarefa atualizada!');
            $this->redirect('/tasks');
        }
    }

    /**
     * Remove uma tarefa permanentemente via AJAX.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
     */
    public function destroy(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $task = $this->tasks->findById($id);
        if (!$task) {
            $this->redirect('/tasks');
            return;
        }

        if (!$this->authorizeTaskMutation($task)) {
            return;
        }

        $this->tasks->delete($id);

        if ($this->isAjax()) {
            $this->json(ApiResponse::success(token: true));
        } else {
            $this->flash('success', 'Tarefa removida.');
            $this->redirect('/tasks');
        }
    }

    public function cancelRecurrence(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $task = $this->tasks->findById($id);
        if (!$task) {
            $this->json(ApiResponse::error('Tarefa não encontrada.'), 404);
            return;
        }

        if (!$this->authorizeTaskMutation($task, json: true)) {
            return;
        }

        $parentId = $task['recurrence_parent_id'] !== null
            ? (int) $task['recurrence_parent_id']
            : $id;

        $this->tasks->cancelRecurrence($parentId);
        $this->json(ApiResponse::success(token: true));
    }
}
