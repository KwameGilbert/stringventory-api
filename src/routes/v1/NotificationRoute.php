<?php

/**
 * Notification Routes (v1 API)
 */

use App\Controllers\NotificationController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $notificationController = $app->getContainer()->get(NotificationController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    // Notification routes (Protected)
    $app->group('/v1/notifications', function ($group) use ($notificationController) {
        $group->get('', [$notificationController, 'index']);
        $group->post('/subscribe', [$notificationController, 'subscribe']);
        $group->delete('/unsubscribe', [$notificationController, 'unsubscribe']);
        $group->post('/read-all', [$notificationController, 'markAllAsRead']);
        $group->delete('/delete-all', [$notificationController, 'deleteAll']);
        $group->post('/{id}/read', [$notificationController, 'markAsRead']);
        $group->delete('/{id}', [$notificationController, 'delete']);
    })->add($authMiddleware);
};
