<?php
// ============================================================
// app/Controllers/ClientController.php — CRUD de Clientes
// ============================================================

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
     * GET /clients
     * Lista todos os clientes com filtros opcionais.
     */
    public function index(array $params = []): void
    {
        $clientModel = new Client();
        $stageModel  = new PipelineStage();
        $userModel   = new User();

        // Lê os filtros da query string (?search=...&stage_id=...&assigned_to=...)
        $filters = [
            'search'      => $_GET['search']      ?? '',
            'stage_id'    => $_GET['stage_id']    ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? '',
        ];

        $clients = $clientModel->findAllWithRelations($filters);
        $stages  = $stageModel->findAllOrdered();
        $users   = $userModel->findAllActive();

        $this->render('clients/index', [
            'pageTitle' => 'Clientes',
            'title'     => 'Clientes — ' . APP_NAME,
            'clients'   => $clients,
            'stages'    => $stages,
            'users'     => $users,
            'filters'   => $filters,
        ]);
    }

    /**
     * GET /clients/create
     * Exibe o formulário de cadastro de cliente.
     */
    public function create(array $params = []): void
    {
        $stageModel = new PipelineStage();
        $userModel  = new User();

        $this->render('clients/create', [
            'pageTitle'  => 'Novo Cliente',
            'title'      => 'Novo Cliente — ' . APP_NAME,
            'stages'     => $stageModel->findAllOrdered(),
            'users'      => $userModel->findAllActive(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * POST /clients/store
     * Processa o formulário e cria o cliente no banco.
     */
    public function store(array $params = []): void
    {
        // Validação mínima: nome e etapa do funil são obrigatórios
        $name    = $this->input('name');
        $stageId = $this->inputRaw('pipeline_stage_id');

        if (empty($name) || empty($stageId)) {
            $this->flash('error', 'Nome e Etapa do Funil são obrigatórios.');
            $this->redirect('/clients/create');
            return;
        }

        $data = [
            'name'              => $name,
            'email'             => $this->input('email'),
            'phone'             => $this->input('phone'),
            'company'           => $this->input('company'),
            'cnpj_cpf'          => $this->input('cnpj_cpf'),
            'address'           => $this->input('address'),
            'city'              => $this->input('city'),
            'state'             => $this->input('state'),
            'zip_code'          => $this->input('zip_code'),
            'pipeline_stage_id' => $stageId,
            'assigned_to'       => $this->inputRaw('assigned_to'),
            'deal_value'        => $this->inputRaw('deal_value', '0'),
            'source'            => $this->input('source'),
            'notes'             => $this->input('notes'),
        ];

        $clientModel = new Client();
        $id = $clientModel->create($data);

        $this->flash('success', 'Cliente cadastrado com sucesso!');
        $this->redirect('/clients/' . $id);
    }

    /**
     * GET /clients/{id}
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
        $taskModel        = new Task();
        $stageModel       = new PipelineStage();
        $userModel        = new User();

        $this->render('clients/show', [
            'pageTitle'    => $client['name'],
            'title'        => $client['name'] . ' — ' . APP_NAME,
            'client'       => $client,
            'interactions' => $interactionModel->findByClient($id),
            'tasks'        => $taskModel->findByClient($id),
            'stages'       => $stageModel->findAllOrdered(),
            'users'        => $userModel->findAllActive(),
            'csrf_token'   => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * GET /clients/{id}/edit
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
        $userModel  = new User();

        $this->render('clients/edit', [
            'pageTitle'  => 'Editar: ' . $client['name'],
            'title'      => 'Editar Cliente — ' . APP_NAME,
            'client'     => $client,
            'stages'     => $stageModel->findAllOrdered(),
            'users'      => $userModel->findAllActive(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * POST /clients/{id}/update
     * Processa o formulário de edição.
     */
    public function update(array $params = []): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $name = $this->input('name');

        if (empty($name)) {
            $this->flash('error', 'O nome do cliente é obrigatório.');
            $this->redirect('/clients/' . $id . '/edit');
            return;
        }

        $data = [
            'name'              => $name,
            'email'             => $this->input('email'),
            'phone'             => $this->input('phone'),
            'company'           => $this->input('company'),
            'cnpj_cpf'          => $this->input('cnpj_cpf'),
            'address'           => $this->input('address'),
            'city'              => $this->input('city'),
            'state'             => $this->input('state'),
            'zip_code'          => $this->input('zip_code'),
            'pipeline_stage_id' => $this->inputRaw('pipeline_stage_id'),
            'assigned_to'       => $this->inputRaw('assigned_to'),
            'deal_value'        => $this->inputRaw('deal_value', '0'),
            'source'            => $this->input('source'),
            'notes'             => $this->input('notes'),
        ];

        $clientModel = new Client();
        $clientModel->update($id, $data);

        $this->flash('success', 'Cliente atualizado com sucesso!');
        $this->redirect('/clients/' . $id);
    }

    /**
     * POST /clients/{id}/delete
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
}
