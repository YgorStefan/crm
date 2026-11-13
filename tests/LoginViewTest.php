<?php

use PHPUnit\Framework\TestCase;

class LoginViewTest extends TestCase
{
    private function renderLogin(string $appName, string $appUrl): string
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/Support/render-view.php', VIEW_PATH . DS . 'auth' . DS . 'login.php', $appName, $appUrl],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);

        $html   = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $errors);

        return $html;
    }

    public function testNomeDoAppSaiEscapadoNoCabecalho(): void
    {
        $html = $this->renderLogin('CRM <b>&</b>', 'http://crm.test');

        $this->assertStringContainsString('>CRM &lt;b&gt;&amp;&lt;/b&gt;</h1>', $html);
        $this->assertStringNotContainsString('<b>&</b>', $html);
    }

    public function testUrlDoAppSaiEscapadaNaAcaoDoFormulario(): void
    {
        $html = $this->renderLogin('CRM', 'http://crm.test/"><x');

        $this->assertStringContainsString('action="http://crm.test/&quot;&gt;&lt;x/login"', $html);
        $this->assertStringNotContainsString('"><x', $html);
    }
}
