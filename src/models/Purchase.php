<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $table = 'purchases';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'supplierId',
        'purchaseNumber',
        'purchaseDate',
        'dueDate',
        'expectedDeliveryDate',
        'receivedDate',
        'subtotal',
        'tax',
        'shippingCost',
        'totalAmount',
        'status',
        'paymentStatus',
        'paymentMethod',
        'notes',
    ];

    protected $casts = [
        'supplierId' => 'integer',
        'purchaseDate' => 'datetime',
        'dueDate' => 'datetime',
        'expectedDeliveryDate' => 'datetime',
        'receivedDate' => 'datetime',
        'subtotal' => 'float',
        'tax' => 'float',
        'shippingCost' => 'float',
        'totalAmount' => 'float',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplierId');
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class, 'purchaseId');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'purchaseId');
    }
}
