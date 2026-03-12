<?php

/**
 * Payout Routes (v1 API)
 * 
 * All payout-related routes for both organizers and admins
 */

use App\Controllers\PayoutController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    $payoutController = $app->getContainer()->get(PayoutController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);

    // ==================== Organizer Payout Routes ====================
    $app->group('/v1/organizers/finance', function ($group) use ($payoutController) {
        // Balance and payout history
        $group->get('/balance', [$payoutController, 'getOrganizerBalance']);
        $group->get('/payouts', [$payoutController, 'getOrganizerPayouts']);
        
        // Request payouts
        $group->post('/payouts/events/{eventId}', [$payoutController, 'requestEventPayout']);
        $group->post('/payouts/awards/{awardId}', [$payoutController, 'requestAwardPayout']);
        
        // Cancel payout request
        $group->post('/payouts/{payoutId}/cancel', [$payoutController, 'cancelPayout']);
    })->add($authMiddleware);

    // ==================== Admin Payout Routes ====================
    // Note: Admin role verification is handled in the controller
    $app->group('/v1/admin/payouts', function ($group) use ($payoutController) {
        // View all payouts
        $group->get('', [$payoutController, 'getAllPayouts']);
        $group->get('/summary', [$payoutController, 'getPayoutSummary']);
        
        // Payout actions
        $group->post('/{payoutId}/approve', [$payoutController, 'approvePayout']);
        $group->post('/{payoutId}/reject', [$payoutController, 'rejectPayout']);
        $group->post('/{payoutId}/complete', [$payoutController, 'completePayout']);
    })->add($authMiddleware);
};
