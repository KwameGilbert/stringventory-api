<?php

/**
 * Organizer Routes (v1 API)
 */

use App\Controllers\OrganizerController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller and middleware from container
    $organizerController = $app->getContainer()->get(OrganizerController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);

    // Public organizer routes
    $app->group('/v1/organizers', function ($group) use ($organizerController) {
        $group->get('', [$organizerController, 'index']);

        // Query Params: ?query={search_term}
        $group->get('/search', [$organizerController, 'search']);
        $group->get('/{id}', [$organizerController, 'show']);
    });

    // Protected organizer routes (require authentication)
    $app->group('/v1/organizers', function ($group) use ($organizerController) {
        // Dashboard - fetch all dashboard data in a single call
        $group->get('/data/dashboard', [$organizerController, 'getDashboard']);

        // Events - fetch all events for the organizer
        $group->get('/data/events', [$organizerController, 'getEvents']);

        // Event Details - fetch detailed data for a single event
        $group->get('/data/events/{id}', [$organizerController, 'getEventDetails']);

        // Orders - fetch all orders for organizer's events
        $group->get('/data/orders', [$organizerController, 'getOrders']);
        
        // Order Details - fetch single order details
        $group->get('/data/orders/{id}', [$organizerController, 'getOrderDetails']);

        // Awards - fetch all awards for the organizer
        $group->get('/data/awards', [$organizerController, 'getAwards']);
        
        // Award Details - fetch detailed data for a single award
        $group->get('/data/awards/{id}', [$organizerController, 'getAwardDetails']);

        // Attendees - fetch all attendees/ticket holders for organizer's events
        $group->get('/data/attendees', [$organizerController, 'getAttendees']);

        // Send bulk email to attendees
        $group->post('/data/attendees/send-email', [$organizerController, 'sendBulkEmail']);

        // Order management - resend confirmation and process refund
        $group->post('/data/orders/{orderId}/resend-confirmation', [$organizerController, 'resendOrderConfirmation']);
        $group->post('/data/orders/{orderId}/refund', [$organizerController, 'processOrderRefund']);

        // Finance - financial overview with combined events + awards revenue
        $group->get('/finance/overview', [$organizerController, 'getFinanceOverview']);
        
        // Finance - detailed events revenue
        $group->get('/finance/events', [$organizerController, 'getEventsRevenue']);
        
        // Finance - detailed awards revenue
        $group->get('/finance/awards', [$organizerController, 'getAwardsRevenue']);

        // Note: Payout routes (/finance/balance, /finance/payouts/*) are in PayoutRoute.php

        // CRUD operations
        $group->post('', [$organizerController, 'create']);
        $group->put('/{id}', [$organizerController, 'update']);
        $group->delete('/{id}', [$organizerController, 'delete']);
    })->add($authMiddleware);
};

