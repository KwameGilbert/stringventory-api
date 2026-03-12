<?php

declare(strict_types=1);

namespace App\Models;

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
        'status',
        'discountId',
        'discountPercentage',
        'discountAmount',
        'discountType',
        'discountedPrice',
        'discountedTotalPrice',
    ];

    protected $casts = [
        'customerId' => 'integer',
        'discountId' => 'integer',
        'discountPercentage' => 'float',
        'discountAmount' => 'float',
        'discountedPrice' => 'float',
        'discountedTotalPrice' => 'float',
        'updatedAt' => 'datetime',
        'createdAt' => 'datetime',
    ];

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
