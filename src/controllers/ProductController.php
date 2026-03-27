<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Product;
use App\Models\Inventory;
use App\Models\PurchaseItem;
use App\Models\OrderItem;
use App\Helper\ResponseHelper;
use App\Services\UploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class ProductController
{
    private UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get all products with relations
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $products = Product::with(['category', 'supplier', 'inventory'])->orderBy('name', 'asc')->get();
            return ResponseHelper::success($response, 'Products fetched successfully', $products->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch products', 500, $e->getMessage());
        }
    }

    /**
     * Get single product
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $product = Product::with(['category', 'supplier', 'inventory', 'purchaseItems', 'orderItems'])->find($args['id']);
            if (!$product) {
                return ResponseHelper::error($response, 'Product not found', 404);
            }
            return ResponseHelper::success($response, 'Product fetched successfully', $product->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch product', 500, $e->getMessage());
        }
    }

    /**
     * Create product
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = (array)($request->getParsedBody() ?? []);
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($data['name'])) {
                return ResponseHelper::error($response, 'Product name is required', 400);
            }

            // Default prices to 0 if not provided
            $data['sellingPrice'] = $data['sellingPrice'] ?? 0;
            $data['costPrice'] = $data['costPrice'] ?? 0;

            // Handle SKU: Generate default if not provided
            if (empty($data['sku'])) {
                do {
                    $sku = 'SKU-' . strtoupper(substr(uniqid(), 7));
                } while (Product::where('sku', $sku)->exists());
                $data['sku'] = $sku;
            } else {
                if (Product::where('sku', $data['sku'])->exists()) {
                    return ResponseHelper::error($response, 'Product with this SKU already exists', 409);
                }
            }

            // Handle image upload
            if (!empty($uploadedFiles['image'])) {
                $file = $uploadedFiles['image'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['image'] = $this->uploadService->uploadFile($file, 'image', 'products');
                }
            }

            // Create product
            $product = Product::create($data);

            // Initialize inventory if provided
            if (isset($data['quantity']) && (int)$data['quantity'] > 0) {
                Inventory::create([
                    'productId' => $product->id,
                    'quantity' => (int)$data['quantity'],
                    'status' => 'in_stock',
                    'lastUpdated' => date('Y-m-d H:i:s')
                ]);
            } else {
                 Inventory::create([
                    'productId' => $product->id,
                    'quantity' => 0,
                    'status' => 'out_of_stock',
                    'lastUpdated' => date('Y-m-d H:i:s')
                ]);
            }

            return ResponseHelper::success($response, 'Product created successfully', $product->load('inventory')->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create product', 500, $e->getMessage());
        }
    }

    /**
     * Update product
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $product = Product::find($args['id']);
            if (!$product) {
                return ResponseHelper::error($response, 'Product not found', 404);
            }

            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            // Uniqueness check for SKU on update
            if (!empty($data['sku']) && $data['sku'] !== $product->sku) {
                if (Product::where('sku', $data['sku'])->exists()) {
                    return ResponseHelper::error($response, 'Another product with this SKU already exists', 409);
                }
            }

            // Handle image upload
            if (!empty($uploadedFiles['image'])) {
                $file = $uploadedFiles['image'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['image'] = $this->uploadService->replaceFile($file, $product->image, 'image', 'products');
                }
            }

            $product->update($data);

            return ResponseHelper::success($response, 'Product updated successfully', $product->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update product', 500, $e->getMessage());
        }
    }

    /**
     * Delete product
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $product = Product::withCount(['purchaseItems', 'orderItems'])->find($args['id']);
            if (!$product) {
                return ResponseHelper::error($response, 'Product not found', 404);
            }

            // Dependency validation: Prevents deleting products tied to sales/intake
            if ($product->purchase_items_count > 0 || $product->order_items_count > 0) {
                 return ResponseHelper::error($response, 'Cannot delete product with associated sales history or purchase logs.', 400);
            }

            // Delete associated image if it exists
            if ($product->image) {
                $this->uploadService->deleteFile($product->image);
            }

            // Inventory will be deleted by CASCADE in DB if configured, 
            // but let's be safe and delete it here if needed or let Eloquent handle it if relation set.
            $product->delete();
            return ResponseHelper::success($response, 'Product deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete product', 500, $e->getMessage());
        }
    }

    /**
     * Get expiring products
     */
    public function expiring(Request $request, Response $response): Response
    {
        try {
            $limit = (int)($request->getQueryParams()['limit'] ?? 5);
            
            $products = PurchaseItem::join('products', 'purchaseItems.productId', '=', 'products.id')
                ->join('purchases', 'purchaseItems.purchaseId', '=', 'purchases.id')
                ->where('purchaseItems.expiryDate', '>=', date('Y-m-d'))
                ->select(
                    'products.*',
                    'purchaseItems.expiryDate',
                    'purchases.batchNumber'
                )
                ->orderBy('purchaseItems.expiryDate', 'asc')
                ->limit($limit)
                ->get();

            return ResponseHelper::success($response, 'Expiring products fetched successfully', $products->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch expiring products', 500, $e->getMessage());
        }
    }

    /**
     * Get low stock products
     */
    public function lowStock(Request $request, Response $response): Response
    {
        try {
            $limit = (int)($request->getQueryParams()['limit'] ?? 5);
            
            $products = Product::join('inventory', 'products.id', '=', 'inventory.productId')
                ->whereRaw('inventory.quantity <= products.reorderLevel')
                ->orWhere(function($query) {
                    $query->whereNull('products.reorderLevel')
                          ->where('inventory.quantity', '<=', 10);
                })
                ->select('products.*', 'inventory.quantity as currentQuantity')
                ->orderBy('inventory.quantity', 'asc')
                ->limit($limit)
                ->get();

            return ResponseHelper::success($response, 'Low stock products fetched successfully', $products->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch low stock products', 500, $e->getMessage());
        }
    }
}
