<?php

declare(strict_types=1);

use App\Controllers\PurchaseController;
use Slim\Routing\RouteCollectorProxy;

return function ($app) {
    $auth = $app->getContainer()->get(\App\Middleware\AuthMiddleware::class);

    $app->group('/v1/purchases', function (RouteCollectorProxy $group) {
        $group->get('', [PurchaseController::class, 'index']);
        $group->get('/{id}', [PurchaseController::class, 'show']);
        $group->post('', [PurchaseController::class, 'create']);
        $group->post('/{id}/approve', [PurchaseController::class, 'approve']);
        $group->put('/{id}', [PurchaseController::class, 'update']);
        $group->delete('/{id}', [PurchaseController::class, 'delete']);
    })->add($auth);
};
