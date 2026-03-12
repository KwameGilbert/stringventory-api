<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\Purchase;
use App\Helper\ResponseHelper;
use App\Services\UploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class SupplierController
{
    private UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get all suppliers
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $suppliers = Supplier::withCount(['products', 'purchases'])->get();
            return ResponseHelper::success($response, 'Suppliers fetched successfully', $suppliers->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch suppliers', 500, $e->getMessage());
        }
    }

    /**
     * Get single supplier
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $supplier = Supplier::with(['products', 'purchases'])->find($args['id']);
            if (!$supplier) {
                return ResponseHelper::error($response, 'Supplier not found', 404);
            }
            return ResponseHelper::success($response, 'Supplier fetched successfully', $supplier->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch supplier', 500, $e->getMessage());
        }
    }

    /**
     * Create supplier
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($data['name'])) {
                return ResponseHelper::error($response, 'Supplier name is required', 400);
            }

            // Check for uniqueness if email provided
            if (!empty($data['email'])) {
                if (Supplier::where('email', $data['email'])->exists()) {
                    return ResponseHelper::error($response, 'Supplier with this email already exists', 409);
                }
            }

            // Handle image upload
            if (!empty($uploadedFiles['image'])) {
                $file = $uploadedFiles['image'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['image'] = $this->uploadService->uploadFile($file, 'image', 'suppliers');
                }
            }

            $supplier = Supplier::create($data);
            return ResponseHelper::success($response, 'Supplier created successfully', $supplier->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create supplier', 500, $e->getMessage());
        }
    }

    /**
     * Update supplier
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $supplier = Supplier::find($args['id']);
            if (!$supplier) {
                return ResponseHelper::error($response, 'Supplier not found', 404);
            }

            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            // Check email uniqueness on update
            if (!empty($data['email']) && $data['email'] !== $supplier->email) {
                if (Supplier::where('email', $data['email'])->exists()) {
                    return ResponseHelper::error($response, 'Another supplier with this email already exists', 409);
                }
            }

            // Handle image upload
            if (!empty($uploadedFiles['image'])) {
                $file = $uploadedFiles['image'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['image'] = $this->uploadService->replaceFile($file, $supplier->image, 'image', 'suppliers');
                }
            }

            $supplier->update($data);
            return ResponseHelper::success($response, 'Supplier updated successfully', $supplier->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update supplier', 500, $e->getMessage());
        }
    }

    /**
     * Delete supplier
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $supplier = Supplier::withCount(['products', 'purchases'])->find($args['id']);
            if (!$supplier) {
                return ResponseHelper::error($response, 'Supplier not found', 404);
            }

            // Cross-check: Ensure no dependencies exist
            if ($supplier->products_count > 0 || $supplier->purchases_count > 0) {
                 return ResponseHelper::error($response, 'Cannot delete supplier with associated products or purchase history', 400);
            }

            // Delete associated image
            if ($supplier->image) {
                $this->uploadService->deleteFile($supplier->image);
            }

            $supplier->delete();
            return ResponseHelper::success($response, 'Supplier deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete supplier', 500, $e->getMessage());
        }
    }
}
