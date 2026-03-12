<?php

declare(strict_types=1);

use App\Controllers\SupplierController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(SupplierController::class);
    $auth = $container->get(AuthMiddleware::class);
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/suppliers', function ($group) use ($controller, $managementRoles) {
        $group->get('', [$controller, 'index']);
        $group->get('/{id}', [$controller, 'show']);

        $group->post('', [$controller, 'create'])->add(new RoleMiddleware($managementRoles));
        $group->put('/{id}', [$controller, 'update'])->add(new RoleMiddleware($managementRoles));
        $group->delete('/{id}', [$controller, 'delete'])->add(new RoleMiddleware($managementRoles));
    })->add($auth);
};
