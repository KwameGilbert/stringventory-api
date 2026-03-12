<?php

/**
 * Award Nominee Routes (v1 API)
 */

use App\Controllers\AwardNomineeController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    $nomineeController = $app->getContainer()->get(AwardNomineeController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);

    // Public routes (no auth required)
    $app->group('/v1', function ($group) use ($nomineeController) {
        // List nominees by category
        // Query Params: ?include_stats=true|false
        $group->get('/nominees/award-categories/{categoryId}', [$nomineeController, 'index']);

        // List all nominees for an award
        // Query Params: ?include_stats=true|false
        $group->get('/nominees/awards/{awardId}', [$nomineeController, 'getByAward']);

        // Get single nominee details
        // Query Params: ?include_stats=true|false
        $group->get('/nominees/{id}', [$nomineeController, 'show']);

        // Get nominee statistics
        $group->get('/nominees/{id}/stats', [$nomineeController, 'getStats']);
    });

    // Protected routes (auth required - organizer/admin only)
    $app->group('/v1', function ($group) use ($nomineeController) {
        // Create new nominee (with image upload)
        $group->post('/nominees/award-categories/{categoryId}', [$nomineeController, 'create']);

        // Update nominee (with image upload)
        $group->put('/nominees/{id}', [$nomineeController, 'update']);
        // POST endpoint for update with file uploads (multipart/form-data doesn't work well with PUT)
        $group->post('/nominees/{id}', [$nomineeController, 'update']);

        // Delete nominee
        $group->delete('/nominees/{id}', [$nomineeController, 'delete']);

        // Reorder nominees
        $group->post('/nominees/award-categories/{categoryId}/reorder', [$nomineeController, 'reorder']);
    })->add($authMiddleware);
};
