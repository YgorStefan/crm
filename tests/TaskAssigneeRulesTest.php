<?php

use App\Controllers\TaskController;
use PHPUnit\Framework\TestCase;

class TaskAssigneeRulesTest extends TestCase
{
    public function testSellerNaoAtribuiAColega(): void
    {
        $this->assertSame(5, TaskController::assigneeForStore('9', 'seller', 5));
    }

    public function testAdminAtribuiAOutro(): void
    {
        $this->assertSame(9, TaskController::assigneeForStore('9', 'admin', 5));
    }

    public function testAdminSemCampoFicaComEle(): void
    {
        $this->assertSame(5, TaskController::assigneeForStore('', 'admin', 5));
    }

    public function testTrocaSoComAdmin(): void
    {
        $this->assertNull(TaskController::assigneeChange('9', 'seller', 5));
    }

    public function testTrocaDevolveNovoId(): void
    {
        $this->assertSame(9, TaskController::assigneeChange('9', 'admin', 5));
    }

    public function testMesmoResponsavelNaoEhTroca(): void
    {
        $this->assertNull(TaskController::assigneeChange('5', 'admin', 5));
    }

    public function testCampoVazioNaoEhTroca(): void
    {
        $this->assertNull(TaskController::assigneeChange('', 'admin', 5));
    }

    public function testAdminComIdInvalidoNaCriacaoFicaComEle(): void
    {
        $this->assertSame(5, TaskController::assigneeForStore('9abc', 'admin', 5));
        $this->assertSame(5, TaskController::assigneeForStore('0', 'admin', 5));
    }

    public function testIdInvalidoNaoEhTroca(): void
    {
        $this->assertNull(TaskController::assigneeChange('9abc', 'admin', 5));
        $this->assertNull(TaskController::assigneeChange('0', 'admin', 5));
        $this->assertNull(TaskController::assigneeChange(null, 'admin', 5));
    }
}
