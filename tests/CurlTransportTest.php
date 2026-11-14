<?php

declare(strict_types=1);

use App\Services\Google\CurlTransport;
use PHPUnit\Framework\TestCase;

// servidor local que aceita a conexao e nunca responde: sem rede externa, mede
// so o que o transporte faz com o tempo.
final class CurlTransportTest extends TestCase
{
    /** @var resource|null */
    private $servidor = null;

    protected function setUp(): void
    {
        $servidor = stream_socket_server('tcp://127.0.0.1:0', $codigo, $mensagem);
        if ($servidor === false) {
            $this->fail("nao abriu o servidor local: {$mensagem}");
        }
        $this->servidor = $servidor;
    }

    protected function tearDown(): void
    {
        if ($this->servidor !== null) {
            fclose($this->servidor);
            $this->servidor = null;
        }
    }

    private function url(): string
    {
        return 'http://' . stream_socket_get_name($this->servidor, false) . '/';
    }

    private function temConexaoPendente(): bool
    {
        $leitura = [$this->servidor];
        $escrita = null;
        $excecao = null;
        return stream_select($leitura, $escrita, $excecao, 0) > 0;
    }

    public function testServidorMudoEstouraNoTempoPedido(): void
    {
        $inicio = microtime(true);

        $resposta = (new CurlTransport())->send('GET', $this->url(), [], null, 300);

        $this->assertSame(['status' => 0, 'body' => ''], $resposta);
        $this->assertLessThan(5.0, microtime(true) - $inicio);
        $this->assertTrue($this->temConexaoPendente(), 'o curl devia ter conectado no servidor local');
    }

    public function testSemOrcamentoNaoConecta(): void
    {
        $resposta = (new CurlTransport())->send('GET', $this->url(), [], null, 0);

        $this->assertSame(['status' => 0, 'body' => ''], $resposta);
        $this->assertFalse($this->temConexaoPendente());
    }
}
