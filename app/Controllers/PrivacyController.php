<?php

namespace App\Controllers;

use Core\Controller;

class PrivacyController extends Controller
{
    public function index(array $params = []): void
    {
        $this->render('privacy/index', [
            'title' => 'Política de Privacidade | ' . APP_NAME,
            'contactEmail' => (string) env('APP_CONTACT_EMAIL', ''),
        ], 'blank');
    }
}
