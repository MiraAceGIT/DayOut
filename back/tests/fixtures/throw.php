<?php
declare(strict_types=1);

// Lance par ErrorHandlerTest dans un processus a part : les filets de securite
// d'ErrorHandler n'agissent qu'en fin de script.
//   php throw.php uncaught|fatal

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/autoload.php';

App\Core\Log\Log::configure(App\Core\Log\Level::Info, (string) getenv('LOG_PATH'));
App\Core\Log\ErrorHandler::register();
ini_set('display_errors', '0');

switch ($argv[1] ?? '') {
    case 'uncaught':
        // Exception levee hors du Kernel (ex. config invalide au demarrage).
        throw new RuntimeException("Missing environment variable(s): DB_HOST (secret_detail)");
    case 'fatal':
        // E_ERROR "Maximum execution time" : invisible pour set_error_handler.
        echo 'partial output that must be discarded';
        set_time_limit(1);
        while (true) {
        }
    default:
        throw new LogicException('Usage: throw.php uncaught|fatal');
}
