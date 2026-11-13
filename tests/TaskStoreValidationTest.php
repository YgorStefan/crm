<?php

use App\Controllers\TaskController;
use Core\Database;
use PHPUnit\Framework\TestCase;

class StoreRespondeu extends \RuntimeException
{
    public function __construct(
        public readonly string $via,
        public readonly mixed $payload,
        public readonly int $status,
    ) {
        parent::__construct($via);
    }
}

// json() e redirect() terminam em exit, que derrubaria o processo do phpunit; aqui eles lancam
// StoreRespondeu, que para o fluxo de store() no mesmo ponto em que o exit pararia e entrega a resposta ao teste.
class CapturingTaskController extends TaskController
{
    protected function json(mixed $data, int $status = 200): void
    {
        throw new StoreRespondeu('json', $data, $status);
    }

    protected function redirect(string $path): void
    {
        throw new StoreRespondeu('redirect', $path, 302);
    }
}

class TaskStoreValidationTest extends TestCase
{
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
                created_by INT UNSIGNED, tenant_id INT UNSIGNED,
                google_event_id VARCHAR(255) NULL, google_owner_id INT UNSIGNED NULL,
                google_etag VARCHAR(100) NULL, google_duration_min INT UNSIGNED NULL,
                google_sync_pending INT UNSIGNED NOT NULL DEFAULT 1
            ) ENGINE=InnoDB'
        );

        $pdo->exec("INSERT INTO users (id, name, tenant_id) VALUES (1,'Ana',1), (2,'Bob',2)");
        $pdo->exec("INSERT INTO clients (id, name, tenant_id, is_active) VALUES (10,'Cliente A',1,1), (11,'Cliente Inativo',1,0), (20,'Cliente B',2,1)");

        $prop = (new \ReflectionClass(Database::class))->getProperty('instance');
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
            $prop->setValue(null, null);
            self::$pdo = null;
        }
    }

    protected function setUp(): void
    {
        self::$pdo->exec('DELETE FROM tasks');
        $_SESSION = [
            'tenant_id' => 1,
            'user'      => ['id' => 1, 'role' => 'admin'],
        ];
        $_POST = [
            'title'    => 'Ligar para o cliente',
            'due_date' => '2026-06-15T10:00',
        ];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    private function storeResponse(): StoreRespondeu
    {
        try {
            (new CapturingTaskController())->store();
        } catch (StoreRespondeu $resposta) {
            return $resposta;
        } catch (\InvalidArgumentException $e) {
            $this->fail('store() deixou a excecao subir: ' . $e->getMessage());
        }

        $this->fail('store() terminou sem responder por json() nem redirect()');
    }

    private function assertNenhumaTarefaGravada(): void
    {
        $this->assertSame(0, (int) self::$pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    private function assertRespondeu422(StoreRespondeu $resposta, string $mensagem): void
    {
        $this->assertSame('json', $resposta->via);
        $this->assertSame(422, $resposta->status);
        $this->assertSame(['success' => false, 'error' => $mensagem], $resposta->payload);
    }

    private function assertVoltouComFlash(StoreRespondeu $resposta, string $destino, string $mensagem): void
    {
        $this->assertSame('redirect', $resposta->via);
        $this->assertSame($destino, $resposta->payload);
        $this->assertSame(['type' => 'error', 'message' => $mensagem], $_SESSION['flash']);
    }

    public function testResponsavelDeOutroTenantViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['assigned_to'] = '2';

        $this->assertRespondeu422($this->storeResponse(), 'Responsável inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testClienteInativoViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['client_id'] = '11';

        $this->assertRespondeu422($this->storeResponse(), 'Cliente inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testClienteDeOutroTenantViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['client_id'] = '20';

        $this->assertRespondeu422($this->storeResponse(), 'Cliente inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testTituloVazioViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['title'] = '';

        $this->assertRespondeu422($this->storeResponse(), 'Título e prazo são obrigatórios.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testResponsavelInvalidoViaFormularioVoltaComFlash(): void
    {
        $_POST['assigned_to'] = '2';

        $this->assertVoltouComFlash($this->storeResponse(), '/tasks', 'Responsável inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testResponsavelInvalidoComClienteValidoViaFormularioVoltaParaATelaDoCliente(): void
    {
        $_POST['assigned_to'] = '2';
        $_POST['client_id'] = '10';

        $this->assertVoltouComFlash($this->storeResponse(), '/clients/10', 'Responsável inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testClienteInativoViaFormularioVoltaParaTarefas(): void
    {
        $_POST['client_id'] = '11';

        $this->assertVoltouComFlash($this->storeResponse(), '/tasks', 'Cliente inválido.');
        $this->assertNenhumaTarefaGravada();
    }
}
