<?php

/**
 * Award Routes (v1 API)
 */

use App\Controllers\AwardController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    $awardController = $app->getContainer()->get(AwardController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);

    // Public routes (no auth required)
    $app->group('/v1/awards', function ($group) use ($awardController) {
        // List all awards
        $group->get('', [$awardController, 'index']);
        
        // Get featured awards
        $group->get('/featured', [$awardController, 'featured']);
        
        // Search awards
        $group->get('/search', [$awardController, 'search']);
        
        // Get single award details
        $group->get('/{id}', [$awardController, 'show']);
        
        // Get award leaderboard
        $group->get('/{id}/leaderboard', [$awardController, 'leaderboard']);
    });

    // Protected routes (auth required - organizer or admin)
    $app->group('/v1/awards', function ($group) use ($awardController) {
        // Create new award
        $group->post('', [$awardController, 'create']);
        
        // Update award
        $group->put('/{id}', [$awardController, 'update']);
        
        // Toggle show_results
        $group->put('/{id}/toggle-results', [$awardController, 'toggleShowResults']);
        
        // Submit award for approval (draft -> pending)
        $group->put('/{id}/submit-for-approval', [$awardController, 'submitForApproval']);
        
        // Delete award
        $group->delete('/{id}', [$awardController, 'delete']);
    })->add($authMiddleware);
};
