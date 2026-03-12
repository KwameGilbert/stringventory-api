<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ExpenseCategory Model
 * 
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class ExpenseCategory extends Model
{
    protected $table = 'expenseCategories';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function schedules()
    {
        return $this->hasMany(ExpenseSchedule::class, 'expenseCategoryId');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expenseCategoryId');
    }
}
