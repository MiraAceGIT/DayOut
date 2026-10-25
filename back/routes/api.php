<?php
declare(strict_types=1);

// Les fichiers de routes utilisent $router (App\Core\Router), cree par public/index.php.
foreach (glob(__DIR__ . '/*.php') as $file) {
    if ($file !== __FILE__) {
        require_once $file;
    }
}
