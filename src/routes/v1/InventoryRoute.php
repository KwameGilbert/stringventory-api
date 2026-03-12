<?php

declare(strict_types=1);

use App\Controllers\InventoryController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(InventoryController::class);
    $auth = $container->get(AuthMiddleware::class);
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/inventory', function ($group) use ($controller, $managementRoles) {
        $group->get('', [$controller, 'index']);
        $group->get('/{id}', [$controller, 'show']);

        $group->post('/adjust', [$controller, 'adjust'])->add(new RoleMiddleware($managementRoles));
    })->add($auth);
};
