<?php

declare(strict_types=1);

use App\Controllers\ProductController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(ProductController::class);
    $auth = $container->get(AuthMiddleware::class);
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/products', function ($group) use ($controller, $managementRoles) {
        $group->get('', [$controller, 'index']);
        $group->get('/expiring', [$controller, 'expiring']);
        $group->get('/low-stock', [$controller, 'lowStock']);
        $group->get('/{id}', [$controller, 'show']);

        $group->post('', [$controller, 'create'])->add(new RoleMiddleware($managementRoles));
        $group->put('/{id}', [$controller, 'update'])->add(new RoleMiddleware($managementRoles));
        $group->delete('/{id}', [$controller, 'delete'])->add(new RoleMiddleware($managementRoles));
    })->add($auth);
};
