<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\ColdContact;
use Core\Http\ApiResponse;

class ColdContactController extends Controller
{
    private const MAX_CSV_BYTES = 5 * 1024 * 1024;
    private const MAX_CSV_ROWS  = 50000;

    public function __construct(
        private ColdContact $coldContacts = new ColdContact(),
    ) {}

    /**
     * Exibe a tela de Cards Mensais com resumo de importações.
     */
    public function index(array $params = []): void
    {
        // Paginação dos cards mensais
        [$page, $limit, $offset] = $this->paginate('cold_contacts');

        $totalCount = $this->coldContacts->countFindMonthSummaries();
        $rawSummaries = $this->coldContacts->findMonthSummaries($limit, $offset);
        $totalPages = (int) ceil($totalCount / $limit);

        $meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
            '04' => 'Abril',   '05' => 'Maio',      '06' => 'Junho',
            '07' => 'Julho',   '08' => 'Agosto',    '09' => 'Setembro',
            '10' => 'Outubro', '11' => 'Novembro',  '12' => 'Dezembro',
        ];

        $summaries = array_map(function (array $row) use ($meses): array {
            $mesAno = $row['mes_ano'] ?? '';
            // Linhas órfãs (imported_year_month NULL em registros antigos) caíam no
            // explode() recebendo null; degrada com label genérico em vez de quebrar.
            if (!preg_match('/^\d{4}-\d{2}$/', (string) $mesAno)) {
                $row['month_label'] = 'Sem data';
                return $row;
            }
            $parts = explode('-', $mesAno); // ['YYYY', 'MM']
            $nomeMes = $meses[$parts[1]] ?? $parts[1];
            $row['month_label'] = $nomeMes . ' ' . $parts[0]; // ex: "Março 2026"
            return $row;
        }, $rawSummaries);

        $pagination_cards = [
            'current_page' => $page,
            'total_pages'  => $totalPages,
            'total_items'  => $totalCount,
            'per_page'     => $limit,
            'base_url'     => '/cold-contacts',
            'query_params' => [],
        ];

        $this->render('cold-contacts/index', [
            'pageTitle'        => 'Contatos Frios',
            'title'            => 'Contatos Frios — ' . APP_NAME,
            'summaries'        => $summaries,
            'csrf_token'       => CsrfMiddleware::getToken(),
            'pagination_cards' => $pagination_cards,
        ]);
    }

    /**
     * Processa upload de CSV e insere os contatos no banco.
     */
    public function import(array $params = []): void
    {
        $this->requireRole(['admin', 'seller']);
        $tipoLista = trim($_POST['tipo_lista'] ?? '');
        $telefoneEnviado = trim($_POST['telefone_enviado'] ?? '');
        $dataMensagem = trim($_POST['data_mensagem'] ?? '');

        if (empty($tipoLista)) {
            $this->flash('error', 'O campo "Tipo de lista" é obrigatório para importar.');
            $this->redirect('/cold-contacts');
            return;
        }

        if (!empty($telefoneEnviado) && (!ctype_digit($telefoneEnviado) || strlen($telefoneEnviado) > 4)) {
            $this->flash('error', 'Telefone enviado deve ser numérico com até 4 dígitos.');
            $this->redirect('/cold-contacts');
            return;
        }

        $dataNormalizada = null;
        if (!empty($dataMensagem)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataMensagem)) {
                $dataNormalizada = $dataMensagem;
            }
        }

        // Valida upload do arquivo
        $uploadError = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Nenhum arquivo CSV foi enviado ou ocorreu erro no upload.');
            $this->redirect('/cold-contacts');
            return;
        }

        $originalName = $_FILES['csv_file']['name'] ?? '';
        $fileSize     = (int) ($_FILES['csv_file']['size'] ?? 0);
        $tmpPath      = $_FILES['csv_file']['tmp_name'] ?? '';

        // Limite de tamanho (5 MB) — evita streaming de arquivos enormes.
        if ($fileSize > self::MAX_CSV_BYTES) {
            $this->flash('error', 'Arquivo muito grande. Limite de 5 MB.');
            $this->redirect('/cold-contacts');
            return;
        }

        // Aceita apenas extensao .csv/.txt.
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $this->flash('error', 'Formato invalido. Envie um arquivo .csv.');
            $this->redirect('/cold-contacts');
            return;
        }

        // Rejeita conteudo que claramente nao e texto/CSV (ex.: imagens, binarios).
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowedMime = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'];
        if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
            $this->flash('error', 'O conteudo do arquivo nao parece ser CSV.');
            $this->redirect('/cold-contacts');
            return;
        }

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

        $inserted = 0;
        $skipped = 0;
        $lineNum = 0;

        $limitReached = false;
        // Importa em transacao unica: ou todos os contatos validos entram,
        // ou nenhum (evita import parcial se uma insercao falhar no meio).
        $pdo = \Core\Database::getInstance();
        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                $lineNum++;

                // Teto de linhas — protege contra arquivos com volume abusivo.
                if ($lineNum > self::MAX_CSV_ROWS) {
                    $limitReached = true;
                    break;
                }

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

                $this->coldContacts->create([
                    'phone' => $phone,
                    'name' => $name,
                    'tipo_lista' => $tipoLista,
                    'telefone_enviado' => !empty($telefoneEnviado) ? $telefoneEnviado : null,
                    'data_mensagem' => $dataNormalizada,
                ]);
                $inserted++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        if ($limitReached) {
            $this->flash('warning', "Limite de " . self::MAX_CSV_ROWS . " linhas atingido. {$inserted} contato(s) importado(s); o restante foi ignorado.");
        } elseif ($inserted === 0) {
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
        $this->requireRole(['admin', 'seller'], json: true);

        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            $this->json(ApiResponse::error('ID inválido.'), 400);
            return;
        }

        $phone           = $this->input('phone');
        $name            = $this->input('name');
        $telefoneEnviado = $this->inputPost('telefone_enviado');
        $dataMensagem    = $this->inputPost('data_mensagem');

        if (empty($phone) || empty($name)) {
            $this->json(ApiResponse::error('Celular e Nome são obrigatórios.'), 422);
            return;
        }

        if (!empty($telefoneEnviado) && (!ctype_digit($telefoneEnviado) || strlen($telefoneEnviado) > 4)) {
            $this->json(ApiResponse::error('Telefone enviado deve ser numérico com até 4 dígitos.'), 422);
            return;
        }

        $dataNormalizada = null;
        if (!empty($dataMensagem)) {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataMensagem)) {
                $parts           = explode('/', $dataMensagem);
                $dataNormalizada = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataMensagem)) {
                $dataNormalizada = $dataMensagem;
            }
        }

        $updated = $this->coldContacts->update($id, [
            'phone'            => $phone,
            'name'             => $name,
            'telefone_enviado' => !empty($telefoneEnviado) ? $telefoneEnviado : null,
            'data_mensagem'    => $dataNormalizada,
        ]);

        $this->json(ApiResponse::success(['updated' => $updated], token: true));
    }

    /**
     * Deleta um contato frio. Retorna JSON.
     */
    public function destroy(array $params = []): void
    {
        $this->requireRole(['admin', 'seller'], json: true);

        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            $this->json(ApiResponse::error('ID inválido.'), 400);
            return;
        }

        $deleted = $this->coldContacts->destroy($id);
        $this->json(ApiResponse::success(['deleted' => $deleted], token: true));
    }

    /**
     * Deleta todos os contatos de um mês. Recebe {yearMonth} = YYYY-MM. Retorna JSON.
     */
    public function deleteMonth(array $params = []): void
    {
        $this->requireRole(['admin', 'seller'], json: true);

        $yearMonth = trim($params['year_month'] ?? '');
        if (empty($yearMonth) || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $this->json(ApiResponse::error('Mês inválido.'), 422);
            return;
        }

        try {
            $deleted = $this->coldContacts->deleteByMonth($yearMonth);
            $this->json(ApiResponse::success(['deleted' => $deleted], token: true));
        } catch (\Throwable $e) {
            error_log('[ColdContact deleteMonth] yearMonth=' . $yearMonth . ' exception: ' . $e->getMessage());
            $this->json(ApiResponse::error('Erro ao excluir mês.'), 500);
        }
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

        $contacts = $this->coldContacts->findForExport($yearMonth, $filters);

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
                $this->csvSafe(trim($c['phone'])),
                $this->csvSafe(trim($c['name'])),
                $this->csvSafe(trim($c['tipo_lista'])),
                $this->csvSafe(trim($c['telefone_enviado'] ?? '')),
                $c['data_mensagem'] ? date('d/m/Y', strtotime($c['data_mensagem'])) : '',
            ], ';');
        }

        fclose($out);
        exit;
    }

    /**
     * Neutraliza CSV/formula injection: celulas iniciadas por = + - @ (ou
     * tab/CR) sao executadas como formula pelo Excel. Prefixa com aspa simples.
     */
    private function csvSafe(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Atualiza telefone enviado e data em lote para os IDs selecionados.
     */
    public function bulkUpdate(array $params = []): void
    {
        $this->requireRole(['admin', 'seller'], json: true);

        $telefone = trim($_POST['telefone_enviado'] ?? '');
        $dataMsg  = trim($_POST['data_mensagem'] ?? '');

        if (!empty($telefone) && (!ctype_digit($telefone) || strlen($telefone) > 4)) {
            $this->json(ApiResponse::error('Tel. enviado deve ser numérico com até 4 dígitos.'), 422);
            return;
        }

        $dataNormalizada = null;
        if (!empty($dataMsg) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataMsg)) {
            $dataNormalizada = $dataMsg;
        }

        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        if (empty($ids)) {
            $this->json(ApiResponse::error('Nenhum contato selecionado.'), 422);
            return;
        }

        if ($telefone === '' && $dataMsg === '') {
            $this->json(ApiResponse::error('Nenhum dado informado para atualizar.'), 422);
            return;
        }

        $updated = $this->coldContacts->bulkAtualizarExtras(
            $ids,
            $telefone === '' ? null : $telefone,
            $dataMsg  === '' ? null : $dataNormalizada
        );

        $this->json(ApiResponse::success(['updated' => $updated], token: true));
    }

    /**
     * Retorna JSON com contatos filtrados do mês — usado pela modal JS.
     * Suporta paginação via page e per_page, retornando metadados no JSON.
     */
    public function listJson(array $params = []): void
    {
        $yearMonth = trim($_GET['month'] ?? '');
        if (empty($yearMonth) || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $this->json(['contacts' => [], 'pagination' => []]);
            return;
        }

        $filters = [];
        if (!empty($_GET['tipo_lista']))       $filters['tipo_lista']       = trim($_GET['tipo_lista']);
        if (!empty($_GET['dia']))              $filters['dia']              = (int) $_GET['dia'];
        if (!empty($_GET['telefone_enviado'])) $filters['telefone_enviado'] = trim($_GET['telefone_enviado']);

        [$page, $limit, $offset] = $this->paginate('cold_contacts');

        try {
            $totalCount = $this->coldContacts->countByMonth($yearMonth, $filters);
            $contacts   = $this->coldContacts->findByMonth($yearMonth, $filters, $limit, $offset);

            $this->json([
                'contacts'   => $contacts,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages'  => (int) ceil($totalCount / $limit),
                    'total_items'  => $totalCount,
                    'per_page'     => $limit,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[ColdContact listJson] yearMonth=' . $yearMonth . ' exception: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            $this->json(['contacts' => [], 'pagination' => []]);
        }
    }
}
