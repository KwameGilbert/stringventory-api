<?php

declare(strict_types=1);

/**
 * Order Routes (v1 API)
 */

use App\Controllers\OrderController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\User;
use Slim\App;

return function (App $app): void {
    // Get controller from container
    $orderController = $app->getContainer()->get(OrderController::class);
    $authMiddleware = $app->getContainer()->get(AuthMiddleware::class);
    
    $managementRoles = [User::ROLE_CEO, User::ROLE_MANAGER];
    $allRoles = [User::ROLE_CEO, User::ROLE_MANAGER, User::ROLE_SALESPERSON];

    // Order routes (Protected)
    $app->group('/v1/orders', function ($group) use ($orderController, $managementRoles, $allRoles) {
        // Everyone can view list and single orders
        $group->get('', [$orderController, 'index']);
        $group->get('/{id}', [$orderController, 'show']);

        // Everyone can create an order (make a sale)
        $group->post('', [$orderController, 'create'])->add(new RoleMiddleware($allRoles));

        // Fulfill individual items
        $group->post('/item/{itemId}/fulfill', [$orderController, 'fulfillItem'])->add(new RoleMiddleware($allRoles));

        // Only management can cancel orders (reverses stock/transactions)
        $group->post('/{id}/cancel', [$orderController, 'cancel'])->add(new RoleMiddleware($managementRoles));

        // Bulk fulfillment
        $group->post('/{id}/fulfill', [$orderController, 'fulfill'])->add(new RoleMiddleware($allRoles));
    })->add($authMiddleware);
};
