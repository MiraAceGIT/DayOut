<?php
declare(strict_types=1);

use App\Controller\FaqController;

/** @var \App\Core\Router $router */
$router->get('/faq', [FaqController::class, 'index']);
