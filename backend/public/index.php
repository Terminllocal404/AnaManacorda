<?php

declare(strict_types=1);

use App\Core\Autoload;
use App\Core\Config;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\RateLimiter;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Services\LogService;

$root = dirname(__DIR__);

/* ---------------------------------------------------------------------------
 | 1. Autoload
 |
 | O autoloader proprio (PSR-4) carrega as classes App\* e funciona mesmo sem
 | o Composer. O vendor/autoload.php, quando presente, fornece as bibliotecas
 | externas: PHPMailer e o SDK oficial do Mercado Pago (mercadopago/dx-php).
 --------------------------------------------------------------------------- */
require $root . '/core/Autoload.php';
Autoload::register();
require $root . '/helpers/functions.php';

$vendor = $root . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
}

/* ---------------------------------------------------------------------------
 | 2. Ambiente e configuracao
 --------------------------------------------------------------------------- */
Env::load($root . '/.env');
date_default_timezone_set((string) (Config::get('app.timezone') ?: 'America/Sao_Paulo'));

// A API sempre responde em JSON: erros do PHP nunca devem ser impressos.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* ---------------------------------------------------------------------------
 | 3. Sessao e requisicao
 --------------------------------------------------------------------------- */
Auth::start();
$request = new Request();

/* ---------------------------------------------------------------------------
 | 4. CORS + cabecalhos de seguranca + preflight
 --------------------------------------------------------------------------- */
(new CorsMiddleware())->handle($request);
(new SecurityHeadersMiddleware())->handle($request);
if ($request->method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ---------------------------------------------------------------------------
 | 5. Rate limiting global (por IP)
 --------------------------------------------------------------------------- */
$rl     = Config::get('app.rate_limit', ['max' => 120, 'window' => 60]);
$bucket = RateLimiter::hit('ip:' . $request->ip(), (int) $rl['max'], (int) $rl['window']);

if (!$bucket['allowed']) {
    if (!headers_sent()) {
        header('Retry-After: ' . $bucket['retry_after']);
    }
    Response::error('Muitas requisicoes. Aguarde alguns instantes e tente novamente.', 429);
    exit;
}

/* ---------------------------------------------------------------------------
 | 6. Protecao CSRF
 |
 | Metodos seguros (GET/HEAD/OPTIONS) sao isentos. O webhook do Mercado Pago
 | tambem e isento: sua autenticidade e garantida pela assinatura HMAC.
 --------------------------------------------------------------------------- */
$metodosSeguros = ['GET', 'HEAD', 'OPTIONS'];
$ehWebhook      = $request->path() === '/api/webhooks/mercadopago';

if (!in_array($request->method(), $metodosSeguros, true) && !$ehWebhook) {
    if (!Csrf::validate($request)) {
        Response::error('Token CSRF invalido ou ausente.', 419);
        exit;
    }
}

/* ---------------------------------------------------------------------------
 | 7. Rotas
 --------------------------------------------------------------------------- */
$router = new Router();
(require $root . '/routes/web.php')($router);
(require $root . '/routes/api.php')($router);

/* ---------------------------------------------------------------------------
 | 8. Despacho com tratamento centralizado de erros
 --------------------------------------------------------------------------- */
try {
    $router->dispatch($request);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode());
} catch (\Throwable $e) {
    error_log('[ana-manacorda] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    try {
        (new LogService())->erro('erro_nao_tratado', Auth::id(), $request->ip(), $e->getMessage());
    } catch (\Throwable) {
        // O registro de log jamais deve interromper a resposta de erro.
    }

    Response::error(
        Config::get('app.debug') ? $e->getMessage() : 'Erro interno do servidor.',
        500
    );
}
