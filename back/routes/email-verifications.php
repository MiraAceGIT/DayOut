<?php
declare(strict_types=1);

use App\Controller\EmailVerificationController;
use App\Controller\Middleware\EmailVerificationRateLimit;

/** @var \App\Core\Router $router */
$router->post('/email-verifications', [EmailVerificationController::class, 'create'], [EmailVerificationRateLimit::class]);
