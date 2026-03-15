<?php

declare(strict_types=1);

use Slim\App;
use App\Controllers\SettingsController;
use App\Middleware\AuthMiddleware;

/**
 * Settings Routes
 */
return function (App $app) {
    $app->group('/v1/settings', function ($group) {
        $group->get('/business', SettingsController::class . ':getBusinessSettings');
        $group->put('/business', SettingsController::class . ':updateBusinessSettings');
        
        $group->get('/notifications', SettingsController::class . ':getNotificationSettings');
        $group->put('/notifications', SettingsController::class . ':updateNotificationSettings');
        
        $group->get('/payment', SettingsController::class . ':getPaymentSettings');
        $group->put('/payment', SettingsController::class . ':updatePaymentSettings');
        
        $group->get('/api', SettingsController::class . ':getApiSettings');
        $group->post('/api/regenerate-key', SettingsController::class . ':regenerateApiKey');
    })->add(AuthMiddleware::class);
};
