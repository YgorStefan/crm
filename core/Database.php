<?php
// ============================================================
// core/Database.php — Classe de Conexão PDO (Padrão Singleton)
// ============================================================
// O padrão Singleton garante que apenas UMA instância do PDO
// seja criada por requisição, evitando abrir múltiplas conexões
// desnecessárias com o banco de dados.
//
// Uso em qualquer Model:
//   $pdo = Database::getInstance();
//   $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
// ============================================================

namespace Core;

use PDO;
use PDOException;

class Database
{
    // Armazena a única instância desta classe (padrão Singleton)
    private static ?PDO $instance = null;

    // Construtor privado: impede que se use `new Database()` diretamente.
    // A única forma de obter o PDO é pelo método estático getInstance().
    private function __construct() {}

    // Clonagem também proibida (parte do contrato do Singleton)
    private function __clone() {}

    /**
     * Retorna a instância única do PDO.
     *
     * Na primeira chamada, lê as credenciais de config/database.php,
     * cria a conexão PDO com as opções de segurança recomendadas e
     * armazena em self::$instance.
     *
     * Nas chamadas subsequentes, simplesmente retorna a instância já criada.
     *
     * @return PDO  Objeto PDO pronto para uso
     */
    public static function getInstance(): PDO
    {
        // Se ainda não existe uma conexão ativa, cria uma agora
        if (self::$instance === null) {
            // Carrega o array de configurações definido em config/database.php
            $config = require ROOT_PATH . DS . 'config' . DS . 'database.php';

            // DSN (Data Source Name): string que identifica o banco para o PDO
            // Formato: mysql:host=...;port=...;dbname=...;charset=...
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            try {
                // Opções do PDO que melhoram segurança e comportamento:
                $options = [
                    // Lança exceções PDOException em vez de retornar false em erros.
                    // Facilita muito o tratamento de erros com try/catch.
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

                    // Retorna os resultados como arrays associativos por padrão
                    // (ex.: ['id' => 1, 'name' => 'João'] em vez de [0 => 1, 'id' => 1])
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    // Desativa emulação de prepared statements.
                    // Com false, o MySQL realmente prepara a query no servidor,
                    // aumentando a proteção contra SQL Injection.
                    PDO::ATTR_EMULATE_PREPARES   => false,

                    // Garante que números retornados como string pelo MySQL
                    // sejam convertidos para tipos nativos PHP (int, float)
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ];

                self::$instance = new PDO($dsn, $config['user'], $config['pass'], $options);

            } catch (PDOException $e) {
                // Em produção, não expomos mensagens de erro do banco para o usuário.
                // Logamos internamente e mostramos uma mensagem genérica.
                if (APP_ENV === 'development') {
                    // Em desenvolvimento, mostramos o erro completo para facilitar debug
                    die('Erro de conexão com o banco de dados: ' . $e->getMessage());
                } else {
                    // Em produção, log silencioso + mensagem amigável
                    error_log('[CRM] Falha na conexão PDO: ' . $e->getMessage());
                    die('Serviço temporariamente indisponível. Tente novamente em instantes.');
                }
            }
        }

        return self::$instance;
    }
}
