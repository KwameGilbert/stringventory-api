<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Transaction Model
 * 
 * @property int $id
 * @property int|null $orderId
 * @property int|null $expenseId
 * @property int|null $purchaseId
 * @property int|null $adjustmentId
 * @property int|null $refundId
 * @property string $transactionType
 * @property string|null $paymentMethod
 * @property float|null $amount
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $createdAt
 */
class Transaction extends Model
{
    protected $table = 'transactions';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'orderId',
        'expenseId',
        'purchaseId',
        'adjustmentId',
        'refundId',
        'transactionType',
        'paymentMethod',
        'amount',
        'status',
    ];

    protected $casts = [
        'orderId' => 'integer',
        'expenseId' => 'integer',
        'purchaseId' => 'integer',
        'adjustmentId' => 'integer',
        'refundId' => 'integer',
        'amount' => 'float',
        'createdAt' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expenseId');
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refundId');
    }
}
