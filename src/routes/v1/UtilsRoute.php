<?php

declare(strict_types=1);

use App\Controllers\UtilsController;
use Slim\App;

return function (App $app): void {
    $utilsController = $app->getContainer()->get(UtilsController::class);
    
    $app->group('/v1/utils', function ($group) use ($utilsController) {
        $group->get('/generate-token', [$utilsController, 'generateToken']);
        $group->get('/currencies', [$utilsController, 'currencies']);
    });
};
