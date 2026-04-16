<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Client;
use App\Models\PipelineStage;
use App\Models\User;
use App\Models\Interaction;
use App\Models\Task;

class ClientController extends Controller
{
    /**
     * Lista todos os clientes com filtros opcionais.
     */
    public function index(array $params = []): void
    {
        $clientModel = new Client();
        $stageModel = new PipelineStage();
        $userModel = new User();

        // Lê os filtros da query string (?search=...&stage_id=...&assigned_to=...&tipo_venda=...)
        $filters = [
            'search' => $_GET['search'] ?? '',
            'stage_id' => $_GET['stage_id'] ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? '',
            'tipo_venda' => $_GET['tipo_venda'] ?? '',
        ];

        $clients = $clientModel->findAllWithRelations($filters);
        $stages = $stageModel->findAllOrdered();
        $users = $userModel->findAllActive();

        $this->render('clients/index', [
            'pageTitle' => 'Clientes',
            'title' => 'Clientes — ' . APP_NAME,
            'clients' => $clients,
            'stages' => $stages,
            'users' => $users,
            'filters' => $filters,
        ]);
    }

    /**
     * Exibe o formulário de cadastro de cliente.
     */
    public function create(array $params = []): void
    {
        $stageModel = new PipelineStage();
        $userModel = new User();

        $this->render('clients/create', [
            'pageTitle' => 'Novo Cliente',
            'title' => 'Novo Cliente — ' . APP_NAME,
            'stages' => $stageModel->findAllOrdered(),
            'users' => $userModel->findAllActive(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Processa o formulário e cria o cliente no banco.
     */
    public function store(array $params = []): void
    {
        // nome e etapa do funil são obrigatórios
        $name = $this->input('name');
        $stageId = $this->inputRaw('pipeline_stage_id');

        if (empty($name) || empty($stageId)) {
            $this->flash('error', 'Nome e Etapa do Funil são obrigatórios.');
            $this->redirect('/clients/create');
            return;
        }

        $stageModel = new PipelineStage();
        $stage = $stageModel->findById((int) $stageId);
        $isVendaFechada = $stage && stripos($stage['name'], 'venda fechada') !== false;

        $data = [
            'name' => $name,
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'company' => $this->input('company'),
            'cnpj_cpf' => $this->input('cnpj_cpf'),
            'address' => $this->input('address'),
            'city' => $this->input('city'),
            'state' => $this->input('state'),
            'zip_code' => $this->input('zip_code'),
            'pipeline_stage_id' => $stageId,
            'assigned_to' => $this->inputRaw('assigned_to'),
            'deal_value' => $this->inputRaw('deal_value', '0'),
            'source' => $this->input('source'),
            'notes' => $this->input('notes'),
            'birth_date' => $this->inputRaw('birth_date'),
            'referido_por' => $this->input('referido_por'),
            'closed_at' => $isVendaFechada ? ($this->inputRaw('closed_at') ?: null) : null,
        ];

        $clientModel = new Client();

        if (!empty($data['phone'])) {
            $existing = $clientModel->findByPhone($data['phone']);
            if ($existing) {
                $this->flash('error', 'Já existe um cliente cadastrado com este telefone: ' . htmlspecialchars($existing['name'], ENT_QUOTES, 'UTF-8') . '.');
                $this->redirect('/clients/create');
                return;
            }
        }

        $id = $clientModel->create($data);

        $this->flash('success', 'Cliente cadastrado com sucesso!');
        $this->redirect('/clients/' . $id);
    }

    /**
     * Exibe os detalhes de um cliente com histórico de interações e tarefas.
     */
    public function show(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $clientModel = new Client();
        $client = $clientModel->findByIdWithRelations($id);

        if (!$client) {
            $this->flash('error', 'Cliente não encontrado.');
            $this->redirect('/clients');
        }

        $interactionModel = new Interaction();
        $taskModel = new Task();
        $stageModel = new PipelineStage();
        $userModel = new User();
        $sales = $clientModel->findSalesWithPaymentStatus($id);

        $this->render('clients/show', [
            'pageTitle' => $client['name'],
            'title' => $client['name'] . ' — ' . APP_NAME,
            'client' => $client,
            'interactions' => $interactionModel->findByClient($id),
            'tasks' => $taskModel->findByClient($id),
            'stages' => $stageModel->findAllOrdered(),
            'users' => $userModel->findAllActive(),
            'csrf_token' => CsrfMiddleware::getToken(),
            'sales' => $sales,
        ]);
    }

    /**
     * Exibe o formulário de edição de um cliente.
     */
    public function edit(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $clientModel = new Client();
        $client = $clientModel->findById($id);

        if (!$client) {
            $this->flash('error', 'Cliente não encontrado.');
            $this->redirect('/clients');
        }

        $stageModel = new PipelineStage();
        $userModel = new User();

        $this->render('clients/edit', [
            'pageTitle' => 'Editar: ' . $client['name'],
            'title' => 'Editar Cliente — ' . APP_NAME,
            'client' => $client,
            'stages' => $stageModel->findAllOrdered(),
            'users' => $userModel->findAllActive(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Processa o formulário de edição.
     */
    public function update(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $name = $this->input('name');

        if (empty($name)) {
            $this->flash('error', 'O nome do cliente é obrigatório.');
            $this->redirect('/clients/' . $id . '/edit');
            return;
        }

        $stageId = (int) $this->inputRaw('pipeline_stage_id');
        $stageModel = new PipelineStage();
        $stage = $stageModel->findById($stageId);
        $isVendaFechada = $stage && stripos($stage['name'], 'venda fechada') !== false;

        $data = [
            'name' => $name,
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'company' => $this->input('company'),
            'cnpj_cpf' => $this->input('cnpj_cpf'),
            'address' => $this->input('address'),
            'city' => $this->input('city'),
            'state' => $this->input('state'),
            'zip_code' => $this->input('zip_code'),
            'pipeline_stage_id' => $stageId,
            'assigned_to' => $this->inputRaw('assigned_to'),
            'deal_value' => $this->inputRaw('deal_value', '0'),
            'source' => $this->input('source'),
            'notes' => $this->input('notes'),
            'birth_date' => $this->inputRaw('birth_date'),
            'referido_por' => $this->input('referido_por'),
            'closed_at' => $isVendaFechada ? ($this->inputRaw('closed_at') ?: null) : null,
        ];

        $clientModel = new Client();
        $clientModel->update($id, $data);

        $this->flash('success', 'Cliente atualizado com sucesso!');
        $this->redirect('/clients/' . $id);
    }

    /**
     * Realiza soft-delete do cliente.
     */
    public function destroy(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $clientModel = new Client();
        $clientModel->softDelete($id);

        $this->flash('success', 'Cliente removido com sucesso.');
        $this->redirect('/clients');
    }

    /**
     * Cria uma nova cota de consórcio para o cliente. Retorna JSON.
     */
    public function storeSale(array $params = []): void
    {
        header('Content-Type: application/json');
        $clientId = (int) ($params['id'] ?? 0);

        if (!$clientId) {
            echo json_encode(['success' => false, 'error' => 'Cliente inválido.']);
            exit;
        }

        $tipo = $this->inputRaw('tipo');
        $tiposValidos = ['Imóvel', 'Veículo', 'Serviço'];
        if (!in_array($tipo, $tiposValidos, true)) {
            echo json_encode(['success' => false, 'error' => 'Tipo de consórcio inválido.']);
            exit;
        }

        $data = [
            'grupo' => $this->input('grupo'),
            'cota' => $this->input('cota'),
            'tipo' => $tipo,
            'credito_contratado' => $this->inputRaw('credito_contratado', '0'),
        ];

        $clientModel = new Client();
        $saleId = $clientModel->createSale($clientId, $data);

        echo json_encode([
            'success' => true,
            'csrf_token' => CsrfMiddleware::getToken(),
            'sale' => [
                'id' => $saleId,
                'grupo' => $data['grupo'],
                'cota' => $data['cota'],
                'tipo' => $data['tipo'],
                'credito_contratado' => $data['credito_contratado'],
            ],
        ]);
        exit;
    }

    /**
     * Remove uma cota de consórcio. Retorna JSON.
     */
    public function destroySale(array $params = []): void
    {
        header('Content-Type: application/json');
        $clientId = (int) ($params['id'] ?? 0);
        $saleId = (int) ($params['sale_id'] ?? 0);

        if (!$clientId || !$saleId) {
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
            exit;
        }

        $clientModel = new Client();
        $deleted = $clientModel->deleteSale($saleId, $clientId);

        echo json_encode(['success' => $deleted, 'csrf_token' => CsrfMiddleware::getToken()]);
        exit;
    }

    /**
     * Registra paid_at = NOW() para a cota. Retorna JSON.
     */
    public function markSalePaid(array $params = []): void
    {
        header('Content-Type: application/json');
        $clientId = (int) ($params['id'] ?? 0);
        $saleId = (int) ($params['sale_id'] ?? 0);

        if (!$clientId || !$saleId) {
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
            exit;
        }

        $clientModel = new Client();
        $updated = $clientModel->updateSalePaidAt($saleId, $clientId);

        // Busca paid_at atualizado para retornar a data formatada ao front
        $paidFormatted = null;
        if ($updated) {
            $sales = $clientModel->findSalesWithPaymentStatus($clientId);
            foreach ($sales as $s) {
                if ((int) $s['id'] === $saleId) {
                    $paidFormatted = $s['paid_at_formatted'];
                    break;
                }
            }
        }

        echo json_encode([
            'success' => $updated,
            'csrf_token' => CsrfMiddleware::getToken(),
            'paid_at_formatted' => $paidFormatted,
        ]);
        exit;
    }

    /**
     * Atualiza o campo notes do cliente. Retorna JSON.
     */
    public function updateNotes(array $params = []): void
    {
        header('Content-Type: application/json');
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Cliente inválido.']);
            exit;
        }

        $notes = $_POST['notes'] ?? '';

        $clientModel = new Client();
        $ok = $clientModel->updateNotes($id, $notes);

        echo json_encode([
            'success'    => $ok,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
        exit;
    }
}
