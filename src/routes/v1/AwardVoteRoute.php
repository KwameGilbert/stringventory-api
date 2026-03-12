<?php

/**
 * Award Vote Routes (v1 API)
 */

use App\Controllers\AwardVoteController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    $voteController = $app->getContainer()->get(AwardVoteController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);

    // Public routes (no auth required - voting is open to everyone)
    $app->group('/v1', function ($group) use ($voteController) {
        // Initiate a vote (create pending vote)
        $group->post('/votes/nominees/{nomineeId}', [$voteController, 'initiate']);

        // Confirm vote payment (callback from payment gateway)
        $group->post('/votes/confirm', [$voteController, 'confirmPayment']);

        // Get vote details by reference
        $group->get('/votes/reference/{reference}', [$voteController, 'getByReference']);

        // Get votes for a nominee
        // Query Params: ?status=pending|paid
        $group->get('/votes/nominees/{nomineeId}', [$voteController, 'getByNominee']);

        // Get votes for a category
        // Query Params: ?status=pending|paid
        $group->get('/votes/award-categories/{categoryId}', [$voteController, 'getByCategory']);

        // Get category leaderboard (public - for display)
        $group->get('/votes/award-categories/{categoryId}/leaderboard', [$voteController, 'getLeaderboard']);
    });

    // Protected routes (auth required - organizer/admin only)
    $app->group('/v1', function ($group) use ($voteController) {
        // Get all votes for an award (organizer only)
        // Query Params: ?status=pending|paid
        $group->get('/votes/awards/{awardId}', [$voteController, 'getByAward']);

        // Get comprehensive award vote statistics (organizer only)
        $group->get('/votes/awards/{awardId}/stats', [$voteController, 'getAwardStats']);
    })->add($authMiddleware);
};

