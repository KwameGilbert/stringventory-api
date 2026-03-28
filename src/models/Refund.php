<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Refund Model
 * 
 * @property int $id
 * @property int $orderId
 * @property int $customerId
 * @property string $refundType
 * @property float $refundAmount
 * @property \Illuminate\Support\Carbon $refundDate
 * @property string|null $refundReason
 * @property string $refundStatus
 * @property string|null $notes
 * @property string|null $paymentMethod
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property \Illuminate\Support\Carbon|null $createdAt
 */
class Refund extends Model
{
    protected $table = 'refunds';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'orderId',
        'customerId',
        'refundType',
        'paymentMethod',
        'refundAmount',
        'refundDate',
        'refundReason',
        'refundStatus',
        'items',
        'notes',
        'isRestocked',
    ];

    protected $casts = [
        'orderId' => 'integer',
        'customerId' => 'integer',
        'refundAmount' => 'float',
        'refundDate' => 'datetime',
        'items' => 'array',
        'isRestocked' => 'boolean',
        'updatedAt' => 'datetime',
        'createdAt' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customerId');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'refundId');
    }
}
