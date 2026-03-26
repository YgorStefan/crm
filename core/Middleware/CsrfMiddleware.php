<?php

namespace Core\Middleware;

class CsrfMiddleware
{
    /**
     * Valida o token CSRF enviado no formulário.
     * Aborta a requisição com HTTP 403 se o token for inválido ou ausente.
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Recupera o token enviado pelo formulário (campo hidden "_csrf_token")
        $tokenFromForm = $_POST['_csrf_token'] ?? '';

        // Recupera o token armazenado na sessão do usuário
        $tokenFromSession = $_SESSION['csrf_token'] ?? '';

        // hash_equals() faz comparação em tempo constante, prevenindo
        // ataques de timing (timing attacks) onde o atacante mede o
        // tempo de resposta para adivinhar o token caractere por caractere.
        if (
            empty($tokenFromForm)
            || empty($tokenFromSession)
            || !hash_equals($tokenFromSession, $tokenFromForm)
        ) {
            http_response_code(403);
            die('Ação bloqueada: token CSRF inválido. Por favor, recarregue a página e tente novamente.');
        }

        // Após validação bem-sucedida, regenera o token para a próxima requisição.
        // Isso implementa o padrão "Synchronizer Token"
        $_SESSION['csrf_token'] = self::generateToken();
    }

    /**
     * Gera um token CSRF criptograficamente seguro.
     *
     * @return string  Token hexadecimal de 64 caracteres
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Garante que exista um token na sessão 
     * Se já existir um token, não o substitui
     *
     * @return string  O token atual da sessão
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }

        return $_SESSION['csrf_token'];
    }
}
