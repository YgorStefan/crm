<?php

namespace Core\Middleware;

use Core\Database;
use Core\Logger;

class RateLimitMiddleware
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 60;

    // Bucket por conta: protege contra brute force distribuido (varios IPs)
    // contra um mesmo email. Mais tentativas, porem janela mais longa.
    private const MAX_ACCOUNT_ATTEMPTS = 10;
    private const ACCOUNT_WINDOW_SECONDS = 900;

    public function handle(): void
    {
        $ip = $this->clientIp();
        $identifier = $this->identifier();
        $pdo = Database::getInstance();

        // Limpar registros antigos (cobre a maior janela: bucket por conta).
        $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")
            ->execute();

        // Contar tentativas recentes por IP
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip = :ip
              AND attempted_at > DATE_SUB(NOW(), INTERVAL :seconds SECOND)
        ");
        $stmt->execute([':ip' => $ip, ':seconds' => self::WINDOW_SECONDS]);
        $ipCount = (int) $stmt->fetchColumn();

        // Contar tentativas recentes para a conta (email) informada
        $accountCount = 0;
        if ($identifier !== '') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE identifier = :id
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL :seconds SECOND)
            ");
            $stmt->execute([':id' => $identifier, ':seconds' => self::ACCOUNT_WINDOW_SECONDS]);
            $accountCount = (int) $stmt->fetchColumn();
        }

        // Registrar esta tentativa
        $pdo->prepare("INSERT INTO login_attempts (ip, identifier) VALUES (:ip, :id)")
            ->execute([':ip' => $ip, ':id' => $identifier !== '' ? $identifier : null]);

        if ($ipCount >= self::MAX_ATTEMPTS) {
            $this->block("Rate limit atingido para IP {$ip}", 'Muitas tentativas. Aguarde 1 minuto antes de tentar novamente.');
        }

        if ($accountCount >= self::MAX_ACCOUNT_ATTEMPTS) {
            $this->block("Lockout por conta atingido para {$identifier}", 'Muitas tentativas para esta conta. Aguarde 15 minutos antes de tentar novamente.');
        }
    }

    private function block(string $logMessage, string $userMessage): void
    {
        (new Logger())->warning($logMessage);
        $_SESSION['flash'] = ['type' => 'error', 'message' => $userMessage];
        header('Location: ' . APP_URL . '/login');
        exit;
    }

    private function identifier(): string
    {
        return strtolower(trim($_POST['email'] ?? ''));
    }

    private function clientIp(): string
    {
        // Verificar X-Forwarded-For apenas se confiável (detrás de proxy conhecido)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
