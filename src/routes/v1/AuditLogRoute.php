<?php

declare(strict_types=1);

use App\Controllers\AuditLogController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(AuditLogController::class);
    $auth = $container->get(AuthMiddleware::class);
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/audit-logs', function ($group) use ($controller) {
        $group->get('', [$controller, 'index']);
    })->add(new RoleMiddleware($managementRoles))->add($auth);
};
