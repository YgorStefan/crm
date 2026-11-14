<?php

declare(strict_types=1);

namespace App\Services\Google;

final class CurlTransport implements HttpTransport
{
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): array
    {
        // o curl le zero como sem limite, entao orcamento esgotado tem que
        // virar estouro de tempo aqui, antes de abrir a conexao.
        if ($timeoutMs <= 0) {
            return ['status' => 0, 'body' => ''];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => ''];
        }

        $options = [
            CURLOPT_CUSTOMREQUEST     => $method,
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_HTTPHEADER        => $headers,
            CURLOPT_TIMEOUT_MS        => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_SSL_VERIFYPEER    => true,
            // sem isto, um curl com resolvedor de nome sincrono limita o tempo
            // com sinal, que nao e seguro no frankenphp com threads e recusa na
            // hora qualquer tempo abaixo de um segundo.
            CURLOPT_NOSIGNAL          => true,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if (!is_string($raw)) {
            return ['status' => 0, 'body' => ''];
        }

        return ['status' => $status, 'body' => $raw];
    }
}
