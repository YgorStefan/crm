<?php

namespace App\Controllers;

use Core\Controller;

class PrivacyController extends Controller
{
    public function index(array $params = []): void
    {
        $contactEmail = (string) env('APP_CONTACT_EMAIL', '');

        $this->render('privacy/index', [
            'title' => 'Política de Privacidade | ' . APP_NAME,
            'contactEmail' => filter_var($contactEmail, FILTER_VALIDATE_EMAIL) !== false ? $contactEmail : '',
        ], 'blank');
    }
}
