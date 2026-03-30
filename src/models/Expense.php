<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Expense Model
 * 
 * @property int $id
 * @property int|null $expenseScheduleId
 * @property int $expenseCategoryId
 * @property float $amount
 * @property \Illuminate\Support\Carbon $transactionDate
 * @property string|null $notes
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $createdAt
 */
class Expense extends Model
{
    protected $table = 'expenses';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'expenseScheduleId',
        'expenseCategoryId',
        'amount',
        'transactionDate',
        'notes',
        'status',
        'createdBy',
        'currency',
        'evidence',
        'reference',
    ];

    protected $casts = [
        'expenseScheduleId' => 'integer',
        'expenseCategoryId' => 'integer',
        'amount' => 'float',
        'transactionDate' => 'datetime',
        'createdAt' => 'datetime',
        'createdBy' => 'integer',
        'currency' => 'string',
        'evidence' => 'string',
        'reference' => 'string',
    ];

    public function getCreatedByAttribute($value): string|int|null
    {
        if ($this->relationLoaded('creator') && $this->creator) {
            return trim($this->creator->firstName . ' ' . $this->creator->lastName);
        }
        return $value;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy');
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expenseCategoryId');
    }

    public function schedule()
    {
        return $this->belongsTo(ExpenseSchedule::class, 'expenseScheduleId');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'expenseId');
    }
}
