<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\Discount;
use App\Models\Customer;
use App\Models\PurchaseItem;
use App\Helper\ResponseHelper;
use App\Services\NotificationService;
use App\Services\CurrencyService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

/**
 * OrderController
 * Handles inventory-based order creation and management for Stringventory.
 */
class OrderController
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all orders
     * GET /v1/orders
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $orders = Order::with(['customer', 'items.product', 'discount', 'transactions', 'creator'])->orderBy('createdAt', 'desc')->get();
            return ResponseHelper::success($response, 'Orders fetched successfully', $orders->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch orders', 500, $e->getMessage());
        }
    }

    /**
     * Get single order
     * GET /v1/orders/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $order = Order::with(['customer', 'items.product', 'discount', 'transactions', 'creator'])->find($args['id']);
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }
            return ResponseHelper::success($response, 'Order fetched successfully', $order->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch order', 500, $e->getMessage());
        }
    }

    /**
     * Create a new order (Checkout)
     * POST /v1/orders
     */
    public function create(Request $request, Response $response): Response
    {
        DB::beginTransaction();
        try {
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');

            if (empty($data['items']) || !is_array($data['items'])) {
                return ResponseHelper::error($response, 'Items are required', 400);
            }

            // 1. Calculate totals and validate stock
            $subtotal = 0;
            $itemsToProcess = [];

            foreach ($data['items'] as $item) {
                if (empty($item['productId']) || empty($item['quantity'])) {
                    throw new Exception('Invalid item data. Product ID and quantity are required.');
                }

                $product = Product::find($item['productId']);
                if (!$product || $product->status !== 'active') {
                    throw new Exception("Product ID {$item['productId']} is not available.");
                }

                // Check stock
                $inventory = Inventory::where('productId', $product->id)->first();
                $stock = $inventory ? $inventory->quantity : 0;
                if ($stock < $item['quantity']) {
                    throw new Exception("Insufficient stock for product: {$product->name}. Requested: {$item['quantity']}, Available: {$stock}");
                }

                $itemTotal = $product->sellingPrice * $item['quantity'];
                $subtotal += $itemTotal;

                $fulfilledAtStart = (int)($item['fulfilledQuantity'] ?? 0);
                if ($fulfilledAtStart < 0) {
                    $fulfilledAtStart = 0;
                }
                if ($fulfilledAtStart > $item['quantity']) {
                    $fulfilledAtStart = (int)$item['quantity'];
                }

                $itemsToProcess[] = [
                    'product' => $product,
                    'quantity' => (int)$item['quantity'],
                    'fulfilledQuantity' => $fulfilledAtStart,
                    'sellingPrice' => $product->sellingPrice,
                    'costPrice' => $product->costPrice,
                    'totalPrice' => $itemTotal
                ];
            }

            // 2. Handle Discount
            $discountAmount = 0;
            $discountId = null;
            $discountPercentage = null;
            $discountType = 'fixed';

            if (!empty($data['discountCode'])) {
                $discount = Discount::where('discountCode', $data['discountCode'])
                    ->where('status', 'active')
                    ->where(function ($query) {
                        $query->whereNull('startDate')->orWhere('startDate', '<=', date('Y-m-d H:i:s'));
                    })
                    ->where(function ($query) {
                        $query->whereNull('endDate')->orWhere('endDate', '>=', date('Y-m-d H:i:s'));
                    })
                    ->first();

                if ($discount) {
                    $discountId = $discount->id;
                    $discountType = $discount->discountType;
                    if ($discount->discountType === 'percentage') {
                        $discountPercentage = (float)$discount->discount;
                        $discountAmount = ($subtotal * $discountPercentage) / 100;
                    } else {
                        $discountAmount = (float)$discount->discountAmount;
                    }
                }
            }

            $discountedTotalPrice = $subtotal - $discountAmount;

            // 3. Determine Order Status
            $allFulfilled = true;
            $anyFulfilled = false;
            foreach ($itemsToProcess as $ip) {
                if ($ip['fulfilledQuantity'] < $ip['quantity']) {
                    $allFulfilled = false;
                }
                if ($ip['fulfilledQuantity'] > 0) {
                    $anyFulfilled = true;
                }
            }

            $orderStatus = 'pending';
            if ($allFulfilled) {
                $orderStatus = 'completed';
            } elseif ($anyFulfilled) {
                $orderStatus = 'partially_fulfilled';
            }

            // 4. Create Order
            $currency = CurrencyService::getCurrent();
            $order = Order::create([
                'orderNumber' => 'ORD-' . strtoupper(bin2hex(random_bytes(4))),
                'customerId' => $data['customerId'] ?? null,
                'createdBy' => $user ? $user->id : null,
                'status' => $orderStatus,
                'currency' => $currency,
                'discountId' => $discountId,
                'discountType' => $discountType,
                'discountPercentage' => $discountPercentage,
                'discountAmount' => $discountAmount,
                'discountedPrice' => $subtotal, // original price
                'discountedTotalPrice' => $discountedTotalPrice,
                'notes' => $data['notes'] ?? null,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ]);

            // 5. Create Order Items and Update Inventory
            foreach ($itemsToProcess as $processItem) {
                $itemStatus = 'pending';
                if ($processItem['fulfilledQuantity'] == $processItem['quantity']) {
                    $itemStatus = 'fulfilled';
                } elseif ($processItem['fulfilledQuantity'] > 0) {
                    $itemStatus = 'partial';
                }

                OrderItem::create([
                    'orderId' => $order->id,
                    'productId' => $processItem['product']->id,
                    'costPrice' => $processItem['costPrice'],
                    'sellingPrice' => $processItem['sellingPrice'],
                    'quantity' => $processItem['quantity'],
                    'fulfilledQuantity' => $processItem['fulfilledQuantity'],
                    'fulfillmentStatus' => $itemStatus,
                    'totalPrice' => $processItem['totalPrice']
                ]);

                // Update stock using FEFO (First Expired First Out)
                $this->deductFromBatches($processItem['product']->id, $processItem['quantity']);

                // Update global aggregate stock
                Inventory::where('productId', $processItem['product']->id)->decrement('quantity', $processItem['quantity']);
            }

            // 6. Record Transaction
            Transaction::create([
                'orderId' => $order->id,
                'transactionType' => 'order',
                'paymentMethod' => $data['paymentMethod'] ?? $data['payment_method'] ?? 'cash',
                'amount' => $discountedTotalPrice,
                'currency' => $currency,
                'status' => 'completed',
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            DB::commit();

            // Trigger notification for admins
            $this->notificationService->notifyAdmins(
                'order',
                'New Order Received',
                "A new order {$order->orderNumber} has been placed for a total of " . number_format((float)$discountedTotalPrice, 2),
                ['orderId' => $order->id, 'orderNumber' => $order->orderNumber, 'total' => $discountedTotalPrice]
            );

            // Also check for low stock on each item
            foreach ($itemsToProcess as $processItem) {
                $product = $processItem['product'];
                $inventory = Inventory::where('productId', $product->id)->first();
                if ($inventory && $inventory->quantity <= ($product->reorderLevel ?? 5)) {
                    $this->notificationService->notifyAdmins(
                        'stock_alert',
                        'Low Stock Alert',
                        "Product '{$product->name}' is running low on stock after order {$order->orderNumber}. Current quantity: {$inventory->quantity}.",
                        ['productId' => $product->id, 'quantity' => $inventory->quantity]
                    );
                }
            }

            return ResponseHelper::success($response, 'Order created successfully', $order->load(['items', 'creator'])->toArray(), 201);
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, $e->getMessage(), 400);
        }
    }

    /**
     * Cancel an order
     * POST /v1/orders/{id}/cancel
     */
    public function cancel(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        try {
            $order = Order::with('items')->find($args['id']);
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }

            if ($order->status === 'cancelled') {
                return ResponseHelper::error($response, 'Order is already cancelled', 400);
            }

            // Return items to inventory
            foreach ($order->items as $item) {
                Inventory::where('productId', $item->productId)->increment('quantity', $item->quantity);
            }

            $order->update(['status' => 'cancelled']);

            // Update transaction status
            Transaction::where('orderId', $order->id)->update(['status' => 'cancelled']);

            DB::commit();
            return ResponseHelper::success($response, 'Order cancelled and stock returned successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, 'Failed to cancel order', 500, $e->getMessage());
        }
    }

    /**
     * Fulfill an order item (Partial or Full)
     * POST /v1/orders/item/{itemId}/fulfill
     */
    public function fulfillItem(Request $request, Response $response, array $args): Response
    {
        try {
            $itemId = $args['itemId'];
            $data = $request->getParsedBody();
            $fulfillAmount = (int) ($data['quantity'] ?? 0);

            if ($fulfillAmount <= 0) {
                return ResponseHelper::error($response, 'Valid quantity to fulfill is required', 400);
            }

            $orderItem = OrderItem::find($itemId);
            if (!$orderItem) {
                return ResponseHelper::error($response, 'Order item not found', 404);
            }

            $remainingQuantity = $orderItem->quantity - $orderItem->fulfilledQuantity;

            if ($fulfillAmount > $remainingQuantity) {
                return ResponseHelper::error($response, "Cannot fulfill more than remaining quantity ($remainingQuantity)", 400);
            }

            $orderItem->fulfilledQuantity += $fulfillAmount;

            if ($orderItem->fulfilledQuantity == $orderItem->quantity) {
                $orderItem->fulfillmentStatus = 'fulfilled';
            } else {
                $orderItem->fulfillmentStatus = 'partial';
            }

            $orderItem->save();

            return ResponseHelper::success($response, "Successfully fulfilled $fulfillAmount units", $orderItem->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fulfill item', 500, $e->getMessage());
        }
    }

    /**
     * Bulk fulfill an order
     * POST /v1/orders/{id}/fulfill
     */
    public function fulfill(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        try {
            $orderId = $args['id'];
            $data = $request->getParsedBody();
            $itemsToFulfill = $data['items'] ?? [];

            if (empty($itemsToFulfill) || !is_array($itemsToFulfill)) {
                return ResponseHelper::error($response, 'Items to fulfill are required', 400);
            }

            $order = Order::with('items')->find($orderId);
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }

            foreach ($itemsToFulfill as $itemData) {
                $orderItemId = $itemData['orderItemId'] ?? null;
                $fulfilledQuantity = (int)($itemData['fulfilledQuantity'] ?? 0);

                if ($fulfilledQuantity <= 0) {
                    // Skip if 0 or negative
                    continue;
                }

                if (!$orderItemId) {
                    throw new Exception('orderItemId is required for each item');
                }

                $orderItem = OrderItem::where('orderId', $order->id)->find($orderItemId);
                if (!$orderItem) {
                    throw new Exception("Order item ID $orderItemId not found in this order");
                }

                $remainingQuantity = $orderItem->quantity - $orderItem->fulfilledQuantity;
                if ($fulfilledQuantity > $remainingQuantity) {
                    throw new Exception("Cannot fulfill more than remaining quantity ($remainingQuantity) for item ID $orderItemId");
                }

                // Increment fulfilled quantity
                $orderItem->fulfilledQuantity += $fulfilledQuantity;

                // Update item status
                if ($orderItem->fulfilledQuantity == $orderItem->quantity) {
                    $orderItem->fulfillmentStatus = 'fulfilled';
                } elseif ($orderItem->fulfilledQuantity > 0) {
                    $orderItem->fulfillmentStatus = 'partial';
                }
                
                $orderItem->save();
            }

            // Recalculate Order Status
            $allFulfilled = true;
            $anyFulfilled = false;
            
            // Reload items to get fresh data
            $order->load('items');
            foreach ($order->items as $item) {
                if ($item->fulfilledQuantity < $item->quantity) {
                    $allFulfilled = false;
                }
                if ($item->fulfilledQuantity > 0) {
                    $anyFulfilled = true;
                }
            }

            $orderStatus = 'pending';
            if ($allFulfilled) {
                $orderStatus = 'completed';
            } elseif ($anyFulfilled) {
                $orderStatus = 'partially_fulfilled';
            }

            $order->update(['status' => $orderStatus]);

            // Notify salesperson
            if ($order->createdBy) {
                $statusText = str_replace('_', ' ', $orderStatus);
                $this->notificationService->notifyUser(
                    $order->createdBy,
                    'order_update',
                    "Order Status Updated",
                    "Your order {$order->orderNumber} is now {$statusText}.",
                    ['orderId' => $order->id, 'status' => $orderStatus]
                );
            }

            DB::commit();
            return ResponseHelper::success($response, 'Order fulfillment updated successfully', $order->load('items')->toArray());
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, $e->getMessage(), 400);
        }
    }

    /**
     * Deduct quantity from batches using FEFO (First Expired First Out)
     */
    private function deductFromBatches(int $productId, int $quantity): void
    {
        $remainingToDeduct = $quantity;

        // Get all batches for this product with stock left, ordered by expiry date (soonest first)
        $batches = PurchaseItem::where('productId', $productId)
            ->where('remainingQuantity', '>', 0)
            ->orderBy('expiryDate', 'asc')
            ->get();

        foreach ($batches as $batch) {
            if ($remainingToDeduct <= 0) break;

            if ($batch->remainingQuantity >= $remainingToDeduct) {
                // If this batch has enough stock, deduct all and stop
                $batch->remainingQuantity -= $remainingToDeduct;
                $batch->save();
                $remainingToDeduct = 0;
            } else {
                // If this batch doesn't have enough, empty it and continue to next batch
                $remainingToDeduct -= $batch->remainingQuantity;
                $batch->remainingQuantity = 0;
                $batch->save();
            }
        }
    }
}
