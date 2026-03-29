<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\PurchaseItem;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use App\Services\NotificationService;
use Exception;

class InventoryController
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all inventory levels
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $inventory = Inventory::with('product')->get();
            return ResponseHelper::success($response, 'Inventory fetched successfully', $inventory->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch inventory', 500, $e->getMessage());
        }
    }

    /**
     * Get inventory for specific product
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $inventory = Inventory::with('product')->where('productId', $args['id'])->first();
            if (!$inventory) {
                return ResponseHelper::error($response, 'Inventory record not found', 404);
            }
            return ResponseHelper::success($response, 'Inventory fetched successfully', $inventory->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch inventory', 500, $e->getMessage());
        }
    }

    /**
     * Adjust stock level
     */
    public function adjust(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (empty($data['productId']) || !isset($data['adjustment'])) {
                return ResponseHelper::error($response, 'Product ID and adjustment value are required', 400);
            }

            // Ensure product exists
            $product = Product::find($data['productId']);
            if (!$product) {
                return ResponseHelper::error($response, 'Product not found', 404);
            }

            DB::beginTransaction();
            
            $inventory = Inventory::where('productId', $data['productId'])->first();
            $adjustment = (int)$data['adjustment'];

            if (!$inventory) {
                // If it's a decrease for a non-existent record, error out
                if ($adjustment < 0) {
                    return ResponseHelper::error($response, 'Cannot decrease stock for non-existent inventory', 400);
                }

                // Create if not exists
                $inventory = Inventory::create([
                    'productId' => $data['productId'],
                    'quantity' => $adjustment,
                    'lastUpdated' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Prevent negative stock
                if ($inventory->quantity + $adjustment < 0) {
                    return ResponseHelper::error($response, 'Insufficient stock. Adjustment would result in negative quantity.', 400);
                }

                $inventory->quantity += $adjustment;
                $inventory->lastUpdated = date('Y-m-d H:i:s');
                $inventory->save();
            }

            // Handle Batch Adjustment if batchId is provided
            if (!empty($data['batchId'])) {
                $batch = PurchaseItem::where('productId', $data['productId'])->find($data['batchId']);
                if ($batch) {
                    $batch->remainingQuantity += $adjustment;
                    if ($batch->remainingQuantity < 0) $batch->remainingQuantity = 0;
                    $batch->save();
                }
            } elseif ($adjustment < 0) {
                // If no batchId but it's a decrease, use FEFO deduction for consistency
                $this->deductFromBatches((int)$data['productId'], abs($adjustment));
            } elseif ($adjustment > 0) {
                // If no batchId but it's an increase, add to the latest batch
                $latestBatch = PurchaseItem::where('productId', $data['productId'])
                    ->orderBy('createdAt', 'desc')
                    ->first();
                if ($latestBatch) {
                    $latestBatch->remainingQuantity += $adjustment;
                    $latestBatch->save();
                }
            }

            // Record transaction for the adjustment
            Transaction::create([
                'adjustmentId' => $inventory->id, // We use the inventory ID as the adjustment reference for now
                'transactionType' => 'adjustment',
                'amount' => 0, // Adjustments don't necessarily have a financial impact shown in amount
                'paymentMethod' => 'internal',
                'status' => 'completed',
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            DB::commit();

            // Trigger notification for admins
            $this->notificationService->notifyAdmins(
                'product',
                'Stock Adjusted',
                "Stock for product '{$product->name}' was adjusted by {$adjustment} units. Current stock: {$inventory->quantity}.",
                ['productId' => $product->id, 'adjustment' => $adjustment, 'newQuantity' => $inventory->quantity]
            );

            // Check for low stock
            if ($inventory->quantity <= ($product->reorderLevel ?? 5)) {
                $this->notificationService->notifyAdmins(
                    'stock_alert',
                    'Low Stock Alert',
                    "Product '{$product->name}' is running low on stock. Current quantity: {$inventory->quantity}.",
                    ['productId' => $product->id, 'quantity' => $inventory->quantity]
                );
            }

            return ResponseHelper::success($response, 'Stock adjusted successfully', $inventory->load('product')->toArray());
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, 'Failed to adjust stock', 500, $e->getMessage());
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
