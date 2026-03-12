<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AwardVote Model
 * 
 * Represents a vote cast for a nominee.
 * Tracks payment status and voter information.
 *
 * @property int $id
 * @property int $nominee_id
 * @property int $category_id
 * @property int $award_id
 * @property int $number_of_votes
 * @property float $cost_per_vote
 * @property float $gross_amount
 * @property float $admin_share_percent
 * @property float $admin_amount
 * @property float $organizer_amount
 * @property float $payment_fee
 * @property string $status
 * @property string $reference
 * @property string|null $voter_name
 * @property string|null $voter_email
 * @property string|null $voter_phone
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AwardVote extends Model
{
    protected $table = 'award_votes';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'nominee_id',
        'category_id',
        'award_id',
        'number_of_votes',
        'cost_per_vote',
        'gross_amount',
        'admin_share_percent',
        'admin_amount',
        'organizer_amount',
        'payment_fee',
        'status',
        'reference',
        'voter_name',
        'voter_email',
        'voter_phone',
    ];

    protected $casts = [
        'nominee_id' => 'integer',
        'category_id' => 'integer',
        'award_id' => 'integer',
        'number_of_votes' => 'integer',
        'cost_per_vote' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'admin_share_percent' => 'decimal:2',
        'admin_amount' => 'decimal:2',
        'organizer_amount' => 'decimal:2',
        'payment_fee' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the nominee that received this vote.
     */
    public function nominee()
    {
        return $this->belongsTo(AwardNominee::class, 'nominee_id');
    }

    /**
     * Get the category this vote belongs to.
     */
    public function category()
    {
        return $this->belongsTo(AwardCategory::class, 'category_id');
    }

    /**
     * Get the award that owns this vote.
     */
    public function award()
    {
        return $this->belongsTo(Award::class, 'award_id');
    }

    /**
     * Mark vote as paid.
     */
    public function markAsPaid(): bool
    {
        return $this->update(['status' => 'paid']);
    }

    /**
     * Check if vote is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if vote is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get total amount for this vote.
     * 
     * @return float
     */
    public function getTotalAmount(): float
    {
        $category = $this->category;
        if (!$category) {
            return 0;
        }
        
        return $this->number_of_votes * $category->cost_per_vote;
    }

    /**
     * Get vote details.
     */
    public function getDetails(): array
    {
        return [
            'id' => $this->id,
            'nominee_id' => $this->nominee_id,
            'category_id' => $this->category_id,
            'award_id' => $this->award_id,
            'number_of_votes' => $this->number_of_votes,
            'status' => $this->status,
            'reference' => $this->reference,
            'voter_name' => $this->voter_name,
            'voter_email' => $this->voter_email,
            'voter_phone' => $this->voter_phone,
            'total_amount' => $this->getTotalAmount(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Scope to get paid votes.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to get pending votes.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get votes by nominee.
     */
    public function scopeByNominee($query, int $nomineeId)
    {
        return $query->where('nominee_id', $nomineeId);
    }

    /**
     * Scope to get votes by category.
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to filter by award.
     */
    public function scopeByAward($query, $awardId)
    {
        return $query->where('award_id', $awardId);
    }

    /**
     * Scope to get votes by payment reference.
     */
    public function scopeByReference($query, string $reference)
    {
        return $query->where('reference', $reference);
    }
}
