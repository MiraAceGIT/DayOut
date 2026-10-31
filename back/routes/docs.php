<?php
declare(strict_types=1);

use App\Controller\DocsController;

/** @var \App\Core\Router $router */

$router->get('/docs',         [DocsController::class, 'ui']);
$router->get('/openapi.json', [DocsController::class, 'spec']);
