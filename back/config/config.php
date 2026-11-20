<?php
declare(strict_types=1);

// Une variable deja definie dans l'environnement l'emporte sur le .env (ex. DB_NAME=dayout_test pour les tests).
App\Core\Env::load(APP_ROOT . '/.env');

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

return $config;
