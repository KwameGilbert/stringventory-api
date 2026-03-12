<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ExpenseSchedule;
use App\Models\ExpenseCategory;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;
use DateTime;

class ExpenseScheduleController
{
    private ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    /**
     * Get all schedules
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $schedules = ExpenseSchedule::with('category')->orderBy('createdAt', 'desc')->get();
            return ResponseHelper::success($response, 'Schedules fetched successfully', $schedules->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch schedules', 500, $e->getMessage());
        }
    }

    /**
     * Get single schedule
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $schedule = ExpenseSchedule::with(['category', 'expenses'])->find($args['id']);
            if (!$schedule) {
                return ResponseHelper::error($response, 'Schedule not found', 404);
            }
            return ResponseHelper::success($response, 'Schedule fetched successfully', $schedule->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch schedule', 500, $e->getMessage());
        }
    }

    /**
     * Create schedule
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Required fields
            if (empty($data['expenseCategoryId']) || empty($data['amount']) || empty($data['frequency']) || empty($data['startDate'])) {
                return ResponseHelper::error($response, 'Category ID, amount, frequency, and start date are required', 400);
            }

            // Verify category
            if (!ExpenseCategory::where('id', $data['expenseCategoryId'])->exists()) {
                return ResponseHelper::error($response, 'Provided expense category does not exist', 404);
            }

            // Initialize nextDueDate as the startDate
            $data['nextDueDate'] = $data['startDate'];
            $data['isActive'] = $data['isActive'] ?? true;

            $schedule = ExpenseSchedule::create($data);
            return ResponseHelper::success($response, 'Schedule created successfully', $schedule->load('category')->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create schedule', 500, $e->getMessage());
        }
    }

    /**
     * Update schedule
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $schedule = ExpenseSchedule::find($args['id']);
            if (!$schedule) {
                return ResponseHelper::error($response, 'Schedule not found', 404);
            }

            $data = $request->getParsedBody();

            if (isset($data['expenseCategoryId']) && !ExpenseCategory::where('id', $data['expenseCategoryId'])->exists()) {
                return ResponseHelper::error($response, 'Provided expense category does not exist', 404);
            }

            $schedule->update($data);
            return ResponseHelper::success($response, 'Schedule updated successfully', $schedule->load('category')->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update schedule', 500, $e->getMessage());
        }
    }

    /**
     * Trigger processing of all due schedules
     */
    public function process(Request $request, Response $response): Response
    {
        try {
            $result = $this->expenseService->processScheduledExpenses();
            $count = count($result['processed']);
            return ResponseHelper::success($response, "Successfully processed $count due schedules", $result);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to process schedules', 500, $e->getMessage());
        }
    }

    /**
     * Delete schedule
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $schedule = ExpenseSchedule::find($args['id']);
            if (!$schedule) {
                return ResponseHelper::error($response, 'Schedule not found', 404);
            }

            // Check if there are generated expenses
            $expenseCount = Expense::where('expenseScheduleId', $args['id'])->count();
            if ($expenseCount > 0) {
                return ResponseHelper::error($response, "Cannot delete schedule as it has $expenseCount generated expenses. Consider deactivating it instead.", 400);
            }

            $schedule->delete();
            return ResponseHelper::success($response, 'Schedule deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete schedule', 500, $e->getMessage());
        }
    }
}
