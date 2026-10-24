<?php
declare(strict_types=1);

// $router->get('/chemin/{param}', [Controller::class, 'action']);
// $router->get('/chemin/{id:\d+}', [...]);                 // param regex
// $router->post('/chemin', [...], [AuthMiddleware::class]); // avec middleware

use App\Controllers\EventController;
use App\Controllers\HealthController;

/** @var \App\Core\Router $router */

$router->get('/api/health',          [HealthController::class, 'index']);

$router->get('/api/events',          [EventController::class, 'index']);
$router->get('/api/events/{id:\d+}', [EventController::class, 'show']);
