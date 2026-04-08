<?php
namespace App\Controllers;

use Core\Controller;
use App\Models\Client;
use App\Models\ColdContact;

class AcompanhamentoController extends Controller
{
    public function index(array $params = []): void
    {
        // Mes selecionado (padrão = mês atual); não permite meses futuros
        $mes = $_GET['mes'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $mes) || $mes > date('Y-m')) {
            $mes = date('Y-m');
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $mes . '-01');
        $prevMes = $dt->modify('first day of last month')->format('Y-m');
        $nextMes = $dt->modify('first day of next month')->format('Y-m');

        $nomeMeses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                      'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $mesLabel = $nomeMeses[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y');

        $clientModel = new Client();
        $coldModel   = new ColdContact();

        $stages    = $clientModel->countByStageAndMonth($mes);
        $abordados = $coldModel->countByMonth($mes);

        $this->render('acompanhamento/index', [
            'pageTitle' => 'Acompanhamento',
            'title'     => 'Acompanhamento — ' . APP_NAME,
            'stages'    => $stages,
            'abordados' => $abordados,
            'mes'       => $mes,
            'mesLabel'  => $mesLabel,
            'prevMes'   => $prevMes,
            'nextMes'   => $nextMes,
            'isMesAtual' => $mes === date('Y-m'),
        ]);
    }
}
