<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/vendor/autoload.php';

use App\Core\Http\Kernel;
use App\Core\Log\AccessLog;
use App\Core\Log\ErrorHandler;
use App\Core\Request;
use App\Core\Router;

// Avant la config : une config invalide doit aussi produire un 500 JSON journalise.
ErrorHandler::register();
AccessLog::register();

/** @var \App\Core\Config $config */
$config = require APP_ROOT . '/config/config.php';

$router = new Router();
require APP_ROOT . '/routes/api.php';
if ($config->docsEnabled) {
    require APP_ROOT . '/routes/docs.php';
}

(new Kernel($router, $config))
    ->handle(Request::fromGlobals())
    ->send();
