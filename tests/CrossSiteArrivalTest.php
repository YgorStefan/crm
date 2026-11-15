<?php

declare(strict_types=1);

use Core\Http\CrossSiteArrival;
use PHPUnit\Framework\TestCase;

class CrossSiteArrivalTest extends TestCase
{
    private const SESSION = 'crm_session';

    /** @return array<string, string> */
    private static function chegadaDeOutroSite(array $extra = []): array
    {
        return array_merge([
            'REQUEST_METHOD'      => 'GET',
            'HTTP_SEC_FETCH_SITE' => 'cross-site',
            'HTTP_SEC_FETCH_MODE' => 'navigate',
            'HTTP_SEC_FETCH_DEST' => 'document',
        ], $extra);
    }

    public function testLinkDeOutroSiteSemCookieRecarrega(): void
    {
        $this->assertTrue(CrossSiteArrival::needsReload(self::chegadaDeOutroSite(), [], self::SESSION));
    }

    public function testHeadDeOutroSiteSemCookieRecarrega(): void
    {
        $server = self::chegadaDeOutroSite(['REQUEST_METHOD' => 'HEAD']);

        $this->assertTrue(CrossSiteArrival::needsReload($server, [], self::SESSION));
    }

    public function testComCookieDeSessaoSegueNormal(): void
    {
        $cookies = [self::SESSION => 'abc'];

        $this->assertFalse(CrossSiteArrival::needsReload(self::chegadaDeOutroSite(), $cookies, self::SESSION));
    }

    public function testOutroCookieQueNaoODaSessaoAindaRecarrega(): void
    {
        $cookies = ['crm_theme' => 'dark'];

        $this->assertTrue(CrossSiteArrival::needsReload(self::chegadaDeOutroSite(), $cookies, self::SESSION));
    }

    public function testNavegacaoDoProprioCrmSegueNormal(): void
    {
        foreach (['same-origin', 'same-site', 'none'] as $site) {
            $server = self::chegadaDeOutroSite(['HTTP_SEC_FETCH_SITE' => $site]);
            $this->assertFalse(CrossSiteArrival::needsReload($server, [], self::SESSION), $site);
        }
    }

    public function testNavegadorSemCabecalhoSecFetchSegueNormal(): void
    {
        $this->assertFalse(CrossSiteArrival::needsReload(['REQUEST_METHOD' => 'GET'], [], self::SESSION));
    }

    public function testPostDeOutroSiteSegueNormal(): void
    {
        $server = self::chegadaDeOutroSite(['REQUEST_METHOD' => 'POST']);

        $this->assertFalse(CrossSiteArrival::needsReload($server, [], self::SESSION));
    }

    public function testPedidoQueNaoENavegacaoDePaginaSegueNormal(): void
    {
        $this->assertFalse(CrossSiteArrival::needsReload(
            self::chegadaDeOutroSite(['HTTP_SEC_FETCH_MODE' => 'no-cors', 'HTTP_SEC_FETCH_DEST' => 'image']),
            [],
            self::SESSION,
        ));
        $this->assertFalse(CrossSiteArrival::needsReload(
            self::chegadaDeOutroSite(['HTTP_SEC_FETCH_DEST' => 'iframe']),
            [],
            self::SESSION,
        ));
    }

    public function testUrlDaRecargaLevaCaminhoEQueryNaOrigemDoAppUrl(): void
    {
        $this->assertSame(
            'https://crm.example.test/clients/7?aba=tarefas&x=1',
            CrossSiteArrival::reloadUrl('/clients/7?aba=tarefas&x=1', 'https://crm.example.test'),
        );
    }

    public function testUrlDaRecargaGuardaAPortaEIgnoraOCaminhoDoAppUrl(): void
    {
        // o request_uri ja traz o caminho base de quem roda numa subpasta.
        $this->assertSame(
            'http://localhost:8000/crm/public/tasks',
            CrossSiteArrival::reloadUrl('/crm/public/tasks', 'http://localhost:8000/crm/public'),
        );
    }

    public function testUrlDaRecargaNaoSaiParaOutroSite(): void
    {
        $appUrl = 'https://crm.example.test';

        $this->assertSame('https://crm.example.test/outro.example/x', CrossSiteArrival::reloadUrl('//outro.example/x', $appUrl));
        $this->assertSame('https://crm.example.test/outro.example/x', CrossSiteArrival::reloadUrl('/\\outro.example/x', $appUrl));
        $this->assertSame('https://crm.example.test/http://outro.example/x', CrossSiteArrival::reloadUrl('http://outro.example/x', $appUrl));
    }

    public function testUrlDaRecargaSemCaminhoVaiParaARaiz(): void
    {
        $this->assertSame('https://crm.example.test/', CrossSiteArrival::reloadUrl('', 'https://crm.example.test'));
    }

    public function testPaginaDeRecargaApontaParaAUrlEscapada(): void
    {
        $html = CrossSiteArrival::reloadPage('https://crm.example.test/clients?a=1&b="x"');

        $this->assertStringContainsString(
            '<meta http-equiv="refresh" content="0;url=https://crm.example.test/clients?a=1&amp;b=&quot;x&quot;">',
            $html,
        );
        $this->assertStringContainsString('<a href="https://crm.example.test/clients?a=1&amp;b=&quot;x&quot;">', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
