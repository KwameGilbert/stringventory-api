<?php

declare(strict_types=1);

/**
 * Unit of Measure Routes (v1 API)
 */

use App\Controllers\UnitOfMeasureController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $unitController = $app->getContainer()->get(UnitOfMeasureController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];
    $allRoles = [User::ROLE_CEO, User::ROLE_MANAGER, User::ROLE_SALESPERSON];

    // Routes (Protected)
    $app->group('/v1/units-of-measure', function ($group) use ($unitController, $managementRoles, $allRoles) {
        $group->get('', [$unitController, 'index']);
        $group->post('', [$unitController, 'create'])->add(new RoleMiddleware($managementRoles));
        $group->put('/{id}', [$unitController, 'update'])->add(new RoleMiddleware($managementRoles));
        $group->delete('/{id}', [$unitController, 'delete'])->add(new RoleMiddleware($managementRoles));
    })->add($authMiddleware);
};
