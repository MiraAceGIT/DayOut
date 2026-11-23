<?php
declare(strict_types=1);

use App\Controller\CategoryController;

/** @var \App\Core\Router $router */
$router->get('/categories', [CategoryController::class, 'findMany']);
