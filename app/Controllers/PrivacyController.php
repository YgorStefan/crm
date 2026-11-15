<?php

namespace App\Controllers;

use Core\Controller;

class PrivacyController extends Controller
{
    public function index(array $params = []): void
    {
        // pagina publica, linkada pela tela de consentimento do google: quem chega
        // sem cookie nao precisa de sessao, e a que o bootstrap abriu sai sem
        // set-cookie, como no callback.
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
