<?php

declare(strict_types=1);

use App\Controllers\TransactionController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(TransactionController::class);
    $auth = $container->get(AuthMiddleware::class);
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/transactions', function ($group) use ($controller) {
        $group->get('', [$controller, 'index']);
        $group->get('/{id}', [$controller, 'show']);
    })->add(new RoleMiddleware($managementRoles))->add($auth);
};
