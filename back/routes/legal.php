<?php declare(strict_types=1);

use App\Controller\LegalController;

/** @var \App\Core\Router $router */
$router->get('/legal/{type:cgu|mentions}', [LegalController::class, 'show']);