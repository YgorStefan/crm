<?php

use Core\Middleware\CspMiddleware;
use PHPUnit\Framework\TestCase;

class CspMiddlewareTest extends TestCase
{
    public function testHandleComONonceJaDefinidoMantemAConstante(): void
    {
        // o nonce e uma constante, e os testes que renderizam layout ja a definem
        // no mesmo processo do phpunit.
        if (!defined('CSP_NONCE')) {
            define('CSP_NONCE', 'nonce-de-teste');
        }
        $antes = CSP_NONCE;

        (new CspMiddleware())->handle();

        $this->assertSame($antes, CSP_NONCE);
    }
}
