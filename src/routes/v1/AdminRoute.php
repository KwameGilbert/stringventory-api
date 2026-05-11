<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use Slim\App;

return function (App $app): void {
    $adminController = $app->getContainer()->get(AdminController::class);
    
    $app->group('/v1/admin', function ($group) use ($adminController) {
        $group->get('/health', [$adminController, 'systemHealth']);
        $group->get('/metrics', [$adminController, 'metrics']);
    });
};
