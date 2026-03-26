<?php
// ============================================================
// app/Controllers/PipelineController.php — Kanban Pipeline
// ============================================================

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Client;
use App\Models\PipelineStage;

class PipelineController extends Controller
{
    /**
     * GET /pipeline
     * Exibe o board Kanban com todos os clientes agrupados por etapa.
     */
    public function index(array $params = []): void
    {
        $stageModel  = new PipelineStage();
        $clientModel = new Client();

        $stages  = $stageModel->findAllOrdered();
        $grouped = $clientModel->findGroupedByStage(); // ['stage_id' => [clientes...]]

        $this->render('pipeline/index', [
            'pageTitle'  => 'Pipeline de Vendas',
            'title'      => 'Pipeline — ' . APP_NAME,
            'stages'     => $stages,
            'grouped'    => $grouped,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * POST /pipeline/move  (AJAX)
     * Move um cliente para uma nova etapa do funil via drag & drop.
     *
     * Recebe JSON: {"client_id": 5, "stage_id": 3}
     * Retorna JSON: {"success": true} ou {"success": false, "message": "..."}
     */
    public function move(array $params = []): void
    {
        // Lê o corpo da requisição JSON enviado pelo Fetch API
        $body = json_decode(file_get_contents('php://input'), true);

        $clientId = isset($body['client_id']) ? (int) $body['client_id'] : 0;
        $stageId  = isset($body['stage_id'])  ? (int) $body['stage_id']  : 0;

        if (!$clientId || !$stageId) {
            $this->json(['success' => false, 'message' => 'Parâmetros inválidos.'], 422);
        }

        $clientModel = new Client();
        $ok = $clientModel->updateStage($clientId, $stageId);

        $this->json(['success' => $ok]);
    }

    /**
     * GET /pipeline/stages
     * Exibe a lista de etapas (gerenciamento pelo admin).
     */
    public function stages(array $params = []): void
    {
        $this->requireRole('admin');
        $stageModel = new PipelineStage();

        $this->render('pipeline/stages', [
            'pageTitle'  => 'Gerenciar Etapas do Funil',
            'title'      => 'Etapas — ' . APP_NAME,
            'stages'     => $stageModel->findAllOrdered(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * POST /pipeline/stages/store
     * Cria uma nova etapa no funil.
     */
    public function storeStage(array $params = []): void
    {
        $this->requireRole('admin');
        $name  = $this->input('name');
        $color = $this->inputRaw('color', '#6366f1');

        if (empty($name)) {
            $this->flash('error', 'O nome da etapa é obrigatório.');
            $this->redirect('/pipeline/stages');
            return;
        }

        $stageModel = new PipelineStage();
        $stageModel->create(['name' => $name, 'color' => $color]);

        $this->flash('success', "Etapa \"{$name}\" criada com sucesso!");
        $this->redirect('/pipeline/stages');
    }

    /**
     * POST /pipeline/stages/{id}/delete
     * Deleta uma etapa do funil (apenas se não houver clientes vinculados).
     */
    public function destroyStage(array $params = []): void
    {
        $this->requireRole('admin');
        $id = (int) ($params['id'] ?? 0);
        $stageModel = new PipelineStage();

        if ($stageModel->hasClients($id)) {
            $this->flash('error', 'Não é possível excluir uma etapa que possui clientes. Mova-os primeiro.');
            $this->redirect('/pipeline/stages');
            return;
        }

        $stageModel->delete($id);
        $this->flash('success', 'Etapa removida com sucesso.');
        $this->redirect('/pipeline/stages');
    }

    /**
     * POST /pipeline/stages/{id}/update  (AJAX)
     * Atualiza nome e/ou cor de uma etapa.
     * Body: _csrf_token, name, color
     */
    public function updateStage(array $params = []): void
    {
        $this->requireRole('admin');
        $id    = (int) ($params['id'] ?? 0);
        $name  = $this->input('name');
        $color = $this->inputRaw('color', '');

        if (!$id || empty($name)) {
            $this->json(['success' => false, 'message' => 'Parâmetros inválidos.'], 422);
            return;
        }

        $stageModel = new PipelineStage();
        $ok = $stageModel->update($id, ['name' => $name, 'color' => $color]);

        $this->json([
            'success'    => $ok,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * POST /pipeline/stages/{id}/move  (AJAX)
     * Move a etapa uma posição para cima ou para baixo.
     * Body: _csrf_token, direction (up|down)
     */
    public function moveStage(array $params = []): void
    {
        $this->requireRole('admin');
        $id        = (int) ($params['id'] ?? 0);
        $direction = $this->input('direction');

        if (!$id || !in_array($direction, ['up', 'down'], true)) {
            $this->json(['success' => false, 'message' => 'Parâmetros inválidos.'], 422);
            return;
        }

        $stageModel = new PipelineStage();
        $ok = $stageModel->movePosition($id, $direction);

        $this->json([
            'success'    => $ok,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }
}
