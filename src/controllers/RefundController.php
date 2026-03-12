<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Refund;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Inventory;
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
     * Create refund request
     */
    public function create(Request $request, Response $response): Response
    {
        DB::beginTransaction();
        try {
            $data = $request->getParsedBody();

            if (empty($data['orderId']) || empty($data['refundAmount'])) {
                return ResponseHelper::error($response, 'Order ID and refund amount are required', 400);
            }

            $order = Order::find($data['orderId']);
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }

            // Validate refund amount (cannot exceed order total)
            if ($data['refundAmount'] > $order->discountedTotalPrice) {
                return ResponseHelper::error($response, 'Refund amount cannot exceed the total order price', 400);
            }

            // Check if already refunded
            $totalRefunded = Refund::where('orderId', $order->id)
                ->whereIn('refundStatus', ['pending', 'completed'])
                ->sum('refundAmount');
            
            if (($totalRefunded + $data['refundAmount']) > $order->discountedTotalPrice) {
                return ResponseHelper::error($response, 'Total refund amount would exceed the order total price', 400);
            }

            $refund = Refund::create([
                'orderId' => $order->id,
                'customerId' => $order->customerId,
                'refundAmount' => (float)$data['refundAmount'],
                'refundReason' => $data['refundReason'] ?? null,
                'items' => $data['items'] ?? null, // Array of {productId, quantity, restock: bool}
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
                // 1. Record Financial Transaction
                Transaction::create([
                    'orderId' => $refund->orderId,
                    'refundId' => $refund->id,
                    'transactionType' => 'refunds',
                    'amount' => -$refund->refundAmount,
                    'status' => 'completed',
                    'createdAt' => date('Y-m-d H:i:s')
                ]);

                // 2. Handle Restocking if items are provided
                if (!empty($refund->items) && is_array($refund->items)) {
                    foreach ($refund->items as $item) {
                        $isRestockRequested = $item['restock'] ?? false;
                        if ($isRestockRequested) {
                            $productId = $item['productId'] ?? null;
                            $quantity = (int)($item['quantity'] ?? 0);

                            if ($productId && $quantity > 0) {
                                $inventory = Inventory::where('productId', $productId)->first();
                                if ($inventory) {
                                    $inventory->quantity += $quantity;
                                    $inventory->lastUpdated = date('Y-m-d H:i:s');
                                    $inventory->save();
                                }
                            }
                        }
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
