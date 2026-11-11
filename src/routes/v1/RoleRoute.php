<?php

declare(strict_types=1);

use App\Controllers\RoleController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(RoleController::class);
    $auth = $container->get(AuthMiddleware::class);

    $app->group('/v1/roles', function ($group) use ($controller) {
        $group->get('', [$controller, 'index']);
        $group->get('/{id}', [$controller, 'show']);
    })->add($auth);
};
