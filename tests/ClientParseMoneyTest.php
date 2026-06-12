<?php

use App\Models\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClientParseMoneyTest extends TestCase
{
    public static function moneyProvider(): array
    {
        return [
            'formato BR com milhar'      => ['60.000,00', 60000.0],
            'formato BR sem milhar'      => ['60000,00', 60000.0],
            'formato BR decimal parcial' => ['1.234,5', 1234.5],
            'formato neutro com decimal' => ['60000.00', 60000.0],
            'inteiro puro'               => ['60000', 60000.0],
            'centavos'                   => ['0,99', 0.99],
            'string vazia'               => ['', 0.0],
            'espacos em volta'           => [' 1.234,56 ', 1234.56],
        ];
    }

    #[DataProvider('moneyProvider')]
    public function testParseMoney(string $input, float $expected): void
    {
        $this->assertSame($expected, Client::parseMoney($input));
    }
}
