<?php
// ============================================================
// core/Router.php — Sistema de Roteamento da Aplicação
// ============================================================
// O Router lê a URL requisitada, a compara com as rotas
// registradas e despacha para o Controller e método corretos.
//
// Suporta:
//   - Rotas estáticas: GET /dashboard
//   - Rotas com parâmetros: GET /clients/{id}
//   - Middlewares por rota (ex.: autenticação, CSRF)
//
// Todas as requisições passam pelo public/index.php antes de
// chegarem aqui, graças ao .htaccess.
// ============================================================

namespace Core;

class Router
{
    // Lista de rotas registradas. Cada entrada é um array com:
    // [method, pattern, controller, action, middlewares[]]
    private array $routes = [];

    // Parâmetros extraídos da URL (ex.: {id} => 5)
    private array $params = [];

    /**
     * Registra uma rota para o método GET.
     *
     * @param  string    $pattern     Padrão de URL (ex.: '/clients/{id}/edit')
     * @param  string    $controller  Nome da classe Controller (sem namespace)
     * @param  string    $action      Nome do método a chamar
     * @param  array     $middlewares Lista de classes Middleware a executar antes
     */
    public function get(string $pattern, string $controller, string $action, array $middlewares = []): void
    {
        $this->addRoute('GET', $pattern, $controller, $action, $middlewares);
    }

    /**
     * Registra uma rota para o método POST.
     */
    public function post(string $pattern, string $controller, string $action, array $middlewares = []): void
    {
        $this->addRoute('POST', $pattern, $controller, $action, $middlewares);
    }

    /**
     * Armazena a rota no array interno após converter o padrão
     * (com {param}) em uma expressão regular válida.
     */
    private function addRoute(string $method, string $pattern, string $controller, string $action, array $middlewares): void
    {
        // Converte parâmetros de rota como {id} em grupos de captura regex: (\d+) ou ([^/]+)
        // {id}   → captura apenas números (INT UNSIGNED)
        // {slug} → captura qualquer string sem barra
        $regex = preg_replace('/\{id\}/', '(\d+)', $pattern);
        $regex = preg_replace('/\{[a-z_]+\}/', '([^/]+)', $regex);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'      => strtoupper($method),
            'pattern'     => $regex,
            'original'    => $pattern, // guardamos o original para extrair os nomes dos params
            'controller'  => $controller,
            'action'      => $action,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * Processa a requisição atual: extrai a URI, encontra a rota
     * correspondente, executa os middlewares e chama o Controller.
     *
     * Chamado uma vez em public/index.php após registrar todas as rotas.
     */
    public function dispatch(): void
    {
        // Método HTTP da requisição (GET, POST)
        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        // URI sem a query string (ex.: '/clients/5/edit')
        $uri = strtok($_SERVER['REQUEST_URI'], '?');

        // Remove o prefixo do APP_URL para obter apenas o caminho relativo.
        // Ex.: '/crm/public/clients' => '/clients'
        $basePath = parse_url(APP_URL, PHP_URL_PATH);
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        // Garante que a URI começa com / e remove barras duplas
        $uri = '/' . ltrim($uri, '/');

        // Percorre as rotas registradas em busca de uma correspondência
        foreach ($this->routes as $route) {
            // Verifica método HTTP e faz o match do padrão regex
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                // $matches[0] é a URI completa; os demais são os grupos de captura (parâmetros)
                array_shift($matches);
                $this->params = $matches;

                // Extrai os nomes dos parâmetros do padrão original (ex.: {id}, {slug})
                preg_match_all('/\{([a-z_]+)\}/', $route['original'], $paramNames);
                $namedParams = [];
                foreach ($paramNames[1] as $i => $name) {
                    $namedParams[$name] = $matches[$i] ?? null;
                }

                // Executa middlewares em cadeia (verificação de autenticação, CSRF, etc.)
                foreach ($route['middlewares'] as $middlewareClass) {
                    $fullClass = 'Core\\Middleware\\' . $middlewareClass;
                    (new $fullClass())->handle();
                }

                // Instancia o Controller e chama o método (action) correspondente
                $controllerClass = 'App\\Controllers\\' . $route['controller'];
                $controller = new $controllerClass();

                // Passa os parâmetros nomeados como argumento do método
                $controller->{$route['action']}($namedParams);
                return;
            }
        }

        // Nenhuma rota encontrou correspondência → 404
        $this->notFound();
    }

    /**
     * Resposta padrão para rotas não encontradas.
     */
    private function notFound(): void
    {
        http_response_code(404);
        $viewFile = VIEW_PATH . DS . 'errors' . DS . '404.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '<h1>404 — Página não encontrada</h1>';
        }
        exit;
    }
}
