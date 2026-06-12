<?php

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testFormatCurrencyComFloat(): void
    {
        $this->assertSame('R$ 1.234,56', format_currency(1234.56));
    }

    public function testFormatCurrencyComStringBrasileira(): void
    {
        $this->assertSame('R$ 1.234,50', format_currency('1234,5'));
    }

    public function testFormatCurrencyComZero(): void
    {
        $this->assertSame('R$ 0,00', format_currency(0));
    }

    public function testNavLinkAtivoRecebeClasseDeDestaque(): void
    {
        $html = navLink('/clients', '<svg></svg>', 'Clientes', '/clients');
        $this->assertStringContainsString('bg-indigo-50', $html);
        $this->assertStringContainsString(APP_URL . '/clients', $html);
    }

    public function testNavLinkInativoNaoRecebeClasseDeDestaque(): void
    {
        $html = navLink('/clients', '<svg></svg>', 'Clientes', '/tasks');
        $this->assertStringNotContainsString('bg-indigo-50', $html);
    }

    public function testNavLinkAtivoPorPrefixoDeSubrota(): void
    {
        $html = navLink('/clients', '<svg></svg>', 'Clientes', '/clients/5/edit');
        $this->assertStringContainsString('bg-indigo-50', $html);
    }
}
