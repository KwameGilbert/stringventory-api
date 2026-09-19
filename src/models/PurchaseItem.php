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
        'remainingQuantity',
    ];

    protected $casts = [
        'purchaseId' => 'integer',
        'productId' => 'integer',
        'quantity' => 'integer',
        'remainingQuantity' => 'integer',
        'costPrice' => 'float',
        'sellingPrice' => 'float',
        'totalPrice' => 'float',
        'expiryDate' => 'datetime',
    ];

    protected $appends = ['batchNumber'];

    public function getBatchNumberAttribute(): ?string
    {
        return $this->purchase ? $this->purchase->batchNumber : null;
    }

    /**
     * Put returned units back into batch tracking, so batches keep matching the stock on hand
     * after an order is cancelled or a refund is restocked.
     *
     * This reverses how FEFO takes stock out: the batch drawn from last (the latest expiry among
     * batches with room) is refilled first, each up to the quantity it was bought with. Any
     * remainder goes on the latest batch, like a manual stock increase on the inventory adjust
     * endpoint. Only batches of received purchases take part, and a product with no batches is
     * simply not batch-tracked.
     */
    public static function restoreStock(int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $received = fn ($purchase) => $purchase->where('status', 'received');

        $batches = static::where('productId', $productId)
            ->whereHas('purchase', $received)
            ->whereColumn('remainingQuantity', '<', 'quantity')
            ->orderBy('expiryDate', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($batches as $batch) {
            if ($quantity <= 0) {
                break;
            }

            $restored = min($batch->quantity - $batch->remainingQuantity, $quantity);
            $batch->remainingQuantity += $restored;
            $batch->save();
            $quantity -= $restored;
        }

        if ($quantity > 0) {
            $latest = static::where('productId', $productId)
                ->whereHas('purchase', $received)
                ->orderBy('id', 'desc')
                ->first();

            if ($latest) {
                $latest->remainingQuantity += $quantity;
                $latest->save();
            }
        }
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchaseId');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
