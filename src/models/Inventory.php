<?php

declare(strict_types=1);

namespace App\Models;

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

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
