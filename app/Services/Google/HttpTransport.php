<?php

declare(strict_types=1);

namespace App\Services\Google;

interface HttpTransport
{
    /**
     * nunca lanca: falha de rede ou de tempo volta como status 0, e quem chama
     * decide o que ela significa.
     *
     * @param  array<int, string>  $headers  no formato "Nome: valor"
     * @return array{status:int, body:string} status 0 = rede ou timeout
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): array;
}
