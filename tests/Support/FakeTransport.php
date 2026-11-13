<?php

declare(strict_types=1);

use App\Services\Google\HttpTransport;

// o autoloader so cobre App e Core, entao o teste carrega este arquivo com
// require_once. url sem resposta cadastrada lanca, para um teste nunca passar
// por cair numa resposta padrao que ninguem escolheu.
final class FakeTransport implements HttpTransport
{
    public const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    private const BEARER = 'Authorization: Bearer ';

    /** @var array<string, array{status:int, body:string}> */
    private array $responses = [];

    /** @var list<array{urlContains:string, method:?string, status:int, body:string}> */
    private array $once = [];

    /** @var array<string, array<string, array<string, mixed>>> conta => id => evento, cancelados inclusive */
    private array $calendars = [];

    /** @var array<string, string> token => conta; token sem cadastro e a propria conta */
    private array $accountOf = [];

    /** @var list<array{method:string, url:string, headers:array<int, string>, body:?string, timeoutMs:int}> */
    private array $requests = [];

    private bool $down = false;
    private int $msPerCall = 0;
    private float $clock;
    private int $etags = 0;
    private int $pageSize = 250;

    public function __construct()
    {
        $this->clock = microtime(true);
    }

    /** @param array<mixed>|string $body array vira JSON */
    public function respond(string $urlContains, int $status, array|string $body): void
    {
        $this->responses[$urlContains] = [
            'status' => $status,
            'body'   => is_array($body) ? (string) json_encode($body) : $body,
        ];
    }

    /**
     * resposta de uma vez so: vence as de respond() e a agenda, e as cadastradas
     * para a mesma url saem na ordem do cadastro.
     *
     * @param array<mixed>|string $body array vira JSON
     */
    public function respondOnce(string $urlContains, int $status, array|string $body, ?string $method = null): void
    {
        $this->once[] = [
            'urlContains' => $urlContains,
            'method'      => $method,
            'status'      => $status,
            'body'        => is_array($body) ? (string) json_encode($body) : $body,
        ];
    }

    /** corpo de erro no formato da api do google, com o motivo em error.errors[0].reason */
    public static function googleError(int $code, string $reason): string
    {
        return (string) json_encode(['error' => [
            'errors'  => [['domain' => 'global', 'reason' => $reason, 'message' => $reason]],
            'code'    => $code,
            'message' => $reason,
        ]]);
    }

    public function networkDown(bool $down): void
    {
        $this->down = $down;
    }

    public function advanceMsPerCall(int $ms): void
    {
        $this->msPerCall = $ms;
    }

    /**
     * o google guarda a agenda na conta, e o token renovado chega na mesma
     * agenda, entao cada token cadastrado aponta para a agenda da sua conta.
     */
    public function tokenOf(string $account, string $token): void
    {
        $this->accountOf[$token] = $account;
    }

    private function accountFor(string $token): string
    {
        return $this->accountOf[$token] ?? $token;
    }

    public function pageSize(int $size): void
    {
        $this->pageSize = $size;
    }

    /** @return \Closure():float segundos, como microtime(true), avancando a cada chamada */
    public function now(): \Closure
    {
        return fn (): float => $this->clock;
    }

    /** @return list<array{method:string, url:string, headers:array<int, string>, body:?string, timeoutMs:int}> */
    public function requests(): array
    {
        return $this->requests;
    }

    /** @return array<string, array<string, mixed>> id => evento da agenda da conta do token, cancelados inclusive */
    public function events(string $token): array
    {
        return $this->calendars[$this->accountFor($token)] ?? [];
    }

    /** @param array<string, mixed> $event precisa de id; sem etag ou status, ganha um novo e confirmed */
    public function putEvent(string $token, array $event): void
    {
        $event['etag']   ??= $this->nextEtag();
        $event['status'] ??= 'confirmed';
        $this->calendars[$this->accountFor($token)][(string) $event['id']] = $event;
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): array
    {
        $this->requests[] = [
            'method'    => $method,
            'url'       => $url,
            'headers'   => $headers,
            'body'      => $body,
            'timeoutMs' => $timeoutMs,
        ];

        // como o curl: sem teto nao ha chamada, e a que levaria mais que o teto
        // e cortada nele, sem resposta, gastando so o teto.
        if ($timeoutMs <= 0) {
            return ['status' => 0, 'body' => ''];
        }
        if ($this->msPerCall > $timeoutMs) {
            $this->clock += $timeoutMs / 1000;
            return ['status' => 0, 'body' => ''];
        }
        $this->clock += $this->msPerCall / 1000;

        if ($this->down) {
            return ['status' => 0, 'body' => ''];
        }

        foreach ($this->once as $i => $response) {
            if (str_contains($url, $response['urlContains'])
                && ($response['method'] === null || $response['method'] === $method)) {
                array_splice($this->once, $i, 1);
                return ['status' => $response['status'], 'body' => $response['body']];
            }
        }

        foreach ($this->responses as $urlContains => $response) {
            if (str_contains($url, $urlContains)) {
                return $response;
            }
        }

        if (str_starts_with($url, self::EVENTS_URL)) {
            return $this->calendar($method, $url, $headers, $body);
        }

        throw new \LogicException("FakeTransport sem resposta para {$method} {$url}");
    }

    /**
     * agenda primary em memoria, uma por conta, com o que a tarefa 1 mediu:
     * apagar so marca cancelled, o id apagado segue ocupado para o insert, o
     * patch com status confirmed o restaura, e start com date e dateTime juntos
     * volta 400.
     *
     * @param array<int, string> $headers
     * @return array{status:int, body:string}
     */
    private function calendar(string $method, string $url, array $headers, ?string $body): array
    {
        $token = null;
        foreach ($headers as $header) {
            if (str_starts_with($header, self::BEARER)) {
                $token = substr($header, strlen(self::BEARER));
            }
        }
        if ($token === null || $token === '') {
            return self::error(401, 'authError');
        }
        $account = $this->accountFor($token);

        $rest = substr((string) parse_url($url, PHP_URL_PATH), strlen((string) parse_url(self::EVENTS_URL, PHP_URL_PATH)));
        $id   = $rest === '' ? null : rawurldecode(ltrim($rest, '/'));

        if ($id === null && $method === 'GET') {
            return $this->listEvents($account, (string) parse_url($url, PHP_URL_QUERY));
        }
        if ($id === null && $method === 'POST') {
            return $this->insertEvent($account, $body);
        }
        if ($id === null) {
            throw new \LogicException("FakeTransport sem rota de agenda para {$method} {$url}");
        }

        $event = $this->calendars[$account][$id] ?? null;
        if ($event === null) {
            return self::error(404, 'notFound');
        }

        switch ($method) {
            case 'GET':
                return self::ok($event);

            case 'DELETE':
                if ($event['status'] === 'cancelled') {
                    return self::error(410, 'deleted');
                }
                $event['status'] = 'cancelled';
                $event['etag']   = $this->nextEtag();
                $this->calendars[$account][$id] = $event;
                return ['status' => 204, 'body' => ''];

            case 'PATCH':
                $patch = json_decode((string) $body, true);
                if (!is_array($patch)) {
                    return self::error(400, 'parseError');
                }
                $merged = self::merge($event, $patch);
                if (!self::validTimes($merged)) {
                    return self::error(400, 'invalid');
                }
                $merged['etag'] = $this->nextEtag();
                $this->calendars[$account][$id] = $merged;
                return self::ok($merged);
        }

        throw new \LogicException("FakeTransport sem rota de agenda para {$method} {$url}");
    }

    /** @return array{status:int, body:string} */
    private function insertEvent(string $account, ?string $body): array
    {
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            return self::error(400, 'parseError');
        }

        $id = isset($data['id']) ? (string) $data['id'] : 'gerado' . ($this->etags + 1);
        if (isset($this->calendars[$account][$id])) {
            return self::error(409, 'duplicate');
        }

        $event = self::withoutNulls($data);
        $event['id']       = $id;
        $event['status'] ??= 'confirmed';
        if (!self::validTimes($event)) {
            return self::error(400, 'invalid');
        }
        $event['etag'] = $this->nextEtag();
        $this->calendars[$account][$id] = $event;

        return self::ok($event);
    }

    /** @return array{status:int, body:string} */
    private function listEvents(string $account, string $query): array
    {
        parse_str($query, $params);
        $live = array_values(array_filter(
            $this->calendars[$account] ?? [],
            fn (array $event): bool => $event['status'] !== 'cancelled',
        ));

        // timeMin e timeMax nao filtram: a lista devolve todo evento vivo, e o
        // teste que depende da janela escolhe os eventos que poe na agenda.
        $size   = isset($params['maxResults']) ? min($this->pageSize, max(1, (int) $params['maxResults'])) : $this->pageSize;
        $offset = isset($params['pageToken']) ? (int) $params['pageToken'] : 0;

        $page = ['kind' => 'calendar#events', 'items' => array_slice($live, $offset, $size)];
        if ($offset + $size < count($live)) {
            $page['nextPageToken'] = (string) ($offset + $size);
        }

        return self::ok($page);
    }

    /**
     * patch do google: objeto aninhado mescla, null apaga o campo e lista substitui.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if ($value === null) {
                unset($base[$key]);
            } elseif (is_array($value) && !array_is_list($value) && is_array($base[$key] ?? null)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = is_array($value) ? self::withoutNulls($value) : $value;
            }
        }
        return $base;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function withoutNulls(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if ($value !== null) {
                $clean[$key] = is_array($value) ? self::withoutNulls($value) : $value;
            }
        }
        return $clean;
    }

    /** @param array<string, mixed> $event */
    private static function validTimes(array $event): bool
    {
        foreach (['start', 'end'] as $edge) {
            $time = $event[$edge] ?? null;
            if (is_array($time) && isset($time['date'], $time['dateTime'])) {
                return false;
            }
        }
        return true;
    }

    private function nextEtag(): string
    {
        $this->etags++;
        return "\"etag-falso-{$this->etags}\"";
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status:int, body:string}
     */
    private static function ok(array $data): array
    {
        return ['status' => 200, 'body' => (string) json_encode($data)];
    }

    /** @return array{status:int, body:string} */
    private static function error(int $code, string $reason): array
    {
        return ['status' => $code, 'body' => self::googleError($code, $reason)];
    }
}
