<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Inventory Model
 * 
 * @property int $id
 * @property int $productId
 * @property int $quantity
 * @property string|null $warehouseLocation
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $createdAt
 */
class Inventory extends Model
{
    protected $table = 'inventory';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'productId',
        'quantity',
        'warehouseLocation',
        'status',
        'lastUpdated',
    ];

    protected $casts = [
        'productId' => 'integer',
        'quantity' => 'integer',
        'createdAt' => 'datetime',
    ];

    protected $appends = ['soonestExpiryDate'];

    public function getSoonestExpiryDateAttribute(): ?string
    {
        return $this->product ? $this->product->soonestExpiryDate : null;
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    /**
     * Total value of stock on hand: quantity x the product's cost price. This is the single
     * definition shared by the dashboard, the Inventory Report and the Inventory page.
     */
    public static function totalValue(): float
    {
        return (float) static::query()
            ->join('products', 'inventory.productId', '=', 'products.id')
            ->selectRaw('COALESCE(SUM(inventory.quantity * COALESCE(products.costPrice, 0)), 0) AS total')
            ->value('total');
    }

    /**
     * Inventory at or below its product's reorder level. Products with no
     * reorderLevel fall back to Product::DEFAULT_REORDER_LEVEL, matching the
     * /v1/products/low-stock endpoint. Out-of-stock rows count as low.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas('product', function ($product) {
            $product->whereRaw(
                'inventory.quantity <= COALESCE(products.reorderLevel, ?)',
                [Product::DEFAULT_REORDER_LEVEL]
            );
        });
    }
}
