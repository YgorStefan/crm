<?php

namespace Core;

/**
 * Logger PSR-3 inspirado — sem dependências externas.
 *
 * Registra mensagens em arquivos diários no formato:
 *   storage/logs/logger-YYYY-MM-DD.log
 *
 * Níveis suportados (alinhados ao PSR-3 LogLevel):
 *   emergency, alert, critical, error, warning, notice, info, debug.
 */
class Logger
{
    /** Diretório raiz onde os arquivos de log são gravados. */
    private string $logDir;

    public function __construct(?string $logDir = null)
    {
        $this->logDir = $logDir ?? (defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : dirname(__DIR__) . '/storage/logs');
    }

    // ------------------------------------------------------------------
    // Métodos de conveniência por nível (interface PSR-3)
    // ------------------------------------------------------------------

    public function emergency(string $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    // ------------------------------------------------------------------
    // Método central de gravação
    // ------------------------------------------------------------------

    /**
     * Grava uma entrada de log no arquivo do dia corrente.
     *
     * Formato de linha:
     *   [2026-04-12 15:30:00] INFO  — mensagem  {"chave":"valor"}
     *
     * @param string  $level   Nível de severidade (ex: 'INFO', 'ERROR').
     * @param string  $message Mensagem principal.
     * @param array   $context Dados contextuais opcionais (serializado como JSON).
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->ensureLogDir();

        $date      = date('Y-m-d');
        $timestamp = date('Y-m-d H:i:s');
        $levelPad  = str_pad(strtoupper($level), 9); // alinha colunas no arquivo

        // Sanitiza newlines para prevenir log injection (T-05)
        $safeMessage = str_replace(["\r\n", "\r", "\n"], ' ', $message);

        $line      = "[{$timestamp}] {$levelPad} — {$safeMessage}";

        if (!empty($context)) {
            $line .= '  ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $file = $this->logDir . '/logger-' . $date . '.log';

        // file_put_contents com FILE_APPEND é atômico o suficiente para shared hosting
        file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // ------------------------------------------------------------------
    // Utilitários internos
    // ------------------------------------------------------------------

    /**
     * Cria o diretório de logs se ainda não existir.
     * Também escreve um .htaccess de negação para proteger o diretório de
     * acesso web direto (T-07 — Info Disclosure).
     */
    private function ensureLogDir(): void
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        $htaccess = $this->logDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents(
                $htaccess,
                "# Bloqueia acesso web direto aos arquivos de log\n" .
                "<IfModule mod_authz_core.c>\n" .
                "    Require all denied\n" .
                "</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n" .
                "    Deny from all\n" .
                "</IfModule>\n"
            );
        }
    }
}
