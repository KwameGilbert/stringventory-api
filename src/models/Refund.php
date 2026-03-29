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
        'createdBy',
        'currency',
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
        'createdBy' => 'integer',
        'currency' => 'string',
    ];

    public function getCreatedByAttribute($value): string|int|null
    {
        if ($this->relationLoaded('creator') && $this->creator) {
            return trim($this->creator->firstName . ' ' . $this->creator->lastName);
        }
        return $value;
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy');
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
