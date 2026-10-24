<?php
declare(strict_types=1);

// Une variable deja definie dans l'environnement l'emporte sur le .env (ex. DB_NAME=dayout_test pour les tests).

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

$config = App\Core\Config::fromEnv(APP_ROOT);

date_default_timezone_set($config->timezone);

if ($config->debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

App\Core\Log\Log::configure(
    $config->logLevel,
    $config->logPath,
    $config->logRetentionDays,
    $config->auditRetentionDays,
    [$config->dbPassword],
);
App\Core\Database::configure($config);

return $config;
