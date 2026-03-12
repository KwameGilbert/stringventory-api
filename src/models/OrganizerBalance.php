<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * OrganizerBalance Model
 * 
 * Cached balance for quick lookups
 * 
 * @property int $id
 * @property int $organizer_id
 * @property float $available_balance
 * @property float $pending_balance
 * @property float $total_earned
 * @property float $total_withdrawn
 * @property \Carbon\Carbon|null $last_payout_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class OrganizerBalance extends Model
{
    protected $table = 'organizer_balances';

    protected $fillable = [
        'organizer_id',
        'available_balance',
        'pending_balance',
        'total_earned',
        'total_withdrawn',
        'last_payout_at',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'last_payout_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==================== Relationships ====================

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    // ==================== Static Methods ====================

    /**
     * Get or create balance record for an organizer
     */
    public static function getOrCreate(int $organizerId): self
    {
        return self::firstOrCreate(
            ['organizer_id' => $organizerId],
            [
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ]
        );
    }

    /**
     * Add earnings to pending balance
     * Called when a ticket is sold or votes are purchased
     */
    public function addPendingEarnings(float $amount): self
    {
        $this->pending_balance += $amount;
        $this->total_earned += $amount;
        $this->save();
        
        return $this;
    }

    /**
     * Move amount from pending to available
     * Called when hold period expires
     */
    public function releaseToAvailable(float $amount): self
    {
        $amountToRelease = min($amount, $this->pending_balance);
        $this->pending_balance -= $amountToRelease;
        $this->available_balance += $amountToRelease;
        $this->save();
        
        return $this;
    }

    /**
     * Process a withdrawal
     * Called when payout is completed
     */
    public function processWithdrawal(float $amount): self
    {
        if ($amount > $this->available_balance) {
            throw new \Exception('Insufficient available balance');
        }
        
        $this->available_balance -= $amount;
        $this->total_withdrawn += $amount;
        $this->last_payout_at = Carbon::now();
        $this->save();
        
        return $this;
    }

    /**
     * Recalculate balances from transactions
     * Use this for reconciliation
     */
    public function recalculateFromTransactions(): self
    {
        $holdDays = PlatformSetting::getPayoutHoldDays();
        $holdDate = Carbon::now()->subDays($holdDays);

        // Get all completed sale transactions for this organizer
        $transactions = Transaction::where('organizer_id', $this->organizer_id)
            ->whereIn('transaction_type', [Transaction::TYPE_TICKET_SALE, Transaction::TYPE_VOTE_PURCHASE])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->get();

        // Calculate total earnings and pending vs available
        $totalEarned = $transactions->sum('organizer_amount');
        
        $availableAmount = $transactions
            ->where('created_at', '<', $holdDate)
            ->sum('organizer_amount');
        
        $pendingAmount = $transactions
            ->where('created_at', '>=', $holdDate)
            ->sum('organizer_amount');

        // Get total payouts
        $totalWithdrawn = Transaction::where('organizer_id', $this->organizer_id)
            ->where('transaction_type', Transaction::TYPE_PAYOUT)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->sum('organizer_amount');

        // Update balances
        $this->total_earned = $totalEarned;
        $this->total_withdrawn = $totalWithdrawn;
        $this->available_balance = max(0, $availableAmount - $totalWithdrawn);
        $this->pending_balance = $pendingAmount;
        $this->save();

        return $this;
    }

    /**
     * Get current withdrawable balance
     */
    public function getWithdrawableBalance(): float
    {
        return max(0, (float) $this->available_balance);
    }

    /**
     * Check if organizer can request a payout
     */
    public function canRequestPayout(): bool
    {
        $minAmount = PlatformSetting::getMinPayoutAmount();
        return $this->available_balance >= $minAmount;
    }
}
