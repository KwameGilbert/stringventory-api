<?php

/**
 * Attendee Routes (v1 API)
 */

use App\Controllers\AttendeeController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller and middleware from container
    $attendeeController = $app->getContainer()->get(AttendeeController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);

    // Attendee routes (Protected)
    $app->group('/v1/attendees', function ($group) use ($attendeeController) {
        // Current user's profile routes (must be before /{id} to avoid conflict)
        $group->get('/me', [$attendeeController, 'getMyProfile']);
        $group->put('/me', [$attendeeController, 'updateMyProfile']);
        $group->post('/me/image', [$attendeeController, 'uploadProfileImage']);

        // Standard CRUD routes
        $group->get('', [$attendeeController, 'index']);
        $group->get('/{id}', [$attendeeController, 'show']);
        $group->post('', [$attendeeController, 'create']);
        $group->put('/{id}', [$attendeeController, 'update']);
        $group->delete('/{id}', [$attendeeController, 'delete']);
    })->add($authMiddleware);
};
