<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Helper\ResponseHelper;
use App\Services\UploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class CategoryController
{
    private UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get all categories
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $categories = Category::all();
            return ResponseHelper::success($response, 'Categories fetched successfully', $categories->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch categories', 500, $e->getMessage());
        }
    }

    /**
     * Get single category
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $category = Category::find($args['id']);
            if (!$category) {
                return ResponseHelper::error($response, 'Category not found', 404);
            }
            return ResponseHelper::success($response, 'Category fetched successfully', $category->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch category', 500, $e->getMessage());
        }
    }

    /**
     * Create category
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($data['name'])) {
                return ResponseHelper::error($response, 'Category name is required', 400);
            }

            // Handle image upload
            if (!empty($uploadedFiles['image'])) {
                $file = $uploadedFiles['image'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['image'] = $this->uploadService->uploadFile($file, 'image', 'categories');
                }
            }

            $category = Category::create($data);
            return ResponseHelper::success($response, 'Category created successfully', $category->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create category', 500, $e->getMessage());
        }
    }

    /**
     * Update category
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $category = Category::find($args['id']);
            if (!$category) {
                return ResponseHelper::error($response, 'Category not found', 404);
            }

            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            // Handle image upload
            if (!empty($uploadedFiles['image'])) {
                $file = $uploadedFiles['image'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['image'] = $this->uploadService->replaceFile($file, $category->image, 'image', 'categories');
                }
            }

            $category->update($data);
            return ResponseHelper::success($response, 'Category updated successfully', $category->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update category', 500, $e->getMessage());
        }
    }

    /**
     * Delete category
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $category = Category::find($args['id']);
            if (!$category) {
                return ResponseHelper::error($response, 'Category not found', 404);
            }

            // Delete associated image if it exists
            if ($category->image) {
                $this->uploadService->deleteFile($category->image);
            }

            $category->delete();
            return ResponseHelper::success($response, 'Category deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete category', 500, $e->getMessage());
        }
    }
}
