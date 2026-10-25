<?php
declare(strict_types=1);

// Ce que public/index.php et tests/run.php definissent avant de charger le reste.
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/autoload.php';
