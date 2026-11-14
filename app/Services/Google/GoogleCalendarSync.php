<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\GoogleAccount;
use App\Models\GoogleEventDeletion;
use App\Models\Task;
use Core\Logger;

final class GoogleCalendarSync
{
    public const PROFILE_AFTER_SAVE = ['items' => 3,  'budget_ms' => 1500];
    public const PROFILE_FEED       = ['items' => 20, 'budget_ms' => 3000];

    private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    private const TIME_ZONE  = 'America/Sao_Paulo';
    private const DONE_MARK  = '✓ ';
    private const DEFAULT_DURATION_MIN = 30;

    private const LIST_BUDGET_MS = 4000;
    private const LIST_PAGE_SIZE = 250;
    private const GET_BUDGET_MS  = 2000;
    private const GET_ITEMS      = 10;
    // a spec nao tem linha para alterar ou excluir evento do google: e uma
    // leitura e uma escrita, que cabem com folga no orcamento do envio no feed.
    private const EVENT_OP_BUDGET_MS = 3000;

    // a coluna tasks.title e varchar(200).
    private const TITLE_MAX_CHARS  = 200;
    private const NO_TITLE         = '(sem título)';
    private const DUPLICATE_KEY    = 1062;
    private const EVENT_ID_PATTERN = '/^[A-Za-z0-9_-]{1,1024}\z/';
    private const RFC3339_PATTERN  = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})\z/';
    private const DATE_PATTERN     = '/^\d{4}-\d{2}-\d{2}\z/';
    private const FORM_TIME_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/';

    private const ERR_INVALID_ID     = 'Evento inválido.';
    private const ERR_NOT_CONFIGURED = 'A integração com o Google Agenda não está configurada.';
    private const ERR_INVALID_TIME   = 'Data ou horário inválido.';
    private const ERR_MIXED          = 'Para mudar entre dia inteiro e horário, envie o início e o fim.';
    private const ERR_END_BEFORE     = 'O fim precisa ser depois do início.';
    private const ERR_NO_CHANGE      = 'Nenhuma alteração enviada.';
    private const ERR_TASK_EVENT     = 'Este evento é de uma tarefa do CRM. Altere pela tarefa.';
    private const ERR_CANNOT_EDIT    = 'Este evento não pode ser alterado pelo CRM.';
    private const ERR_NOT_FOUND      = 'Este evento não existe mais no Google Agenda.';
    private const ERR_RECONNECT      = 'A conexão com o Google Agenda expirou. Reconecte para continuar.';
    private const ERR_UNAVAILABLE    = 'O Google Agenda não respondeu. Tente de novo.';
    private const ERR_REFUSED        = 'O Google Agenda recusou a alteração.';
    private const ERR_UNEXPECTED     = 'Não foi possível concluir a operação no Google Agenda.';

    // abaixo disto a chamada nem comeca: a ida e volta medida da vm ao google
    // foi de 185 a 202 ms numa chamada recusada com conexao nova, uma escrita
    // leva no minimo isso, e a chamada cortada pelo proprio orcamento pareceria
    // google fora do ar.
    private const MIN_CALL_MS = 300;

    private const STOP_REASONS      = ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded', 'dailyLimitExceeded'];
    private const RECONNECT_REASONS = ['insufficientPermissions', 'ACCESS_TOKEN_SCOPE_INSUFFICIENT'];
    private const DROP_REASONS      = ['forbiddenForNonOrganizer', 'forbidden', 'requiredAccessLevel'];

    private const SEVERITY = ['ok' => 0, 'degraded' => 1, 'reconnect' => 2];

    /** @var \Closure():float */
    private \Closure $now;
    private Logger $logger;

    // estes campos valem so durante um drain, uma leitura do calendario ou uma
    // operacao em evento, entao a mesma instancia nao serve a duas ao mesmo tempo.
    private float $deadline = 0.0;
    private int $itemsLeft = 0;
    private ?string $token = null;
    private bool $renewed = false;
    private bool $gotAnswer = false;
    // com foco o drain devolve so o status da conta em foco, e a parada por
    // rede, limite ou 5xx pode ter vindo de outra conta.
    private bool $sendStopped = false;

    /** @param \Closure():float|null $now segundos, como microtime(true) */
    public function __construct(
        private readonly GoogleConfig $config,
        private readonly HttpTransport $http,
        private readonly GoogleAuth $auth,
        private readonly Task $tasks = new Task(),
        private readonly GoogleAccount $accounts = new GoogleAccount(),
        private readonly GoogleEventDeletion $deletions = new GoogleEventDeletion(),
        ?\Closure $now = null,
        ?Logger $logger = null,
    ) {
        $this->now    = $now ?? static fn (): float => microtime(true);
        $this->logger = $logger ?? new Logger();
    }

    public static function default(): self
    {
        $config = GoogleConfig::fromEnv();
        $http   = new CurlTransport();
        return new self($config, $http, new GoogleAuth($config, $http));
    }

    /**
     * nunca lanca. com foco, o status e so daquela conta, que envia primeiro
     * quando tem trabalho na fila, e so entao a rede fora do ar aparece na
     * primeira chamada dela. sem trabalho dela, a parada por rede em outra
     * conta devolve ok, e quem precisa saber le o sendStopped. trava ocupada
     * conta como ok, e sem foco vale o pior status: reconnect, degraded, ok.
     *
     * @param array{items:int, budget_ms:int} $profile
     * @return 'ok'|'degraded'|'reconnect'
     */
    public function drain(array $profile, ?int $focusUserId = null): string
    {
        $this->sendStopped = false;
        if (!$this->config->isConfigured()) {
            return 'ok';
        }

        try {
            return $this->drainAccounts($profile, $focusUserId);
        } catch (\Throwable $e) {
            $this->logFailure('envio', null, $e);
            return 'degraded';
        }
    }

    /**
     * nunca lanca. as tarefas saem sempre do banco, entao nenhuma some com o
     * google fora do ar; o google acrescenta os eventos so dele e traz para as
     * tarefas vinculadas o que mudou la.
     *
     * @return array{status:'ok'|'degraded'|'reconnect', tasks:list<array<string, mixed>>, team:list<array<string, mixed>>, foreign:list<array<string, mixed>>}
     */
    public function window(int $userId, bool $isAdmin, \DateTimeImmutable $start, \DateTimeImmutable $end, bool $team): array
    {
        $status  = 'ok';
        $foreign = [];

        try {
            // as ocorrencias novas precisam existir antes do envio, senao so
            // chegam ao google na abertura seguinte.
            $this->tasks->materializeRecurring($userId, $isAdmin);
        } catch (\Throwable $e) {
            $this->logFailure('ocorrencias recorrentes', $userId, $e);
            $status = 'degraded';
        }

        if ($this->config->isConfigured()) {
            $sent = $this->drain(self::PROFILE_FEED, $userId);
            if ($sent === 'ok' && !$this->sendStopped) {
                [$read, $foreign] = $this->readCalendar($userId, $start, $end);
                $status = self::worse($status, $read);
            } else {
                // a lista e pulada se o envio parou por rede, limite ou 5xx em
                // qualquer conta, porque o google fora do ar so somaria a espera
                // dela, e tambem se a conta em foco nao terminou ok por qualquer
                // motivo, falha na trava ou excecao inesperada inclusive. a
                // reconexao de outra conta nao pula a lista.
                $status = self::worse($status, $sent === 'ok' ? 'degraded' : $sent);
            }
        }

        $from = self::localDateTime($start);
        $to   = self::localDateTime($end);
        try {
            $tasks     = array_values($this->tasks->findForCalendar($userId, false, $from, $to));
            $teamTasks = $team && $isAdmin ? array_values($this->tasks->findTeamForCalendar($userId, $from, $to)) : [];
        } catch (\Throwable $e) {
            $this->logFailure('tarefas do calendario', $userId, $e);
            return ['status' => self::worse($status, 'degraded'), 'tasks' => [], 'team' => [], 'foreign' => $foreign];
        }

        return ['status' => $status, 'tasks' => $tasks, 'team' => $teamTasks, 'foreign' => $foreign];
    }

    /**
     * nunca lanca. so altera evento de que o usuario e organizador e que nao e
     * de tarefa, com o token dele, e manda so os campos recebidos.
     *
     * @param array{title?:string, description?:string, start?:string, end?:string, all_day?:bool} $fields
     *        start e end em Y-m-d\TH:i no fuso de sao paulo, ou Y-m-d no dia inteiro, com o fim exclusivo
     * @return array{ok:bool, error?:string}
     */
    public function updateForeignEvent(int $userId, string $eventId, array $fields): array
    {
        try {
            $allDay = $fields['all_day'] ?? null;
            $wanted = [];
            foreach (['start', 'end'] as $edge) {
                if (!array_key_exists($edge, $fields)) {
                    continue;
                }
                $time = self::formTime($fields[$edge]);
                if ($time === null || ($allDay !== null && $time['all_day'] !== $allDay)) {
                    return self::failed(self::ERR_INVALID_TIME);
                }
                $wanted[$edge] = $time;
            }

            $patch = [];
            if (array_key_exists('title', $fields)) {
                $patch['summary'] = $fields['title'];
            }
            if (array_key_exists('description', $fields)) {
                $patch['description'] = $fields['description'];
            }
            if ($patch === [] && $wanted === [] && $allDay === null) {
                return self::failed(self::ERR_NO_CHANGE);
            }
            if (isset($wanted['start'], $wanted['end'])) {
                $error = self::rangeError($wanted['start'], $wanted['end']);
                if ($error !== null) {
                    return self::failed($error);
                }
            }

            $refused = $this->refuseEventRequest($userId, $eventId);
            if ($refused !== null) {
                return $refused;
            }

            $this->startCalls(self::EVENT_OP_BUDGET_MS);
            $event = $this->readOwnEvent($userId, $eventId);
            if ($event === null) {
                return self::failed(self::ERR_NOT_FOUND);
            }
            if (is_string($event)) {
                return self::failed($event);
            }
            if (!self::canEdit($event)) {
                return self::failed(self::ERR_CANNOT_EDIT);
            }

            if ($wanted !== [] || $allDay !== null) {
                // o lado nao enviado fica como esta no google, e o evento nao
                // pode terminar com um lado de dia inteiro e o outro com horario.
                $current = ['start' => self::googleTime($event['start'] ?? null), 'end' => self::googleTime($event['end'] ?? null)];
                $start   = $wanted['start'] ?? $current['start'];
                $end     = $wanted['end'] ?? $current['end'];
                if ($start === null || $end === null || $start['all_day'] !== $end['all_day']
                    || ($allDay !== null && $start['all_day'] !== $allDay)) {
                    return self::failed(self::ERR_MIXED);
                }
                $error = self::rangeError($start, $end);
                if ($error !== null) {
                    return self::failed($error);
                }
                foreach ($wanted as $edge => $time) {
                    $patch[$edge] = self::patchTime($time, $current[$edge]);
                }
            }
            if ($patch === []) {
                return ['ok' => true];
            }

            $response = $this->request($userId, 'PATCH', self::eventUrl($eventId), self::encodeBody($patch));
            return $this->writeResult($userId, $response, false);
        } catch (GoogleReconnectRequired $e) {
            $this->logFailure('evento do google pede reconexao', $userId, $e);
            return self::failed(self::ERR_RECONNECT);
        } catch (GoogleUnavailable $e) {
            $this->logFailure('renovacao do token sem resposta', $userId, $e);
            return self::failed(self::ERR_UNAVAILABLE);
        } catch (\Throwable $e) {
            $this->logFailure('alterar evento do google', $userId, $e);
            return self::failed(self::ERR_UNEXPECTED);
        }
    }

    /**
     * nunca lanca. evento que ja nao existe conta como excluido.
     *
     * @return array{ok:bool, error?:string}
     */
    public function deleteForeignEvent(int $userId, string $eventId): array
    {
        try {
            $refused = $this->refuseEventRequest($userId, $eventId);
            if ($refused !== null) {
                return $refused;
            }

            $this->startCalls(self::EVENT_OP_BUDGET_MS);
            $event = $this->readOwnEvent($userId, $eventId);
            if ($event === null) {
                return ['ok' => true];
            }
            if (is_string($event)) {
                return self::failed($event);
            }
            if (!self::canEdit($event)) {
                return self::failed(self::ERR_CANNOT_EDIT);
            }

            $response = $this->request($userId, 'DELETE', self::eventUrl($eventId), null);
            return $this->writeResult($userId, $response, true);
        } catch (GoogleReconnectRequired $e) {
            $this->logFailure('evento do google pede reconexao', $userId, $e);
            return self::failed(self::ERR_RECONNECT);
        } catch (GoogleUnavailable $e) {
            $this->logFailure('renovacao do token sem resposta', $userId, $e);
            return self::failed(self::ERR_UNAVAILABLE);
        } catch (\Throwable $e) {
            $this->logFailure('excluir evento do google', $userId, $e);
            return self::failed(self::ERR_UNEXPECTED);
        }
    }

    /** @param array<string, mixed> $googleEvent */
    public static function canEdit(array $googleEvent): bool
    {
        $organizer = $googleEvent['organizer'] ?? null;

        return is_array($organizer) && ($organizer['self'] ?? null) === true
            && ($googleEvent['eventType'] ?? null) === 'default'
            && ($googleEvent['locked'] ?? null) !== true;
    }

    /**
     * @return 'ok'|'gone'|'conflict'|'reconnect'|'stop'|'drop'
     */
    public static function classify(int $status, string $body): string
    {
        if ($status >= 200 && $status < 300) {
            return 'ok';
        }

        return match ($status) {
            404, 410 => 'gone',
            409      => 'conflict',
            401      => 'reconnect',
            400      => 'drop',
            403      => self::classifyForbidden($body),
            // 408 e 429 passam sozinhos, como rede e 5xx: a pendencia fica para
            // a proxima abertura.
            408, 429 => 'stop',
            // outro 4xx que a tabela nao lista e erro do proprio pedido, que
            // repetir nao cura: parado, ele travaria a fila da conta e, pelo
            // sendStopped, a lista do google de todo o tenant.
            default  => $status >= 400 && $status < 500 ? 'drop' : 'stop',
        };
    }

    /**
     * o substituto mantem o corpo valido e nao vazio quando um titulo traz
     * byte que nao e utf-8, que sem ele faria o json_encode devolver false.
     *
     * @param array<string, mixed> $payload
     */
    public static function encodeBody(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param array{items:int, budget_ms:int} $profile
     * @return 'ok'|'degraded'|'reconnect'
     */
    private function drainAccounts(array $profile, ?int $focusUserId): string
    {
        $this->startBudget($profile['budget_ms']);
        $this->itemsLeft = $profile['items'];
        $this->gotAnswer = false;

        $userIds = $this->accounts->userIdsWithWork();
        if ($focusUserId !== null && in_array($focusUserId, $userIds, true)) {
            $userIds = [$focusUserId, ...array_values(array_diff($userIds, [$focusUserId]))];
        }

        $statuses = [];
        foreach ($userIds as $userId) {
            if ($this->itemsLeft <= 0 || !$this->hasTime()) {
                break;
            }
            $result = $this->drainAccount($userId);
            if ($result === 'stop') {
                $statuses[$userId] = 'degraded';
                $this->sendStopped = true;
                break;
            }
            $statuses[$userId] = $result;
        }

        if ($focusUserId !== null) {
            return $statuses[$focusUserId] ?? 'ok';
        }

        $status = 'ok';
        foreach ($statuses as $accountStatus) {
            $status = self::worse($status, $accountStatus);
        }
        return $status;
    }

    /** @return 'ok'|'degraded'|'reconnect'|'stop' */
    private function drainAccount(int $userId): string
    {
        try {
            if (!$this->accounts->acquireDrain($userId)) {
                return 'ok';
            }
        } catch (\Throwable $e) {
            $this->logFailure('trava da conta', $userId, $e);
            return 'degraded';
        }

        try {
            return $this->sendQueue($userId);
        } catch (GoogleReconnectRequired $e) {
            $this->logFailure('conta pede reconexao', $userId, $e);
            return 'reconnect';
        } catch (GoogleUnavailable $e) {
            $this->logFailure('renovacao do token sem resposta', $userId, $e);
            return 'stop';
        } catch (\Throwable $e) {
            $this->logFailure('envio da conta', $userId, $e);
            return 'degraded';
        } finally {
            try {
                $this->accounts->releaseDrain($userId);
            } catch (\Throwable $e) {
                // a trava vence sozinha em 15 s, entao a falha so fica registrada.
                $this->logFailure('soltar a trava', $userId, $e);
            }
        }
    }

    /** @return 'ok'|'reconnect'|'stop' */
    private function sendQueue(int $userId): string
    {
        $this->token   = null;
        $this->renewed = false;

        foreach ($this->deletions->forUser($userId, $this->itemsLeft) as $deletion) {
            if (!$this->hasTime()) {
                return 'ok';
            }
            $this->itemsLeft--;
            $result = $this->sendDeletion($userId, $deletion);
            if ($result !== 'done') {
                return $this->finishAccount($userId, $result);
            }
        }

        if ($this->itemsLeft <= 0) {
            return 'ok';
        }

        foreach ($this->tasks->pendingForUser($userId, $this->itemsLeft) as $task) {
            if (!$this->hasTime()) {
                return 'ok';
            }
            $this->itemsLeft--;
            $result = $this->sendTask($userId, $task);
            if ($result !== 'done') {
                return $this->finishAccount($userId, $result);
            }
        }

        return 'ok';
    }

    /**
     * @param 'budget'|'reconnect'|'stop' $result
     * @return 'ok'|'reconnect'|'stop'
     */
    private function finishAccount(int $userId, string $result): string
    {
        if ($result === 'reconnect') {
            // o googleauth so revoga em invalid_grant e token ilegivel; o 401
            // com token recem-renovado e o 403 de escopo sao revogados aqui.
            $this->accounts->markRevoked($userId);
            return 'reconnect';
        }

        // orcamento acabado nao e falha: o resto fica para a proxima abertura.
        return $result === 'budget' ? 'ok' : 'stop';
    }

    /**
     * @param array{id:int, user_id:int, google_event_id:string} $deletion
     * @return 'done'|'budget'|'reconnect'|'stop'
     */
    private function sendDeletion(int $userId, array $deletion): string
    {
        $response = $this->request($userId, 'DELETE', self::eventUrl($deletion['google_event_id']), null);
        if ($response === null) {
            return 'budget';
        }

        $outcome = self::classify($response['status'], $response['body']);
        if ($outcome === 'reconnect' || $outcome === 'stop') {
            $this->logResponse('remocao parou o envio', $userId, null, $response);
            return $outcome;
        }
        if ($outcome === 'drop' || $outcome === 'conflict') {
            $this->logResponse('remocao recusada, saiu da fila', $userId, null, $response);
        }

        // gone entra aqui: o id deterministico costuma apontar para um evento
        // que nunca existiu, e insistir travaria a fila.
        $this->deletions->remove($deletion['id']);
        return 'done';
    }

    /**
     * @param array<string, mixed> $task linha de Task::pendingForUser
     * @return 'done'|'budget'|'reconnect'|'stop'
     */
    private function sendTask(int $userId, array $task): string
    {
        $taskId   = (int) $task['id'];
        $seen     = (int) $task['google_sync_pending'];
        $storedId = $task['google_event_id'] !== null ? (string) $task['google_event_id'] : null;
        $eventId  = $storedId ?? $this->config->eventIdFor((int) $task['tenant_id'], $taskId);

        if ($task['status'] === 'cancelled') {
            return $this->removeCancelled($userId, $taskId, $seen, $eventId);
        }

        $duration = $task['google_duration_min'] !== null ? (int) $task['google_duration_min'] : self::DEFAULT_DURATION_MIN;
        $event    = $this->eventFor($task, $duration);

        $first    = $storedId !== null ? 'PATCH' : 'INSERT';
        $response = $this->write($userId, $first, $eventId, $event);
        if ($response === null) {
            return 'budget';
        }
        $outcome = self::classify($response['status'], $response['body']);

        $fallback = match (true) {
            $first === 'PATCH' && $outcome === 'gone'      => 'INSERT',
            $first === 'INSERT' && $outcome === 'conflict' => 'PATCH',
            default                                        => null,
        };
        if ($fallback !== null) {
            $response = $this->write($userId, $fallback, $eventId, $event);
            if ($response === null) {
                return 'budget';
            }
            $outcome = self::classify($response['status'], $response['body']);
        }

        if ($outcome === 'ok') {
            $this->tasks->markSynced($taskId, $seen, $eventId, $userId, self::etagOf($response['body']), $duration);
            return 'done';
        }
        if ($outcome === 'reconnect' || $outcome === 'stop') {
            $this->logResponse('tarefa parou o envio', $userId, $taskId, $response);
            return $outcome;
        }

        // erro do proprio evento, ou o outro caminho tambem recusou o id: um
        // segundo desvio voltaria ao primeiro e nunca terminaria.
        $this->logResponse('tarefa recusada pelo google, saiu da fila', $userId, $taskId, $response);
        $this->tasks->dropFromQueue($taskId, $seen);
        return 'done';
    }

    /** @return 'done'|'budget'|'reconnect'|'stop' */
    private function removeCancelled(int $userId, int $taskId, int $seen, string $eventId): string
    {
        $response = $this->request($userId, 'DELETE', self::eventUrl($eventId), null);
        if ($response === null) {
            return 'budget';
        }

        $outcome = self::classify($response['status'], $response['body']);
        if ($outcome === 'reconnect' || $outcome === 'stop') {
            $this->logResponse('remocao de tarefa cancelada parou o envio', $userId, $taskId, $response);
            return $outcome;
        }

        // o unlinkGoogle nao faz nada com pendencia, entao a fila zera antes.
        $this->tasks->dropFromQueue($taskId, $seen);
        if ($outcome === 'ok' || $outcome === 'gone') {
            $this->tasks->unlinkGoogle($taskId);
        } else {
            $this->logResponse('remocao de tarefa cancelada recusada, saiu da fila', $userId, $taskId, $response);
        }
        return 'done';
    }

    /**
     * passos 3 a 5 da spec: o mapa das vinculadas, a lista da janela e a
     * reconciliacao, com os eventos so do google de volta.
     *
     * @return array{0:'ok'|'degraded'|'reconnect', 1:list<array<string, mixed>>}
     */
    private function readCalendar(int $userId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $foreign = [];
        try {
            // lido antes da lista, para uma lista velha nao desfazer o que o crm
            // gravou enquanto ela estava no ar.
            $linked = $this->tasks->linkMap($userId);

            $this->startCalls(self::LIST_BUDGET_MS);
            [$status, $complete, $events] = $this->listEvents($userId, $start, $end);
            if ($status === 'reconnect') {
                return ['reconnect', []];
            }

            $seen = [];
            foreach ($events as $event) {
                $eventId = (string) $event['id'];
                if (($event['status'] ?? null) === 'cancelled') {
                    continue;
                }
                if (isset($linked[$eventId])) {
                    $seen[$eventId] = true;
                    $status = self::worse($status, $this->applyFromGoogle($userId, $linked[$eventId], $event));
                    continue;
                }
                $item = self::foreignItem($event);
                if ($item !== null) {
                    $foreign[] = $item;
                }
            }

            // so a lista inteira prova que um evento saiu da janela: com parte
            // dela, a tarefa ausente poderia estar na pagina que nao veio.
            if ($complete) {
                $status = self::worse($status, $this->checkMissing($userId, $linked, $seen, $start, $end));
            }
            return [$status, $foreign];
        } catch (GoogleReconnectRequired $e) {
            $this->logFailure('leitura da agenda pede reconexao', $userId, $e);
            return ['reconnect', $foreign];
        } catch (\Throwable $e) {
            $this->logFailure('leitura da agenda', $userId, $e);
            return ['degraded', $foreign];
        }
    }

    /**
     * @return array{0:'ok'|'degraded'|'reconnect', 1:bool, 2:list<array<string, mixed>>} status, se veio inteira, eventos
     */
    private function listEvents(int $userId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $query = [
            'singleEvents' => 'true',
            'timeMin'      => $start->format(DATE_RFC3339),
            'timeMax'      => $end->format(DATE_RFC3339),
            'maxResults'   => self::LIST_PAGE_SIZE,
        ];
        $events    = [];
        $pageToken = null;

        do {
            $page     = $pageToken === null ? $query : $query + ['pageToken' => $pageToken];
            $response = $this->request($userId, 'GET', self::EVENTS_URL . '?' . http_build_query($page), null);
            if ($response === null) {
                // orcamento acabado no meio da paginacao: faltam eventos na tela.
                return ['degraded', false, $events];
            }

            $outcome = self::classify($response['status'], $response['body']);
            if ($outcome === 'reconnect') {
                $this->logResponse('lista da agenda pede reconexao', $userId, null, $response);
                $this->accounts->markRevoked($userId);
                return ['reconnect', false, $events];
            }
            $data = $outcome === 'ok' ? json_decode($response['body'], true) : null;
            if (!is_array($data)) {
                $this->logResponse('lista da agenda sem resposta util', $userId, null, $response);
                return ['degraded', false, $events];
            }

            foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
                if (is_array($item) && is_string($item['id'] ?? null) && $item['id'] !== '') {
                    $events[] = $item;
                }
            }
            $next      = $data['nextPageToken'] ?? null;
            $pageToken = is_string($next) && $next !== '' ? $next : null;
        } while ($pageToken !== null);

        return ['ok', true, $events];
    }

    /**
     * @param array<string, mixed> $row linha do Task::linkMap
     * @param array<string, mixed> $event
     * @return 'ok'|'degraded'
     */
    private function applyFromGoogle(int $userId, array $row, array $event): string
    {
        $taskId = (int) $row['id'];
        try {
            $etag = is_string($event['etag'] ?? null) ? $event['etag'] : null;
            // com pendencia o crm ganha: o envio ainda vai levar a versao dele.
            if ((int) $row['google_sync_pending'] > 0 || $etag === null || $etag === $row['google_etag']) {
                return 'ok';
            }

            $etagRead = $row['google_etag'] !== null ? (string) $row['google_etag'] : null;
            $fields   = self::fieldsFromEvent($event, (string) $row['due_date']);
            try {
                $this->tasks->applyGoogleChange($taskId, $etagRead, $fields, $etag);
            } catch (\PDOException $e) {
                if (!self::isDuplicateKey($e) || !array_key_exists('due_date', $fields)) {
                    throw $e;
                }
                // a data nova e a de outra ocorrencia da mesma serie. a tarefa fica
                // onde esta, e o etag guardado evita repetir a colisao a cada abertura.
                unset($fields['due_date']);
                $this->tasks->applyGoogleChange($taskId, $etagRead, $fields, $etag);
                $this->logFailure('data do google colide com outra ocorrencia da serie', $userId, $e, $taskId);
                return 'degraded';
            }
            return 'ok';
        } catch (\Throwable $e) {
            $this->logFailure('reconciliar tarefa', $userId, $e, $taskId);
            return 'degraded';
        }
    }

    /**
     * tarefas vinculadas que nao vieram na lista: um get por evento, as de
     * prazo mais proximo primeiro, dentro do limite e do orcamento proprios.
     *
     * @param array<array-key, array<string, mixed>> $linked
     * @param array<array-key, true> $seen
     * @return 'ok'|'degraded'|'reconnect'
     */
    private function checkMissing(int $userId, array $linked, array $seen, \DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        $from    = self::localDateTime($start);
        $to      = self::localDateTime($end);
        $missing = array_filter(
            $linked,
            fn (array $row, int|string $eventId): bool => !isset($seen[$eventId])
                && (int) $row['google_sync_pending'] === 0
                && (string) $row['due_date'] >= $from
                && (string) $row['due_date'] < $to,
            ARRAY_FILTER_USE_BOTH,
        );
        uasort(
            $missing,
            fn (array $a, array $b): int => [(string) $a['due_date'], (int) $a['id']] <=> [(string) $b['due_date'], (int) $b['id']],
        );

        $this->startBudget(self::GET_BUDGET_MS);
        $status = 'ok';
        foreach (array_slice($missing, 0, self::GET_ITEMS, true) as $eventId => $row) {
            $response = $this->request($userId, 'GET', self::eventUrl((string) $eventId), null);
            if ($response === null) {
                // orcamento acabado: o resto fica para a proxima abertura.
                break;
            }

            $outcome = self::classify($response['status'], $response['body']);
            if ($outcome === 'reconnect') {
                $this->logResponse('leitura de evento pede reconexao', $userId, (int) $row['id'], $response);
                $this->accounts->markRevoked($userId);
                return 'reconnect';
            }
            if ($outcome === 'stop') {
                $this->logResponse('leitura de evento sem resposta', $userId, (int) $row['id'], $response);
                return self::worse($status, 'degraded');
            }
            $status = self::worse($status, $this->settleMissing($userId, $row, $outcome, $response));
        }
        return $status;
    }

    /**
     * @param array<string, mixed> $row
     * @param 'ok'|'gone'|'conflict'|'drop' $outcome
     * @param array{status:int, body:string} $response
     * @return 'ok'|'degraded'
     */
    private function settleMissing(int $userId, array $row, string $outcome, array $response): string
    {
        $taskId = (int) $row['id'];
        try {
            if ($outcome === 'gone') {
                $this->deletedInGoogle($row);
                return 'ok';
            }
            $event = $outcome === 'ok' ? json_decode($response['body'], true) : null;
            if (!is_array($event)) {
                $this->logResponse('leitura de evento recusada, tarefa mantida', $userId, $taskId, $response);
                return 'ok';
            }

            if (($event['status'] ?? null) === 'cancelled') {
                // o google mantem titulo e inicio do evento apagado pelo dono
                // (medido na tarefa 1). so com o id, ele mudou de agenda ou o
                // motivo e desconhecido, e a tarefa so perde o vinculo.
                if (isset($event['summary']) || isset($event['start'])) {
                    $this->deletedInGoogle($row);
                } else {
                    $this->tasks->unlinkGoogle($taskId);
                }
                return 'ok';
            }
            return $this->applyFromGoogle($userId, $row, $event);
        } catch (\Throwable $e) {
            $this->logFailure('tarefa ausente da lista', $userId, $e, $taskId);
            return 'degraded';
        }
    }

    /** @param array<string, mixed> $row */
    private function deletedInGoogle(array $row): void
    {
        // o molde cancelado pararia de gerar a serie, e a concluida e historico:
        // os dois so perdem o vinculo.
        $isTemplate = ($row['recurrence_type'] ?? 'none') !== 'none' && $row['recurrence_parent_id'] === null;
        $isOpen     = in_array($row['status'], ['pending', 'in_progress'], true);
        $this->tasks->unlinkGoogle((int) $row['id'], $isOpen && !$isTemplate ? 'cancelled' : null);
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private static function fieldsFromEvent(array $event, string $taskDue): array
    {
        $fields = [];

        $start = self::googleTime($event['start'] ?? null);
        if ($start !== null) {
            $fields['due_date'] = $start['all_day']
                ? $start['at']->format('Y-m-d') . ' ' . (new \DateTimeImmutable($taskDue))->format('H:i:s')
                : $start['at']->format('Y-m-d H:i:s');
        }

        $title = self::titleFromGoogle($event['summary'] ?? null);
        if ($title !== null) {
            $fields['title'] = $title;
        }

        // dia inteiro nao vira duracao, senao o proximo envio criaria um evento de 24 h.
        $end = self::googleTime($event['end'] ?? null);
        if ($start !== null && $end !== null && !$start['all_day'] && !$end['all_day']) {
            $minutes = intdiv($end['at']->getTimestamp() - $start['at']->getTimestamp(), 60);
            if ($minutes > 0) {
                $fields['google_duration_min'] = $minutes;
            }
        }

        return $fields;
    }

    private static function titleFromGoogle(mixed $summary): ?string
    {
        if (!is_string($summary)) {
            return null;
        }
        if (str_starts_with($summary, self::DONE_MARK)) {
            $summary = substr($summary, strlen(self::DONE_MARK));
        }
        $title = trim($summary);

        // o corte e por caractere para nao partir um acento ao meio.
        return $title === '' ? null : mb_substr($title, 0, self::TITLE_MAX_CHARS, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>|null null quando o inicio nao e legivel
     */
    private static function foreignItem(array $event): ?array
    {
        $start = self::googleTime($event['start'] ?? null);
        if ($start === null) {
            return null;
        }
        $end = self::googleTime($event['end'] ?? null);
        if ($end !== null && $end['all_day'] !== $start['all_day']) {
            return null;
        }
        // o fim de dia inteiro do google e exclusivo e segue assim para a tela.
        $end ??= ['all_day' => $start['all_day'], 'at' => $start['all_day'] ? $start['at']->modify('+1 day') : $start['at']];

        $id      = (string) $event['id'];
        $format  = $start['all_day'] ? 'Y-m-d' : 'Y-m-d\TH:i:s';
        $summary = $event['summary'] ?? null;
        $link    = $event['htmlLink'] ?? null;

        return [
            'id'          => 'g_' . $id,
            'google_id'   => $id,
            'title'       => is_string($summary) && trim($summary) !== '' ? $summary : self::NO_TITLE,
            'start'       => $start['at']->format($format),
            'end'         => $end['at']->format($format),
            'all_day'     => $start['all_day'],
            'recurring'   => is_string($event['recurringEventId'] ?? null) && $event['recurringEventId'] !== '',
            'description' => is_string($event['description'] ?? null) ? $event['description'] : '',
            // o link vira href na tela, entao so passa endereco https.
            'html_link'   => is_string($link) && str_starts_with($link, 'https://') ? $link : null,
            'can_edit'    => self::canEdit($event),
        ];
    }

    /**
     * horario de um start ou end do google; o de dia inteiro fica a meia-noite.
     *
     * @return array{all_day:bool, at:\DateTimeImmutable}|null at no fuso de sao paulo
     */
    private static function googleTime(mixed $time): ?array
    {
        if (!is_array($time)) {
            return null;
        }

        $dateTime = $time['dateTime'] ?? null;
        if (is_string($dateTime)) {
            if (preg_match(self::RFC3339_PATTERN, $dateTime) !== 1) {
                return null;
            }
            try {
                $at = new \DateTimeImmutable($dateTime);
            } catch (\Exception) {
                return null;
            }
            return ['all_day' => false, 'at' => $at->setTimezone(self::zone())];
        }

        $date = $time['date'] ?? null;
        if (is_string($date) && preg_match(self::DATE_PATTERN, $date) === 1) {
            $at = self::parseLocal('Y-m-d', $date);
            return $at === null ? null : ['all_day' => true, 'at' => $at];
        }

        return null;
    }

    /** @return array{all_day:bool, at:\DateTimeImmutable}|null */
    private static function formTime(string $value): ?array
    {
        if (preg_match(self::DATE_PATTERN, $value) === 1) {
            $at = self::parseLocal('Y-m-d', $value);
            return $at === null ? null : ['all_day' => true, 'at' => $at];
        }
        if (preg_match(self::FORM_TIME_PATTERN, $value) === 1) {
            $at = self::parseLocal('Y-m-d\TH:i', $value);
            return $at === null ? null : ['all_day' => false, 'at' => $at];
        }
        return null;
    }

    private static function parseLocal(string $format, string $value): ?\DateTimeImmutable
    {
        // a volta ao texto recusa o dia que nao existe, que o php empurraria para o mes seguinte.
        $at = \DateTimeImmutable::createFromFormat('!' . $format, $value, self::zone());
        return $at !== false && $at->format($format) === $value ? $at : null;
    }

    /**
     * @param array{all_day:bool, at:\DateTimeImmutable} $start
     * @param array{all_day:bool, at:\DateTimeImmutable} $end
     */
    private static function rangeError(array $start, array $end): ?string
    {
        if ($start['all_day'] !== $end['all_day']) {
            return self::ERR_MIXED;
        }
        return $end['at'] <= $start['at'] ? self::ERR_END_BEFORE : null;
    }

    /**
     * @param array{all_day:bool, at:\DateTimeImmutable} $time
     * @param array{all_day:bool, at:\DateTimeImmutable}|null $current o mesmo lado no evento lido
     * @return array<string, string|null>
     */
    private static function patchTime(array $time, ?array $current): array
    {
        if (!$time['all_day']) {
            return self::eventTime($time['at']);
        }
        // nao medido: o dateTime null tira o horario do evento que vira dia
        // inteiro, como o date null medido na tarefa 1 faz no sentido contrario.
        return $current !== null && !$current['all_day']
            ? ['date' => $time['at']->format('Y-m-d'), 'dateTime' => null]
            : ['date' => $time['at']->format('Y-m-d')];
    }

    /** @return array{ok:false, error:string}|null */
    private function refuseEventRequest(int $userId, string $eventId): ?array
    {
        if (preg_match(self::EVENT_ID_PATTERN, $eventId) !== 1) {
            return self::failed(self::ERR_INVALID_ID);
        }
        if (!$this->config->isConfigured()) {
            return self::failed(self::ERR_NOT_CONFIGURED);
        }
        // o mesmo mapa que tira o evento da lista de eventos so do google.
        if (array_key_exists($eventId, $this->tasks->linkMap($userId))) {
            return self::failed(self::ERR_TASK_EVENT);
        }
        return null;
    }

    /**
     * @return array<string, mixed>|string|null o evento; null se ele nao existe mais; texto do erro para a tela
     */
    private function readOwnEvent(int $userId, string $eventId): array|string|null
    {
        $response = $this->request($userId, 'GET', self::eventUrl($eventId), null);
        if ($response === null) {
            return self::ERR_UNAVAILABLE;
        }

        $outcome = self::classify($response['status'], $response['body']);
        if ($outcome === 'gone') {
            return null;
        }
        if ($outcome !== 'ok') {
            return $this->failureMessage($userId, $outcome, $response);
        }

        $event = json_decode($response['body'], true);
        if (!is_array($event)) {
            return self::ERR_UNAVAILABLE;
        }
        return ($event['status'] ?? null) === 'cancelled' ? null : $event;
    }

    /**
     * @param array{status:int, body:string}|null $response
     * @return array{ok:bool, error?:string}
     */
    private function writeResult(int $userId, ?array $response, bool $goneIsDone): array
    {
        if ($response === null) {
            return self::failed(self::ERR_UNAVAILABLE);
        }

        $outcome = self::classify($response['status'], $response['body']);
        if ($outcome === 'ok' || ($outcome === 'gone' && $goneIsDone)) {
            return ['ok' => true];
        }
        if ($outcome === 'gone') {
            return self::failed(self::ERR_NOT_FOUND);
        }
        return self::failed($this->failureMessage($userId, $outcome, $response));
    }

    /**
     * @param 'conflict'|'reconnect'|'stop'|'drop' $outcome
     * @param array{status:int, body:string} $response
     */
    private function failureMessage(int $userId, string $outcome, array $response): string
    {
        $this->logResponse('evento do google recusado', $userId, null, $response);
        if ($outcome === 'reconnect') {
            $this->accounts->markRevoked($userId);
            return self::ERR_RECONNECT;
        }
        return $outcome === 'stop' ? self::ERR_UNAVAILABLE : self::ERR_REFUSED;
    }

    /** @return array{ok:false, error:string} */
    private static function failed(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }

    private static function isDuplicateKey(\PDOException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === self::DUPLICATE_KEY;
    }

    private static function localDateTime(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(self::zone())->format('Y-m-d H:i:s');
    }

    private static function zone(): \DateTimeZone
    {
        return new \DateTimeZone(self::TIME_ZONE);
    }

    /**
     * fora do envio, a leitura e a operacao em evento usam so o token do
     * proprio usuario: o envio pode ter terminado com o de outra conta.
     */
    private function startCalls(int $budgetMs): void
    {
        $this->token     = null;
        $this->renewed   = false;
        $this->gotAnswer = false;
        $this->startBudget($budgetMs);
    }

    private function startBudget(int $budgetMs): void
    {
        $this->deadline = ($this->now)() + $budgetMs / 1000;
    }

    /**
     * @param 'INSERT'|'PATCH' $kind
     * @param array<string, mixed> $event
     * @return array{status:int, body:string}|null null quando o orcamento acabou
     */
    private function write(int $userId, string $kind, string $eventId, array $event): ?array
    {
        if ($kind === 'INSERT') {
            // o lembrete padrao vai so na criacao: o patch mescla o corpo no
            // evento, e useDefault true junto do lembrete que a pessoa pos no
            // google e combinacao que a api recusa.
            $created = ['id' => $eventId] + $event + ['reminders' => ['useDefault' => true]];
            return $this->request($userId, 'POST', self::EVENTS_URL, self::encodeBody($created));
        }
        return $this->request($userId, 'PATCH', self::eventUrl($eventId), self::encodeBody($event));
    }

    /**
     * cada chamada recebe o que resta do orcamento, e a renovacao do token
     * entra nele.
     *
     * @return array{status:int, body:string}|null null quando o orcamento acabou
     */
    private function request(int $userId, string $method, string $url, ?string $body): ?array
    {
        if (!$this->hasTime()) {
            return null;
        }
        if ($this->token === null) {
            $this->token = $this->tokenFor($userId, false);
            if ($this->token === null || !$this->hasTime()) {
                return null;
            }
        }

        $response = $this->send($method, $url, $body);
        if ($response === null || $response['status'] !== 401 || $this->renewed) {
            return $response;
        }

        // o google pode invalidar o token antes do prazo salvo, entao o 401
        // renova e repete uma vez por conta; o 401 seguinte, com token novo,
        // e que pede reconexao.
        $this->renewed = true;
        if (!$this->hasTime()) {
            return null;
        }
        $renewed = $this->tokenFor($userId, true);
        if ($renewed === null || !$this->hasTime()) {
            return null;
        }
        $this->token = $renewed;

        return $this->send($method, $url, $body);
    }

    /** @return array{status:int, body:string}|null null quando o proprio orcamento cortou a chamada */
    private function send(string $method, string $url, ?string $body): ?array
    {
        $response = $this->http->send($method, $url, $this->headers($body !== null), $body, $this->remainingMs());
        if ($response['status'] !== 0) {
            $this->gotAnswer = true;
            return $response;
        }
        return $this->cutByBudget() ? null : $response;
    }

    /** @return string|null null quando o proprio orcamento cortou a renovacao */
    private function tokenFor(int $userId, bool $forceRefresh): ?string
    {
        try {
            $token = $this->auth->accessTokenFor($userId, $this->remainingMs(), $forceRefresh);
        } catch (GoogleUnavailable $e) {
            if ($this->cutByBudget()) {
                return null;
            }
            throw $e;
        }

        // sem forcar, o token pode ter vindo do banco, e isso nao prova que o google responde.
        if ($forceRefresh) {
            $this->gotAnswer = true;
        }
        return $token;
    }

    /**
     * a chamada sem resposta que termina no fim do orcamento so conta como
     * orcamento se o google ja respondeu neste envio: a primeira que estoura
     * pode ser host bloqueado, e ai o aviso de fora do ar tem que aparecer.
     */
    private function cutByBudget(): bool
    {
        return $this->gotAnswer && !$this->hasTime();
    }

    /** @return array<int, string> */
    private function headers(bool $withBody): array
    {
        $headers = ['Authorization: Bearer ' . $this->token];
        if ($withBody) {
            $headers[] = 'Content-Type: application/json';
        }
        return $headers;
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function eventFor(array $task, int $durationMin): array
    {
        $start = new \DateTimeImmutable((string) $task['due_date'], new \DateTimeZone(self::TIME_ZONE));
        $end   = $start->add(new \DateInterval("PT{$durationMin}M"));

        return [
            'summary'     => ($task['status'] === 'done' ? self::DONE_MARK : '') . (string) $task['title'],
            'description' => self::descriptionFor($task),
            'start'       => self::eventTime($start),
            'end'         => self::eventTime($end),
            'status'      => 'confirmed',
        ];
    }

    /** @return array{date:null, dateTime:string, timeZone:string} */
    private static function eventTime(\DateTimeImmutable $when): array
    {
        // date null sempre: o patch de horario num evento que virou dia
        // inteiro no google volta 400 sem ele (medido na tarefa 1), e o 400
        // tiraria a tarefa da fila. no insert e no evento com horario ele passa.
        return ['date' => null, 'dateTime' => $when->format(DATE_RFC3339), 'timeZone' => self::TIME_ZONE];
    }

    /** @param array<string, mixed> $task */
    private static function descriptionFor(array $task): string
    {
        $parts = [];
        $description = trim((string) ($task['description'] ?? ''));
        if ($description !== '') {
            $parts[] = $description;
        }
        // sem o link da ficha: clicado no google agenda web, ele chega sem o
        // cookie samesite strict, e a sessao nova troca o cookie e desloga o usuario.
        if ($task['client_id'] !== null && ($task['client_name'] ?? null) !== null) {
            $parts[] = 'Cliente: ' . (string) $task['client_name'];
        }
        return implode("\n\n", $parts);
    }

    private static function eventUrl(string $eventId): string
    {
        return self::EVENTS_URL . '/' . rawurlencode($eventId);
    }

    private static function etagOf(string $body): ?string
    {
        $data = json_decode($body, true);
        return is_array($data) && is_string($data['etag'] ?? null) ? $data['etag'] : null;
    }

    /** @return 'stop'|'reconnect'|'drop' */
    private static function classifyForbidden(string $body): string
    {
        foreach (self::errorReasons($body) as $reason) {
            if (in_array($reason, self::STOP_REASONS, true)) {
                return 'stop';
            }
            if (in_array($reason, self::RECONNECT_REASONS, true)) {
                return 'reconnect';
            }
            if (in_array($reason, self::DROP_REASONS, true)) {
                return 'drop';
            }
        }

        // 403 de motivo que a tabela nao conhece pode ser de cota, entao trata
        // como rede: para o envio e mantem a pendencia.
        return 'stop';
    }

    /**
     * o motivo vem em error.errors[0].reason, e o google manda o de escopo
     * tambem em error.details[].reason.
     *
     * @return list<string>
     */
    private static function errorReasons(string $body): array
    {
        $data  = json_decode($body, true);
        $error = is_array($data) && is_array($data['error'] ?? null) ? $data['error'] : [];

        $reasons = [];
        $first   = $error['errors'][0]['reason'] ?? null;
        if (is_string($first)) {
            $reasons[] = $first;
        }
        foreach (is_array($error['details'] ?? null) ? $error['details'] : [] as $detail) {
            if (is_array($detail) && is_string($detail['reason'] ?? null)) {
                $reasons[] = $detail['reason'];
            }
        }
        return $reasons;
    }

    /**
     * @param 'ok'|'degraded'|'reconnect' $current
     * @param 'ok'|'degraded'|'reconnect' $other
     * @return 'ok'|'degraded'|'reconnect'
     */
    private static function worse(string $current, string $other): string
    {
        return self::SEVERITY[$other] > self::SEVERITY[$current] ? $other : $current;
    }

    /** @phpstan-impure le o relogio, que anda a cada chamada */
    private function hasTime(): bool
    {
        return $this->remainingMs() >= self::MIN_CALL_MS;
    }

    /** @phpstan-impure */
    private function remainingMs(): int
    {
        // round e nao floor: o relogio em segundos com fracao erra no
        // milionesimo de milissegundo, e o floor transformaria 300 em 299.
        return (int) round(($this->deadline - ($this->now)()) * 1000);
    }

    /** @param array{status:int, body:string} $response */
    private function logResponse(string $what, int $userId, ?int $taskId, array $response): void
    {
        // so status e motivo: o corpo de erro do google pode ecoar o pedido.
        $this->logger->warning("[GoogleCalendarSync] {$what}", [
            'user_id'     => $userId,
            'task_id'     => $taskId,
            'http_status' => $response['status'],
            'reason'      => self::errorReasons($response['body'])[0] ?? null,
        ]);
    }

    private function logFailure(string $what, ?int $userId, \Throwable $e, ?int $taskId = null): void
    {
        $context = ['user_id' => $userId];
        if ($taskId !== null) {
            $context['task_id'] = $taskId;
        }
        $this->logger->error("[GoogleCalendarSync] {$what}", $context + [
            'exception' => $e::class,
            'message'   => $e->getMessage(),
        ]);
    }
}
