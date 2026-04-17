<?php

namespace Core\Middleware;

use Core\Database;
use Core\Logger;

class RateLimitMiddleware
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 60;

    public function handle(): void
    {
        $ip = $this->clientIp();
        $pdo = Database::getInstance();

        // Limpar registros antigos (janela de 10 minutos)
        $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)")
            ->execute();

        // Contar tentativas recentes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip = :ip
              AND attempted_at > DATE_SUB(NOW(), INTERVAL :seconds SECOND)
        ");
        $stmt->execute([':ip' => $ip, ':seconds' => self::WINDOW_SECONDS]);
        $count = (int) $stmt->fetchColumn();

        // Registrar esta tentativa
        $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)")
            ->execute([':ip' => $ip]);

        if ($count >= self::MAX_ATTEMPTS) {
            (new Logger())->warning("Rate limit atingido para IP {$ip}");
            $_SESSION['flash'] = [
                'type'    => 'error',
                'message' => 'Muitas tentativas. Aguarde 1 minuto antes de tentar novamente.',
            ];
            header('Location: ' . APP_URL . '/login');
            exit;
        }
    }

    private function clientIp(): string
    {
        // Verificar X-Forwarded-For apenas se confiável (detrás de proxy conhecido)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
