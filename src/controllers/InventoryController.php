<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class InventoryController
{
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

            return ResponseHelper::success($response, 'Stock adjusted successfully', $inventory->load('product')->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to adjust stock', 500, $e->getMessage());
        }
    }
}
