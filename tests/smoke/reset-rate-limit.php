<?php

/**
 * Reset do rate-limit de login para o IP local usado pelo Playwright.
 *
 * Por quê: `RateLimitMiddleware` (produção, não deve ser tocado/enfraquecido)
 * permite só 5 tentativas de POST /login por IP a cada 60s. Como a suíte E2E
 * roda várias dezenas de logins do mesmo `127.0.0.1` em poucos minutos, ela
 * esbarraria nesse limite legítimo. Em vez de alterar a lógica de produção,
 * limpamos apenas os registros de tentativa entre specs — o controle
 * continua 100% ativo e testável isoladamente (não há teste E2E cobrindo o
 * próprio rate-limit; isso já é coberto por integração/unitário).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

try {
    $pdo = Core\Database::getInstance();
    $pdo->exec("DELETE FROM login_attempts WHERE ip IN ('127.0.0.1', '::1')");
    echo "[reset-rate-limit] OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[reset-rate-limit] ERRO: {$e->getMessage()}\n");
    exit(1);
}
