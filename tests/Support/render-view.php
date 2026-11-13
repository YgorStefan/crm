<?php
// roda num processo proprio porque APP_NAME e APP_URL sao constantes, e o
// bootstrap dos testes ja as define com valores que nao exercitam o escape.
[, $viewFile, $appName, $appUrl] = $argv;

define('APP_NAME', $appName);
define('APP_URL', $appUrl);
define('CSP_NONCE', 'nonce-de-teste');

require $viewFile;
