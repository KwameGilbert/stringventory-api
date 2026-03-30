<?php

/**
 * Service Container Registration
 * 
 * Registers all services, controllers, and middleware with the DI container
 */

use App\Services\EmailService;
use App\Services\SMSService;
use App\Services\AuthService;
use App\Services\PasswordResetService;
use App\Services\VerificationService;
use App\Services\ExpenseService;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\OrganizerController;
use App\Controllers\PasswordResetController;
use App\Controllers\AttendeeController;
use App\Controllers\EventController;
use App\Controllers\EventImageController;
use App\Controllers\TicketTypeController;
use App\Controllers\OrderController;
use App\Controllers\TicketController;
use App\Controllers\ScannerController;
use App\Controllers\PosController;
use App\Controllers\AwardController;
use App\Controllers\AwardCategoryController;
use App\Controllers\AwardNomineeController;
use App\Controllers\AwardVoteController;
use App\Controllers\CategoryController;
use App\Controllers\SupplierController;
use App\Controllers\ExpenseCategoryController;
use App\Controllers\DiscountController;
use App\Controllers\ProductController;
use App\Controllers\CustomerController;
use App\Controllers\ExpenseController;
use App\Controllers\InventoryController;
use App\Controllers\RefundController;
use App\Controllers\PurchaseController;
use App\Controllers\ExpenseScheduleController;
use App\Controllers\TransactionController;
use App\Controllers\AuditLogController;
use App\Controllers\AnalyticsController;
use App\Controllers\NotificationController;
use App\Controllers\UnitOfMeasureController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\JsonBodyParserMiddleware;

return function ($container) {
    
    // ==================== SERVICES ====================
    
    $container->set(EmailService::class, function () {
        return new EmailService();
    });

    $container->set(SMSService::class, function () {
        return new SMSService();
    });
    
    $container->set(AuthService::class, function () {
        return new AuthService();
    });
    
    $container->set(PasswordResetService::class, function ($container) {
        return new PasswordResetService($container->get(EmailService::class));
    });
    
    $container->set(VerificationService::class, function ($container) {
        return new VerificationService($container->get(EmailService::class));
    });

    // Notification System Services
    $container->set(\App\Services\NotificationQueue::class, function () {
        return new \App\Services\NotificationQueue();
    });

    $container->set(\App\Services\WebPushService::class, function () {
        return new \App\Services\WebPushService();
    });

    $container->set(\App\Services\TemplateEngine::class, function () {
        return new \App\Services\TemplateEngine();
    });

    $container->set(\App\Services\UploadService::class, function () {
        return new \App\Services\UploadService();
    });

    $container->set(\App\Services\NotificationService::class, function ($container) {
        return new \App\Services\NotificationService(
            $container->get(EmailService::class),
            $container->get(SMSService::class),
            $container->get(\App\Services\NotificationQueue::class),
            $container->get(\App\Services\TemplateEngine::class),
            $container->get(\App\Services\WebPushService::class)
        );
    });

    $container->set(\Psr\Http\Message\ResponseFactoryInterface::class, function () {
        return new \Slim\Psr7\Factory\ResponseFactory();
    });

    $container->set(ExpenseService::class, function () {
        return new ExpenseService();
    });

    $container->set(\App\Services\CurrencyService::class, function () {
        return new \App\Services\CurrencyService();
    });
    
    // ==================== CONTROLLERS ====================
    
    $container->set(AuthController::class, function ($container) {
        return new AuthController(
            $container->get(AuthService::class),
            $container->get(VerificationService::class),
            $container->get(EmailService::class)
        );
    });
    
    $container->set(UserController::class, function ($container) {
        return new UserController(
            $container->get(VerificationService::class),
            $container->get(\App\Services\UploadService::class),
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(OrganizerController::class, function () {
        return new OrganizerController();
    });
    
    $container->set(PasswordResetController::class, function ($container) {
        return new PasswordResetController(
            $container->get(AuthService::class),
            $container->get(PasswordResetService::class)
        );
    });

    $container->set(OrderController::class, function ($container) {
        return new OrderController(
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(CategoryController::class, function ($container) {
        return new CategoryController(
            $container->get(\App\Services\UploadService::class)
        );
    });

    $container->set(SupplierController::class, function ($container) {
        return new SupplierController(
            $container->get(\App\Services\UploadService::class),
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(ExpenseCategoryController::class, function () {
        return new ExpenseCategoryController();
    });

    $container->set(DiscountController::class, function ($container) {
        return new DiscountController(
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(ProductController::class, function ($container) {
        return new ProductController(
            $container->get(\App\Services\UploadService::class),
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(CustomerController::class, function () {
        return new CustomerController();
    });

    $container->set(ExpenseController::class, function ($container) {
        return new ExpenseController(
            $container->get(\App\Services\NotificationService::class),
            $container->get(\App\Services\UploadService::class)
        );
    });

    $container->set(InventoryController::class, function ($container) {
        return new InventoryController(
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(RefundController::class, function ($container) {
        return new RefundController(
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(PurchaseController::class, function ($container) {
        return new PurchaseController(
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(ExpenseScheduleController::class, function ($container) {
        return new ExpenseScheduleController($container->get(ExpenseService::class));
    });

    $container->set(TransactionController::class, function () {
        return new TransactionController();
    });

    $container->set(AuditLogController::class, function () {
        return new AuditLogController();
    });

    $container->set(AnalyticsController::class, function () {
        return new AnalyticsController();
    });
    
    $container->set(\App\Controllers\SettingsController::class, function ($container) {
        return new \App\Controllers\SettingsController(
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(NotificationController::class, function ($container) {
        return new NotificationController(
            $container->get(\App\Services\WebPushService::class)
        );
    });

    $container->set(UnitOfMeasureController::class, function () {
        return new UnitOfMeasureController();
    });
    
    // ==================== MIDDLEWARES ====================
    
    $container->set(AuthMiddleware::class, function ($container) {
        return new AuthMiddleware($container->get(AuthService::class));
    });
    
    $container->set(RateLimitMiddleware::class, function () {
        return new RateLimitMiddleware();
    });
    
    $container->set(JsonBodyParserMiddleware::class, function () {
        return new JsonBodyParserMiddleware();
    });

    
    return $container;
};
