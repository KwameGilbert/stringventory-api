<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ExpenseCategory;
use App\Models\Expense;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class ExpenseCategoryController
{
    /**
     * Get all expense categories
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $categories = ExpenseCategory::all();
            return ResponseHelper::success($response, 'Expense categories fetched successfully', $categories->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch expense categories', 500, $e->getMessage());
        }
    }

    /**
     * Get single expense category
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $category = ExpenseCategory::find($args['id']);
            if (!$category) {
                return ResponseHelper::error($response, 'Expense category not found', 404);
            }
            return ResponseHelper::success($response, 'Expense category fetched successfully', $category->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch expense category', 500, $e->getMessage());
        }
    }

    /**
     * Create expense category
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (empty($data['name'])) {
                return ResponseHelper::error($response, 'Expense category name is required', 400);
            }

            // Check for duplicate name
            if (ExpenseCategory::where('name', $data['name'])->exists()) {
                return ResponseHelper::error($response, 'Expense category with this name already exists', 409);
            }

            $category = ExpenseCategory::create($data);
            return ResponseHelper::success($response, 'Expense category created successfully', $category->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create expense category', 500, $e->getMessage());
        }
    }

    /**
     * Update expense category
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $category = ExpenseCategory::find($args['id']);
            if (!$category) {
                return ResponseHelper::error($response, 'Expense category not found', 404);
            }

            $data = $request->getParsedBody();
            
            if (isset($data['name']) && ExpenseCategory::where('name', $data['name'])->where('id', '!=', $args['id'])->exists()) {
                return ResponseHelper::error($response, 'Another category with this name already exists', 409);
            }

            $category->update($data);
            return ResponseHelper::success($response, 'Expense category updated successfully', $category->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update expense category', 500, $e->getMessage());
        }
    }

    /**
     * Delete expense category
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $category = ExpenseCategory::find($args['id']);
            if (!$category) {
                return ResponseHelper::error($response, 'Expense category not found', 404);
            }

            // Check if there are associated expenses
            $expensesCount = Expense::where('expenseCategoryId', $args['id'])->count();
            if ($expensesCount > 0) {
                return ResponseHelper::error($response, "Cannot delete category as it has $expensesCount associated expenses.", 400);
            }

            $category->delete();
            return ResponseHelper::success($response, 'Expense category deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete expense category', 500, $e->getMessage());
        }
    }
}
