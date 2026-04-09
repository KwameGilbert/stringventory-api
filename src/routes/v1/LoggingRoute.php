<?php

declare(strict_types=1);

use App\Controllers\LoggingController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(LoggingController::class);
    $auth = $container->get(AuthMiddleware::class);
    $adminRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/logs', function ($group) use ($controller, $adminRoles) {
        // Get logs with filtering
        $group->get('', [$controller, 'index']);

        // Get log statistics
        $group->get('/stats', [$controller, 'stats']);

        // Get available log levels
        $group->get('/levels', [$controller, 'levels']);

        // Get specific log entry
        $group->get('/{id}', [$controller, 'show']);

        // Clean old logs (admin only)
        $group->post('/clean', [$controller, 'clean'])->add(new RoleMiddleware($adminRoles));
    })->add($auth);
};