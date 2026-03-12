<?php

/**
 * Ticket Routes (v1 API)
 */

use App\Controllers\TicketController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $ticketController = $app->getContainer()->get(TicketController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    // Ticket routes (Protected)
    $app->group('/v1/tickets', function ($group) use ($ticketController) {
        $group->get('', [$ticketController, 'index']);
        $group->get('/{id}', [$ticketController, 'show']);
        $group->post('/admit', [$ticketController, 'admit']);
    })->add($authMiddleware);

    // Public Ticket Verification
    $app->post('/v1/tickets/verify', [$ticketController, 'verify']);
};
