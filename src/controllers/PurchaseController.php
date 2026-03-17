<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class PurchaseController
{
    /**
     * Get all purchases (Restocks)
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $purchases = Purchase::with(['supplier', 'items.product'])->orderBy('createdAt', 'desc')->get();
            return ResponseHelper::success($response, 'Purchases fetched successfully', $purchases->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch purchases', 500, $e->getMessage());
        }
    }

    /**
     * Get single purchase details
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $purchase = Purchase::with(['supplier', 'items.product', 'transactions'])->find($args['id']);
            if (!$purchase) {
                return ResponseHelper::error($response, 'Purchase not found', 404);
            }
            return ResponseHelper::success($response, 'Purchase fetched successfully', $purchase->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch purchase', 500, $e->getMessage());
        }
    }

    /**
     * Create a new restock/purchase order
     */
    public function create(Request $request, Response $response): Response
    {
        DB::beginTransaction();
        try {
            $data = $request->getParsedBody();

            if (empty($data['supplierId']) || empty($data['items'])) {
                return ResponseHelper::error($response, 'Supplier ID and items are required', 400);
            }

            // 1. Verify Supplier
            $supplier = Supplier::find($data['supplierId']);
            if (!$supplier) {
                return ResponseHelper::error($response, 'Supplier not found', 404);
            }

            $purchaseNumber = 'PO-' . strtoupper(substr(uniqid(), 7));
            
            $user = $request->getAttribute('user');
            $role = $user ? $user->role : \App\Models\User::ROLE_MANAGER; // Default to manager if guest (shouldn't happen with auth)

            // 2. Determine Initial Status
            $status = $data['status'] ?? 'pending';
            
            if ($role === \App\Models\User::ROLE_CEO) {
                // If CEO adds a purchase, it shouldn't be 'pending'. 
                // Default to 'ordered' (approved) if not specified as 'received'
                if ($status === 'pending') {
                    $status = 'ordered';
                }
            } else {
                // Managers and others MUST be 'pending' for CEO approval
                $status = 'pending';
            }

            // 3. Create Header
            $purchase = Purchase::create([
                'supplierId' => $data['supplierId'],
                'purchaseNumber' => $purchaseNumber,
                'waybillNumber' => $data['waybillNumber'] ?? null,
                'batchNumber' => $data['batchNumber'] ?? null,
                'purchaseDate' => $data['purchaseDate'] ?? date('Y-m-d H:i:s'),
                'dueDate' => $data['dueDate'] ?? null,
                'expectedDeliveryDate' => $data['expectedDeliveryDate'] ?? null,
                'tax' => (float)($data['tax'] ?? 0),
                'shippingCost' => (float)($data['shippingCost'] ?? 0),
                'status' => $status,
                'paymentStatus' => $data['paymentStatus'] ?? 'unpaid',
                'paymentMethod' => $data['paymentMethod'] ?? 'bank_transfer',
                'notes' => $data['notes'] ?? null,
            ]);

            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $product = Product::find($item['productId']);
                if (!$product) {
                    throw new Exception("Product ID {$item['productId']} not found");
                }

                $quantity = (int)$item['quantity'];
                $costPrice = (float)($item['costPrice'] ?? $product->costPrice);
                $totalPrice = $quantity * $costPrice;

                PurchaseItem::create([
                    'purchaseId' => $purchase->id,
                    'productId' => $product->id,
                    'quantity' => $quantity,
                    'costPrice' => $costPrice,
                    'sellingPrice' => (float)($item['sellingPrice'] ?? $product->sellingPrice),
                    'totalPrice' => $totalPrice,
                    'expiryDate' => $item['expiryDate'] ?? null
                ]);

                $subtotal += $totalPrice;

                // 3. Immediate Inventory update if status is 'received'
                if ($purchase->status === 'received') {
                    $this->updateInventoryAndPricing($product, $quantity, $costPrice, (float)($item['sellingPrice'] ?? $product->sellingPrice));
                }
            }

            $purchase->subtotal = $subtotal;
            $purchase->totalAmount = $subtotal + $purchase->tax + $purchase->shippingCost;
            $purchase->save();

            // 4. Financial Record Keeping (Transactions)
            // If the purchase is already paid or received, mark money as gone
            if ($purchase->paymentStatus === 'paid' || $purchase->status === 'received') {
                $this->recordTransaction($purchase);
            }

            DB::commit();
            return ResponseHelper::success($response, 'Purchase order created successfully', $purchase->load('items')->toArray(), 201);
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, 'Failed to create purchase', 500, $e->getMessage());
        }
    }

    /**
     * Update/Receive Purchase
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        try {
            $purchase = Purchase::with('items')->find($args['id']);
            if (!$purchase) {
                return ResponseHelper::error($response, 'Purchase not found', 404);
            }

            if ($purchase->status === 'received') {
                return ResponseHelper::error($response, 'Received purchases cannot be edited', 400);
            }

            $data = $request->getParsedBody();
            $oldStatus = $purchase->status;
            
            $purchase->update($data);

            // Trigger inventory inflow when status changes to 'received'
            if ($purchase->status === 'received' && $oldStatus !== 'received') {
                $purchase->receivedDate = date('Y-m-d H:i:s');
                $purchase->save();

                foreach ($purchase->items as $item) {
                    $product = Product::find($item->productId);
                    $this->updateInventoryAndPricing($product, $item->quantity, $item->costPrice, $item->sellingPrice);
                }

                // If no transaction was recorded, record it now
                if (!Transaction::where('purchaseId', $purchase->id)->exists()) {
                    $this->recordTransaction($purchase);
                }
            }

            DB::commit();
            return ResponseHelper::success($response, 'Purchase updated successfully', $purchase->toArray());
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, 'Failed to update purchase', 500, $e->getMessage());
        }
    }

    /**
     * Helper: Restock inventory and update product price tags
     */
    private function updateInventoryAndPricing(Product $product, int $quantity, float $cost, float $selling): void
    {
        // Update product metadata with latest prices
        $product->costPrice = $cost;
        $product->sellingPrice = $selling;
        $product->save();

        // restock inventory
        $inventory = Inventory::where('productId', $product->id)->first();
        if (!$inventory) {
            Inventory::create([
                'productId' => $product->id,
                'quantity' => $quantity,
                'status' => 'in_stock',
                'lastUpdated' => date('Y-m-d H:i:s')
            ]);
        } else {
            $inventory->quantity += $quantity;
            $inventory->status = 'in_stock';
            $inventory->lastUpdated = date('Y-m-d H:i:s');
            $inventory->save();
        }
    }

    /**
     * Helper: Create financial transaction record
     */
    private function recordTransaction(Purchase $purchase): void
    {
        Transaction::create([
            'purchaseId' => $purchase->id,
            'transactionType' => 'purchase',
            'amount' => -$purchase->totalAmount, // Outflow (Negative)
            'status' => 'completed',
            'paymentMethod' => $purchase->paymentMethod ?? 'bank_transfer',
            'createdAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Cancel/Delete Purchase
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $purchase = Purchase::find($args['id']);
            if (!$purchase) {
                return ResponseHelper::error($response, 'Purchase not found', 404);
            }

            if ($purchase->status === 'received') {
                return ResponseHelper::error($response, 'Cannot delete a received purchase as inventory has already been modified.', 400);
            }

            $purchase->delete();
            return ResponseHelper::success($response, 'Purchase deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete purchase', 500, $e->getMessage());
        }
    }
}
