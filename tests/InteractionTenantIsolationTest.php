<?php

use App\Models\Interaction;
use Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Garante que interactions.tenant_id (populado/garantido pela migration 013)
 * isola corretamente findById()/delete() entre tenants agora que
 * Interaction::$isGlobal = false (antes, isGlobal = true fazia delete()
 * ignorar o tenant por completo).
 */
class InteractionTenantIsolationTest extends TestCase
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

        $pdo->exec('DROP TABLE IF EXISTS interactions');
        $pdo->exec('DROP TABLE IF EXISTS clients');
        $pdo->exec('DROP TABLE IF EXISTS users');
        $pdo->exec('CREATE TABLE users (id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), tenant_id INT UNSIGNED) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE clients (id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), tenant_id INT UNSIGNED, is_active TINYINT DEFAULT 1) ENGINE=InnoDB');
        $pdo->exec(
            'CREATE TABLE interactions (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                client_id INT UNSIGNED, user_id INT UNSIGNED, type VARCHAR(20),
                description TEXT, occurred_at DATETIME, tenant_id INT UNSIGNED
            ) ENGINE=InnoDB'
        );

        $pdo->exec("INSERT INTO users (id, name, tenant_id) VALUES (1,'Ana',1), (2,'Bob',2)");
        $pdo->exec("INSERT INTO clients (id, name, tenant_id) VALUES (10,'Cliente A',1), (20,'Cliente B',2)");

        $prop = (new \ReflectionClass(Database::class))->getProperty('instance');
        $prop->setValue(null, $pdo);

        self::$pdo = $pdo;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo !== null) {
            self::$pdo->exec('DROP TABLE IF EXISTS interactions');
            self::$pdo->exec('DROP TABLE IF EXISTS clients');
            self::$pdo->exec('DROP TABLE IF EXISTS users');
            $prop = (new \ReflectionClass(Database::class))->getProperty('instance');
            $prop->setValue(null, null);
            self::$pdo = null;
        }
    }

    protected function setUp(): void
    {
        self::$pdo->exec('DELETE FROM interactions');
    }

    private function createInteractionInTenant(int $tenantId, int $clientId, int $userId): int
    {
        $_SESSION['tenant_id'] = $tenantId;
        return (new Interaction())->create([
            'client_id'   => $clientId,
            'user_id'     => $userId,
            'type'        => 'note',
            'description' => 'Interação de teste',
            'occurred_at' => '2026-06-15 10:00:00',
        ]);
    }

    public function testFindByIdNaoVazaParaOutroTenant(): void
    {
        $id = $this->createInteractionInTenant(self::TENANT_A, 10, 1);

        $_SESSION['tenant_id'] = self::TENANT_B;
        $this->assertFalse((new Interaction())->findById($id));
    }

    public function testFindByIdVisivelNoProprioTenant(): void
    {
        $id = $this->createInteractionInTenant(self::TENANT_A, 10, 1);

        $_SESSION['tenant_id'] = self::TENANT_A;
        $this->assertIsArray((new Interaction())->findById($id));
    }

    public function testDeleteNaoRemoveInteracaoDeOutroTenant(): void
    {
        $id = $this->createInteractionInTenant(self::TENANT_A, 10, 1);

        // Antes da correção (isGlobal = true), delete() ignorava o tenant e
        // removia qualquer ID — esse teste trava essa regressão.
        $_SESSION['tenant_id'] = self::TENANT_B;
        $this->assertFalse((new Interaction())->delete($id));

        $_SESSION['tenant_id'] = self::TENANT_A;
        $this->assertIsArray((new Interaction())->findById($id));
    }

    public function testDeleteRemoveInteracaoDoProprioTenant(): void
    {
        $id = $this->createInteractionInTenant(self::TENANT_A, 10, 1);

        $_SESSION['tenant_id'] = self::TENANT_A;
        $this->assertTrue((new Interaction())->delete($id));
        $this->assertFalse((new Interaction())->findById($id));
    }

    public function testCreateRejeitaClienteDeOutroTenant(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Tenant A tentando criar interação para cliente do tenant B.
        $this->createInteractionInTenant(self::TENANT_A, 20, 1);
    }
}
