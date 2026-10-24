<?php
declare(strict_types=1);

// Lance par ErrorHandlerTest dans un processus a part : handleException() termine par exit.
//   php throw.php http|pdo

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/autoload.php';

App\Core\Log\ErrorHandler::register();

match ($argv[1] ?? '') {
    'http' => throw new App\Core\HttpException(409, 'CONFLICT', 'Already registered', ['eventId' => 3]),
    'pdo'  => throw new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'secret_col'"),
    default => throw new LogicException('Usage: throw.php http|pdo'),
};
