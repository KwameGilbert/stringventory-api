<?php

declare(strict_types=1);

use App\Controllers\PurchaseController;
use Slim\Routing\RouteCollectorProxy;

return function ($app) {
    $app->group('/v1/purchases', function (RouteCollectorProxy $group) {
        $group->get('', [PurchaseController::class, 'index']);
        $group->get('/{id}', [PurchaseController::class, 'show']);
        $group->post('', [PurchaseController::class, 'create']);
        $group->put('/{id}', [PurchaseController::class, 'update']);
        $group->delete('/{id}', [PurchaseController::class, 'delete']);
    });
};
