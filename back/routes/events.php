<?php
declare(strict_types=1);

use App\Controllers\EventController;

/** @var \App\Core\Router $router */

$router->get('/api/events',          [EventController::class, 'index']);
$router->get('/api/events/{id:\d+}', [EventController::class, 'show']);
