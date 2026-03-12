<?php

/**
 * Order Routes (v1 API)
 */

use App\Controllers\OrderController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $orderController = $app->getContainer()->get(OrderController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    // Order routes (Protected)
    $app->group('/v1/orders', function ($group) use ($orderController) {
        $group->post('', [$orderController, 'create']);
        $group->get('', [$orderController, 'index']);
        $group->get('/{id}', [$orderController, 'show']);
        $group->post('/{id}/pay', [$orderController, 'initializePayment']);
        $group->get('/{id}/verify', [$orderController, 'verifyPayment']);
        $group->post('/{id}/cancel', [$orderController, 'cancel']);
    })->add($authMiddleware);

    // Public Paystack Webhook (no auth required - verified by signature)
    $app->post('/v1/payment/webhook', [$orderController, 'paystackWebhook']);
};
