<?php
declare(strict_types=1);

use App\Controller\CreatorController;

/** @var \App\Core\Router $router */

$router->get('/creators', [CreatorController::class, 'index']);
