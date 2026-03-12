<?php

declare(strict_types=1);

use App\Controllers\RefundController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(RefundController::class);
    $auth = $container->get(AuthMiddleware::class);
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/refunds', function ($group) use ($controller, $managementRoles) {
        $group->get('', [$controller, 'index'])->add(new RoleMiddleware($managementRoles));
        $group->post('', [$controller, 'create']);
        $group->put('/{id}/status', [$controller, 'updateStatus'])->add(new RoleMiddleware($managementRoles));
    })->add($auth);
};
