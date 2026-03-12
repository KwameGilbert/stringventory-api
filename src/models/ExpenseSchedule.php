<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ExpenseSchedule Model
 * 
 * @property int $id
 * @property int $expenseCategoryId
 * @property float $amount
 * @property string|null $description
 * @property string $frequency
 * @property \Illuminate\Support\Carbon $startDate
 * @property \Illuminate\Support\Carbon|null $nextDueDate
 * @property \Illuminate\Support\Carbon|null $endDate
 * @property bool $isActive
 * @property \Illuminate\Support\Carbon|null $createdAt
 */
class ExpenseSchedule extends Model
{
    protected $table = 'expenseSchedules';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'expenseCategoryId',
        'amount',
        'description',
        'frequency',
        'startDate',
        'nextDueDate',
        'endDate',
        'isActive',
    ];

    protected $casts = [
        'expenseCategoryId' => 'integer',
        'amount' => 'float',
        'startDate' => 'date',
        'nextDueDate' => 'date',
        'endDate' => 'date',
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expenseCategoryId');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expenseScheduleId');
    }
}
