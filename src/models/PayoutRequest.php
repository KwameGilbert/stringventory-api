<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PayoutRequest Model
 * 
 * Represents a payout request from an organizer
 * 
 * @property int $id
 * @property int $organizer_id
 * @property int|null $event_id
 * @property int|null $award_id
 * @property string $payout_type
 * @property float $amount
 * @property float $gross_amount
 * @property float $admin_fee
 * @property string $payment_method
 * @property string $account_number
 * @property string $account_name
 * @property string|null $bank_name
 * @property string $status
 * @property int|null $processed_by
 * @property \Carbon\Carbon|null $processed_at
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PayoutRequest extends Model
{
    protected $table = 'payout_requests';

    // Payout Types
    public const TYPE_EVENT = 'event';
    public const TYPE_AWARD = 'award';

    // Payment Methods
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_MOBILE_MONEY = 'mobile_money';

    // Payout Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organizer_id',
        'event_id',
        'award_id',
        'payout_type',
        'amount',
        'gross_amount',
        'admin_fee',
        'payment_method',
        'account_number',
        'account_name',
        'bank_name',
        'status',
        'processed_by',
        'processed_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // ==================== Scopes ====================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('organizer_id', $organizerId);
    }

    public function scopeForEvents($query)
    {
        return $query->where('payout_type', self::TYPE_EVENT);
    }

    public function scopeForAwards($query)
    {
        return $query->where('payout_type', self::TYPE_AWARD);
    }

    // ==================== Helper Methods ====================

    /**
     * Check if payout is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payout can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Approve the payout request (set to processing)
     */
    public function approve(int $adminId, ?string $notes = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = self::STATUS_PROCESSING;
        $this->processed_by = $adminId;
        $this->processed_at = Carbon::now();
        
        if ($notes) {
            $this->notes = $notes;
        }
        
        return $this->save();
    }

    /**
     * Mark payout as completed
     */
    public function markCompleted(?string $notes = null): bool
    {
        if ($this->status !== self::STATUS_PROCESSING) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        
        if ($notes) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . $notes;
        }
        
        // Update organizer balance
        $balance = OrganizerBalance::getOrCreate($this->organizer_id);
        $balance->processWithdrawal((float) $this->amount);
        
        // Create transaction record
        Transaction::createPayout(
            $this->organizer_id,
            $this->id,
            (float) $this->amount,
            $this->event_id,
            $this->award_id,
            "Payout completed for " . ($this->payout_type === self::TYPE_EVENT ? "event" : "award")
        )->update(['status' => Transaction::STATUS_COMPLETED]);
        
        return $this->save();
    }

    /**
     * Reject the payout request
     */
    public function reject(int $adminId, string $reason): bool
    {
        if (!$this->isPending() && $this->status !== self::STATUS_PROCESSING) {
            return false;
        }

        $this->status = self::STATUS_REJECTED;
        $this->processed_by = $adminId;
        $this->processed_at = Carbon::now();
        $this->rejection_reason = $reason;
        
        return $this->save();
    }

    /**
     * Get the source name (event or award title)
     */
    public function getSourceName(): string
    {
        if ($this->payout_type === self::TYPE_EVENT && $this->event) {
            return $this->event->title;
        }
        
        if ($this->payout_type === self::TYPE_AWARD && $this->award) {
            return $this->award->title;
        }
        
        return 'Unknown';
    }

    /**
     * Get formatted payment method
     */
    public function getPaymentMethodLabel(): string
    {
        return $this->payment_method === self::METHOD_MOBILE_MONEY 
            ? 'Mobile Money' 
            : 'Bank Transfer';
    }

    /**
     * Get formatted status
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown'
        };
    }
}
