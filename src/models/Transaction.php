<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transaction Model
 * 
 * Financial audit trail for all money movements
 * 
 * @property int $id
 * @property string $reference
 * @property string $transaction_type
 * @property int|null $organizer_id
 * @property int|null $event_id
 * @property int|null $award_id
 * @property int|null $order_id
 * @property int|null $order_item_id
 * @property int|null $vote_id
 * @property int|null $payout_id
 * @property float $gross_amount
 * @property float $admin_amount
 * @property float $organizer_amount
 * @property float $payment_fee
 * @property string $status
 * @property string|null $description
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Transaction extends Model
{
    protected $table = 'transactions';

    // Transaction Types
    public const TYPE_TICKET_SALE = 'ticket_sale';
    public const TYPE_VOTE_PURCHASE = 'vote_purchase';
    public const TYPE_PAYOUT = 'payout';
    public const TYPE_REFUND = 'refund';

    // Transaction Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'reference',
        'transaction_type',
        'organizer_id',
        'event_id',
        'award_id',
        'order_id',
        'order_item_id',
        'vote_id',
        'payout_id',
        'gross_amount',
        'admin_amount',
        'organizer_amount',
        'payment_fee',
        'status',
        'description',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'admin_amount' => 'decimal:2',
        'organizer_amount' => 'decimal:2',
        'payment_fee' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Generate a unique transaction reference
     */
    public static function generateReference(string $prefix = 'TXN'): string
    {
        return $prefix . '_' . strtoupper(uniqid()) . '_' . time();
    }

    /**
     * Create a ticket sale transaction
     */
    public static function createTicketSale(
        int $organizerId,
        int $eventId,
        int $orderId,
        int $orderItemId,
        float $grossAmount,
        float $adminAmount,
        float $organizerAmount,
        float $paymentFee,
        ?string $description = null
    ): self {
        return self::create([
            'reference' => self::generateReference('TKT'),
            'transaction_type' => self::TYPE_TICKET_SALE,
            'organizer_id' => $organizerId,
            'event_id' => $eventId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'gross_amount' => $grossAmount,
            'admin_amount' => $adminAmount,
            'organizer_amount' => $organizerAmount,
            'payment_fee' => $paymentFee,
            'status' => self::STATUS_COMPLETED,
            'description' => $description ?? "Ticket sale for order #{$orderId}",
        ]);
    }

    /**
     * Create a vote purchase transaction
     */
    public static function createVotePurchase(
        int $organizerId,
        int $awardId,
        int $voteId,
        float $grossAmount,
        float $adminAmount,
        float $organizerAmount,
        float $paymentFee,
        ?string $description = null
    ): self {
        return self::create([
            'reference' => self::generateReference('VOT'),
            'transaction_type' => self::TYPE_VOTE_PURCHASE,
            'organizer_id' => $organizerId,
            'award_id' => $awardId,
            'vote_id' => $voteId,
            'gross_amount' => $grossAmount,
            'admin_amount' => $adminAmount,
            'organizer_amount' => $organizerAmount,
            'payment_fee' => $paymentFee,
            'status' => self::STATUS_COMPLETED,
            'description' => $description ?? "Vote purchase for award #{$awardId}",
        ]);
    }

    /**
     * Create a payout transaction
     */
    public static function createPayout(
        int $organizerId,
        int $payoutId,
        float $amount,
        ?int $eventId = null,
        ?int $awardId = null,
        ?string $description = null
    ): self {
        return self::create([
            'reference' => self::generateReference('PAY'),
            'transaction_type' => self::TYPE_PAYOUT,
            'organizer_id' => $organizerId,
            'event_id' => $eventId,
            'award_id' => $awardId,
            'payout_id' => $payoutId,
            'gross_amount' => $amount,
            'admin_amount' => 0,
            'organizer_amount' => $amount,
            'payment_fee' => 0,
            'status' => self::STATUS_PENDING,
            'description' => $description ?? "Payout request #{$payoutId}",
        ]);
    }

    // ==================== Relationships ====================

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(AwardVote::class, 'vote_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class, 'payout_id');
    }

    // ==================== Scopes ====================

    public function scopeTicketSales($query)
    {
        return $query->where('transaction_type', self::TYPE_TICKET_SALE);
    }

    public function scopeVotePurchases($query)
    {
        return $query->where('transaction_type', self::TYPE_VOTE_PURCHASE);
    }

    public function scopePayouts($query)
    {
        return $query->where('transaction_type', self::TYPE_PAYOUT);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('organizer_id', $organizerId);
    }
}
