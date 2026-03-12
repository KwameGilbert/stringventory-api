<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Order Model
 * 
 * Represents a financial transaction for ticket purchases.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $pos_user_id
 * @property float $subtotal
 * @property float $fees
 * @property float $total_amount
 * @property string $status
 * @property string|null $payment_reference
 * @property string|null $customer_email
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Order extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'pos_user_id',
        'subtotal',
        'fees',
        'total_amount',
        'status',
        'payment_reference',
        'customer_email',
        'customer_name',
        'customer_phone',
        'paid_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'pos_user_id' => 'integer',
        'subtotal' => 'decimal:2',
        'fees' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['formatted_total', 'ticket_count'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function posUser()
    {
        return $this->belongsTo(User::class, 'pos_user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'order_id');
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'GH₵' . number_format((float)$this->total_amount, 2);
    }

    /**
     * Get total ticket count
     */
    public function getTicketCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Check if order is paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if order is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Scope for pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Get orders older than specified minutes (for cleanup)
     */
    public static function getExpiredPendingOrders(int $minutes = 30)
    {
        return self::where('status', self::STATUS_PENDING)
            ->where('created_at', '<', \Illuminate\Support\Carbon::now()->subMinutes($minutes))
            ->get();
    }
}
