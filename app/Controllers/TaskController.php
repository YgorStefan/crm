<?php

namespace App\Controllers;

use Core\Controller;
use Core\Http\ApiResponse;
use Core\Logger;
use Core\Middleware\CsrfMiddleware;
use App\Models\GoogleAccount;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Services\Google\GoogleCalendarSync;
use App\Services\Google\GoogleConfig;

class TaskController extends Controller
{
    private const GOOGLE_EVENT_COLOR = '#64748b';
    // o fullcalendar manda a janela com o offset do navegador, e sem ele a
    // lista do google pediria horarios deslocados.
    private const FEED_INSTANT_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})\z/';

    private ?GoogleCalendarSync $sync = null;

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
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $isAdmin = $role === 'admin';

        $googleStatus = null;
        // a spec manda qualquer falha nao prevista do lado do google cair no
        // feed de sempre, com o aviso de degradado.
        try {
            $connection = self::googleBarState(
                GoogleConfig::fromEnv()->isConfigured(),
                $role,
                static fn (): array|false => (new GoogleAccount())->findForUser($userId),
            )['connection'];
            $window = self::parseFeedWindow($_GET['start'] ?? null, $_GET['end'] ?? null);

            if ($connection === 'active' && $window !== null) {
                $sync = $this->sync();
                // o google pode levar segundos, e a sessao aberta seguraria as
                // outras requisicoes deste usuario ate o feed terminar.
                session_write_close();
                $result = $sync->window($userId, $isAdmin, $window[0], $window[1], ($_GET['team'] ?? '') === '1');

                header('X-CRM-Google-Status: ' . $result['status']);
                $this->json([
                    ...array_map(self::calendarTaskEvent(...), $result['tasks']),
                    ...array_map(self::calendarTeamEvent(...), $result['team']),
                    ...array_map(self::calendarGoogleEvent(...), $result['foreign']),
                ]);
                return;
            }

            // a conta revogada nao chama o google, mas a tela precisa do pedido de reconexao.
            if ($connection === 'revoked') {
                $googleStatus = 'reconnect';
            }
        } catch (\Throwable $e) {
            (new Logger())->error('[TaskController] feed do calendario sem o google', [
                'user_id'   => $userId,
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
            $googleStatus = 'degraded';
        }

        if ($googleStatus !== null) {
            header('X-CRM-Google-Status: ' . $googleStatus);
        }

        // FullCalendar envia start/end (janela visível) automaticamente
        // quando "events" é configurado como URL — antes ignorados, o que
        // fazia esse endpoint carregar TODAS as tarefas do tenant a cada abertura.
        $start = self::parseCalendarDate($_GET['start'] ?? null);
        $end   = self::parseCalendarDate($_GET['end'] ?? null);
        $tasks = $this->tasks->findForCalendar($userId, $isAdmin, $start, $end);

        $this->json(array_map(self::calendarTaskEvent(...), $tasks));
    }

    /**
     * @param array<string, mixed> $task linha do Task::findForCalendar
     * @return array<string, mixed>
     */
    public static function calendarTaskEvent(array $task): array
    {
        return [
            'id'    => $task['id'],
            'title' => (($task['recurrence_type'] !== 'none' || $task['recurrence_parent_id'] !== null) ? '↻ ' : '')
                       . htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'),
            'start' => $task['due_date'],
            'color' => match ($task['priority']) {
                'high'   => '#ef4444',
                'medium' => '#f59e0b',
                default  => '#6366f1',
            },
            'extendedProps' => [
                'status'               => $task['status'],
                'priority'             => $task['priority'],
                'client_id'            => $task['client_id'] ? (int) $task['client_id'] : null,
                'client_name'          => $task['client_name'] ?? null,
                'recurrence_type'      => $task['recurrence_type'],
                'recurrence_parent_id' => $task['recurrence_parent_id'] !== null ? (int) $task['recurrence_parent_id'] : null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $task linha do Task::findTeamForCalendar
     * @return array<string, mixed>
     */
    public static function calendarTeamEvent(array $task): array
    {
        $event = self::calendarTaskEvent($task);
        $event['extendedProps']['assigned_name'] = $task['assigned_name'] ?? null;

        return $event;
    }

    /**
     * o titulo vai sem escape porque a tela o poe como texto.
     *
     * @param array<string, mixed> $item item foreign do GoogleCalendarSync::window
     * @return array<string, mixed>
     */
    public static function calendarGoogleEvent(array $item): array
    {
        $allDay = $item['all_day'] === true;
        $event  = [
            'id'    => $item['id'],
            'title' => $item['title'],
            'start' => $item['start'],
            'end'   => $item['end'],
            'color' => self::GOOGLE_EVENT_COLOR,
        ];
        if ($allDay) {
            $event['allDay'] = true;
        }
        $event['extendedProps'] = [
            'source'      => 'google',
            'google_id'   => $item['google_id'],
            'can_edit'    => $item['can_edit'] === true,
            'recurring'   => $item['recurring'] === true,
            'html_link'   => $item['html_link'],
            'description' => $item['description'],
            'all_day'     => $allDay,
        ];

        return $event;
    }

    /** @return array{0:\DateTimeImmutable,1:\DateTimeImmutable}|null */
    public static function parseFeedWindow(mixed $start, mixed $end): ?array
    {
        $from = self::parseFeedInstant($start);
        $to   = self::parseFeedInstant($end);

        return $from !== null && $to !== null && $to > $from ? [$from, $to] : null;
    }

    private static function parseFeedInstant(mixed $raw): ?\DateTimeImmutable
    {
        if (!is_string($raw) || preg_match(self::FEED_INSTANT_PATTERN, $raw) !== 1) {
            return null;
        }
        try {
            $at = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
        // o php aceita o dia 31 de fevereiro e o empurra para marco com um aviso.
        $errors = \DateTimeImmutable::getLastErrors();

        return $errors === false || $errors['warning_count'] === 0 ? $at : null;
    }

    private function sync(): GoogleCalendarSync
    {
        return $this->sync ??= GoogleCalendarSync::default();
    }

    private function drainAfterSave(): void
    {
        // a tarefa ja foi gravada, e um erro aqui levaria a pessoa a salvar de novo.
        try {
            if (GoogleConfig::fromEnv()->isConfigured()) {
                $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, (int) ($_SESSION['user']['id'] ?? 0));
            }
        } catch (\Throwable $e) {
            (new Logger())->error('[TaskController] envio ao google depois de salvar', [
                'user_id'   => (int) ($_SESSION['user']['id'] ?? 0),
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Converte a data ISO enviada pelo FullCalendar (start/end da janela
     * visível) para o formato DATETIME do MySQL. Retorna null se ausente
     * ou inválida (nesse caso a consulta simplesmente não filtra por data).
     */
    public static function parseCalendarDate(mixed $raw): ?string
    {
        // a query aceita start[]=x, e o array derrubaria o feed com erro 500.
        if (!is_string($raw) || empty($raw)) return null;
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

        $userId = (int) $_SESSION['user']['id'];
        $google = self::googleBarState(
            \App\Services\Google\GoogleConfig::fromEnv()->isConfigured(),
            (string) ($_SESSION['user']['role'] ?? ''),
            static fn (): array|false => (new \App\Models\GoogleAccount())->findForUser($userId),
        );

        $this->render('tasks/index', [
            'title' => 'Tarefas — ' . APP_NAME,
            'tasks' => $tasks,
            'overdue' => $overdue,
            'users' => $users,
            'filters' => $filters,
            'csrf_token' => CsrfMiddleware::getToken(),
            'googleEnabled' => $google['enabled'],
            'googleConnection' => $google['connection'],
            'googleEmail' => $google['email'],
        ]);
    }

    /**
     * @param \Closure(): (array|false) $findAccount
     * @return array{enabled: bool, connection: ?string, email: ?string}
     */
    public static function googleBarState(bool $configured, string $role, \Closure $findAccount): array
    {
        $none = ['enabled' => false, 'connection' => null, 'email' => null];
        if (!$configured || !in_array($role, ['admin', 'seller'], true)) {
            return $none;
        }

        // conta desconectada guarda o google_email para comparar na proxima
        // conexao, entao so o status diz se ela ainda esta ligada.
        $account = $findAccount();
        $status = is_array($account) ? ($account['status'] ?? null) : null;
        if ($status !== 'active' && $status !== 'revoked') {
            return ['enabled' => true, 'connection' => null, 'email' => null];
        }

        return ['enabled' => true, 'connection' => $status, 'email' => (string) ($account['google_email'] ?? '')];
    }

    public static function assigneeForStore(mixed $posted, string $role, int $sessionUserId): int
    {
        // um seller que mandasse o id de um colega gravaria na agenda do google dele.
        if ($role !== 'admin') {
            return $sessionUserId;
        }

        return self::postedUserId($posted) ?? $sessionUserId;
    }

    public static function assigneeChange(mixed $posted, string $role, int $currentAssignee): ?int
    {
        if ($role !== 'admin') {
            return null;
        }

        $newAssignee = self::postedUserId($posted);

        return $newAssignee !== null && $newAssignee !== $currentAssignee ? $newAssignee : null;
    }

    private static function postedUserId(mixed $posted): ?int
    {
        $id = filter_var($posted, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
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
            $this->rejectTaskRequest('Título e prazo são obrigatórios.');
            return;
        }

        $dueDate = self::parseDueDate((string) $dueDate);
        if ($dueDate === null) {
            $this->rejectTaskRequest('Prazo inválido.');
            return;
        }

        $priority = $this->inputPost('priority', 'medium');
        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            $this->rejectTaskRequest('Prioridade inválida.');
            return;
        }

        $assignedTo = self::assigneeForStore(
            $this->inputPost('assigned_to'),
            (string) ($_SESSION['user']['role'] ?? ''),
            (int) ($_SESSION['user']['id'] ?? 0),
        );

        $recurrenceType = $this->inputPost('recurrence_type', 'none');
        if (!in_array($recurrenceType, ['none', 'weekly', 'monthly', 'yearly'], true)) {
            $recurrenceType = 'none';
        }

        try {
            $this->tasks->create([
                'client_id'       => $clientId ?: null,
                'assigned_to'     => $assignedTo,
                'title'           => $title,
                'description'     => $this->input('description'),
                'due_date'        => $dueDate,
                'priority'        => $priority,
                'recurrence_type' => $recurrenceType,
                'created_by'      => $_SESSION['user']['id'],
            ]);
        } catch (\InvalidArgumentException $e) {
            if ($e->getCode() === Task::INVALID_CLIENT) {
                $this->rejectTaskRequest('Cliente inválido.');
            } elseif ($e->getCode() === Task::INVALID_ASSIGNEE) {
                // o task::create confere o cliente antes do responsavel, entao aqui o cliente ja e valido.
                $this->rejectTaskRequest('Responsável inválido.', $clientId ? '/clients/' . (int) $clientId : '/tasks');
            } else {
                throw $e;
            }
            return;
        }

        $this->drainAfterSave();

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

    // o espaco no lugar do t continua aceito porque o cadastro ja aceitava as duas
    // formas antes, e o que nao casar o banco recusaria com erro 500. o utc so serve
    // para guardar o texto como veio: no fuso do app, um horario que nao existe por
    // causa do horario de verao seria empurrado uma hora para frente.
    private static function parseDueDate(string $raw): ?string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', str_replace('T', ' ', $raw), new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && $errors['warning_count'] + $errors['error_count'] > 0)) {
            return null;
        }

        return $parsed->format('Y-m-d H:i:s');
    }

    // o ajax recebe o erro em json e nunca redirect, porque o fetch segue o 302,
    // chega a um 200 e a tela fecharia o modal como se a tarefa tivesse sido salva.
    private function rejectTaskRequest(string $message, string $redirectTo = '/tasks', int $status = 422): void
    {
        if ($this->isAjax()) {
            $this->json(ApiResponse::error($message), $status);
        } else {
            $this->flash('error', $message);
            $this->redirect($redirectTo);
        }
    }

    /**
     * Atualiza campos de uma tarefa (principalmente o status).
     */
    public function update(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $task = $this->tasks->findById($id);
        if (!$task) {
            $this->rejectTaskRequest('Tarefa não encontrada.', '/tasks', 404);
            return;
        }

        if (!$this->authorizeTaskMutation($task)) {
            return;
        }

        $data = [];
        if (isset($_POST['status'])) {
            $status = $this->inputPost('status');
            if (!in_array($status, ['pending', 'in_progress', 'done', 'cancelled'], true)) {
                $this->rejectTaskRequest('Status inválido.');
                return;
            }
            $data['status'] = $status;
        }
        if (isset($_POST['priority'])) {
            $priority = $this->inputPost('priority');
            if (!in_array($priority, ['low', 'medium', 'high'], true)) {
                $this->rejectTaskRequest('Prioridade inválida.');
                return;
            }
            $data['priority'] = $priority;
        }
        if (isset($_POST['title'])) {
            $title = $this->input('title');
            if ($title === '') {
                $this->rejectTaskRequest('Título é obrigatório.');
                return;
            }
            $data['title'] = $title;
        }
        if (isset($_POST['due_date'])) {
            $dueDate = self::parseDueDate((string) $this->inputPost('due_date'));
            if ($dueDate === null) {
                $this->rejectTaskRequest('Prazo inválido.');
                return;
            }
            $data['due_date'] = $dueDate;
        }
        if (isset($_POST['description']))
            $data['description'] = $this->input('description');

        $newAssignee = self::assigneeChange(
            $this->inputPost('assigned_to'),
            (string) ($_SESSION['user']['role'] ?? ''),
            (int) $task['assigned_to'],
        );
        if ($newAssignee !== null) {
            $data['assigned_to'] = $newAssignee;
        }

        try {
            $this->tasks->update($id, $data);
        } catch (\InvalidArgumentException $e) {
            if ($e->getCode() !== Task::INVALID_ASSIGNEE) {
                throw $e;
            }
            $this->rejectTaskRequest('Responsável inválido.');
            return;
        }

        $this->drainAfterSave();

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
            $this->rejectTaskRequest('Tarefa não encontrada.', '/tasks', 404);
            return;
        }

        if (!$this->authorizeTaskMutation($task)) {
            return;
        }

        $this->tasks->delete($id);
        $this->drainAfterSave();

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
        $this->drainAfterSave();
        $this->json(ApiResponse::success(token: true));
    }
}
