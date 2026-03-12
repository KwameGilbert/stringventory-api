<?php

/**
 * POS Routes (v1 API)
 */

use App\Controllers\PosController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $posController = $app->getContainer()->get(PosController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    // POS routes (Protected)
    $app->group('/v1/pos', function ($group) use ($posController) {
        $group->post('', [$posController, 'create']);
        $group->post('/assign', [$posController, 'assign']);
        $group->delete('/{id}', [$posController, 'delete']);
    })->add($authMiddleware);
};
