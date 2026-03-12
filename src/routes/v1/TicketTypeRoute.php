<?php

/**
 * Ticket Type Routes (v1 API)
 */

use App\Controllers\TicketTypeController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $ticketTypeController = $app->getContainer()->get(TicketTypeController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    // Ticket Type routes (Protected - mostly for organizers)
    $app->group('/v1/ticket-types', function ($group) use ($ticketTypeController) {
        $group->post('', [$ticketTypeController, 'create']);
        $group->put('/{id}', [$ticketTypeController, 'update']);
        $group->delete('/{id}', [$ticketTypeController, 'delete']);
    })->add($authMiddleware);

    // Public routes (Listing ticket types for an event)
    // Query Params: ?event_id={id}
    $app->get('/v1/ticket-types', [$ticketTypeController, 'index']);
    $app->get('/v1/ticket-types/{id}', [$ticketTypeController, 'show']);
};
