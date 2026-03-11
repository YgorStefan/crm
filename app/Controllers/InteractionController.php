<?php
// ============================================================
// app/Controllers/InteractionController.php
// ============================================================

namespace App\Controllers;

use Core\Controller;
use App\Models\Interaction;

class InteractionController extends Controller
{
    /**
     * POST /interactions/store
     * Registra uma nova interação com o cliente.
     * Redireciona de volta para a página do cliente após salvar.
     */
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

        // Converte o formato datetime-local (YYYY-MM-DDTHH:MM) para MySQL (YYYY-MM-DD HH:MM:SS)
        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        $interactionModel = new Interaction();
        $interactionModel->create([
            'client_id'   => $clientId,
            'user_id'     => $_SESSION['user']['id'], // usuário logado
            'type'        => $this->inputRaw('type', 'note'),
            'description' => $description,
            'occurred_at' => $occurredAt,
        ]);

        $this->flash('success', 'Interação registrada com sucesso!');
        $this->redirect('/clients/' . $clientId);
    }

    /**
     * POST /interactions/{id}/delete
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
