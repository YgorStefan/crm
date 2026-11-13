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

    private const STOP_REASONS      = ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded', 'dailyLimitExceeded'];
    private const RECONNECT_REASONS = ['insufficientPermissions', 'ACCESS_TOKEN_SCOPE_INSUFFICIENT'];
    private const DROP_REASONS      = ['forbiddenForNonOrganizer', 'forbidden', 'requiredAccessLevel'];

    private const SEVERITY = ['ok' => 0, 'degraded' => 1, 'reconnect' => 2];

    /** @var \Closure():float */
    private \Closure $now;
    private Logger $logger;

    // estes campos valem so durante um drain, entao a mesma instancia nao
    // serve a dois envios ao mesmo tempo.
    private float $deadline = 0.0;
    private int $itemsLeft = 0;
    private ?string $token = null;
    private bool $renewed = false;

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
     * nunca lanca. com foco, o status e so daquela conta, que envia primeiro,
     * entao rede fora do ar aparece na primeira chamada dela. trava ocupada
     * conta como ok, e sem foco vale o pior status: reconnect, degraded, ok.
     *
     * @param array{items:int, budget_ms:int} $profile
     * @return 'ok'|'degraded'|'reconnect'
     */
    public function drain(array $profile, ?int $focusUserId = null): string
    {
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
            // rede, 429, 5xx e qualquer status que a tabela nao lista: a
            // pendencia fica para a proxima abertura em vez de sumir da fila.
            default  => 'stop',
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
        $this->deadline  = ($this->now)() + $profile['budget_ms'] / 1000;
        $this->itemsLeft = $profile['items'];

        $userIds = $this->accounts->userIdsWithWork();
        if ($focusUserId !== null && in_array($focusUserId, $userIds, true)) {
            $userIds = [$focusUserId, ...array_values(array_diff($userIds, [$focusUserId]))];
        }

        $statuses = [];
        foreach ($userIds as $userId) {
            if ($this->itemsLeft <= 0 || $this->remainingMs() <= 0) {
                break;
            }
            $result = $this->drainAccount($userId);
            if ($result === 'stop') {
                $statuses[$userId] = 'degraded';
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
     * @param 'INSERT'|'PATCH' $kind
     * @param array<string, mixed> $event
     * @return array{status:int, body:string}|null null quando o orcamento acabou
     */
    private function write(int $userId, string $kind, string $eventId, array $event): ?array
    {
        if ($kind === 'INSERT') {
            return $this->request($userId, 'POST', self::EVENTS_URL, self::encodeBody(['id' => $eventId] + $event));
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
        $this->token ??= $this->auth->accessTokenFor($userId, $this->remainingMs());

        if (!$this->hasTime()) {
            return null;
        }
        $response = $this->http->send($method, $url, $this->headers($body !== null), $body, $this->remainingMs());
        if ($response['status'] !== 401 || $this->renewed) {
            return $response;
        }

        // o google pode invalidar o token antes do prazo salvo, entao o 401
        // renova e repete uma vez por conta; o 401 seguinte, com token novo,
        // e que pede reconexao.
        $this->renewed = true;
        if (!$this->hasTime()) {
            return null;
        }
        $this->token = $this->auth->accessTokenFor($userId, $this->remainingMs(), forceRefresh: true);

        if (!$this->hasTime()) {
            return null;
        }
        return $this->http->send($method, $url, $this->headers($body !== null), $body, $this->remainingMs());
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
            'reminders'   => ['useDefault' => true],
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
        if ($task['client_id'] !== null && ($task['client_name'] ?? null) !== null) {
            $parts[] = 'Cliente: ' . (string) $task['client_name'] . "\n" . APP_URL . '/clients/' . (int) $task['client_id'];
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

    private function hasTime(): bool
    {
        return $this->remainingMs() > 0;
    }

    private function remainingMs(): int
    {
        return (int) floor(($this->deadline - ($this->now)()) * 1000);
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

    private function logFailure(string $what, ?int $userId, \Throwable $e): void
    {
        $this->logger->error("[GoogleCalendarSync] {$what}", [
            'user_id'   => $userId,
            'exception' => $e::class,
            'message'   => $e->getMessage(),
        ]);
    }
}
