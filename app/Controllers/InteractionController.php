<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Interaction;
use App\Models\Client;

class InteractionController extends Controller
{
    public function store(array $params = []): void
    {
        $clientId    = (int) ($this->inputRaw('client_id') ?? 0);
        $description = trim($_POST['description'] ?? '');
        $occurredAt  = $this->inputRaw('occurred_at');

        if (!$clientId || empty($description) || empty($occurredAt)) {
            $this->flash('error', 'Preencha todos os campos da interação.');
            $this->redirect('/clients/' . $clientId);
            return;
        }

        // Garante que o cliente pertence ao tenant do usuário logado
        $clientModel = new Client();
        $client = $clientModel->findById($clientId);
        if (!$client) {
            $this->flash('error', 'Cliente não encontrado.');
            $this->redirect('/clients');
            return;
        }

        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        $interactionModel = new Interaction();
        $interactionModel->create([
            'client_id'   => $clientId,
            'user_id'     => $_SESSION['user']['id'],
            'type'        => $this->inputRaw('type', 'note'),
            'description' => $description,
            'occurred_at' => $occurredAt,
        ]);

        $this->flash('success', 'Interação registrada com sucesso!');
        $this->redirect('/clients/' . $clientId);
    }

    public function update(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido.'], 400);
            return;
        }

        // Garante que a interação pertence ao tenant (via override findById)
        $interactionModel = new Interaction();
        $interaction = $interactionModel->findById($id);
        if (!$interaction) {
            $this->json(['success' => false, 'error' => 'Interação não encontrada.'], 404);
            return;
        }

        $description = trim($_POST['description'] ?? '');
        $type        = $_POST['type'] ?? '';
        $occurredAt  = $_POST['occurred_at'] ?? '';

        $validTypes = ['call', 'email', 'meeting', 'whatsapp', 'note', 'other'];
        if (empty($description) || !in_array($type, $validTypes, true) || empty($occurredAt)) {
            $this->json(['success' => false, 'error' => 'Campos inválidos.'], 422);
            return;
        }

        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        $ok = $interactionModel->update($id, [
            'description' => $description,
            'type'        => $type,
            'occurred_at' => $occurredAt,
        ]);

        $this->json(['success' => $ok, 'csrf_token' => CsrfMiddleware::getToken()]);
    }

    public function destroy(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $clientId = (int) ($this->inputRaw('client_id') ?? 0);

        $interactionModel = new Interaction();

        // findById já aplica tenant gate via INNER JOIN clients
        $inter = $interactionModel->findById($id);
        if ($inter) {
            $clientId = $clientId ?: (int) $inter['client_id'];
            $interactionModel->delete($id);
        }

        $this->flash('success', 'Interação removida.');
        $this->redirect('/clients/' . $clientId);
    }
}
