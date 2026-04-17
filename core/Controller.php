<?php
// core/Controller.php — Classe Base para todos os Controllers
// Fornece métodos utilitários usados por todos os controllers:
//   - render()    → carrega uma View com dados
//   - redirect()  → redireciona para outra URL
//   - json()      → retorna resposta JSON (para AJAX)
//   - flash()     → mensagens de sucesso/erro via sessão

namespace Core;

abstract class Controller
{
    /**
     * Renderiza uma View passando variáveis para ela.
     * Usa uma closure para injetar as chaves do array $data como variáveis
     * locais no escopo da view de forma segura (sem extract()).
     * @param  string  $view   Caminho relativo à pasta Views/ (sem .php)
     * @param  array   $data   Dados a serem disponibilizados como variáveis na view
     * @param  string  $layout Layout padrão (usa 'main' se não especificado)
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        // Caminho absoluto do arquivo de view
        $viewFile = VIEW_PATH . DS . str_replace('/', DS, $view) . '.php';

        if (!file_exists($viewFile)) {
            die("View não encontrada: {$viewFile}");
        }

        // Closure segura que injeta variáveis do array $data no escopo da view
        // sem contaminar $this nem usar extract() (que poderia sobrescrever propriedades
        // do Controller via variáveis como $this ou propriedades mágicas).
        $renderView = function(string $__viewFile, array $__data) {
            foreach ($__data as $__k => $__v) { $$__k = $__v; }
            unset($__k, $__v);
            ob_start();
            require $__viewFile;
            return ob_get_clean();
        };
        $content = $renderView($viewFile, $data);

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
        if (str_contains($path, '://') || !str_starts_with($path, '/')) {
            $path = '/dashboard';
        }
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
     * Verifica se o usuário logado tem o papel exigido.
     * Caso contrário, redireciona para o dashboard com mensagem de acesso negado.
     *
     * @param  string|array  $roles  Role(s) permitidos: 'admin' ou ['admin', 'seller']
     */
    protected function requireRole(string|array $roles): void
    {
        $roles    = (array) $roles;
        $userRole = $_SESSION['user']['role'] ?? '';

        if (!in_array($userRole, $roles, true)) {
            $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                   || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

            if ($isJson) {
                $this->json([
                    'success' => false,
                    'error'   => ['code' => 'forbidden', 'message' => 'Acesso negado.'],
                ], 403);
            } else {
                $this->flash('error', 'Acesso negado: você não tem permissão para esta ação.');
                $this->redirect('/dashboard');
            }
        }
    }

    /**
     * Retorna valor do $_POST com trim. Sem htmlspecialchars — encoding deve
     * ser feito nas views (e.g. htmlspecialchars ao exibir, não ao gravar).
     *
     * @param  string  $key      Nome do campo
     * @param  string  $default  Valor padrão se o campo não existir
     * @return string
     */
    protected function input(string $key, string $default = ''): string
    {
        return trim((string) ($_POST[$key] ?? $default));
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
