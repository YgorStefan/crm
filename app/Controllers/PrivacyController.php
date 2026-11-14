<?php

namespace App\Controllers;

use Core\Controller;

class PrivacyController extends Controller
{
    public function index(array $params = []): void
    {
        // a tela de consentimento do google linka esta pagina, e o clique de la
        // chega sem o cookie samesite strict. a sessao nova que o bootstrap abriu
        // mandaria um set-cookie que troca o do usuario logado e derruba o state
        // da conexao em andamento, entao ela sai sem sessao, como o callback.
        if (!isset($_COOKIE[session_name()])) {
            session_destroy();
            header_remove('Set-Cookie');
        }

        $contactEmail = (string) env('APP_CONTACT_EMAIL', '');

        $this->render('privacy/index', [
            'title' => 'Política de Privacidade | ' . APP_NAME,
            'contactEmail' => filter_var($contactEmail, FILTER_VALIDATE_EMAIL) !== false ? $contactEmail : '',
        ], 'blank');
    }
}
