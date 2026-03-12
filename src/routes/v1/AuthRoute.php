<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\PasswordResetController;
use App\Middleware\AuthMiddleware;
use Slim\App;

/**
 * Authentication Routes
 * 
 * All authentication-related endpoints
 * Prefix: /v1/auth
 */

return function (App $app) {
    // Get controllers from container
    $authController = $app->getContainer()->get(AuthController::class);
    $passwordResetController = $app->getContainer()->get(PasswordResetController::class);

    // Public routes (no authentication required)
    $app->post('/v1/auth/register', [$authController, 'register']);
    $app->post('/v1/auth/login', [$authController, 'login']);
    $app->post('/v1/auth/refresh', [$authController, 'refresh']);

    // Password reset routes (public)
    $app->post('/v1/auth/password/forgot', [$passwordResetController, 'requestReset']);
    $app->post('/v1/auth/password/reset', [$passwordResetController, 'resetPassword']);

    // Email verification routes (public)
    $app->get('/v1/auth/verify-email', [$authController, 'verifyEmail']);
    $app->post('/v1/auth/resend-verification', [$authController, 'resendVerificationEmail']);

    // Protected routes (authentication required)
    $app->group('/v1/auth', function ($group) use ($authController) {
        $group->get('/me', [$authController, 'me']);
        $group->post('/logout', [$authController, 'logout']);
        $group->post('/password/change', [$authController, 'changePassword']);
    })->add($app->getContainer()->get(AuthMiddleware::class));
};
