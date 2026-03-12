<?php

/**
 * Event Image Routes (v1 API)
 */

use App\Controllers\EventImageController;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $imageController = $app->getContainer()->get(EventImageController::class);
    
    // Event Image routes
    $app->group('/v1/event-images', function ($group) use ($imageController) {
        // Query Params: ?event_id={id}
        $group->get('', [$imageController, 'index']);
        $group->post('', [$imageController, 'create']);
        $group->delete('/{id}', [$imageController, 'delete']);
    });
};
