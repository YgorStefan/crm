<?php

namespace App\Controllers;

use Core\Controller;

class AboutController extends Controller
{
    public function index(array $params = []): void
    {
        // e a pagina inicial cadastrada no app oauth do google, e a tela de
        // consentimento linka ela. o clique de la chega sem o cookie samesite
        // strict, e a sessao nova trocaria o cookie do usuario, como na privacidade.
        if (!isset($_COOKIE[session_name()])) {
            session_destroy();
            header_remove('Set-Cookie');
        }

        $this->render('about/index', [
            'title' => 'Sobre o CRM',
        ], 'blank');
    }
}
