<?php

/**
 * Event Routes (v1 API)
 */

use App\Controllers\EventController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $eventController = $app->getContainer()->get(EventController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);

    // Public event routes (no auth required)
    $app->group('/v1/events', function ($group) use ($eventController) {
        // Get all published events (with filters)
        // Query Params: ?status={status}&event_type_id={id}&organizer_id={id}&category={slug}&search={query}&location={location}&upcoming=true&page={page}&per_page={limit}
        $group->get('', [$eventController, 'index']);

        // Get featured events for homepage
        // Query Params: ?limit={limit}
        $group->get('/featured', [$eventController, 'featured']);

        // Get all event types (categories) for dropdowns
        $group->get('/types', [$eventController, 'getEventTypes']);

        // Search events
        // Query Params: ?query={query}
        $group->get('/search', [$eventController, 'search']);

        // Increment event views
        $group->post('/{id}/view', [$eventController, 'incrementViews']);

        // Get single event by ID or slug
        $group->get('/{id}', [$eventController, 'show']);
    });

    // Protected event routes (auth required)
    $app->group('/v1/events', function ($group) use ($eventController) {
        $group->post('', [$eventController, 'create']);
        $group->put('/{id}', [$eventController, 'update']);
        // POST endpoint for update with file uploads (multipart/form-data doesn't work well with PUT)
        $group->post('/{id}', [$eventController, 'update']);
        $group->put('/{id}/submit-for-approval', [$eventController, 'submitForApproval']);
        $group->delete('/{id}', [$eventController, 'delete']);
    })->add($authMiddleware);
};
