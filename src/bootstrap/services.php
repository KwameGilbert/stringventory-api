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
use App\Middleware\AuthMiddleware;
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
            $container->get(\App\Services\TemplateEngine::class)
        );
    });

    $container->set(\Psr\Http\Message\ResponseFactoryInterface::class, function () {
        return new \Slim\Psr7\Factory\ResponseFactory();
    });
    
    // ==================== CONTROLLERS ====================
    
    $container->set(AuthController::class, function ($container) {
        return new AuthController($container->get(AuthService::class));
    });
    
    $container->set(UserController::class, function () {
        return new UserController();
    });

    $container->set(OrganizerController::class, function () {
        return new OrganizerController();
    });
    
    $container->set(PasswordResetController::class, function ($container) {
        return new PasswordResetController(
            $container->get(AuthService::class),
            $container->get(EmailService::class)
        );
    });

    $container->set(AttendeeController::class, function () {
        return new AttendeeController();
    });

    $container->set(EventController::class, function () {
        return new EventController();
    });

    $container->set(EventImageController::class, function ($container) {
        return new EventImageController(
            $container->get(\App\Services\UploadService::class)
        );
    });

    $container->set(TicketTypeController::class, function () {
        return new TicketTypeController();
    });

   $container->set(OrderController::class, function ($container) {
        return new OrderController(
            $container->get(\App\Services\NotificationService::class)
        );
    });

    $container->set(TicketController::class, function () {
        return new TicketController();
    });

    $container->set(ScannerController::class, function () {
        return new ScannerController();
    });

    $container->set(PosController::class, function () {
        return new PosController();
    });

    $container->set(AwardController::class, function ($container) {
        return new AwardController(
            $container->get(\App\Services\UploadService::class)
        );
    });

    $container->set(AwardCategoryController::class, function () {
        return new AwardCategoryController();
    });

    $container->set(AwardNomineeController::class, function ($container) {
        return new AwardNomineeController(
            $container->get(\App\Services\UploadService::class)
        );
    });

    $container->set(AwardVoteController::class, function () {
        return new AwardVoteController();
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
