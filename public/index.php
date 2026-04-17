<?php
// public/index.php — Front Controller (Único Ponto de Entrada)
// TODAS as requisições HTTP passam por aqui, graças ao .htaccess.

// Bootstrap: sessão, autoloader, helpers e configurações globais
require_once dirname(__DIR__) . '/core/bootstrap.php';

// Iniciando Infra
use Core\Router;
$router = new Router();

// Roteamento
require_once dirname(__DIR__) . '/config/routes.php';

// Despacho
$router->dispatch();
