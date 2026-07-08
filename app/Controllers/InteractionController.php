<?php

namespace App\Controllers;

use Core\Controller;
use Core\Http\ApiResponse;
use Core\Logger;
use App\Models\Interaction;
use App\Models\Client;

class InteractionController extends Controller
{
    public function __construct(
        private Interaction $interactions = new Interaction(),
        private Client      $clients      = new Client(),
    ) {}

    public function store(array $params = []): void
    {
        $this->requireRole(['admin', 'seller']);

        $clientId    = (int) ($this->inputPost('client_id') ?? 0);
        $description = trim($_POST['description'] ?? '');
        $occurredAt  = $this->inputPost('occurred_at');
        $type        = $this->inputPost('type', 'note');

        $validTypes = ['call', 'email', 'meeting', 'whatsapp', 'note', 'other'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'note';
        }

        if (!$clientId || empty($description) || empty($occurredAt)) {
            $this->flash('error', 'Preencha todos os campos da interação.');
            $this->redirect('/clients/' . $clientId);
            return;
        }

        // Garante que o cliente pertence ao tenant do usuário logado
        $client = $this->clients->findById($clientId);
        if (!$client) {
            $this->flash('error', 'Cliente não encontrado.');
            $this->redirect('/clients');
            return;
        }

        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        try {
            $this->interactions->create([
                'client_id'   => $clientId,
                'user_id'     => $_SESSION['user']['id'],
                'type'        => $type,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);
        } catch (\Throwable $e) {
            // Loga a exceção real internamente; o usuário recebe mensagem genérica
            // (detalhes de schema/SQL não devem vazar para o navegador)
            (new Logger())->error('[InteractionController::store] falha ao registrar interacao', ['client_id' => $clientId, 'exception' => $e->getMessage()]);
            $this->flash('error', 'Não foi possível registrar a interação. Tente novamente.');
            $this->redirect('/clients/' . $clientId);
            return;
        }

        $this->flash('success', 'Interação registrada com sucesso!');
        $this->redirect('/clients/' . $clientId);
    }

    public function update(array $params = []): void
    {
        $this->requireRole(['admin', 'seller'], json: true);

        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            $this->json(ApiResponse::error('ID inválido.'), 400);
            return;
        }

        // Garante que a interação pertence ao tenant (via override findById)
        $interaction = $this->interactions->findById($id);
        if (!$interaction) {
            $this->json(ApiResponse::error('Interação não encontrada.'), 404);
            return;
        }

        $description = trim($_POST['description'] ?? '');
        $type        = $_POST['type'] ?? '';
        $occurredAt  = $_POST['occurred_at'] ?? '';

        $validTypes = ['call', 'email', 'meeting', 'whatsapp', 'note', 'other'];
        if (empty($description) || !in_array($type, $validTypes, true) || empty($occurredAt)) {
            $this->json(ApiResponse::error('Campos inválidos.'), 422);
            return;
        }

        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        $ok = $this->interactions->update($id, [
            'description' => $description,
            'type'        => $type,
            'occurred_at' => $occurredAt,
        ]);

        $this->json(ApiResponse::success(['success' => $ok], token: true));
    }

    public function destroy(array $params = []): void
    {
        $this->requireRole(['admin', 'seller']);

        $id       = (int) ($params['id'] ?? 0);
        $clientId = (int) ($this->inputPost('client_id') ?? 0);

        // findById já aplica o escopo de tenant (Core\Model::findById())
        $inter = $this->interactions->findById($id);
        if (!$inter) {
            $this->flash('error', 'Interação não encontrada.');
            $this->redirect('/clients/' . $clientId);
            return;
        }

        $clientId = $clientId ?: (int) $inter['client_id'];
        $this->interactions->delete($id);

        $this->flash('success', 'Interação removida.');
        $this->redirect('/clients/' . $clientId);
    }
}
