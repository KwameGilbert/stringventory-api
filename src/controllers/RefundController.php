<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Refund;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\OrderItem;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class RefundController
{
    /**
     * Get all refunds
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $refunds = Refund::with(['order', 'customer'])->orderBy('createdAt', 'desc')->get();
            return ResponseHelper::success($response, 'Refunds fetched successfully', $refunds->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch refunds', 500, $e->getMessage());
        }
    }

    /**
     * Get single refund
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $refund = Refund::with(['order', 'customer'])->find($args['id']);
            if (!$refund) {
                return ResponseHelper::error($response, 'Refund record not found', 404);
            }
            return ResponseHelper::success($response, 'Refund fetched successfully', $refund->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch refund', 500, $e->getMessage());
        }
    }

    /**
     * Create refund request
     */
    public function create(Request $request, Response $response): Response
    {
        DB::beginTransaction();
        try {
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user'); 

            if (empty($data['orderId']) || empty($data['refundAmount'])) {
                return ResponseHelper::error($response, 'Order ID and refund amount are required', 400);
            }

            /** @var Order $order */
            $order = Order::with('items.product')->find((int)$data['orderId']);
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }

            // Validate refund amount (cannot exceed order total)
            $refundAmount = (float)$data['refundAmount'];
            if ($refundAmount > $order->discountedTotalPrice) {
                return ResponseHelper::error($response, 'Refund amount cannot exceed the total order price', 400);
            }

            // Check if already refunded (total amount)
            $totalRefunded = Refund::where('orderId', $order->id)
                ->whereIn('refundStatus', ['pending', 'completed'])
                ->sum('refundAmount');
            
            if (($totalRefunded + $refundAmount) > $order->discountedTotalPrice) {
                return ResponseHelper::error($response, 'Total refund amount would exceed the order total price', 400);
            }

            // Validate Items if provided
            $refundItems = $data['items'] ?? [];
            if (!empty($refundItems)) {
                foreach ($refundItems as $item) {
                    $orderItemId = $item['orderItemId'] ?? null;
                    $refundQty = (int)($item['quantity'] ?? 0);

                    if (!$orderItemId || $refundQty <= 0) {
                        return ResponseHelper::error($response, 'Invalid item data provided', 400);
                    }

                    $orderItem = $order->items->firstWhere('id', $orderItemId);
                    if (!$orderItem) {
                        return ResponseHelper::error($response, "Item with ID $orderItemId not found in this order", 400);
                    }

                    $availableToRefund = $orderItem->quantity - $orderItem->refundedQuantity;
                    if ($refundQty > $availableToRefund) {
                        return ResponseHelper::error($response, "Cannot refund $refundQty units of product '{$orderItem->product->name}'. Only $availableToRefund remaining.", 400);
                    }
                }
            }

            $refund = Refund::create([
                'orderId' => $order->id,
                'customerId' => $order->customerId,
                'refundType' => $data['refundType'] ?? 'partial',
                'paymentMethod' => $data['paymentMethod'] ?? null,
                'refundAmount' => $refundAmount,
                'refundReason' => $data['reason'] ?? null,
                'items' => $refundItems, // Array of {orderItemId, quantity}
                'notes' => $data['notes'] ?? null,
                'refundStatus' => 'pending',
                'refundDate' => date('Y-m-d H:i:s'),
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            DB::commit();
            return ResponseHelper::success($response, 'Refund request submitted successfully', $refund->toArray(), 201);
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, 'Failed to submit refund request', 500, $e->getMessage());
        }
    }

    /**
     * Update refund status (Approve/Reject)
     */
    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        try {
            $refund = Refund::find($args['id']);
            if (!$refund) {
                return ResponseHelper::error($response, 'Refund record not found', 404);
            }

            $data = $request->getParsedBody();
            $status = $data['refundStatus'] ?? $data['status'] ?? null;
            if (!$status) {
                return ResponseHelper::error($response, 'Status is required', 400);
            }

            if ($refund->refundStatus === 'completed') {
                return ResponseHelper::error($response, 'This refund is already completed and cannot be modified', 400);
            }

            $oldStatus = $refund->refundStatus;
            $refund->refundStatus = $status;

            // If approved, record a transaction and handle restocking
            if ($status === 'completed' && $oldStatus !== 'completed') {
                // Allow optional paymentMethod update during approval
                if (!empty($data['paymentMethod'])) {
                    $refund->paymentMethod = $data['paymentMethod'];
                }

                // 1. Record Financial Transaction
                Transaction::create([
                    'orderId' => $refund->orderId,
                    'refundId' => $refund->id,
                    'transactionType' => 'refunds',
                    'paymentMethod' => $refund->paymentMethod,
                    'amount' => -$refund->refundAmount,
                    'status' => 'completed',
                    'createdAt' => date('Y-m-d H:i:s')
                ]);

                // 2. Handle OrderItem updates and Restocking
                if (!empty($refund->items) && is_array($refund->items)) {
                    $totalLostStockValue = 0;
                    
                    foreach ($refund->items as $item) {
                        $orderItemId = $item['orderItemId'] ?? null;
                        $quantity = (int)($item['quantity'] ?? 0);
                        $restock = $item['restock'] ?? true; 

                        if ($orderItemId && $quantity > 0) {
                            $orderItem = OrderItem::with('product')->find($orderItemId);
                            if ($orderItem) {
                                // Update refunded quantity
                                $orderItem->refundedQuantity += $quantity;
                                $orderItem->save();

                                // Handle Restocking vs Loss
                                if ($restock && $orderItem->productId) {
                                    $inventory = Inventory::where('productId', $orderItem->productId)->first();
                                    if ($inventory) {
                                        $inventory->quantity += $quantity;
                                        $inventory->lastUpdated = date('Y-m-d H:i:s');
                                        $inventory->save();
                                    }
                                } else {
                                    // Calculate loss if not restocked
                                    // Fallback order: OrderItem cost -> Product current cost -> 0
                                    $unitCost = $orderItem->costPrice ?? ($orderItem->product->costPrice ?? 0);
                                    $totalLostStockValue += ($unitCost * $quantity);
                                }
                            }
                        }
                    }
                    
                    // 3. Record Stock Loss Transaction if applicable
                    if ($totalLostStockValue > 0) {
                        Transaction::create([
                            'orderId' => $refund->orderId,
                            'refundId' => $refund->id,
                            'transactionType' => 'stock_loss',
                            'amount' => -$totalLostStockValue,
                            'status' => 'completed',
                            'createdAt' => date('Y-m-d H:i:s')
                        ]);
                    }

                    $refund->isRestocked = true;
                }
            }

            $refund->save();
            DB::commit();
            
            return ResponseHelper::success($response, 'Refund status updated successfully', $refund->toArray());
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, 'Failed to update refund status', 500, $e->getMessage());
        }
    }
}
