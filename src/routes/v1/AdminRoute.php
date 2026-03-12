<?php

/**
 * Admin Routes (v1 API)
 */

use App\Controllers\AdminController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller and middleware from container
    $adminController = $app->getContainer()->get(AdminController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);

    // Protected admin routes (require admin authentication)
    $app->group('/v1/admin', function ($group) use ($adminController) {
        // Dashboard
        $group->get('/dashboard', [$adminController, 'getDashboard']);

        // Users Management
        $group->get('/users', [$adminController, 'getUsers']);
        $group->get('/users/{id}', [$adminController, 'getUser']);
        $group->put('/users/{id}/status', [$adminController, 'updateUserStatus']);
        $group->post('/users/{id}/reset-password', [$adminController, 'resetUserPassword']);
        $group->put('/users/{id}/role', [$adminController, 'updateUserRole']);
        $group->delete('/users/{id}', [$adminController, 'deleteUser']);

        
        // Event Management (Full Admin Control)
        $group->get('/events', [$adminController, 'getEvents']);
        $group->get('/events/{id}', [$adminController, 'getEventDetail']);
        $group->put('/events/{id}', [$adminController, 'updateEventFull']);
        $group->put('/events/{id}/status', [$adminController, 'updateEventStatus']);
        $group->put('/events/{id}/feature', [$adminController, 'toggleEventFeatured']);
        $group->delete('/events/{id}', [$adminController, 'deleteEvent']);
        
        // Event Approvals
        $group->put('/events/{id}/approve', [$adminController, 'approveEvent']);
        $group->put('/events/{id}/reject', [$adminController, 'rejectEvent']);
        
        // Award Management (Full Admin Control)
        $group->get('/awards', [$adminController, 'getAwards']);
        $group->get('/awards/{id}', [$adminController, 'getAwardDetail']);
        $group->put('/awards/{id}', [$adminController, 'updateAwardFull']);
        $group->put('/awards/{id}/status', [$adminController, 'updateAwardStatus']);
        $group->put('/awards/{id}/feature', [$adminController, 'toggleAwardFeatured']);
        $group->delete('/awards/{id}', [$adminController, 'deleteAward']);
        
        // Award Approvals
        $group->put('/awards/{id}/approve', [$adminController, 'approveAward']);
        $group->put('/awards/{id}/reject', [$adminController, 'rejectAward']);

        // Finance Overview
        $group->get('/finance', [$adminController, 'getFinanceOverview']);

        // Analytics
        $group->get('/analytics', [$adminController, 'getAnalytics']);

        // Settings
        $group->get('/settings', [$adminController, 'getSettings']);
        $group->put('/settings', [$adminController, 'updateSettings']);

        // Note: Payout management routes (/payouts/*) are in PayoutRoute.php
    })->add($authMiddleware);
};

