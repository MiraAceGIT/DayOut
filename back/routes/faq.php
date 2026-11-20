<?php
declare(strict_types=1);

use App\Controller\FaqController;
use App\Core\Http\RequireAuth;

/** @var \App\Core\Router $router */
$router->get('/faq',             [FaqController::class, 'findMany']);
$router->get('/faq/{id:\d+}',     [FaqController::class, 'findOne']);
$router->post('/faq',            [FaqController::class, 'create'], [RequireAuth::class]);
$router->patch('/faq/{id:\d+}',   [FaqController::class, 'update'], [RequireAuth::class]);
$router->delete('/faq/{id:\d+}',  [FaqController::class, 'delete'], [RequireAuth::class]);
