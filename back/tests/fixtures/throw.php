<?php
declare(strict_types=1);

// Processus a part (ErrorHandlerTest) : les filets d'ErrorHandler n'agissent qu'en fin de script.

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

App\Core\Log\Log::configure(App\Core\Log\Level::Info, (string) getenv('LOG_PATH'));
App\Core\Log\ErrorHandler::register();
ini_set('display_errors', '0');

switch ($argv[1] ?? '') {
    case 'uncaught':
        throw new RuntimeException("Missing environment variable(s): DB_HOST (secret_detail)");
    case 'fatal':
        // E_ERROR "Maximum execution time" : invisible pour set_error_handler.
        echo 'partial output that must be discarded';
        set_time_limit(1);
        while (true) { // @phpstan-ignore while.alwaysTrue (boucle voulue : declenche le timeout)
        }
    default:
        throw new LogicException('Usage: throw.php uncaught|fatal');
}
