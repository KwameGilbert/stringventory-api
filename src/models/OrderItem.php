<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OrderItem Model
 * 
 * @property int $id
 * @property int $orderId
 * @property int|null $productId
 * @property float|null $costPrice
 * @property float|null $sellingPrice
 * @property int $quantity
 * @property int $fulfilledQuantity
 * @property int $refundedQuantity
 * @property float|null $totalPrice
 */
class OrderItem extends Model
{
    protected $table = 'orderItems';
    public $timestamps = false; // No timestamps in migration

    protected $fillable = [
        'orderId',
        'productId',
        'costPrice',
        'sellingPrice',
        'quantity',
        'fulfilledQuantity',
        'refundedQuantity',
        'fulfillmentStatus',
        'totalPrice',
    ];

    protected $casts = [
        'orderId' => 'integer',
        'productId' => 'integer',
        'costPrice' => 'float',
        'sellingPrice' => 'float',
        'quantity' => 'integer',
        'fulfilledQuantity' => 'integer',
        'refundedQuantity' => 'integer',
        'totalPrice' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
