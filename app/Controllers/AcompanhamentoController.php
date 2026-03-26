<?php
// ============================================================
// app/Controllers/AcompanhamentoController.php — Dashboard de Acompanhamento
// ============================================================

namespace App\Controllers;

use Core\Controller;
use App\Models\ColdContact;

class AcompanhamentoController extends Controller
{
    /**
     * GET /acompanhamento
     * Carrega os dados de acompanhamento semanal de contatos frios.
     */
    public function index(array $params = []): void
    {
        $model     = new ColdContact();
        $chartData = $model->weeklyStats(4); // ultimas 4 semanas, per D-04

        $this->render('acompanhamento/index', [
            'pageTitle' => 'Acompanhamento',
            'title'     => 'Acompanhamento — ' . APP_NAME,
            'chartData' => $chartData,
        ]);
    }
}
