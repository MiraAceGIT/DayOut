<?php
declare(strict_types=1);

// .env minimal (KEY=VALUE, pas d'interpolation) pousse dans getenv()/$_ENV.
// Une variable deja definie dans l'environnement l'emporte (ex. DB_NAME=dayout_test pour les tests).

$envFile = APP_ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if (getenv($k) !== false) {
            continue;
        }
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}

// UTC par defaut, comme la session MySQL.
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

if (getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}
