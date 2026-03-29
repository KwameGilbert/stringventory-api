<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Order Model
 * 
 * @property int $id
 * @property string $orderNumber
 * @property int|null $customerId
 * @property string $status
 * @property int|null $discountId
 * @property float|null $discountPercentage
 * @property float|null $discountAmount
 * @property string $discountType
 * @property float|null $discountedPrice
 * @property float|null $discountedTotalPrice
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property \Illuminate\Support\Carbon|null $createdAt
 * 
 * @property-read \Illuminate\Database\Eloquent\Collection $items
 * @property-read \Illuminate\Database\Eloquent\Collection $refunds
 * @property-read \Illuminate\Database\Eloquent\Collection $transactions
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\Discount|null $discount
 */
class Order extends Model
{
    protected $table = 'orders';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'orderNumber',
        'customerId',
        'createdBy',
        'status',
        'discountId',
        'discountPercentage',
        'discountAmount',
        'discountType',
        'discountedPrice',
        'discountedTotalPrice',
        'notes',
        'currency',
    ];

    protected $casts = [
        'customerId' => 'integer',
        'createdBy' => 'integer',
        'discountId' => 'integer',
        'discountPercentage' => 'float',
        'discountAmount' => 'float',
        'discountedPrice' => 'float',
        'discountedTotalPrice' => 'float',
        'updatedAt' => 'datetime',
        'createdAt' => 'datetime',
        'currency' => 'string',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customerId');
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discountId');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'orderId');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'orderId');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'orderId');
    }
}
