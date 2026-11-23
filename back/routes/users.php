<?php
declare(strict_types=1);

use App\Controller\Middleware\RegistrationRateLimit;
use App\Controller\UserController;

/** @var \App\Core\Router $router */
$router->post('/users', [UserController::class, 'create'], [RegistrationRateLimit::class]);
