<?php

use App\Controllers\GoogleController;
use App\Controllers\TaskController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GoogleControllerRulesTest extends TestCase
{
    private const AGORA  = 1_800_000_000;
    private const VALIDO = self::AGORA + 600;

    public function testStateValido(): void
    {
        $this->assertTrue(GoogleController::stateIsValid('state-falso', 'state-falso', self::VALIDO, self::AGORA));
    }

    public function testStateNaoStringNaoLanca(): void
    {
        $this->assertFalse(GoogleController::stateIsValid(null, 'abc', self::VALIDO, self::AGORA));
        $this->assertFalse(GoogleController::stateIsValid(['abc'], 'abc', self::VALIDO, self::AGORA));
        $this->assertFalse(GoogleController::stateIsValid('abc', null, self::VALIDO, self::AGORA));
    }

    public function testStateVazioNaoValeMesmoIgualAoGuardado(): void
    {
        $this->assertFalse(GoogleController::stateIsValid('', '', self::VALIDO, self::AGORA));
    }

    public function testStateExpirado(): void
    {
        $this->assertFalse(GoogleController::stateIsValid('abc', 'abc', self::AGORA - 1, self::AGORA));
        $this->assertFalse(GoogleController::stateIsValid('abc', 'abc', self::AGORA, self::AGORA));
    }

    public function testStateDiferente(): void
    {
        $this->assertFalse(GoogleController::stateIsValid('abd', 'abc', self::VALIDO, self::AGORA));
    }

    public function testJanelaValidaPreservaOffset(): void
    {
        $janela = TaskController::parseFeedWindow('2026-11-01T00:00:00-03:00', '2026-12-13T00:00:00-03:00');

        $this->assertNotNull($janela);
        $this->assertSame('2026-11-01T00:00:00-03:00', $janela[0]->format(DATE_RFC3339));
        $this->assertSame('2026-12-13T00:00:00-03:00', $janela[1]->format(DATE_RFC3339));
    }

    public function testJanelaEmUtcMantemOInstante(): void
    {
        $janela = TaskController::parseFeedWindow('2026-11-01T03:00:00Z', '2026-12-13T03:00:00Z');

        $this->assertNotNull($janela);
        $this->assertSame(
            (new \DateTimeImmutable('2026-11-01T00:00:00-03:00'))->getTimestamp(),
            $janela[0]->getTimestamp(),
        );
    }

    /** @return array<string, array{mixed, mixed}> */
    public static function janelasInvalidas(): array
    {
        $inicio = '2026-11-01T00:00:00-03:00';
        $fim    = '2026-12-13T00:00:00-03:00';

        return [
            'inicio nulo'        => [null, $fim],
            'fim nulo'           => [$inicio, null],
            'inicio array'       => [[], $fim],
            'fim array'          => [$inicio, []],
            'inicio lixo'        => ['abc', $fim],
            'fim lixo'           => [$inicio, 'abc'],
            'vazios'             => ['', ''],
            'relativos'          => ['now', 'tomorrow'],
            'sem offset'         => ['2026-11-01T00:00:00', '2026-12-13T00:00:00'],
            'dia que nao existe' => ['2026-02-31T00:00:00-03:00', $fim],
        ];
    }

    #[DataProvider('janelasInvalidas')]
    public function testJanelaNulaArrayOuLixoDevolveNull(mixed $inicio, mixed $fim): void
    {
        $this->assertNull(TaskController::parseFeedWindow($inicio, $fim));
    }

    public function testJanelaInvertidaDevolveNull(): void
    {
        $this->assertNull(TaskController::parseFeedWindow('2026-12-13T00:00:00-03:00', '2026-11-01T00:00:00-03:00'));
        $this->assertNull(TaskController::parseFeedWindow('2026-11-01T00:00:00-03:00', '2026-11-01T00:00:00-03:00'));
    }

    public function testCamposDoEventoSoLevamOsEnviados(): void
    {
        $this->assertSame(
            ['title' => 'Reunião renomeada'],
            GoogleController::eventFields(['title' => 'Reunião renomeada', '_csrf_token' => 'token-falso']),
        );
    }

    public function testCamposDoEventoConvertemODiaInteiro(): void
    {
        $this->assertSame(
            ['description' => 'Pauta', 'start' => '2026-11-15', 'end' => '2026-11-17', 'all_day' => true],
            GoogleController::eventFields(['description' => 'Pauta', 'start' => '2026-11-15', 'end' => '2026-11-17', 'all_day' => '1']),
        );
        $this->assertSame(['all_day' => false], GoogleController::eventFields(['all_day' => '0']));
    }

    public function testCampoDoEventoQueNaoETextoRecusaOPedido(): void
    {
        $this->assertNull(GoogleController::eventFields(['title' => ['Reunião']]));
        $this->assertNull(GoogleController::eventFields(['all_day' => ['1']]));
    }

    public function testCallbackRepassaSoCodeStateEError(): void
    {
        $this->assertSame(
            APP_URL . '/google/callback/finish?code=codigo-falso&state=a%22b%27c%3Cd%3E&error=access_denied',
            GoogleController::finishUrl([
                'code'  => 'codigo-falso',
                'state' => 'a"b\'c<d>',
                'scope' => 'escopo-falso',
                'error' => 'access_denied',
            ]),
        );
    }

    public function testCallbackIgnoraValorQueNaoETexto(): void
    {
        $this->assertSame(
            APP_URL . '/google/callback/finish?state=state-falso',
            GoogleController::finishUrl(['code' => ['codigo-falso'], 'state' => 'state-falso']),
        );
        $this->assertSame(APP_URL . '/google/callback/finish', GoogleController::finishUrl([]));
    }

    /** @return array<string, mixed> */
    private static function itemDoGoogle(array $mudancas = []): array
    {
        return $mudancas + [
            'id'          => 'g_evtfalso123',
            'google_id'   => 'evtfalso123',
            'title'       => 'Reunião <b>falsa</b>',
            'start'       => '2026-11-15T14:00:00',
            'end'         => '2026-11-15T15:30:00',
            'all_day'     => false,
            'recurring'   => true,
            'description' => 'Pauta de teste',
            'html_link'   => 'https://calendar.google.com/calendar/event?eid=ZXZlbnRvLWZhbHNv',
            'can_edit'    => false,
        ];
    }

    public function testEventoDoGoogleComHorarioNoFormatoDoCalendario(): void
    {
        $this->assertSame(
            [
                'id'    => 'g_evtfalso123',
                'title' => 'Reunião <b>falsa</b>',
                'start' => '2026-11-15T14:00:00',
                'end'   => '2026-11-15T15:30:00',
                'color' => '#64748b',
                'extendedProps' => [
                    'source'      => 'google',
                    'google_id'   => 'evtfalso123',
                    'can_edit'    => false,
                    'recurring'   => true,
                    'html_link'   => 'https://calendar.google.com/calendar/event?eid=ZXZlbnRvLWZhbHNv',
                    'description' => 'Pauta de teste',
                    'all_day'     => false,
                ],
            ],
            TaskController::calendarGoogleEvent(self::itemDoGoogle()),
        );
    }

    public function testEventoDoGoogleDeDiaInteiroLevaAllDay(): void
    {
        $evento = TaskController::calendarGoogleEvent(self::itemDoGoogle([
            'start'     => '2026-11-15',
            'end'       => '2026-11-17',
            'all_day'   => true,
            'can_edit'  => true,
            'html_link' => null,
        ]));

        $this->assertTrue($evento['allDay']);
        $this->assertSame('2026-11-17', $evento['end']);
        $this->assertTrue($evento['extendedProps']['all_day']);
        $this->assertTrue($evento['extendedProps']['can_edit']);
        $this->assertNull($evento['extendedProps']['html_link']);
    }

    /** @return array<string, mixed> */
    private static function tarefa(): array
    {
        return [
            'id'                   => 7,
            'title'                => 'Ligar <para> cliente',
            'due_date'             => '2026-11-15 09:00:00',
            'priority'             => 'high',
            'status'               => 'pending',
            'client_id'            => '3',
            'client_name'          => 'Cliente Falso',
            'recurrence_type'      => 'weekly',
            'recurrence_parent_id' => '5',
            'assigned_name'        => 'Pessoa Colega',
        ];
    }

    public function testTarefaSegueOFormatoDeHoje(): void
    {
        $this->assertSame(
            [
                'id'    => 7,
                'title' => '↻ Ligar &lt;para&gt; cliente',
                'start' => '2026-11-15 09:00:00',
                'color' => '#ef4444',
                'extendedProps' => [
                    'status'               => 'pending',
                    'priority'             => 'high',
                    'client_id'            => 3,
                    'client_name'          => 'Cliente Falso',
                    'recurrence_type'      => 'weekly',
                    'recurrence_parent_id' => 5,
                ],
            ],
            TaskController::calendarTaskEvent(self::tarefa()),
        );
    }

    public function testTarefaDaEquipeLevaOResponsavel(): void
    {
        $propria = TaskController::calendarTaskEvent(self::tarefa());
        $equipe  = TaskController::calendarTeamEvent(self::tarefa());

        $this->assertSame('Pessoa Colega', $equipe['extendedProps']['assigned_name']);
        unset($equipe['extendedProps']['assigned_name']);
        $this->assertSame($propria, $equipe);
    }
}
