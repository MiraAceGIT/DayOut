<?php
declare(strict_types=1);

// =============================================================================
// Table de routage de l'API.
// $router est injecte depuis public/index.php.
//
// Ajout d'une route :
//   $router->METHODE('/chemin/{param}', [Controller::class, 'action']);
//   $router->METHODE('/chemin/{id:\d+}', [...]);                 // param typee
//   $router->METHODE('/chemin', [...], [AuthMiddleware::class]); // avec middleware
// =============================================================================

use App\Controllers\EventController;
use App\Controllers\HealthController;

/** @var \App\Core\Router $router */

$router->get('/api/health',          [HealthController::class, 'index']);

$router->get('/api/events',          [EventController::class, 'index']);
$router->get('/api/events/{id:\d+}', [EventController::class, 'show']);
