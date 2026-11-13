<?php

declare(strict_types=1);

use App\Services\Google\HttpTransport;

// o autoloader so cobre App e Core, entao o teste carrega este arquivo com
// require_once. url sem resposta cadastrada lanca, para um teste nunca passar
// por cair numa resposta padrao que ninguem escolheu.
final class FakeTransport implements HttpTransport
{
    /** @var array<string, array{status:int, body:string}> */
    private array $responses = [];

    /** @var list<array{method:string, url:string, headers:array<int, string>, body:?string, timeoutMs:int}> */
    private array $requests = [];

    private bool $down = false;
    private int $msPerCall = 0;
    private float $clock;

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

    public function networkDown(bool $down): void
    {
        $this->down = $down;
    }

    public function advanceMsPerCall(int $ms): void
    {
        $this->msPerCall = $ms;
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

    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): array
    {
        $this->requests[] = [
            'method'    => $method,
            'url'       => $url,
            'headers'   => $headers,
            'body'      => $body,
            'timeoutMs' => $timeoutMs,
        ];
        $this->clock += $this->msPerCall / 1000;

        if ($this->down) {
            return ['status' => 0, 'body' => ''];
        }

        foreach ($this->responses as $urlContains => $response) {
            if (str_contains($url, $urlContains)) {
                return $response;
            }
        }

        throw new \LogicException("FakeTransport sem resposta para {$method} {$url}");
    }
}
