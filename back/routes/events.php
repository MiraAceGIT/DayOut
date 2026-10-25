<?php
declare(strict_types=1);

use App\Controllers\EventController;

/** @var \App\Core\Router $router */

$router->get('/events',          [EventController::class, 'index']);
$router->get('/events/{id:\d+}', [EventController::class, 'show']);
