<?php

/**
 * Utility Routes (v1 API)
 * Provides utility endpoints like image-to-base64 conversion
 */

use App\Controllers\EventController;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $eventController = $app->getContainer()->get(EventController::class);

    // Public utility routes (no auth required)
    $app->group('/v1/utils', function ($group) use ($eventController) {
        // Convert image URL to base64 (bypasses CORS)
        // Query Params: ?url={imageUrl}
        $group->get('/image-to-base64', [$eventController, 'imageToBase64']);
    });
};
