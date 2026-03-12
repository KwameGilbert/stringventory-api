<?php

/**
 * User Routes (v1 API)
 * Demonstrates Eloquent ORM usage in Slim Framework
 */

use App\Controllers\UserController;
use Slim\App;

return function (App $app): void {
    // Get controller from container (allows for Dependency Injection)
    $userController = $app->getContainer()->get(UserController::class);
    
    // User routes
    $app->get('/v1/users', [$userController, 'index']);
    $app->get('/v1/users/{id}', [$userController, 'show']);
    $app->post('/v1/users', [$userController, 'create']);
    $app->put('/v1/users/{id}', [$userController, 'update']);
    $app->delete('/v1/users/{id}', [$userController, 'delete']);
};