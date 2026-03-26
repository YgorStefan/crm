<?php
namespace App\Controllers;

use Core\Controller;
use App\Models\ColdContact;

class AcompanhamentoController extends Controller
{
    //Carrega os dados de acompanhamento semanal de contatos frios.

    public function index(array $params = []): void
    {
        $model = new ColdContact();
        $chartData = $model->weeklyStats(4); // ultimas 4 semanas

        $this->render('acompanhamento/index', [
            'pageTitle' => 'Acompanhamento',
            'title' => 'Acompanhamento — ' . APP_NAME,
            'chartData' => $chartData,
        ]);
    }
}
