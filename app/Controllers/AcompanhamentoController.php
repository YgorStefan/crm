<?php
namespace App\Controllers;

use Core\Controller;
use App\Models\Client;

class AcompanhamentoController extends Controller
{
    public function index(array $params = []): void
    {
        $clientModel = new Client();
        $stages = $clientModel->countByStage();

        $this->render('acompanhamento/index', [
            'pageTitle' => 'Acompanhamento',
            'title' => 'Acompanhamento — ' . APP_NAME,
            'stages' => $stages,
        ]);
    }
}
