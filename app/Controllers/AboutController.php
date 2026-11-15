<?php

namespace App\Controllers;

use Core\Controller;

class AboutController extends Controller
{
    public function index(array $params = []): void
    {
        $this->withoutNewSession();

        $this->render('about/index', [
            'title' => 'Sobre o CRM',
        ], 'blank');
    }
}
