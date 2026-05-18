<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Client;
use App\Models\ClientSale;
use App\Models\PipelineStage;
use App\Models\User;
use App\Models\Interaction;
use App\Models\Task;
use Core\Http\ApiResponse;
use App\Services\ClientService;

class ClientController extends Controller
{
    public function __construct(
        private Client        $clients      = new Client(),
        private PipelineStage $stages       = new PipelineStage(),
        private User          $users        = new User(),
        private Interaction   $interactions = new Interaction(),
        private Task          $tasks        = new Task(),
        private ClientSale    $sales        = new ClientSale(),
        private ClientService $service      = new ClientService(),
    ) {}

    /**
     * Lista clientes com filtros, paginação e ordenação.
     *
     * @param  array  $params  Parâmetros da rota (não utilizados)
     * @return void
     */
    public function index(array $params = []): void
    {
        // Lê os filtros da query string (?search=...&stage_id=...&assigned_to=...&tipo_venda=...)
        $filters = [
            'search'      => $_GET['search'] ?? '',
            'stage_id'    => $_GET['stage_id'] ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? '',
            'tipo_venda'  => $_GET['tipo_venda'] ?? '',
            'sort'        => $_GET['sort'] ?? 'name',
            'dir'         => $_GET['dir'] ?? 'asc',
        ];

        // Paginação: página atual
        $page = max(1, (int) ($_GET['page'] ?? 1));

        // Itens por página: valida e persiste em sessão
        $allowedLimits = [15, 25, 50, 100];
        if (!empty($_GET['per_page'])) {
            $requestedLimit = (int) $_GET['per_page'];
            if (in_array($requestedLimit, $allowedLimits, true)) {
                $_SESSION['per_page'] = $requestedLimit;
            }
        }
        $limit = isset($_SESSION['per_page']) && in_array((int) $_SESSION['per_page'], $allowedLimits, true)
            ? (int) $_SESSION['per_page']
            : 25;

        $offset = ($page - 1) * $limit;

        $overdueIds = $this->service->getOverdueClientIds();
        $totalCount = $this->clients->countAllWithRelations($filters);
        $clients    = $this->clients->findAllWithRelations($filters, $limit, $offset, $overdueIds);
        $totalPages = (int) ceil($totalCount / $limit);

        $stages = $this->stages->findAllOrdered();
        $users = $this->users->findAllActive();

        $pagination = [
            'current_page' => $page,
            'total_pages'  => $totalPages,
            'total_items'  => $totalCount,
            'per_page'     => $limit,
            'base_url'     => '/clients',
            'query_params' => $filters,
        ];

        $this->render('clients/index', [
            'pageTitle'  => 'Clientes',
            'title'      => 'Clientes — ' . APP_NAME,
            'clients'    => $clients,
            'stages'     => $stages,
            'users'      => $users,
            'filters'    => $filters,
            'pagination' => $pagination,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Exibe o formulário de cadastro de novo cliente.
     *
     * @param  array  $params  Parâmetros da rota (não utilizados)
     * @return void
     */
    public function create(array $params = []): void
    {
        $this->render('clients/create', [
            'pageTitle' => 'Novo Cliente',
            'title' => 'Novo Cliente — ' . APP_NAME,
            'stages' => $this->stages->findAllOrdered(),
            'users' => $this->users->findAllActive(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Persiste um novo cliente após validação; redireciona para a ficha criada.
     *
     * @param  array  $params  Parâmetros da rota (não utilizados)
     * @return void
     */
    public function store(array $params = []): void
    {
        // nome e etapa do funil são obrigatórios
        $name = $this->input('name');
        $stageId = $this->inputPost('pipeline_stage_id');

        if (empty($name) || empty($stageId)) {
            $this->flash('error', 'Nome e Etapa do Funil são obrigatórios.');
            $this->redirect('/clients/create');
            return;
        }

        $stage = $this->stages->findById((int) $stageId);
        $isVendaFechada = $stage && !empty($stage['is_won_stage']);

        $data = [
            'name' => $name,
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'company' => $this->input('company'),
            'cnpj_cpf' => $this->input('cnpj_cpf'),
            'address' => $this->input('address'),
            'address_number'     => $this->input('address_number'),
            'address_complement' => $this->input('address_complement'),
            'neighborhood' => $this->input('neighborhood'),
            'city' => $this->input('city'),
            'state' => $this->input('state'),
            'zip_code' => $this->input('zip_code'),
            'pipeline_stage_id' => $stageId,
            'assigned_to' => $this->inputPost('assigned_to'),
            'deal_value' => $this->inputPost('deal_value', '0'),
            'source' => $this->input('source'),
            'notes' => $this->input('notes'),
            'birth_date' => $this->inputPost('birth_date'),
            'referido_por' => $this->input('referido_por'),
            'closed_at' => $isVendaFechada ? ($this->inputPost('closed_at') ?: null) : null,
        ];

        if (!empty($data['phone'])) {
            $existing = $this->clients->findByPhone($data['phone']);
            if ($existing) {
                $this->flash('error', 'Já existe um cliente cadastrado com este telefone: ' . htmlspecialchars($existing['name'], ENT_QUOTES, 'UTF-8') . '.');
                $this->redirect('/clients/create');
                return;
            }
        }

        $id = $this->clients->create($data);

        $this->flash('success', 'Cliente cadastrado com sucesso!');
        $this->redirect('/clients/' . $id);
    }

    /**
     * Exibe a ficha completa de um cliente com interações, tarefas e vendas.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
     */
    public function show(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $client = $this->clients->findByIdWithRelations($id);

        if (!$client) {
            $this->flash('error', 'Cliente não encontrado.');
            $this->redirect('/clients');
        }

        $sales = $this->service->getSalesWithPaymentStatus($id);

        $this->render('clients/show', [
            'pageTitle' => $client['name'],
            'title' => $client['name'] . ' — ' . APP_NAME,
            'client' => $client,
            'interactions' => $this->interactions->findByClient($id),
            'tasks' => $this->tasks->findByClient($id),
            'stages' => $this->stages->findAllOrdered(),
            'users' => $this->users->findAllActive(),
            'csrf_token' => CsrfMiddleware::getToken(),
            'sales' => $sales,
        ]);
    }

    /**
     * Exibe o formulário de edição de um cliente existente.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
     */
    public function edit(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $client = $this->clients->findById($id);

        if (!$client) {
            $this->flash('error', 'Cliente não encontrado.');
            $this->redirect('/clients');
        }

        $this->render('clients/edit', [
            'pageTitle' => 'Editar: ' . $client['name'],
            'title' => 'Editar Cliente — ' . APP_NAME,
            'client' => $client,
            'stages' => $this->stages->findAllOrdered(),
            'users' => $this->users->findAllActive(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Atualiza os dados de um cliente após validação.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
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

        $stageId = (int) $this->inputPost('pipeline_stage_id');
        $stage = $this->stages->findById($stageId);
        $isVendaFechada = $stage && !empty($stage['is_won_stage']);

        $data = [
            'name' => $name,
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'company' => $this->input('company'),
            'cnpj_cpf' => $this->input('cnpj_cpf'),
            'address' => $this->input('address'),
            'address_number'     => $this->input('address_number'),
            'address_complement' => $this->input('address_complement'),
            'neighborhood' => $this->input('neighborhood'),
            'city' => $this->input('city'),
            'state' => $this->input('state'),
            'zip_code' => $this->input('zip_code'),
            'pipeline_stage_id' => $stageId,
            'assigned_to' => $this->inputPost('assigned_to'),
            'deal_value' => $this->inputPost('deal_value', '0'),
            'source' => $this->input('source'),
            'notes' => $this->input('notes'),
            'birth_date' => $this->inputPost('birth_date'),
            'referido_por' => $this->input('referido_por'),
            'closed_at' => $isVendaFechada ? ($this->inputPost('closed_at') ?: null) : null,
        ];

        $client = $this->clients->findById($id);
        if (!$client) {
            $this->redirect('/clients');
            return;
        }
        $this->clients->update($id, $data);

        $this->flash('success', 'Cliente atualizado com sucesso!');
        $this->redirect('/clients/' . $id);
    }

    /**
     * Remove um cliente via soft delete.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
     */
    public function destroy(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $client = $this->clients->findById($id);
        if (!$client) {
            $this->redirect('/clients');
            return;
        }
        $this->clients->softDelete($id);

        $this->flash('success', 'Cliente removido com sucesso.');
        $this->redirect('/clients');
    }

    /**
     * Registra uma nova cota de consórcio para o cliente via AJAX.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
     */
    public function storeSale(array $params = []): void
    {
        $clientId = (int) ($params['id'] ?? 0);
        if (!$clientId) {
            $this->json(ApiResponse::error('Cliente inválido.'), 400);
            return;
        }

        $tipo        = $this->inputPost('tipo');
        $tiposValidos = ['Imóvel', 'Veículo', 'Serviço'];
        if (!in_array($tipo, $tiposValidos, true)) {
            $this->json(ApiResponse::error('Tipo de consórcio inválido.'), 422);
            return;
        }

        $data = [
            'grupo'               => $this->input('grupo'),
            'cota'                => $this->input('cota'),
            'tipo'                => $tipo,
            'credito_contratado'  => $this->inputPost('credito_contratado', '0'),
        ];

        $saleId = $this->sales->create($clientId, $data);

        $this->json(ApiResponse::success([
            'sale' => [
                'id'                 => $saleId,
                'grupo'              => $data['grupo'],
                'cota'               => $data['cota'],
                'tipo'               => $data['tipo'],
                'credito_contratado' => $data['credito_contratado'],
            ],
        ], token: true));
    }

    /**
     * Remove uma cota de consórcio do cliente via AJAX.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id' e 'sale_id')
     * @return void
     */
    public function destroySale(array $params = []): void
    {
        $clientId = (int) ($params['id'] ?? 0);
        $saleId   = (int) ($params['sale_id'] ?? 0);

        if (!$clientId || !$saleId) {
            $this->json(ApiResponse::error('Parâmetros inválidos.'), 400);
            return;
        }

        $deleted = $this->sales->deleteBySaleAndClient($saleId, $clientId);

        if (!$deleted) {
            $this->json(ApiResponse::error('Cota não encontrada.'), 404);
            return;
        }

        $this->json(ApiResponse::success([], token: true));
    }

    /**
     * Marca uma cota como paga (atualiza paid_at para NOW()) via AJAX.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id' e 'sale_id')
     * @return void
     */
    public function markSalePaid(array $params = []): void
    {
        $clientId = (int) ($params['id'] ?? 0);
        $saleId   = (int) ($params['sale_id'] ?? 0);

        if (!$clientId || !$saleId) {
            $this->json(ApiResponse::error('Parâmetros inválidos.'), 400);
            return;
        }

        $updated = $this->sales->updatePaidAt($saleId, $clientId);

        if (!$updated) {
            $this->json(ApiResponse::error('Cota não encontrada.'), 404);
            return;
        }

        $paidFormatted = null;
        $sales = $this->service->getSalesWithPaymentStatus($clientId);
        foreach ($sales as $s) {
            if ((int) $s['id'] === $saleId) {
                $paidFormatted = $s['paid_at_formatted'];
                break;
            }
        }

        $this->json(ApiResponse::success([
            'paid_at_formatted' => $paidFormatted,
        ], token: true));
    }

    /**
     * Atualiza o campo de notas livres do cliente via AJAX.
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
     */
    public function updateNotes(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            $this->json(ApiResponse::error('Cliente inválido.'), 400);
            return;
        }

        $notes = $_POST['notes'] ?? '';
        $ok    = $this->clients->updateNotes($id, $notes);

        $this->json(ApiResponse::success(['ok' => $ok], token: true));
    }
}
