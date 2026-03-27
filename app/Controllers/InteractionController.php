<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Interaction;

class InteractionController extends Controller
{
    /**
     * Registra uma nova interação com o cliente.
     * Redireciona de volta para a página do cliente após salvar.
     */
    public function store(array $params = []): void
    {
        $clientId = (int) ($this->inputRaw('client_id') ?? 0);
        $description = trim($_POST['description'] ?? '');
        $occurredAt = $this->inputRaw('occurred_at');

        if (!$clientId || empty($description) || empty($occurredAt)) {
            $this->flash('error', 'Preencha todos os campos da interação.');
            $this->redirect('/clients/' . $clientId);
            return;
        }

        // Converte o formato datetime-local (YYYY-MM-DDTHH:MM) para MySQL (YYYY-MM-DD HH:MM:SS)
        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        $interactionModel = new Interaction();
        $interactionModel->create([
            'client_id' => $clientId,
            'user_id' => $_SESSION['user']['id'], // usuário logado
            'type' => $this->inputRaw('type', 'note'),
            'description' => $description,
            'occurred_at' => $occurredAt,
        ]);

        $this->flash('success', 'Interação registrada com sucesso!');
        $this->redirect('/clients/' . $clientId);
    }

    /**
     * Atualiza uma interação existente. Retorna JSON.
     */
    public function update(array $params = []): void
    {
        header('Content-Type: application/json');
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        $description = trim($_POST['description'] ?? '');
        $type        = $_POST['type'] ?? '';
        $occurredAt  = $_POST['occurred_at'] ?? '';

        $validTypes = ['call', 'email', 'meeting', 'whatsapp', 'note', 'other'];
        if (empty($description) || !in_array($type, $validTypes, true) || empty($occurredAt)) {
            echo json_encode(['success' => false, 'error' => 'Campos inválidos.']);
            exit;
        }

        // Converte datetime-local (YYYY-MM-DDTHH:MM) para MySQL (YYYY-MM-DD HH:MM:SS)
        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        $interactionModel = new Interaction();
        $ok = $interactionModel->update($id, [
            'description' => $description,
            'type'        => $type,
            'occurred_at' => $occurredAt,
        ]);

        echo json_encode([
            'success'    => $ok,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
        exit;
    }

    /**
     * Remove uma interação do histórico.
     */
    public function destroy(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        // Precisamos do client_id para redirecionar de volta após deletar
        $clientId = (int) ($this->inputRaw('client_id') ?? 0);

        $interactionModel = new Interaction();

        // Busca a interação para recuperar o client_id se não veio no form
        if (!$clientId) {
            $inter = $interactionModel->findById($id);
            $clientId = $inter['client_id'] ?? 0;
        }

        $interactionModel->delete($id);

        $this->flash('success', 'Interação removida.');
        $this->redirect('/clients/' . $clientId);
    }
}
