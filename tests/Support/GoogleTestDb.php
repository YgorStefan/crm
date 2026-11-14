<?php

declare(strict_types=1);

use Core\Database;
use PHPUnit\Framework\Assert;

// o autoloader so cobre App e Core, entao o teste carrega este arquivo com
// require_once. as fks de tenant_id ficam so na migration e no schema.sql, e
// aqui entram so as restricoes de que o comportamento testado depende.
final class GoogleTestDb
{
    // filhas antes das maes, na ordem em que um DROP com FK ligada aceitaria.
    private const TABLES = [
        'google_event_deletions', 'google_accounts', 'tasks', 'clients', 'users',
        'login_attempts', 'prospecting_requests',
    ];

    public static function setUp(): \PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USER') ?: 'root';
        $pass = ($p = getenv('DB_PASS')) !== false ? $p : '';
        $dbName = testDatabaseName();

        try {
            $pdo = new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
        } catch (\PDOException $e) {
            Assert::markTestSkipped('MySQL indisponivel para teste de integracao: ' . $e->getMessage());
        }

        // o mesmo fuso que Core\Database poe na conexao, senao CURDATE() e NOW() viram UTC.
        $pdo->exec("SET time_zone = '-03:00'");

        self::dropAll($pdo);

        $pdo->exec("CREATE TABLE users (
            id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), tenant_id INT UNSIGNED,
            role VARCHAR(20) NOT NULL DEFAULT 'seller',
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB");
        $pdo->exec('CREATE TABLE clients (
            id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), tenant_id INT UNSIGNED,
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB');
        $pdo->exec("CREATE TABLE tasks (
            id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            client_id INT UNSIGNED NULL, assigned_to INT UNSIGNED, title VARCHAR(200),
            description TEXT NULL, due_date DATETIME, priority VARCHAR(20), status VARCHAR(20),
            recurrence_type VARCHAR(20) DEFAULT 'none', recurrence_parent_id INT UNSIGNED NULL,
            created_by INT UNSIGNED, tenant_id INT UNSIGNED,
            google_event_id VARCHAR(255) NULL, google_owner_id INT UNSIGNED NULL,
            google_etag VARCHAR(100) NULL, google_duration_min INT UNSIGNED NULL,
            google_sync_pending INT UNSIGNED NOT NULL DEFAULT 1,
            google_changed_fields TINYINT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_tasks_recurrence_instance (recurrence_parent_id, due_date),
            CONSTRAINT fk_task_recurrence_parent
                FOREIGN KEY (recurrence_parent_id) REFERENCES tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE google_accounts (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            google_email VARCHAR(255) NOT NULL,
            google_sub VARCHAR(255) NOT NULL,
            refresh_token TEXT NULL,
            access_token TEXT NULL,
            access_token_expires_at DATETIME NULL,
            status ENUM('active','revoked','disconnected') NOT NULL DEFAULT 'active',
            drain_until DATETIME NULL,
            connected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $pdo->exec('CREATE TABLE google_event_deletions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            google_event_id VARCHAR(1024) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_google_event_deletions_tenant_user (tenant_id, user_id)
        ) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL, identifier VARCHAR(150) NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE prospecting_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');

        (new \ReflectionClass(Database::class))->getProperty('instance')->setValue(null, $pdo);

        return $pdo;
    }

    public static function tearDown(\PDO $pdo): void
    {
        self::dropAll($pdo);
        (new \ReflectionClass(Database::class))->getProperty('instance')->setValue(null, null);
    }

    private static function dropAll(\PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
