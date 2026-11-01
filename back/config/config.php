<?php
declare(strict_types=1);

// Une variable deja definie dans l'environnement l'emporte sur le .env (ex. DB_NAME=dayout_test pour les tests).

$envFile = APP_ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        $v = trim($v);
        if (preg_match('/^(["\'])(.*)\1$/', $v, $m) === 1) {
            $v = $m[2]; // entre guillemets : pris tel quel, `#` compris
        } else {
            $v = trim((string) preg_replace('/\s+#.*$/', '', $v)); // commentaire en fin de ligne
        }
        if (getenv($k) !== false) {
            continue;
        }
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}

$config = App\Core\Config::fromEnv(APP_ROOT);

date_default_timezone_set($config->timezone);

// Jamais display_errors, meme en debug : le texte de PHP casserait le JSON. Les erreurs sont dans error.log.
ini_set('display_errors', '0');
error_reporting($config->debug ? E_ALL : E_ALL & ~E_NOTICE & ~E_DEPRECATED);

App\Core\Log\Log::configure(
    $config->logLevel,
    $config->logPath,
    $config->logRetentionDays,
    $config->auditRetentionDays,
    [$config->dbPassword],
);
App\Core\Database::configure($config);

return $config;
