<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExpenseSchedule;
use App\Models\Expense;
use App\Models\Transaction;
use App\Services\CurrencyService;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;
use DateTime;

class ExpenseService
{
    /**
     * Process all active expense schedules and generate individual expense records.
     * This should typically run via a cron job or a daily trigger.
     */
    public function processScheduledExpenses(): array
    {
        $today = new DateTime();
        $processed = [];
        $errors = [];

        // Fetch all active schedules that are due today or in the past
        $schedules = ExpenseSchedule::where('isActive', true)
            ->where(function ($query) use ($today) {
                $query->where('nextDueDate', '<=', $today->format('Y-m-d'))
                      ->orWhereNull('nextDueDate');
            })
            ->get();

        foreach ($schedules as $schedule) {
            DB::beginTransaction();
            try {
                // 1. Determine the due date
                $dueDateStr = $schedule->nextDueDate ? $schedule->nextDueDate->format('Y-m-d') : $schedule->startDate->format('Y-m-d');
                $dueDate = new DateTime($dueDateStr);

                // If the due date is still in the future (though our query filters it out, safety first), skip
                if ($dueDate > $today && $schedule->nextDueDate !== null) {
                    DB::rollBack();
                    continue;
                }

                // 2. Create the Expense record
                $expense = Expense::create([
                    'expenseScheduleId' => $schedule->id,
                    'expenseCategoryId' => $schedule->expenseCategoryId,
                    'amount' => $schedule->amount,
                    'currency' => CurrencyService::getCurrent(),
                    'transactionDate' => $dueDate->format('Y-m-d H:i:s'),
                    'notes' => "Auto-generated from schedule: " . ($schedule->description ?: 'Recurring Expense'),
                    'status' => 'paid', // Assuming automated payment or recording
                    'createdAt' => date('Y-m-d H:i:s')
                ]);

                // 3. Create the Transaction record
                Transaction::create([
                    'expenseId' => $expense->id,
                    'transactionType' => 'expense',
                    'amount' => -$schedule->amount, // Outflow
                    'currency' => CurrencyService::getCurrent(),
                    'status' => 'completed',
                    'paymentMethod' => 'automated',
                    'createdAt' => date('Y-m-d H:i:s')
                ]);

                // 4. Calculate and Update Next Due Date
                $nextDate = $this->calculateNextDueDate($dueDate, $schedule->frequency);
                
                // If frequency is 'none', deactivate the schedule
                if ($schedule->frequency === 'none' || ($schedule->endDate && $nextDate > $schedule->endDate)) {
                    $schedule->isActive = false;
                    $schedule->nextDueDate = null;
                } else {
                    $schedule->nextDueDate = $nextDate->format('Y-m-d');
                }

                $schedule->save();
                DB::commit();
                
                $processed[] = [
                    'scheduleId' => $schedule->id,
                    'expenseId' => $expense->id,
                    'amount' => $schedule->amount
                ];

            } catch (Exception $e) {
                DB::rollBack();
                $errors[] = "Schedule ID {$schedule->id}: " . $e->getMessage();
            }
        }

        return [
            'status' => count($errors) === 0 ? 'success' : 'partial_success',
            'processed' => $processed,
            'errors' => $errors
        ];
    }

    /**
     * Calculate the next date based on frequency
     */
    private function calculateNextDueDate(DateTime $currentDate, string $frequency): ?DateTime
    {
        $nextDate = clone $currentDate;
        
        switch ($frequency) {
            case 'daily':
                $nextDate->modify('+1 day');
                break;
            case 'weekly':
                $nextDate->modify('+1 week');
                break;
            case 'monthly':
                $nextDate->modify('+1 month');
                break;
            case 'yearly':
                $nextDate->modify('+1 year');
                break;
            default:
                return null;
        }
        
        return $nextDate;
    }
}
