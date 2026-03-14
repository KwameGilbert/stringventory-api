<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $table = 'purchaseItems';
    public $timestamps = false;

    protected $fillable = [
        'purchaseId',
        'productId',
        'quantity',
        'costPrice',
        'sellingPrice',
        'totalPrice',
        'expiryDate',
    ];

    protected $casts = [
        'purchaseId' => 'integer',
        'productId' => 'integer',
        'quantity' => 'integer',
        'costPrice' => 'float',
        'sellingPrice' => 'float',
        'totalPrice' => 'float',
        'expiryDate' => 'datetime',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchaseId');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
