<?php
declare(strict_types=1);

use App\Controller\EventController;
use App\Controller\TransportController;
use App\Core\Http\RequireAuth;

/** @var \App\Core\Router $router */

$router->get('/events',          [EventController::class, 'findMany']);
$router->get('/events/{id:\d+}', [EventController::class, 'findOne']);
$router->post('/events',            [EventController::class, 'create'], [RequireAuth::class]);
$router->patch('/events/{id:\d+}',  [EventController::class, 'update'], [RequireAuth::class]);
$router->delete('/events/{id:\d+}', [EventController::class, 'delete'], [RequireAuth::class]);

// D8 Transports : accessible sans auth ; si l'appelant est connecte, l'ecole est utilisee comme origine.
$router->get('/events/{id:\d+}/transport', [TransportController::class, 'forEvent']);
