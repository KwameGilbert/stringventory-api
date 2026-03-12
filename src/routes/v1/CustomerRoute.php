<?php

declare(strict_types=1);

use App\Controllers\CustomerController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(CustomerController::class);
    $auth = $container->get(AuthMiddleware::class);
    $allRoles = [User::ROLE_CEO, User::ROLE_MANAGER, User::ROLE_SALESPERSON];
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/customers', function ($group) use ($controller, $managementRoles, $allRoles) {
        $group->get('', [$controller, 'index']);
        $group->get('/{id}', [$controller, 'show']);

        // Salespeople can create and update customers
        $group->post('', [$controller, 'create']);
        $group->put('/{id}', [$controller, 'update']);

        // Only management can delete customers
        $group->delete('/{id}', [$controller, 'delete'])->add(new RoleMiddleware($managementRoles));
    })->add($auth);
};
