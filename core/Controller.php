<?php
// ============================================================
// core/Controller.php — Classe Base para todos os Controllers
// ============================================================
// Fornece métodos utilitários usados por todos os controllers:
//   - render()    → carrega uma View com dados
//   - redirect()  → redireciona para outra URL
//   - json()      → retorna resposta JSON (para AJAX)
//   - flash()     → mensagens de sucesso/erro via sessão
// ============================================================

namespace Core;

abstract class Controller
{
    /**
     * Renderiza uma View passando variáveis para ela.
     *
     * O método usa extract() para transformar as chaves do array $data
     * em variáveis locais dentro do escopo do arquivo de view.
     *
     * Exemplo:
     *   $this->render('clients/index', ['clients' => $clientes]);
     *   // Dentro da view, estará disponível: $clients
     *
     * @param  string  $view   Caminho relativo à pasta Views/ (sem .php)
     *                         Ex.: 'clients/index', 'auth/login'
     * @param  array   $data   Dados a serem extraídos como variáveis na view
     * @param  string  $layout Layout padrão (usa 'main' se não especificado)
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        // Extrai o array como variáveis: ['title' => 'CRM'] vira $title = 'CRM'
        extract($data);

        // Caminho absoluto do arquivo de view
        $viewFile = VIEW_PATH . DS . str_replace('/', DS, $view) . '.php';

        if (!file_exists($viewFile)) {
            die("View não encontrada: {$viewFile}");
        }

        // O conteúdo da view é capturado em buffer e depois injetado no layout
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Carrega o layout que envolve a view (header + sidebar + footer)
        $layoutFile = VIEW_PATH . DS . 'layouts' . DS . $layout . '.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            // Se não houver layout, exibe a view diretamente
            echo $content;
        }
    }

    /**
     * Redireciona o navegador para outra URL e encerra a execução.
     *
     * @param  string  $path  Caminho relativo a APP_URL (ex.: '/clients', '/login')
     */
    protected function redirect(string $path): void
    {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    /**
     * Retorna uma resposta em formato JSON.
     * Usado pelas rotas que respondem a requisições AJAX (Fetch API).
     *
     * @param  mixed  $data    Dados a serializar (array, objeto, etc.)
     * @param  int    $status  Código HTTP de resposta (200, 201, 400, 422, 500...)
     */
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Armazena uma mensagem flash na sessão para exibir na próxima requisição.
     * Ideal para mostrar "Cliente salvo com sucesso!" após um redirect.
     *
     * @param  string  $type     Tipo de alerta: 'success', 'error', 'warning', 'info'
     * @param  string  $message  Texto da mensagem
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Verifica se o usuário logado tem o papel (role) exigido.
     * Caso contrário, redireciona para o dashboard com mensagem de acesso negado.
     *
     * @param  string|array  $roles  Role(s) permitidos: 'admin' ou ['admin', 'seller']
     */
    protected function requireRole(string|array $roles): void
    {
        $roles = (array) $roles;
        $userRole = $_SESSION['user']['role'] ?? '';

        if (!in_array($userRole, $roles, true)) {
            $this->flash('error', 'Acesso negado: você não tem permissão para esta ação.');
            $this->redirect('/dashboard');
        }
    }

    /**
     * Retorna um valor sanitizado do array $_POST.
     * Aplica htmlspecialchars para prevenir XSS nos dados exibidos em HTML.
     *
     * @param  string  $key      Nome do campo
     * @param  string  $default  Valor padrão se o campo não existir
     * @return string
     */
    protected function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Versão de input() que NÃO aplica htmlspecialchars.
     * Use apenas quando o valor for numérico ou for para inserção no banco
     * (o PDO com prepared statements já protege contra SQL Injection).
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    protected function inputRaw(string $key, mixed $default = null): mixed
    {
        return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
    }
}
