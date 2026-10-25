<?php
declare(strict_types=1);

use App\Controllers\DocsController;

/** @var \App\Core\Router $router */

$router->get('/api/docs',         [DocsController::class, 'ui']);
$router->get('/api/openapi.json', [DocsController::class, 'spec']);
