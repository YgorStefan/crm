<?php

declare(strict_types=1);

use Core\Http\CrossSiteRequest;
use PHPUnit\Framework\TestCase;

class CrossSiteRequestTest extends TestCase
{
    public function testEscritaDeOutroSiteERecusada(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'post'] as $method) {
            $server = ['REQUEST_METHOD' => $method, 'HTTP_SEC_FETCH_SITE' => 'cross-site'];
            $this->assertTrue(CrossSiteRequest::isUnsafe($server), $method);
        }
    }

    public function testLeituraDeOutroSiteSegue(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $server = ['REQUEST_METHOD' => $method, 'HTTP_SEC_FETCH_SITE' => 'cross-site'];
            $this->assertFalse(CrossSiteRequest::isUnsafe($server), $method);
        }
    }

    public function testEscritaDoProprioSiteSegue(): void
    {
        foreach (['same-origin', 'same-site', 'none'] as $site) {
            $server = ['REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => $site];
            $this->assertFalse(CrossSiteRequest::isUnsafe($server), $site);
        }
    }

    public function testNavegadorSemCabecalhoSecFetchSegue(): void
    {
        $this->assertFalse(CrossSiteRequest::isUnsafe(['REQUEST_METHOD' => 'POST']));
        $this->assertFalse(CrossSiteRequest::isUnsafe([]));
    }
}
