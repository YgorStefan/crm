<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\ColdContact;

class ColdContactController extends Controller
{
    /**
     * Exibe a tela de Cards Mensais com resumo de importações.
     */
    public function index(array $params = []): void
    {
        $model = new ColdContact();
        $rawSummaries = $model->findMonthSummaries();

        $meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
            '04' => 'Abril',   '05' => 'Maio',      '06' => 'Junho',
            '07' => 'Julho',   '08' => 'Agosto',    '09' => 'Setembro',
            '10' => 'Outubro', '11' => 'Novembro',  '12' => 'Dezembro',
        ];

        $summaries = array_map(function (array $row) use ($meses): array {
            $parts = explode('-', $row['mes_ano']); // ['YYYY', 'MM']
            $nomeMes = $meses[$parts[1]] ?? $parts[1];
            $row['month_label'] = $nomeMes . ' ' . $parts[0]; // ex: "Março 2026"
            return $row;
        }, $rawSummaries);

        $this->render('cold-contacts/index', [
            'pageTitle' => 'Contatos Frios',
            'title'     => 'Contatos Frios — ' . APP_NAME,
            'summaries' => $summaries,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Processa upload de CSV e insere os contatos no banco.
     */
    public function import(array $params = []): void
    {
        $tipoLista = trim($_POST['tipo_lista'] ?? '');
        if (empty($tipoLista)) {
            $this->flash('error', 'O campo "Tipo de lista" é obrigatório para importar.');
            $this->redirect('/cold-contacts');
            return;
        }

        // Valida upload do arquivo
        $uploadError = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Nenhum arquivo CSV foi enviado ou ocorreu erro no upload.');
            $this->redirect('/cold-contacts');
            return;
        }

        $tmpPath = $_FILES['csv_file']['tmp_name'] ?? '';
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            $this->flash('error', 'Não foi possível ler o arquivo CSV enviado.');
            $this->redirect('/cold-contacts');
            return;
        }

        // Autodetecta separador a partir da primeira linha
        $firstLine = fgets($handle);
        $separator = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        $model = new ColdContact();
        $inserted = 0;
        $skipped = 0;
        $lineNum = 0;

        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            $lineNum++;

            // Precisa de ao menos 2 colunas
            if (count($row) < 2) {
                $skipped++;
                continue;
            }

            $name  = trim($row[0] ?? '');
            $phone = trim($row[1] ?? '');

            // Normaliza encoding: arquivos XLS/XLSX brasileiros frequentemente usam Windows-1252
            if ($name !== '' && !mb_check_encoding($name, 'UTF-8')) {
                $name = mb_convert_encoding($name, 'UTF-8', 'Windows-1252');
            }

            // Ignora linhas sem dados ou possível header (coluna B sem dígito)
            if (empty($name) || empty($phone)) {
                $skipped++;
                continue;
            }

            // Ignora header textual: se a primeira linha não tem nenhum dígito na coluna B
            if ($lineNum === 1 && !preg_match('/\d/', $phone)) {
                $skipped++;
                continue;
            }

            $model->create([
                'phone' => $phone,
                'name' => $name,
                'tipo_lista' => $tipoLista,
                'telefone_enviado' => null,
                'data_mensagem' => null,
            ]);
            $inserted++;
        }

        fclose($handle);

        if ($inserted === 0) {
            $this->flash('warning', 'Nenhum contato válido encontrado no CSV. Verifique o formato: coluna A = Nome, coluna B = Telefone.');
        } else {
            $this->flash('success', "{$inserted} contato(s) importado(s) com sucesso da lista \"{$tipoLista}\"." . ($skipped > 0 ? " ({$skipped} linha(s) ignorada(s))" : ''));
        }

        $this->redirect('/cold-contacts');
    }

    /**
     * Atualiza campos editáveis de um contato frio. Retorna JSON.
     */
    public function update(array $params = []): void
    {
        header('Content-Type: application/json');
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        $phone = $this->input('phone');
        $name = $this->input('name');
        $telefoneEnviado = $this->inputRaw('telefone_enviado');
        $dataMensagem = $this->inputRaw('data_mensagem');

        if (empty($phone) || empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Celular e Nome são obrigatórios.']);
            exit;
        }

        // Valida telefone_enviado se preenchido, deve ser numérico, máx 4 dígitos
        if (!empty($telefoneEnviado) && (!ctype_digit($telefoneEnviado) || strlen($telefoneEnviado) > 4)) {
            echo json_encode(['success' => false, 'error' => 'Telefone enviado deve ser numérico com até 4 dígitos.']);
            exit;
        }

        // Normaliza data_mensagem aceita YYYY-MM-DD ou DD/MM/YYYY, converte para YYYY-MM-DD
        $dataNormalizada = null;
        if (!empty($dataMensagem)) {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataMensagem)) {
                $parts = explode('/', $dataMensagem);
                $dataNormalizada = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataMensagem)) {
                $dataNormalizada = $dataMensagem;
            }
        }

        $model = new ColdContact();
        $updated = $model->update($id, [
            'phone' => $phone,
            'name' => $name,
            'telefone_enviado' => !empty($telefoneEnviado) ? $telefoneEnviado : null,
            'data_mensagem' => $dataNormalizada,
        ]);

        echo json_encode([
            'success' => $updated,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
        exit;
    }

    /**
     * Deleta um contato frio. Retorna JSON.
     */
    public function destroy(array $params = []): void
    {
        header('Content-Type: application/json');
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        $model = new ColdContact();
        $deleted = $model->destroy($id);

        echo json_encode([
            'success' => $deleted,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
        exit;
    }

    /**
     * Deleta todos os contatos de um mês. Recebe {yearMonth} = YYYY-MM. Retorna JSON.
     */
    public function deleteMonth(array $params = []): void
    {
        header('Content-Type: application/json');
        $yearMonth = trim($params['yearMonth'] ?? '');

        if (empty($yearMonth) || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            echo json_encode(['success' => false, 'error' => 'Mês inválido.']);
            exit;
        }

        try {
            $model = new ColdContact();
            $deleted = $model->deleteByMonth($yearMonth);
            echo json_encode([
                'success'    => true,
                'deleted'    => $deleted,
                'csrf_token' => CsrfMiddleware::getToken(),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir mês.']);
        }
        exit;
    }

    /**
     * Exporta CSV filtrado dos contatos do mês
     * Se nenhum filtro ativo, exporta todos do mês
     * Se filtros ativos, exporta apenas os filtrados
     */
    public function export(array $params = []): void
    {
        $yearMonth = trim($_GET['month'] ?? '');
        if (empty($yearMonth) || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $this->flash('error', 'Mês inválido para exportação.');
            $this->redirect('/cold-contacts');
            return;
        }

        $filters = [];
        if (!empty($_GET['dia'])) {
            $filters['dia'] = (int) $_GET['dia'];
        }
        if (!empty($_GET['telefone_enviado'])) {
            $filters['telefone_enviado'] = trim($_GET['telefone_enviado']);
        }

        $model = new ColdContact();
        $contacts = $model->findForExport($yearMonth, $filters);

        // Nome do arquivo: contatos-frios-YYYY-MM.csv
        $filename = 'contatos-frios-' . $yearMonth . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        // BOM UTF-8 para compatibilidade com Excel
        fputs($out, "\xEF\xBB\xBF");

        // Header do CSV
        fputcsv($out, ['Celular', 'Nome', 'Tipo de lista', 'Telefone enviado', 'Data da mensagem'], ';');

        foreach ($contacts as $c) {
            fputcsv($out, [
                trim($c['phone']),
                trim($c['name']),
                trim($c['tipo_lista']),
                trim($c['telefone_enviado'] ?? ''),
                $c['data_mensagem'] ? date('d/m/Y', strtotime($c['data_mensagem'])) : '',
            ], ';');
        }

        fclose($out);
        exit;
    }

    /**
     * Marca telefone_enviado em lote para os IDs selecionados. Retorna JSON.
     */
    public function bulkUpdate(array $params = []): void
    {
        header('Content-Type: application/json');

        $telefone = trim($_POST['telefone_enviado'] ?? '');
        if (empty($telefone) || !ctype_digit($telefone) || strlen($telefone) > 4) {
            echo json_encode(['success' => false, 'error' => 'Tel. enviado deve ser numérico com até 4 dígitos.']);
            exit;
        }

        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'Nenhum contato selecionado.']);
            exit;
        }

        $model = new ColdContact();
        $updated = $model->bulkSetTelefoneEnviado($ids, $telefone);

        echo json_encode([
            'success' => $updated > 0,
            'updated' => $updated,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
        exit;
    }

    /**
     * Retorna JSON com contatos filtrados do mês — usado pela modal JS
     */
    public function listJson(array $params = []): void
    {
        header('Content-Type: application/json');
        $yearMonth = trim($_GET['month'] ?? '');
        if (empty($yearMonth) || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            echo json_encode(['contacts' => []]);
            exit;
        }
        $filters = [];
        if (!empty($_GET['dia']))
            $filters['dia'] = (int) $_GET['dia'];
        if (!empty($_GET['telefone_enviado']))
            $filters['telefone_enviado'] = trim($_GET['telefone_enviado']);

        try {
            $model = new ColdContact();
            $contacts = $model->findByMonth($yearMonth, $filters);
            echo json_encode(['contacts' => $contacts]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['contacts' => [], 'error' => 'Erro ao carregar contatos.']);
        }
        exit;
    }
}
