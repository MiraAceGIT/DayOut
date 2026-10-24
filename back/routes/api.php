<?php
declare(strict_types=1);


/** @var \App\Core\Router $router */

foreach (glob(__DIR__ . '/*.php') as $file) {
    if ($file !== __FILE__) {
        require_once $file;
    }
}
