<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * AwardCategory Model
 * 
 * Represents an award category within an awards event.
 * Each category can have multiple nominees and votes.
 *
 * @property int $id
 * @property int $award_id
 * @property string $name
 * @property string|null $image
 * @property string|null $description
 * @property float $cost_per_vote
 * @property \DateTime|null $voting_start
 * @property \DateTime|null $voting_end
 * @property string $status
 * @property int $display_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AwardCategory extends Model
{
    protected $table = 'award_categories';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'award_id',
        'name',
        'image',
        'description',
        'cost_per_vote',
        'voting_start',
        'voting_end',
        'status',
        'display_order',
    ];

    protected $casts = [
        'award_id' => 'integer',
        'cost_per_vote' => 'decimal:2',
        'display_order' => 'integer',
        'voting_start' => 'datetime',
        'voting_end' => 'datetime',
    ];

    /**
     * Get the award that owns this category.
     */
    public function award()
    {
        return $this->belongsTo(Award::class, 'award_id');
    }

    /**
     * Get all nominees in this category.
     */
    public function nominees()
    {
        return $this->hasMany(AwardNominee::class, 'category_id')
                    ->orderBy('display_order');
    }

    /**
     * Get all votes for this category.
     */
    public function votes()
    {
        return $this->hasMany(AwardVote::class, 'category_id');
    }

    /**
     * Get total number of votes for this category.
     */
    public function getTotalVotes(): int
    {
        return (int) $this->votes()
                    ->where('status', 'paid')
                    ->sum('number_of_votes');
    }

    /**
     * Get total revenue generated from this category.
     */
    public function getTotalRevenue(): float
    {
        $totalVotes = $this->getTotalVotes();
        return $totalVotes * $this->cost_per_vote;
    }

    /**
     * Get category-level total revenue (alias for getTotalRevenue).
     */
    public function getCategoryTotalRevenue(): float
    {
        return $this->getTotalRevenue();
    }

    /**
     * Check if voting is currently active for this category.
     */
    public function isVotingActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = \Illuminate\Support\Carbon::now();

        // If no voting period is set, it's always active
        if (!$this->voting_start && !$this->voting_end) {
            return true;
        }

        // Check if within voting period
        $afterStart = !$this->voting_start || $now->greaterThanOrEqualTo($this->voting_start);
        $beforeEnd = !$this->voting_end || $now->lessThanOrEqualTo($this->voting_end);

        return $afterStart && $beforeEnd;
    }

    /**
     * Alias for isVotingActive() for consistency with Award model.
     */
    public function isVotingOpen(): bool
    {
        return $this->isVotingActive();
    }

    /**
     * Get category details with nominees and vote counts.
     */
    public function getDetailsWithResults(): array
    {
        $nominees = $this->nominees()->get()->map(function ($nominee) {
            return [
                'id' => $nominee->id,
                'name' => $nominee->name,
                'description' => $nominee->description,
                'image' => $nominee->image,
                'display_order' => $nominee->display_order,
                'vote_count' => $nominee->getTotalVotes(),
                'revenue' => $nominee->getTotalRevenue($this->cost_per_vote),
            ];
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'description' => $this->description,
            'cost_per_vote' => $this->cost_per_vote,
            'voting_start' => $this->voting_start?->format('Y-m-d H:m:s'),
            'voting_end' => $this->voting_end?->format('Y-m-d H:m:s'),
            'status' => $this->status,
            'display_order' => $this->display_order,
            'is_voting_active' => $this->isVotingActive(),
            'total_votes' => $this->getTotalVotes(),
            'total_revenue' => $this->getTotalRevenue(),
            'nominees' => $nominees,
        ];
    }

    /**
     * Scope to get only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }
}
