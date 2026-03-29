<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Helper\ResponseHelper;
use App\Services\NotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class ExpenseController
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Get all expenses
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $expenses = Expense::with(['category', 'creator'])->orderBy('transactionDate', 'desc')->get();
            return ResponseHelper::success($response, 'Expenses fetched successfully', $expenses->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch expenses', 500, $e->getMessage());
        }
    }

    /**
     * Get single expense
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $expense = Expense::with(['category', 'creator'])->find($args['id']);
            if (!$expense) {
                return ResponseHelper::error($response, 'Expense not found', 404);
            }
            return ResponseHelper::success($response, 'Expense fetched successfully', $expense->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch expense', 500, $e->getMessage());
        }
    }

    /**
     * Create expense
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Required fields: amount, transactionDate, expenseCategoryId
            if (empty($data['amount']) || empty($data['transactionDate']) || empty($data['expenseCategoryId'])) {
                return ResponseHelper::error($response, 'Amount, transaction date, and expense category ID are required', 400);
            }

            // Verify category exists
            if (!ExpenseCategory::where('id', $data['expenseCategoryId'])->exists()) {
                return ResponseHelper::error($response, 'Provided expense category does not exist', 404);
            }

            // Default status to 'paid' if not provided
            if (empty($data['status'])) {
                $data['status'] = 'paid';
            }

            // Set createdBy
            $user = $request->getAttribute('user');
            $data['createdBy'] = $user ? $user->id : null;

            DB::beginTransaction();
            $expense = Expense::create($data);

            // Record transaction for the expense
            Transaction::create([
                'expenseId' => $expense->id,
                'transactionType' => 'expense',
                'amount' => -$expense->amount, // Expense is an outflow
                'paymentMethod' => $data['paymentMethod'] ?? $data['payment_method'] ?? 'cash',
                'status' => 'completed',
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            DB::commit();

            // Notify admins about new expense
            $this->notificationService->notifyAdmins(
                'expense_created',
                'New Expense Recorded',
                "A new expense of " . number_format((float)$expense->amount, 2) . " has been recorded (" . ($expense->notes ?? 'No description') . ").",
                ['expenseId' => $expense->id, 'amount' => $expense->amount]
            );

            return ResponseHelper::success($response, 'Expense created successfully', $expense->load('category')->toArray(), 201);
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, 'Failed to create expense', 500, $e->getMessage());
        }
    }

    /**
     * Update expense
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $expense = Expense::find($args['id']);
            if (!$expense) {
                return ResponseHelper::error($response, 'Expense not found', 404);
            }

            $data = $request->getParsedBody();

            if (isset($data['expenseCategoryId']) && !ExpenseCategory::where('id', $data['expenseCategoryId'])->exists()) {
                return ResponseHelper::error($response, 'Provided expense category does not exist', 404);
            }

            $expense->update($data);
            return ResponseHelper::success($response, 'Expense updated successfully', $expense->load('category')->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update expense', 500, $e->getMessage());
        }
    }

    /**
     * Delete expense
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $expense = Expense::find($args['id']);
            if (!$expense) {
                return ResponseHelper::error($response, 'Expense not found', 404);
            }

            $expense->delete();
            return ResponseHelper::success($response, 'Expense deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete expense', 500, $e->getMessage());
        }
    }
}
