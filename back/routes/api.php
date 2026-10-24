<?php
declare(strict_types=1);

// Charge un fichier de routes par ressource (routes/<ressource>.php).
//   $router->get('/chemin/{id:\d+}', [Controller::class, 'action']);   // param regex
//   $router->post('/chemin', [...], [AuthMiddleware::class]);         // avec middleware

/** @var \App\Core\Router $router */

foreach (glob(__DIR__ . '/*.php') as $file) {
    if ($file !== __FILE__) {
        require_once $file;
    }
}
