<?php

namespace App\Controllers;

use Core\Controller;

class AboutController extends Controller
{
    public function index(array $params = []): void
    {
        // pagina inicial cadastrada no app oauth do google, publica como a
        // privacidade: quem chega sem cookie nao precisa de sessao, e a que o
        // bootstrap abriu sai sem set-cookie.
        if (!isset($_COOKIE[session_name()])) {
            session_destroy();
            header_remove('Set-Cookie');
        }

        $this->render('about/index', [
            'title' => 'Sobre o CRM',
        ], 'blank');
    }
}
