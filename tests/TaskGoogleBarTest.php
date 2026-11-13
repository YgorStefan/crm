<?php

use App\Controllers\TaskController;
use PHPUnit\Framework\TestCase;

class TaskGoogleBarTest extends TestCase
{
    private static function semConsulta(): \Closure
    {
        return static function (): never {
            throw new \LogicException('a conta nao devia ser consultada');
        };
    }

    public function testSemCredencialNaoMostraNadaNemConsulta(): void
    {
        $this->assertSame(
            ['enabled' => false, 'connection' => null, 'email' => null],
            TaskController::googleBarState(false, 'admin', self::semConsulta()),
        );
    }

    public function testViewerNaoMostraNadaNemConsulta(): void
    {
        $this->assertSame(
            ['enabled' => false, 'connection' => null, 'email' => null],
            TaskController::googleBarState(true, 'viewer', self::semConsulta()),
        );
    }

    public function testPapelDesconhecidoNaoMostraNada(): void
    {
        $this->assertSame(
            ['enabled' => false, 'connection' => null, 'email' => null],
            TaskController::googleBarState(true, '', self::semConsulta()),
        );
    }

    public function testSemContaOfereceConectar(): void
    {
        $this->assertSame(
            ['enabled' => true, 'connection' => null, 'email' => null],
            TaskController::googleBarState(true, 'seller', static fn () => false),
        );
    }

    public function testContaAtivaMostraOEmail(): void
    {
        $conta = ['status' => 'active', 'google_email' => 'pessoa@example.com'];

        $this->assertSame(
            ['enabled' => true, 'connection' => 'active', 'email' => 'pessoa@example.com'],
            TaskController::googleBarState(true, 'admin', static fn () => $conta),
        );
    }

    public function testContaRevogadaPedeReconectar(): void
    {
        $conta = ['status' => 'revoked', 'google_email' => 'pessoa@example.com'];

        $this->assertSame(
            ['enabled' => true, 'connection' => 'revoked', 'email' => 'pessoa@example.com'],
            TaskController::googleBarState(true, 'seller', static fn () => $conta),
        );
    }

    public function testDesconectadaComEmailGuardadoOfereceConectar(): void
    {
        $conta = ['status' => 'disconnected', 'google_email' => 'pessoa@example.com'];

        $this->assertSame(
            ['enabled' => true, 'connection' => null, 'email' => null],
            TaskController::googleBarState(true, 'admin', static fn () => $conta),
        );
    }
}
