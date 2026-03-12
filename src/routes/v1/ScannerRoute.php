<?php

/**
 * Scanner Routes (v1 API)
 */

use App\Controllers\ScannerController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $scannerController = $app->getContainer()->get(ScannerController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    // Scanner routes (Protected)
    $app->group('/v1/scanners', function ($group) use ($scannerController) {
        $group->post('', [$scannerController, 'create']);
        $group->post('/assign', [$scannerController, 'assign']);
        $group->delete('/{id}', [$scannerController, 'delete']);
    })->add($authMiddleware);
};
