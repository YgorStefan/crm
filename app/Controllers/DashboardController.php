<?php

namespace App\Controllers;

use Core\Controller;
use App\Models\Client;
use App\Models\Task;
use App\Models\Interaction;

class DashboardController extends Controller
{
    public function __construct(
        private Client      $clients      = new Client(),
        private Task        $tasks        = new Task(),
        private Interaction $interactions = new Interaction(),
    ) {}

    /**
     * Carrega todos os dados necessários para os widgets e gráficos.
     */
    public function index(array $params = []): void
    {

        // Total de clientes ativos
        $totalClients = $this->clients->countAllWithRelations();

        // Tarefas pendentes do usuário logado
        $pendingTasks = $this->tasks->countPending($_SESSION['user']['id']);

        // Total geral de tarefas em aberto (admin vê tudo)
        $allPendingTasks = $this->tasks->countPending();

        // Tarefas atrasadas
        $overdueTasks = $this->tasks->findOverdue($_SESSION['user']['id']);

        // Distribuição de clientes por etapa (para gráfico de barras/pizza)
        $stageData = $this->clients->countByStage();

        // Atividade recente
        $recentInteractions = $this->interactions->findRecent(20);

        // Minhas tarefas próximas (próximos 7 dias)
        $upcomingTasks = $this->tasks->findAllWithRelations([
            'status' => 'pending',
            'assigned_to' => $_SESSION['user']['id'],
        ]);
        // Filtra apenas as dos próximos 7 dias
        $nextWeek = time() + (7 * 24 * 3600);
        $upcomingTasks = array_filter($upcomingTasks, fn($t) => strtotime($t['due_date']) <= $nextWeek);

        $this->render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'title' => 'Dashboard — ' . APP_NAME,
            'totalClients' => $totalClients,
            'pendingTasks' => $pendingTasks,
            'allPendingTasks' => $allPendingTasks,
            'overdueTasks' => $overdueTasks,
            'stageData' => $stageData,
            'recentInteractions' => $recentInteractions,
            'upcomingTasks' => array_values($upcomingTasks),
        ]);
    }

    /**
     * Endpoint AJAX que retorna os dados dos gráficos em JSON.
     * Chamado pelo dashboard.js para renderizar os charts.
     */
    public function stats(array $params = []): void
    {
        $stageData = $this->clients->countByStage();

        // Prepara os dados no formato esperado pelo Chart.js
        $labels = array_column($stageData, 'name');
        $counts = array_column($stageData, 'total');
        $values = array_column($stageData, 'total_value');
        $colors = array_column($stageData, 'color');

        $this->json([
            'pipeline' => [
                'labels' => $labels,
                'counts' => $counts,
                'values' => $values,
                'colors' => $colors,
            ],
        ]);
    }
}
