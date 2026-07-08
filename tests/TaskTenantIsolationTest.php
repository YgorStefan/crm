<?php

use App\Models\Task;
use Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Garante o invariante central do app: tarefas de um tenant nunca vazam
 * para outro. Usa um banco MySQL descartavel (crm_test) injetado no
 * singleton Database via reflexao. Se nao houver MySQL acessivel, o teste
 * e pulado (nao quebra a suite em ambientes sem banco).
 */
class TaskTenantIsolationTest extends TestCase
{
    private const TENANT_A = 1;
    private const TENANT_B = 2;

    private static ?\PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USER') ?: 'root';
        $pass = ($p = getenv('DB_PASS')) !== false ? $p : '';

        try {
            $pdo = new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS crm_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE crm_test');
        } catch (\Throwable $e) {
            self::markTestSkipped('MySQL indisponivel para teste de integracao: ' . $e->getMessage());
        }

        $pdo->exec('DROP TABLE IF EXISTS tasks');
        $pdo->exec('DROP TABLE IF EXISTS clients');
        $pdo->exec('DROP TABLE IF EXISTS users');
        $pdo->exec('CREATE TABLE users (id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), tenant_id INT UNSIGNED) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE clients (id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), tenant_id INT UNSIGNED, is_active TINYINT(1) DEFAULT 1) ENGINE=InnoDB');
        $pdo->exec(
            'CREATE TABLE tasks (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                client_id INT UNSIGNED NULL, assigned_to INT UNSIGNED, title VARCHAR(200),
                description TEXT NULL, due_date DATETIME, priority VARCHAR(20), status VARCHAR(20),
                recurrence_type VARCHAR(20) DEFAULT "none", recurrence_parent_id INT UNSIGNED NULL,
                created_by INT UNSIGNED, tenant_id INT UNSIGNED
            ) ENGINE=InnoDB'
        );

        $pdo->exec("INSERT INTO users (id, name, tenant_id) VALUES (1,'Ana',1), (2,'Bob',2)");
        $pdo->exec("INSERT INTO clients (id, name, tenant_id) VALUES (10,'Cliente A',1), (20,'Cliente B',2)");

        $prop = (new \ReflectionClass(Database::class))->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, $pdo);

        self::$pdo = $pdo;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo !== null) {
            self::$pdo->exec('DROP TABLE IF EXISTS tasks');
            self::$pdo->exec('DROP TABLE IF EXISTS clients');
            self::$pdo->exec('DROP TABLE IF EXISTS users');
            $prop = (new \ReflectionClass(Database::class))->getProperty('instance');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
            self::$pdo = null;
        }
    }

    protected function setUp(): void
    {
        self::$pdo->exec('DELETE FROM tasks');
        self::$pdo->exec('UPDATE users SET tenant_id = 1 WHERE id = 1');
    }

    private function createTaskInTenant(int $tenantId, int $assignedTo, int $createdBy, ?int $clientId = null): int
    {
        $_SESSION['tenant_id'] = $tenantId;
        return (new Task())->create([
            'assigned_to' => $assignedTo,
            'client_id'   => $clientId,
            'title'       => 'Tarefa',
            'due_date'    => '2026-06-15 10:00:00',
            'priority'    => 'medium',
            'created_by'  => $createdBy,
        ]);
    }

    public function testTarefaNaoVazaParaOutroTenant(): void
    {
        $id = $this->createTaskInTenant(self::TENANT_A, 1, 1);

        $_SESSION['tenant_id'] = self::TENANT_B;
        $task = new Task();
        $this->assertSame([], $task->findAllWithRelations());
        $this->assertFalse($task->findById($id));
        $this->assertSame(0, $task->countPending());
    }

    public function testTarefaVisivelNoProprioTenant(): void
    {
        $id = $this->createTaskInTenant(self::TENANT_A, 1, 1);

        $_SESSION['tenant_id'] = self::TENANT_A;
        $task = new Task();
        $this->assertCount(1, $task->findAllWithRelations());
        $this->assertIsArray($task->findById($id));
        $this->assertSame(1, $task->countPending());
    }

    public function testVisibilidadeSegueTenantDaTarefaNaoDoResponsavel(): void
    {
        // Tarefa criada no tenant A, atribuida a Ana (tenant A).
        $id = $this->createTaskInTenant(self::TENANT_A, 1, 1);

        // Ana e "movida" para o tenant B. A tarefa permanece no tenant A.
        self::$pdo->exec('UPDATE users SET tenant_id = 2 WHERE id = 1');

        // No tenant B a tarefa NAO deve aparecer (antes vazava via assigned_to).
        $_SESSION['tenant_id'] = self::TENANT_B;
        $this->assertSame([], (new Task())->findAllWithRelations());

        // No tenant A a tarefa continua visivel.
        $_SESSION['tenant_id'] = self::TENANT_A;
        $this->assertCount(1, (new Task())->findAllWithRelations());
        $this->assertNotFalse((new Task())->findById($id));
    }

    public function testUpdateNaoAfetaTarefaDeOutroTenant(): void
    {
        $id = $this->createTaskInTenant(self::TENANT_A, 1, 1);

        $_SESSION['tenant_id'] = self::TENANT_B;
        $this->assertFalse((new Task())->update($id, ['status' => 'done']));

        $_SESSION['tenant_id'] = self::TENANT_A;
        $this->assertSame('pending', (new Task())->findById($id)['status']);
    }

    public function testCreateRejeitaResponsavelDeOutroTenant(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Tenant A tentando atribuir a Bob (tenant B).
        $this->createTaskInTenant(self::TENANT_A, 2, 1);
    }

    public function testFindByClientEscopadoAoTenant(): void
    {
        $this->createTaskInTenant(self::TENANT_A, 1, 1, 10);

        $_SESSION['tenant_id'] = self::TENANT_A;
        $this->assertCount(1, (new Task())->findByClient(10));

        $_SESSION['tenant_id'] = self::TENANT_B;
        $this->assertSame([], (new Task())->findByClient(10));
    }
}
