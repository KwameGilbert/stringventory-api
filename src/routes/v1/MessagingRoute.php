<?php

declare(strict_types=1);

use App\Controllers\MessagingController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    $container = $app->getContainer();
    $controller = $container->get(MessagingController::class);
    $auth = $container->get(AuthMiddleware::class);
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];

    $app->group('/v1/messaging', function ($group) use ($controller) {
        $group->post('/bulk-messages', [$controller, 'bulkMessages']);
        $group->post('/messages', [$controller, 'sendMessage']);
        $group->get('/messages', [$controller, 'messages']);
        $group->get('/messages/{id}', [$controller, 'messageDetails']);
        $group->get('/templates', [$controller, 'templates']);
        $group->post('/templates', [$controller, 'createTemplate']);
        $group->put('/templates/{id}', [$controller, 'updateTemplate']);
        $group->delete('/templates/{id}', [$controller, 'deleteTemplate']);
    })->add(new RoleMiddleware($managementRoles))->add($auth);
};
