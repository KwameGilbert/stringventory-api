<?php

declare(strict_types=1);

use Slim\App;
use App\Controllers\AnalyticsController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/**
 * Analytics Routes
 * 
 * Defines all endpoints for reporting and analytics
 */
return function (App $app): void {
    $app->group('/v1/analytics', function ($group) {
        $group->get('/dashboard', AnalyticsController::class . ':getDashboardOverview');
        $group->get('/sales-report', AnalyticsController::class . ':getSalesReport');
        $group->get('/inventory-report', AnalyticsController::class . ':getInventoryReport');
        $group->get('/financial-report', AnalyticsController::class . ':getFinancialReport');
        $group->get('/customer-report', AnalyticsController::class . ':getCustomerReport');
        $group->get('/expense-report', AnalyticsController::class . ':getExpenseReport');
        $group->get('/activity-logs', AnalyticsController::class . ':getActivityLogs');
        $group->get('/export/{reportType}', AnalyticsController::class . ':exportReport');
    })->add(AuthMiddleware::class);
};
