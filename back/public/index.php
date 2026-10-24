<?php
declare(strict_types=1);

// Front controller : toutes les requetes y arrivent via .htaccess.

define('APP_ROOT', dirname(__DIR__));

// Autoload PSR-4 : App\ -> src/
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = APP_ROOT . '/src/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/config/config.php';

use App\Core\Log\AccessLog;
use App\Core\Log\ErrorHandler;
use App\Core\Log\Log;
use App\Core\Response;
use App\Core\Router;

ErrorHandler::register();
AccessLog::register();

header('X-Request-Id: ' . Log::requestId());
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// CORS : CORS_ORIGIN = origines autorisees, separees par des virgules.
$allowedOrigins = array_filter(array_map('trim', explode(',', (string) getenv('CORS_ORIGIN'))));
$requestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Max-Age: 600');
    header('Access-Control-Expose-Headers: X-Request-Id');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    Response::noContent();
}

$router = new Router();
require APP_ROOT . '/routes/api.php';

try {
    $router->dispatch(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
    );
} catch (\PDOException $e) {
    // A traiter avant RuntimeException (classe parente) : getCode() y est un
    // SQLSTATE string ('42S22'), qui passerait le test 4xx ci-dessous.
    Log::error('Database error', ['exception' => $e]);
    Response::error('INTERNAL_ERROR', 'Something went wrong', 500);
} catch (\RuntimeException $e) {
    // Code 4xx = erreur de validation d'entree (ex. Request::json()).
    $code = (int) $e->getCode();
    if ($code >= 400 && $code < 500) {
        Response::error('BAD_REQUEST', $e->getMessage(), $code);
    }
    Log::error('Unhandled exception', ['exception' => $e]);
    Response::error('INTERNAL_ERROR', 'Something went wrong', 500);
} catch (\Throwable $e) {
    // Jamais exposer la stack ni le message au client, meme en debug.
    Log::error('Unhandled exception', ['exception' => $e]);
    Response::error('INTERNAL_ERROR', 'Something went wrong', 500);
}
