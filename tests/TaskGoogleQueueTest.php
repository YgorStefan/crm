<?php

declare(strict_types=1);

use App\Models\GoogleAccount;
use App\Models\GoogleEventDeletion;
use App\Models\Task;
use App\Services\Google\GoogleConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';

final class TaskGoogleQueueTest extends TestCase
{
    private const TENANT       = 1;
    private const OUTRO_TENANT = 2;
    private const ANA  = 1; // tenant 1, conectada
    private const BIA  = 2; // tenant 1, conectada
    private const CAIO = 3; // tenant 1, sem conta google
    private const DUDA = 4; // tenant 2, conectada

    private ?\PDO $pdo = null;
    private string $chave = '';

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $this->chave = 'base64:' . base64_encode(str_repeat('k', 32));
        $_ENV['APP_KEY'] = $this->chave;
        $_SESSION['tenant_id'] = self::TENANT;

        $this->pdo->exec("INSERT INTO users (id, name, tenant_id) VALUES
            (1, 'Ana', 1), (2, 'Bia', 1), (3, 'Caio', 1), (4, 'Duda', 2)");
        $this->pdo->exec("INSERT INTO clients (id, name, tenant_id) VALUES (10, 'Cliente Teste', 1)");
        $this->pdo->exec("INSERT INTO google_accounts (user_id, tenant_id, google_email, google_sub) VALUES
            (1, 1, 'pessoa@example.com', 'sub-falso-1'),
            (2, 1, 'pessoa@example.com', 'sub-falso-2'),
            (4, 2, 'pessoa@example.com', 'sub-falso-4')");
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        unset($_ENV['APP_KEY'], $_ENV['GOOGLE_CLIENT_ID'], $_ENV['GOOGLE_CLIENT_SECRET'], $_SESSION['tenant_id']);
    }

    private function em(string $quando): string
    {
        return (new \DateTime($quando))->format('Y-m-d H:i:s');
    }

    /** @param array<string, mixed> $campos */
    private function tarefa(array $campos = []): int
    {
        $linha = array_merge([
            'tenant_id'            => self::TENANT,
            'assigned_to'          => self::ANA,
            'created_by'           => self::ANA,
            'client_id'            => null,
            'title'                => 'Ligar para cliente',
            'due_date'             => $this->em('+1 day'),
            'priority'             => 'medium',
            'status'               => 'pending',
            'recurrence_type'      => 'none',
            'recurrence_parent_id' => null,
            'google_event_id'      => null,
            'google_owner_id'      => null,
            'google_etag'          => null,
            'google_duration_min'  => null,
            'google_sync_pending'  => 1,
        ], $campos);

        $colunas = implode(', ', array_keys($linha));
        $marcas  = implode(', ', array_map(fn (string $c): string => ":{$c}", array_keys($linha)));
        $valores = [];
        foreach ($linha as $coluna => $valor) {
            $valores[":{$coluna}"] = $valor;
        }
        $this->pdo->prepare("INSERT INTO tasks ({$colunas}) VALUES ({$marcas})")->execute($valores);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|false */
    private function linha(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /** @return list<array{tenant_id:int, user_id:int, google_event_id:string}> */
    private function remocoes(): array
    {
        return $this->pdo
            ->query('SELECT tenant_id, user_id, google_event_id FROM google_event_deletions ORDER BY google_event_id')
            ->fetchAll();
    }

    private function idDeterministico(int $tenantId, int $taskId): string
    {
        return (new GoogleConfig('', '', $this->chave, ''))->eventIdFor($tenantId, $taskId);
    }

    public function testUpdateSomaUmNaPendencia(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 1]);

        (new Task())->update($id, ['title' => 'Ligar de novo']);

        $this->assertSame(2, (int) $this->linha($id)['google_sync_pending']);
    }

    public function testUpdateSemCampoNaoSoma(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 1]);

        $this->assertFalse((new Task())->update($id, ['campo_qualquer' => 'x']));
        $this->assertSame(1, (int) $this->linha($id)['google_sync_pending']);
    }

    public function testSoPrioridadeNaoSomaNemMarca(): void
    {
        // a prioridade nao vai ao google, e somar regravaria o evento inteiro.
        $id = $this->tarefa(['google_sync_pending' => 0]);

        $this->assertTrue((new Task())->update($id, ['priority' => 'high']));

        $linha = $this->linha($id);
        $this->assertSame('high', $linha['priority']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertSame(0, (int) $linha['google_changed_fields']);
    }

    /** @return array<string, array{string, string|null}> */
    public static function modalSemMudanca(): array
    {
        return [
            'prazo como o controller grava, descricao nula' => ['2030-01-10 09:00:00', null],
            'prazo como o formulario manda'                 => ['2030-01-10T09:00', null],
            'prazo sem segundos, descricao vazia'           => ['2030-01-10 09:00', ''],
            'descricao com espacos em volta'                => ['2030-01-10 09:00:00', '  Levar contrato '],
        ];
    }

    #[DataProvider('modalSemMudanca')]
    public function testModalReenviandoOsMesmosValoresNaoSoma(string $prazo, ?string $descricaoGuardada): void
    {
        $id = $this->tarefa([
            'title' => 'Ligar para cliente', 'due_date' => '2030-01-10 09:00:00',
            'description' => $descricaoGuardada, 'google_sync_pending' => 0,
        ]);

        $this->assertTrue((new Task())->update($id, [
            'title'       => 'Ligar para cliente',
            'due_date'    => $prazo,
            'priority'    => 'medium',
            'description' => trim((string) $descricaoGuardada),
        ]));

        $linha = $this->linha($id);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertSame(0, (int) $linha['google_changed_fields']);
    }

    /** @return array<string, array{array<string, string>, int}> */
    public static function campoQueMuda(): array
    {
        return [
            'titulo'    => [['title' => 'Ligar de novo'], Task::GOOGLE_TITLE],
            'descricao' => [['description' => 'Levar contrato'], Task::GOOGLE_DESCRIPTION],
            'prazo'     => [['due_date' => '2030-01-11 09:00:00'], Task::GOOGLE_DUE_DATE],
            'status'    => [['status' => 'done'], Task::GOOGLE_STATUS],
        ];
    }

    /** @param array<string, string> $mudanca */
    #[DataProvider('campoQueMuda')]
    public function testCampoQueMudaSomaEMarcaSoEle(array $mudanca, int $marca): void
    {
        $id = $this->tarefa(['due_date' => '2030-01-10 09:00:00', 'google_sync_pending' => 0]);

        (new Task())->update($id, $mudanca + [
            'title' => 'Ligar para cliente', 'due_date' => '2030-01-10 09:00:00', 'priority' => 'low', 'description' => '',
        ]);

        $linha = $this->linha($id);
        $this->assertSame(1, (int) $linha['google_sync_pending']);
        $this->assertSame($marca, (int) $linha['google_changed_fields']);
    }

    public function testMarcasSeAcumulamAteOEnvio(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 0]);

        (new Task())->update($id, ['title' => 'Ligar de novo']);
        (new Task())->update($id, ['description' => 'Levar contrato']);

        $linha = $this->linha($id);
        $this->assertSame(2, (int) $linha['google_sync_pending']);
        $this->assertSame(Task::GOOGLE_TITLE | Task::GOOGLE_DESCRIPTION, (int) $linha['google_changed_fields']);
    }

    public function testTrocaDeResponsavelSomaSemMarcarCampo(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 0]);

        (new Task())->update($id, ['assigned_to' => self::BIA, 'title' => 'Ligar para cliente']);

        $linha = $this->linha($id);
        $this->assertSame(1, (int) $linha['google_sync_pending']);
        $this->assertSame(0, (int) $linha['google_changed_fields']);
    }

    public function testUpdateDeTarefaDeOutroTenantDevolveFalse(): void
    {
        $id = $this->tarefa([
            'tenant_id' => self::OUTRO_TENANT, 'assigned_to' => self::DUDA, 'created_by' => self::DUDA,
            'google_sync_pending' => 0,
        ]);

        $this->assertFalse((new Task())->update($id, ['title' => 'Invadida']));

        $linha = $this->linha($id);
        $this->assertSame('Ligar para cliente', $linha['title']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
    }

    public function testDeleteCopiaEventoParaRemocoes(): void
    {
        $id = $this->tarefa([
            'google_event_id' => 'evento-ana-1', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);

        $this->assertTrue((new Task())->delete($id));

        $this->assertFalse($this->linha($id));
        $this->assertSame(
            [['tenant_id' => self::TENANT, 'user_id' => self::ANA, 'google_event_id' => 'evento-ana-1']],
            $this->remocoes()
        );
        $fila = (new GoogleEventDeletion())->forUser(self::ANA, 10);
        $this->assertCount(1, $fila);
        $this->assertSame('evento-ana-1', $fila[0]['google_event_id']);
    }

    public function testDeleteDoMoldeCopiaAsFilhas(): void
    {
        $molde = $this->tarefa([
            'recurrence_type' => 'monthly',
            'google_event_id' => 'evento-molde', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);
        $this->tarefa([
            'recurrence_parent_id' => $molde, 'due_date' => $this->em('+1 month'),
            'google_event_id' => 'evento-filha-1', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);
        $this->tarefa([
            'recurrence_parent_id' => $molde, 'due_date' => $this->em('+2 months'), 'status' => 'done',
            'google_event_id' => 'evento-filha-2', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);

        $this->assertTrue((new Task())->delete($molde));

        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
        $this->assertSame(
            ['evento-filha-1', 'evento-filha-2', 'evento-molde'],
            array_column($this->remocoes(), 'google_event_id')
        );
    }

    public function testDeleteSemIdComPendenciaEContaUsaIdDeterministico(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 1]);

        (new Task())->delete($id);

        $fila = (new GoogleEventDeletion())->forUser(self::ANA, 10);
        $this->assertCount(1, $fila);
        $this->assertSame($this->idDeterministico(self::TENANT, $id), $fila[0]['google_event_id']);
    }

    public function testDeleteSemIdESemPendenciaNaoEnfileira(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 0]);

        (new Task())->delete($id);

        $this->assertFalse($this->linha($id));
        $this->assertSame([], $this->remocoes());
    }

    public function testDeleteSemContaNaoEnfileira(): void
    {
        $id = $this->tarefa(['assigned_to' => self::CAIO, 'created_by' => self::CAIO, 'google_sync_pending' => 1]);

        $this->assertTrue((new Task())->delete($id));

        $this->assertFalse($this->linha($id));
        $this->assertSame([], $this->remocoes());
    }

    public function testDeleteSemIdComContaNaoAtivaNaoEnfileira(): void
    {
        $this->pdo->exec("UPDATE google_accounts SET status = 'disconnected' WHERE user_id = 1");
        $this->pdo->exec("UPDATE google_accounts SET status = 'revoked' WHERE user_id = 2");
        $daAna = $this->tarefa(['google_sync_pending' => 1]);
        $daBia = $this->tarefa(['assigned_to' => self::BIA, 'google_sync_pending' => 1]);

        $this->assertTrue((new Task())->delete($daAna));
        $this->assertTrue((new Task())->delete($daBia));

        $this->assertSame([], $this->remocoes());
    }

    public function testDeleteNaoApagaDeOutroTenant(): void
    {
        $id = $this->tarefa([
            'tenant_id' => self::OUTRO_TENANT, 'assigned_to' => self::DUDA, 'created_by' => self::DUDA,
            'google_event_id' => 'evento-duda', 'google_owner_id' => self::DUDA, 'google_sync_pending' => 0,
        ]);

        $this->assertFalse((new Task())->delete($id));

        $this->assertNotFalse($this->linha($id));
        $this->assertSame([], $this->remocoes());
    }

    public function testCancelarSerieCopiaSoFilhasPendentes(): void
    {
        $molde = $this->tarefa([
            'recurrence_type' => 'monthly',
            'google_event_id' => 'evento-molde', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);
        $feita = $this->tarefa([
            'recurrence_parent_id' => $molde, 'due_date' => $this->em('+1 month'), 'status' => 'done',
            'google_event_id' => 'evento-feita', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);
        $pendente = $this->tarefa([
            'recurrence_parent_id' => $molde, 'due_date' => $this->em('+2 months'),
            'google_event_id' => 'evento-pendente', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);
        $semId = $this->tarefa([
            'recurrence_parent_id' => $molde, 'due_date' => $this->em('+3 months'), 'google_sync_pending' => 1,
        ]);

        (new Task())->cancelRecurrence($molde);

        $esperado = ['evento-pendente', $this->idDeterministico(self::TENANT, $semId)];
        sort($esperado);
        $this->assertSame($esperado, array_column($this->remocoes(), 'google_event_id'));
        $this->assertNotFalse($this->linha($feita));
        $this->assertFalse($this->linha($pendente));
        $this->assertFalse($this->linha($semId));
        $this->assertSame('none', $this->linha($molde)['recurrence_type']);
    }

    public function testTrocaDeResponsavelGravaRemocaoELimpa(): void
    {
        $id = $this->tarefa([
            'google_event_id' => 'evento-ana', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_duration_min' => 45, 'google_sync_pending' => 0,
        ]);

        (new Task())->update($id, ['assigned_to' => self::BIA]);

        $this->assertSame(
            [['tenant_id' => self::TENANT, 'user_id' => self::ANA, 'google_event_id' => 'evento-ana']],
            $this->remocoes()
        );
        $linha = $this->linha($id);
        $this->assertSame(self::BIA, (int) $linha['assigned_to']);
        $this->assertNull($linha['google_event_id']);
        $this->assertNull($linha['google_owner_id']);
        $this->assertNull($linha['google_etag']);
        $this->assertSame(45, (int) $linha['google_duration_min']);
        $this->assertSame(1, (int) $linha['google_sync_pending']);
    }

    public function testTrocaSemIdComPendenciaEContaRemoveIdDeterministicoDoDonoAntigo(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 1]);

        (new Task())->update($id, ['assigned_to' => self::BIA]);

        $this->assertSame(
            [['tenant_id' => self::TENANT, 'user_id' => self::ANA, 'google_event_id' => $this->idDeterministico(self::TENANT, $id)]],
            $this->remocoes()
        );
        $linha = $this->linha($id);
        $this->assertSame(self::BIA, (int) $linha['assigned_to']);
        $this->assertSame(2, (int) $linha['google_sync_pending']);
    }

    public function testTrocaSemIdDeDonoSemContaNaoEnfileira(): void
    {
        $id = $this->tarefa(['assigned_to' => self::CAIO, 'created_by' => self::CAIO, 'google_sync_pending' => 1]);

        (new Task())->update($id, ['assigned_to' => self::ANA]);

        $this->assertSame([], $this->remocoes());
        $this->assertSame(self::ANA, (int) $this->linha($id)['assigned_to']);
    }

    public function testTrocaSemIdESemPendenciaNaoEnfileira(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 0]);

        (new Task())->update($id, ['assigned_to' => self::BIA]);

        $this->assertSame([], $this->remocoes());
    }

    public function testMesmoResponsavelNaoMoveEvento(): void
    {
        $id = $this->tarefa([
            'google_event_id' => 'evento-ana', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);

        (new Task())->update($id, ['assigned_to' => self::ANA]);

        $this->assertSame([], $this->remocoes());
        $this->assertSame('evento-ana', $this->linha($id)['google_event_id']);
    }

    public function testTrocaParaResponsavelDeOutroTenantNaoGravaNada(): void
    {
        $id = $this->tarefa([
            'google_event_id' => 'evento-ana', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);

        try {
            (new Task())->update($id, ['assigned_to' => self::DUDA]);
            $this->fail('responsavel de outro tenant deveria ser recusado');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame([], $this->remocoes());
        $this->assertSame('evento-ana', $this->linha($id)['google_event_id']);
        $this->assertSame(0, (int) $this->linha($id)['google_sync_pending']);
    }

    public function testMarkSyncedGravaVinculoEZera(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 1]);

        $this->assertTrue((new Task())->markSynced($id, 1, 'evento-novo', self::ANA, '"etag-1"', 30));

        $linha = $this->linha($id);
        $this->assertSame('evento-novo', $linha['google_event_id']);
        $this->assertSame(self::ANA, (int) $linha['google_owner_id']);
        $this->assertSame('"etag-1"', $linha['google_etag']);
        $this->assertSame(30, (int) $linha['google_duration_min']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
    }

    public function testMarkSyncedRecusaSeContadorMudou(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 1]);
        (new Task())->update($id, ['title' => 'Editada durante o envio']);

        $this->assertFalse((new Task())->markSynced($id, 1, 'evento-novo', self::ANA, '"etag-1"', 30));

        $this->assertSame(2, (int) $this->linha($id)['google_sync_pending']);
    }

    public function testMarkSyncedComContadorMudadoGuardaVinculoDoMesmoDono(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 1]);
        (new Task())->update($id, ['due_date' => $this->em('-2 days')]);

        $this->assertFalse((new Task())->markSynced($id, 1, 'evento-novo', self::ANA, '"etag-1"', 30));

        $linha = $this->linha($id);
        $this->assertSame('evento-novo', $linha['google_event_id']);
        $this->assertSame(self::ANA, (int) $linha['google_owner_id']);
        $this->assertSame('"etag-1"', $linha['google_etag']);
        $this->assertSame(30, (int) $linha['google_duration_min']);
        $this->assertSame(2, (int) $linha['google_sync_pending']);
        // vencida e vinculada, segue na fila para o envio levar a data nova.
        $this->assertSame([$id], array_map(fn (array $t): int => (int) $t['id'], (new Task())->pendingForUser(self::ANA, 10)));
    }

    public function testMarkSyncedComOutroResponsavelNaoGravaNada(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 1]);
        (new Task())->update($id, ['assigned_to' => self::BIA]);

        $this->assertFalse((new Task())->markSynced($id, 1, 'evento-novo', self::ANA, '"etag-1"', 30));

        $linha = $this->linha($id);
        $this->assertNull($linha['google_event_id']);
        $this->assertNull($linha['google_owner_id']);
        $this->assertNull($linha['google_etag']);
        $this->assertSame(2, (int) $linha['google_sync_pending']);
    }

    public function testDropFromQueueZeraSoComContadorVisto(): void
    {
        $id = $this->tarefa(['google_sync_pending' => 2, 'google_changed_fields' => Task::GOOGLE_DESCRIPTION]);

        (new Task())->dropFromQueue($id, 1);
        $this->assertSame(2, (int) $this->linha($id)['google_sync_pending']);
        $this->assertSame(Task::GOOGLE_DESCRIPTION, (int) $this->linha($id)['google_changed_fields']);

        (new Task())->dropFromQueue($id, 2);
        $this->assertSame(0, (int) $this->linha($id)['google_sync_pending']);
        $this->assertSame(0, (int) $this->linha($id)['google_changed_fields']);
    }

    public function testMarkSyncedZeraAsMarcasEAplicaOQueVeioDoGoogle(): void
    {
        $id = $this->tarefa([
            'title' => 'Do CRM', 'due_date' => '2030-01-10 09:00:00', 'google_duration_min' => 30,
            'google_sync_pending' => 1, 'google_changed_fields' => Task::GOOGLE_DESCRIPTION,
        ]);

        $this->assertTrue((new Task())->markSynced($id, 1, 'evento-a', self::ANA, '"etag-2"', 30, [
            'title' => 'Do Google', 'due_date' => '2030-01-12 15:00:00', 'google_duration_min' => 45,
        ]));

        $linha = $this->linha($id);
        $this->assertSame('Do Google', $linha['title']);
        $this->assertSame('2030-01-12 15:00:00', $linha['due_date']);
        $this->assertSame(45, (int) $linha['google_duration_min']);
        $this->assertSame('"etag-2"', $linha['google_etag']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertSame(0, (int) $linha['google_changed_fields']);
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function googleNoSegundoRamo(): array
    {
        return [
            'igual a tarefa grava o etag novo'      => [['title' => 'Editada durante o envio', 'due_date' => '2030-01-10 09:00:00'], '"etag-2"'],
            'inicio diferente mantem o etag antigo' => [['due_date' => '2030-01-12 15:00:00'], '"etag-1"'],
            'titulo so com outra caixa mantem'      => [['title' => 'editada durante o envio'], '"etag-1"'],
            'sem campo do google grava o novo'      => [[], '"etag-2"'],
        ];
    }

    /** @param array<string, string> $doGoogle */
    #[DataProvider('googleNoSegundoRamo')]
    public function testMarkSyncedComContadorMudadoSoGuardaEtagQueNaoEscondeOGoogle(array $doGoogle, string $etagFinal): void
    {
        $id = $this->tarefa([
            'title' => 'Do CRM', 'due_date' => '2030-01-10 09:00:00', 'google_etag' => '"etag-1"',
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_sync_pending' => 1, 'google_changed_fields' => Task::GOOGLE_DESCRIPTION,
        ]);
        (new Task())->update($id, ['title' => 'Editada durante o envio']);

        $this->assertFalse((new Task())->markSynced($id, 1, 'evento-a', self::ANA, '"etag-2"', 30, $doGoogle));

        $this->assertSame($etagFinal, $this->linha($id)['google_etag']);
    }

    public function testMarkSyncedComContadorMudadoMantemMarcasECampos(): void
    {
        $id = $this->tarefa([
            'title' => 'Do CRM', 'due_date' => '2030-01-10 09:00:00',
            'google_sync_pending' => 1, 'google_changed_fields' => Task::GOOGLE_DESCRIPTION,
        ]);
        (new Task())->update($id, ['title' => 'Editada durante o envio']);

        $this->assertFalse((new Task())->markSynced($id, 1, 'evento-a', self::ANA, '"etag-2"', 30, [
            'due_date' => '2030-01-12 15:00:00',
        ]));

        $linha = $this->linha($id);
        $this->assertSame('Editada durante o envio', $linha['title']);
        $this->assertSame('2030-01-10 09:00:00', $linha['due_date']);
        $this->assertSame(2, (int) $linha['google_sync_pending']);
        $this->assertSame(Task::GOOGLE_DESCRIPTION | Task::GOOGLE_TITLE, (int) $linha['google_changed_fields']);
    }

    public function testApplyGoogleChangeNaoSoma(): void
    {
        $id = $this->tarefa([
            'title' => 'Antigo', 'due_date' => '2030-01-10 09:00:00',
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);

        $aplicou = (new Task())->applyGoogleChange($id, '"etag-1"', [
            'title' => 'Novo', 'due_date' => '2030-01-11 14:30:00', 'google_duration_min' => 60,
        ], '"etag-2"');

        $this->assertTrue($aplicou);
        $linha = $this->linha($id);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertSame('"etag-2"', $linha['google_etag']);
        $this->assertSame('Novo', $linha['title']);
        $this->assertSame('2030-01-11 14:30:00', $linha['due_date']);
        $this->assertSame(60, (int) $linha['google_duration_min']);
    }

    public function testApplyGoogleChangeSoGuardaEtagSemCampos(): void
    {
        $id = $this->tarefa([
            'title' => 'Antigo', 'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);

        $this->assertTrue((new Task())->applyGoogleChange($id, '"etag-1"', [], '"etag-2"'));

        $this->assertSame('"etag-2"', $this->linha($id)['google_etag']);
        $this->assertSame('Antigo', $this->linha($id)['title']);
    }

    public function testApplyGoogleChangeIgnoraOutrasChaves(): void
    {
        $id = $this->tarefa([
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);

        (new Task())->applyGoogleChange($id, '"etag-1"', ['status' => 'cancelled', 'assigned_to' => self::BIA], '"etag-2"');

        $linha = $this->linha($id);
        $this->assertSame('pending', $linha['status']);
        $this->assertSame(self::ANA, (int) $linha['assigned_to']);
    }

    public function testApplyGoogleChangeAceitaEtagNulo(): void
    {
        $id = $this->tarefa([
            'title' => 'Antigo', 'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => null, 'google_sync_pending' => 0,
        ]);

        $this->assertTrue((new Task())->applyGoogleChange($id, null, ['title' => 'Novo'], '"etag-2"'));

        $this->assertSame('Novo', $this->linha($id)['title']);
    }

    public function testApplyGoogleChangeRecusaComPendencia(): void
    {
        $id = $this->tarefa([
            'title' => 'Do CRM', 'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 1,
        ]);

        $this->assertFalse((new Task())->applyGoogleChange($id, '"etag-1"', ['title' => 'Do Google'], '"etag-2"'));

        $linha = $this->linha($id);
        $this->assertSame('Do CRM', $linha['title']);
        $this->assertSame('"etag-1"', $linha['google_etag']);
        $this->assertSame(1, (int) $linha['google_sync_pending']);
    }

    public function testApplyGoogleChangeRecusaEtagDiferente(): void
    {
        $id = $this->tarefa([
            'title' => 'Do CRM', 'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);

        $this->assertFalse((new Task())->applyGoogleChange($id, '"etag-0"', ['title' => 'Velho'], '"etag-2"'));

        $this->assertSame('Do CRM', $this->linha($id)['title']);
        $this->assertSame('"etag-1"', $this->linha($id)['google_etag']);
    }

    public function testApplyGoogleChangeNaoMexeEmOutroTenant(): void
    {
        $id = $this->tarefa([
            'tenant_id' => self::OUTRO_TENANT, 'assigned_to' => self::DUDA, 'created_by' => self::DUDA,
            'title' => 'Da Duda', 'google_event_id' => 'evento-d', 'google_owner_id' => self::DUDA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);

        $this->assertFalse((new Task())->applyGoogleChange($id, '"etag-1"', ['title' => 'Invadida'], '"etag-2"'));

        $this->assertSame('Da Duda', $this->linha($id)['title']);
    }

    public function testUnlinkGoogleLimpaVinculoENaoSoma(): void
    {
        $cancelada = $this->tarefa([
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);
        $mantida = $this->tarefa([
            'status' => 'done', 'google_event_id' => 'evento-b', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);

        (new Task())->unlinkGoogle($cancelada, self::ANA, '"etag-1"', 'cancelled');
        (new Task())->unlinkGoogle($mantida, self::ANA, '"etag-1"');

        $linha = $this->linha($cancelada);
        $this->assertSame('cancelled', $linha['status']);
        $this->assertNull($linha['google_event_id']);
        $this->assertNull($linha['google_owner_id']);
        $this->assertNull($linha['google_etag']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertSame('done', $this->linha($mantida)['status']);
        $this->assertNull($this->linha($mantida)['google_event_id']);
    }

    public function testUnlinkGoogleRespeitaPendencia(): void
    {
        $id = $this->tarefa([
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 1,
        ]);

        (new Task())->unlinkGoogle($id, self::ANA, '"etag-1"', 'cancelled');

        $this->assertSame('pending', $this->linha($id)['status']);
        $this->assertSame('evento-a', $this->linha($id)['google_event_id']);
    }

    public function testUnlinkGoogleRecusaDonoOuEtagDiferenteDoLido(): void
    {
        // a linha mudou depois da leitura: outro dono, ou o mesmo dono com outro envio.
        $outroDono = $this->tarefa([
            'assigned_to' => self::BIA, 'google_event_id' => 'evento-bia', 'google_owner_id' => self::BIA,
            'google_etag' => '"etag-2"', 'google_sync_pending' => 0,
        ]);
        $outroEtag = $this->tarefa([
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-2"', 'google_sync_pending' => 0,
        ]);

        (new Task())->unlinkGoogle($outroDono, self::ANA, '"etag-1"', 'cancelled');
        (new Task())->unlinkGoogle($outroEtag, self::ANA, '"etag-1"', 'cancelled');

        $this->assertSame('pending', $this->linha($outroDono)['status']);
        $this->assertSame('evento-bia', $this->linha($outroDono)['google_event_id']);
        $this->assertSame('pending', $this->linha($outroEtag)['status']);
        $this->assertSame('evento-a', $this->linha($outroEtag)['google_event_id']);
    }

    public function testMarkOpenUnlinkedIgnoraVinculadas(): void
    {
        $solta     = $this->tarefa(['google_sync_pending' => 0]);
        $andamento = $this->tarefa(['status' => 'in_progress', 'google_sync_pending' => 0]);
        $vinculada = $this->tarefa([
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA, 'google_sync_pending' => 0,
        ]);
        $feita     = $this->tarefa(['status' => 'done', 'google_sync_pending' => 0]);
        $vencida   = $this->tarefa(['due_date' => $this->em('-2 days'), 'google_sync_pending' => 0]);
        $daBia     = $this->tarefa(['assigned_to' => self::BIA, 'google_sync_pending' => 0]);
        $outroTenant = $this->tarefa([
            'tenant_id' => self::OUTRO_TENANT, 'assigned_to' => self::ANA, 'google_sync_pending' => 0,
        ]);

        $marcadas = (new Task())->markOpenUnlinkedPending(self::ANA);

        $this->assertSame(2, $marcadas);
        $this->assertSame(1, (int) $this->linha($solta)['google_sync_pending']);
        $this->assertSame(1, (int) $this->linha($andamento)['google_sync_pending']);
        foreach ([$vinculada, $feita, $vencida, $daBia, $outroTenant] as $id) {
            $this->assertSame(0, (int) $this->linha($id)['google_sync_pending'], "tarefa {$id}");
        }
    }

    public function testClearLinksFicaNoUsuarioENoTenant(): void
    {
        $daAna = $this->tarefa([
            'google_event_id' => 'evento-ana', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);
        $daBia = $this->tarefa([
            'assigned_to' => self::BIA, 'google_event_id' => 'evento-bia', 'google_owner_id' => self::BIA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);
        $outroTenant = $this->tarefa([
            'tenant_id' => self::OUTRO_TENANT, 'google_event_id' => 'evento-t2', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);

        (new Task())->clearGoogleLinksForUser(self::ANA);

        $linha = $this->linha($daAna);
        $this->assertNull($linha['google_event_id']);
        $this->assertNull($linha['google_owner_id']);
        $this->assertNull($linha['google_etag']);
        $this->assertSame('evento-bia', $this->linha($daBia)['google_event_id']);
        $this->assertSame('evento-t2', $this->linha($outroTenant)['google_event_id']);
    }

    public function testPendingSegueElegibilidadeEOrdem(): void
    {
        $futuraLonge = $this->tarefa(['due_date' => $this->em('+5 days')]);
        $futuraPerto = $this->tarefa(['due_date' => $this->em('+1 day'), 'client_id' => 10]);
        $vencidaSolta = $this->tarefa(['due_date' => $this->em('-3 days')]);
        $vencidaVinculada = $this->tarefa([
            'due_date' => $this->em('-3 days'), 'status' => 'done',
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
        ]);
        $cancelada = $this->tarefa(['due_date' => $this->em('+3 days'), 'status' => 'cancelled']);
        $semPendencia = $this->tarefa(['due_date' => $this->em('+2 days'), 'google_sync_pending' => 0]);
        $daBia = $this->tarefa(['assigned_to' => self::BIA, 'due_date' => $this->em('+2 days')]);

        $fila = (new Task())->pendingForUser(self::ANA, 10);

        $this->assertSame(
            [$vencidaVinculada, $futuraPerto, $cancelada, $futuraLonge],
            array_map(fn (array $t): int => (int) $t['id'], $fila)
        );
        $this->assertSame('Cliente Teste', $fila[1]['client_name']);
        $this->assertSame(1, (int) $fila[1]['google_sync_pending']);

        $limitada = (new Task())->pendingForUser(self::ANA, 2);
        $this->assertSame([$vencidaVinculada, $futuraPerto], array_map(fn (array $t): int => (int) $t['id'], $limitada));

        $this->assertNotContains($vencidaSolta, array_column($fila, 'id'));
        $this->assertNotContains($semPendencia, array_column($fila, 'id'));
        $this->assertNotContains($daBia, array_column($fila, 'id'));
    }

    public function testPendingNaoVazaEntreTenants(): void
    {
        $doTenant = $this->tarefa();
        $this->tarefa(['tenant_id' => self::OUTRO_TENANT, 'assigned_to' => self::ANA]);

        $fila = (new Task())->pendingForUser(self::ANA, 10);

        $this->assertSame([$doTenant], array_map(fn (array $t): int => (int) $t['id'], $fila));
    }

    public function testLinkMapTrazSoVinculadasDoDonoSemCanceladas(): void
    {
        $vinculada = $this->tarefa([
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_sync_pending' => 0,
        ]);
        $this->tarefa([
            'status' => 'cancelled', 'google_event_id' => 'evento-c', 'google_owner_id' => self::ANA,
        ]);
        $this->tarefa();
        $this->tarefa([
            'assigned_to' => self::BIA, 'google_event_id' => 'evento-b', 'google_owner_id' => self::BIA,
        ]);
        $this->tarefa([
            'tenant_id' => self::OUTRO_TENANT, 'google_event_id' => 'evento-t2', 'google_owner_id' => self::ANA,
        ]);

        $mapa = (new Task())->linkMap(self::ANA);

        $this->assertSame(['evento-a'], array_keys($mapa));
        $this->assertSame($vinculada, (int) $mapa['evento-a']['id']);
        $this->assertSame('"etag-1"', $mapa['evento-a']['google_etag']);
        $this->assertSame(0, (int) $mapa['evento-a']['google_sync_pending']);
    }

    public function testFeedTrazColunasDoGoogle(): void
    {
        $this->tarefa([
            'google_event_id' => 'evento-a', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"', 'google_duration_min' => 45, 'google_sync_pending' => 0,
        ]);

        $feed = (new Task())->findForCalendar(self::ANA, false);

        $this->assertCount(1, $feed);
        $this->assertSame('evento-a', $feed[0]['google_event_id']);
        $this->assertSame(self::ANA, (int) $feed[0]['google_owner_id']);
        $this->assertSame('"etag-1"', $feed[0]['google_etag']);
        $this->assertSame(45, (int) $feed[0]['google_duration_min']);
        $this->assertSame(0, (int) $feed[0]['google_sync_pending']);
    }

    public function testMaterializeRecurringGeraFilhas(): void
    {
        $molde = $this->tarefa(['recurrence_type' => 'monthly']);

        (new Task())->materializeRecurring(self::ANA, false);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tasks WHERE recurrence_parent_id = :pid');
        $stmt->execute([':pid' => $molde]);
        $this->assertGreaterThan(0, (int) $stmt->fetchColumn());
    }

    public function testEquipeMaterializaOutrosUsuarios(): void
    {
        $moldeDaBia = $this->tarefa([
            'assigned_to' => self::BIA, 'created_by' => self::BIA, 'recurrence_type' => 'monthly',
        ]);

        (new Task())->findTeamForCalendar(self::ANA, null, null);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tasks WHERE recurrence_parent_id = :pid');
        $stmt->execute([':pid' => $moldeDaBia]);
        $this->assertGreaterThan(0, (int) $stmt->fetchColumn());
    }

    public function testEquipeTrazOutrosResponsaveisComNome(): void
    {
        $this->tarefa(['title' => 'Da Ana']);
        $this->tarefa(['assigned_to' => self::BIA, 'title' => 'Da Bia']);
        $this->tarefa(['assigned_to' => self::CAIO, 'title' => 'Cancelada do Caio', 'status' => 'cancelled']);
        $this->tarefa([
            'tenant_id' => self::OUTRO_TENANT, 'assigned_to' => self::DUDA, 'created_by' => self::DUDA,
            'title' => 'Da Duda',
        ]);

        $equipe = (new Task())->findTeamForCalendar(self::ANA, null, null);

        $this->assertSame(['Da Bia'], array_column($equipe, 'title'));
        $this->assertSame('Bia', $equipe[0]['assigned_name']);
    }

    public function testEquipeRespeitaJanela(): void
    {
        $this->tarefa(['assigned_to' => self::BIA, 'title' => 'Dentro', 'due_date' => '2030-03-10 10:00:00']);
        $this->tarefa(['assigned_to' => self::BIA, 'title' => 'Fora', 'due_date' => '2030-04-10 10:00:00']);

        $equipe = (new Task())->findTeamForCalendar(self::ANA, '2030-03-01 00:00:00', '2030-04-01 00:00:00');

        $this->assertSame(['Dentro'], array_column($equipe, 'title'));
    }

    public function testRemocoesFicamNoUsuarioENoTenant(): void
    {
        $remocoes = new GoogleEventDeletion();
        $remocoes->enqueue(self::ANA, 'evento-1');
        $remocoes->enqueue(self::ANA, 'evento-2');
        $remocoes->enqueue(self::ANA, 'evento-3');
        $remocoes->enqueue(self::BIA, 'evento-bia');
        $this->pdo->exec("INSERT INTO google_event_deletions (tenant_id, user_id, google_event_id)
            VALUES (2, 1, 'evento-t2')");

        $fila = $remocoes->forUser(self::ANA, 2);
        $this->assertSame(['evento-1', 'evento-2'], array_column($fila, 'google_event_id'));
        $this->assertSame(self::ANA, $fila[0]['user_id']);
        $this->assertIsInt($fila[0]['id']);

        $remocoes->remove($fila[0]['id']);
        $this->assertSame(['evento-2', 'evento-3'], array_column($remocoes->forUser(self::ANA, 10), 'google_event_id'));

        $remocoes->purgeForUser(self::ANA);
        $this->assertSame([], $remocoes->forUser(self::ANA, 10));
        $this->assertSame(['evento-bia'], array_column($remocoes->forUser(self::BIA, 10), 'google_event_id'));
        $this->assertSame(
            ['evento-bia', 'evento-t2'],
            array_column($this->remocoes(), 'google_event_id')
        );
    }

    public function testContaGoogleFicaNoTenant(): void
    {
        $this->assertSame('pessoa@example.com', (new GoogleAccount())->findForUser(self::ANA)['google_email']);
        $this->assertFalse((new GoogleAccount())->findForUser(self::CAIO));

        $_SESSION['tenant_id'] = self::OUTRO_TENANT;
        $this->assertFalse((new GoogleAccount())->findForUser(self::ANA));
    }

    public function testEventIdTemFormatoDoGoogle(): void
    {
        $config = new GoogleConfig('', '', $this->chave, '');
        $id = $config->eventIdFor(1, 42);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $id);
        $this->assertSame($id, $config->eventIdFor(1, 42));
        $this->assertNotSame($id, $config->eventIdFor(2, 42));
        $this->assertNotSame($id, (new GoogleConfig('', '', 'outra-chave-falsa', ''))->eventIdFor(1, 42));
        // o id ja enviado ao google precisa continuar o mesmo entre versoes,
        // senao o reenvio cria evento duplicado: a formula da spec fica fixada.
        $this->assertSame(substr(hash_hmac('sha256', 'google-event|1|42', $this->chave), 0, 40), $id);
    }

    public function testIsConfiguredExigeOsTres(): void
    {
        $this->assertTrue((new GoogleConfig('id-falso', 'segredo-falso', 'chave-falsa', ''))->isConfigured());
        $this->assertFalse((new GoogleConfig('', 'segredo-falso', 'chave-falsa', ''))->isConfigured());
        $this->assertFalse((new GoogleConfig('id-falso', '', 'chave-falsa', ''))->isConfigured());
        $this->assertFalse((new GoogleConfig('id-falso', 'segredo-falso', '', ''))->isConfigured());
    }

    public function testConfigVemDoAmbiente(): void
    {
        $_ENV['GOOGLE_CLIENT_ID'] = 'id-falso';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'segredo-falso';

        $config = GoogleConfig::fromEnv();

        $this->assertSame('id-falso', $config->clientId);
        $this->assertSame('segredo-falso', $config->clientSecret);
        $this->assertSame($this->chave, $config->appKey);
        $this->assertSame('http://localhost/crm/public/google/callback', $config->redirectUri);
        $this->assertTrue($config->isConfigured());
    }
}
