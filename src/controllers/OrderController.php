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
use App\Helper\ResponseHelper;
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
    /**
     * Get all orders
     * GET /v1/orders
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $orders = Order::with(['customer', 'items.product', 'discount', 'transactions'])->orderBy('createdAt', 'desc')->get();
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
            $order = Order::with(['customer', 'items.product', 'discount', 'transactions'])->find($args['id']);
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

                $itemsToProcess[] = [
                    'product' => $product,
                    'quantity' => (int)$item['quantity'],
                    'sellingPrice' => $product->sellingPrice,
                    'costPrice' => $product->costPrice,
                    'totalPrice' => $itemTotal
                ];
            }

            // 2. Handle Discount
            $discountAmount = 0;
            $discountId = null;
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
                    if ($discount->discountType === 'percentage') {
                        $discountAmount = ($subtotal * $discount->discount) / 100;
                    } else {
                        $discountAmount = $discount->discountAmount;
                    }
                }
            }

            $totalPrice = $subtotal - $discountAmount;

            // 3. Create Order
            $order = Order::create([
                'orderNumber' => 'ORD-' . strtoupper(bin2hex(random_bytes(4))),
                'customerId' => $data['customerId'] ?? null,
                'status' => 'completed',
                'discountId' => $discountId,
                'discountAmount' => $discountAmount,
                'discountedTotalPrice' => $totalPrice,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ]);

            // 4. Create Order Items and Update Inventory
            foreach ($itemsToProcess as $processItem) {
                OrderItem::create([
                    'orderId' => $order->id,
                    'productId' => $processItem['product']->id,
                    'costPrice' => $processItem['costPrice'],
                    'sellingPrice' => $processItem['sellingPrice'],
                    'quantity' => $processItem['quantity'],
                    'totalPrice' => $processItem['totalPrice']
                ]);

                // Update stock
                Inventory::where('productId', $processItem['product']->id)->decrement('quantity', $processItem['quantity']);
            }

            // 5. Record Transaction
            Transaction::create([
                'orderId' => $order->id,
                'transactionType' => 'order',
                'paymentMethod' => $data['paymentMethod'] ?? 'cash',
                'amount' => $totalPrice,
                'status' => 'completed',
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            DB::commit();

            return ResponseHelper::success($response, 'Order created successfully', $order->load('items')->toArray(), 201);
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
}
