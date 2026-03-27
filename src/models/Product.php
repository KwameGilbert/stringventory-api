<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Product Model
 * 
 * @property int $id
 * @property string $name
 * @property string|null $sku
 * @property string|null $description
 * @property int|null $categoryId
 * @property int|null $supplierId
 * @property float $sellingPrice
 * @property float|null $costPrice
 * @property int|null $unitOfMeasureId
 * @property string|null $barcode
 * @property string|null $image
 * @property int|null $reorderLevel
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class Product extends Model
{
    protected $table = 'products';
    public $timestamps = true;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'sku',
        'description',
        'categoryId',
        'supplierId',
        'sellingPrice',
        'costPrice',
        'unitOfMeasureId',
        'barcode',
        'image',
        'reorderLevel',
        'status',
    ];
    
    protected $casts = [
        'categoryId' => 'integer',
        'supplierId' => 'integer',
        'sellingPrice' => 'float',
        'costPrice' => 'float',
        'unitOfMeasureId' => 'integer',
        'reorderLevel' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function unitOfMeasure()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unitOfMeasureId');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'categoryId');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplierId');
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class, 'productId');
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'productId');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'productId');
    }
}
