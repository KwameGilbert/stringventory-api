<?php

/**
 * User Routes (v1 API)
 */

use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $userController = $app->getContainer()->get(UserController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];
    $ceoOnly = [User::ROLE_CEO];

    // User routes (Protected)
    $app->group('/v1/users', function ($group) use ($userController, $managementRoles, $ceoOnly) {
        // Management can list all users
        $group->get('', [$userController, 'index'])->add(new RoleMiddleware($managementRoles));
        
        // Users can view themselves, or management can view any user
        $group->get('/{id}', [$userController, 'show']);
        
        // Creating users is restricted to CEO and Managers
        $group->post('', [$userController, 'create'])->add(new RoleMiddleware($managementRoles));
        
        // Updating/Deleting has internal checks for self or CEO in the controller, 
        // but we can add an initial layer here.
        $group->put('/{id}', [$userController, 'update']);
        $group->delete('/{id}', [$userController, 'delete']);
    })->add($authMiddleware);
};