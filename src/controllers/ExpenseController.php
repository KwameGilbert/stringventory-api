<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\AuditLog;
use App\Helper\ResponseHelper;
use App\Services\NotificationService;
use App\Services\CurrencyService;
use App\Services\UploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class ExpenseController
{
    private NotificationService $notificationService;
    private UploadService $uploadService;

    public function __construct(NotificationService $notificationService, UploadService $uploadService)
    {
        $this->notificationService = $notificationService;
        $this->uploadService = $uploadService;
    }
    /**
     * Get all expenses
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $expenses = Expense::with(['category', 'creator', 'transaction'])->orderBy('transactionDate', 'desc')->get();
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
            $expense = Expense::with(['category', 'creator', 'transaction'])->find($args['id']);
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
            $data = (array)($request->getParsedBody() ?? []);
            $uploadedFiles = $request->getUploadedFiles();

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

            // Handle evidence file upload
            if (!empty($uploadedFiles['evidence'])) {
                $file = $uploadedFiles['evidence'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['evidence'] = $this->uploadService->uploadFile($file, 'evidence', 'expenses');
                }
            }

            // Set createdBy and currency
            $user = $request->getAttribute('user');
            $data['createdBy'] = $user ? $user->id : null;
            $currency = CurrencyService::getCurrent();
            $data['currency'] = $currency;

            DB::beginTransaction();
            $expense = Expense::create($data);

            // Record transaction for the expense
            Transaction::create([
                'expenseId' => $expense->id,
                'transactionType' => 'expense',
                'amount' => -$expense->amount, // Expense is an outflow
                'currency' => $currency,
                'paymentMethod' => $data['paymentMethod'] ?? $data['payment_method'] ?? 'cash',
                'status' => 'completed',
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            DB::commit();

            AuditLog::log($request, $user ? $user->id : null, 'expense_created', [
                'expenseId' => $expense->id,
                'amount' => $expense->amount,
                'currency' => $currency,
            ]);

            // Notify admins about new expense
            $this->notificationService->notifyAdmins(
                'expense_created',
                'New Expense Recorded',
                "A new expense of " . number_format((float)$expense->amount, 2) . " has been recorded (" . ($expense->notes ?? 'No description') . ").",
                ['expenseId' => $expense->id, 'amount' => $expense->amount]
            );

            return ResponseHelper::success($response, 'Expense created successfully', $expense->load(['category', 'transaction'])->toArray(), 201);
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

            $data = (array)($request->getParsedBody() ?? []);
            $uploadedFiles = $request->getUploadedFiles();

            if (isset($data['expenseCategoryId']) && !ExpenseCategory::where('id', $data['expenseCategoryId'])->exists()) {
                return ResponseHelper::error($response, 'Provided expense category does not exist', 404);
            }

            // Handle evidence file replacement
            if (!empty($uploadedFiles['evidence'])) {
                $file = $uploadedFiles['evidence'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['evidence'] = $this->uploadService->replaceFile($file, $expense->evidence, 'evidence', 'expenses');
                }
            }

            $expense->update($data);

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'expense_updated', [
                'expenseId' => $expense->id,
            ]);

            return ResponseHelper::success($response, 'Expense updated successfully', $expense->load(['category', 'transaction'])->toArray());
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

            // Delete associated evidence file if it exists
            if ($expense->evidence) {
                $this->uploadService->deleteFile($expense->evidence);
            }

            $expenseId = $expense->id;
            $expense->delete();

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'expense_deleted', [
                'expenseId' => $expenseId,
            ]);

            return ResponseHelper::success($response, 'Expense deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete expense', 500, $e->getMessage());
        }
    }
}
