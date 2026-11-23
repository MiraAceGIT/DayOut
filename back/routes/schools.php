<?php
declare(strict_types=1);

use App\Controller\SchoolController;

/** @var \App\Core\Router $router */
$router->get('/schools', [SchoolController::class, 'findMany']);
