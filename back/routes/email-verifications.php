<?php
declare(strict_types=1);

use App\Controller\EmailVerificationController;

/** @var \App\Core\Router $router */
$router->post('/email-verifications', [EmailVerificationController::class, 'create']);
