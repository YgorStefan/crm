<?php
/**
 * Teste de rate limiting via HTTP ao vivo.
 * Faz 6 tentativas de login com senha errada e verifica se a 6ª bloqueia.
 */
declare(strict_types=1);

$base     = 'http://localhost:8000';
$email    = 'smoke-ratelimit-http@example.com';
$password = 'senhaErrada123';
$slug     = 'slug-inexistente';

// --- Limpar tentativas anteriores ---
require_once __DIR__ . '/bootstrap.php';
$pdo = crm_smoke_pdo();
$pdo->prepare('DELETE FROM login_attempts WHERE email = :email')
    ->execute([':email' => $email]);
echo "Tentativas anteriores limpas.\n\n";

// --- Obter CSRF token da página de login ---
$loginPage = file_get_contents($base . '/login');
if (!$loginPage) {
    echo "ERRO: não foi possível acessar {$base}/login\n";
    exit(1);
}
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $loginPage, $m);
$csrf = $m[1] ?? '';
if (!$csrf) {
    echo "ERRO: CSRF token não encontrado na página de login\n";
    exit(1);
}

// Extrair cookie de sessão
preg_match('/Set-Cookie:\s*(crm_session=[^;]+)/i', implode("\n", $http_response_header ?? []), $ck);
$cookie = $ck[1] ?? '';

echo "CSRF token obtido: " . substr($csrf, 0, 10) . "...\n\n";

// --- Função para POST de login ---
function doLogin(string $base, string $email, string $password, string $slug, string $csrf, string $cookie): array
{
    $postData = http_build_query([
        '_csrf_token' => $csrf,
        'email'       => $email,
        'password'    => $password,
        'org_slug'    => $slug,
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'          => 'POST',
            'header'          => implode("\r\n", [
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($postData),
                $cookie ? 'Cookie: ' . $cookie : '',
            ]),
            'content'         => $postData,
            'follow_location' => 1,
            'ignore_errors'   => true,
        ],
    ]);

    $body    = file_get_contents($base . '/login', false, $ctx);
    $headers = $http_response_header ?? [];
    $status  = (int) substr($headers[0] ?? 'HTTP/1.1 200', 9, 3);

    return ['body' => (string) $body, 'status' => $status, 'headers' => $headers];
}

// --- 6 tentativas ---
$blocked = false;
for ($i = 1; $i <= 6; $i++) {
    $r = doLogin($base, $email, $password, $slug, $csrf, $cookie);

    $isBlocked = str_contains($r['body'], 'Muitas tentativas');
    $isRateMsg = preg_match('/Muitas tentativas.*Tente novamente em (\d+)/u', $r['body'], $rm);

    if ($isBlocked) {
        $minutos = $rm[1] ?? '?';
        echo "  Tentativa {$i}: BLOQUEADO — 'Muitas tentativas. Tente novamente em {$minutos} minuto(s).'\n";
        $blocked = true;
        break;
    } else {
        // Extrair mensagem de erro se houver
        preg_match('/<[^>]*class="[^"]*text-red[^"]*"[^>]*>([^<]+)</i', $r['body'], $err);
        $msg = trim(strip_tags($err[0] ?? '')) ?: '(sem mensagem de erro visível)';
        echo "  Tentativa {$i}: credencial inválida — {$msg}\n";
    }

    // Atualizar CSRF para próxima tentativa
    preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m2);
    if ($m2[1] ?? '') {
        $csrf = $m2[1];
    }
    usleep(100000); // 100ms entre tentativas
}

echo "\n";
if ($blocked) {
    echo "RESULTADO: OK — Rate limiting funcionando! Bloqueio ativado na tentativa 6.\n";
} else {
    echo "RESULTADO: FALHA — 6 tentativas sem bloqueio (verificar MAX_ATTEMPTS e wiring).\n";
}

// Limpar após teste
$pdo->prepare('DELETE FROM login_attempts WHERE email = :email')->execute([':email' => $email]);
echo "Tentativas de teste removidas da tabela.\n";
