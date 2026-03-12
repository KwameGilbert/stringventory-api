<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Award Model
 * 
 * Represents an awards show/ceremony (e.g., Ghana Music Awards 2025)
 * Completely separate from Events (which handle ticketing)
 *
 * @property int $id
 * @property int $organizer_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $banner_image
 * @property string|null $venue_name
 * @property string|null $address
 * @property string|null $map_url
 * @property \Illuminate\Support\Carbon $ceremony_date
 * @property \Illuminate\Support\Carbon $voting_start
 * @property \Illuminate\Support\Carbon $voting_end
 * @property string $status
 * @property bool $is_featured
 * @property float $admin_share_percent
 * @property string $country
 * @property string $region
 * @property string $city
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $facebook
 * @property string|null $twitter
 * @property string|null $instagram
 * @property string|null $video_url
 * @property int $views
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Award extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'awards';

    /**
     * The primary key for the model.
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     * @var bool
     */
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // Award Status Constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PUBLISHED = 'published';
    const STATUS_CLOSED = 'closed';
    const STATUS_COMPLETED = 'completed';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'organizer_id',
        'title',
        'slug',
        'description',
        'banner_image',
        'venue_name',
        'address',
        'map_url',
        'ceremony_date',
        'voting_start',
        'voting_end',
        'status',
        'show_results',
        'is_featured',
        'admin_share_percent',
        'country',
        'region',
        'city',
        'phone',
        'website',
        'facebook',
        'twitter',
        'instagram',
        'video_url',
        'views',
    ];

    /**
     * The attributes that should be cast.
     * @var array
     */
    protected $casts = [
        'ceremony_date' => 'datetime',
        'voting_start' => 'datetime',
        'voting_end' => 'datetime',
        'is_featured' => 'boolean',
        'show_results' => 'boolean',
        'views' => 'integer',
        'admin_share_percent' => 'decimal:2',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    /**
     * Get the organizer that owns the award.
     */
    public function organizer()
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    /**
     * Get the award categories for this award show.
     */
    public function categories()
    {
        return $this->hasMany(AwardCategory::class, 'award_id')
                    ->orderBy('display_order');
    }

    /**
     * Get all nominees for this award show.
     */
    public function nominees()
    {
        return $this->hasMany(AwardNominee::class, 'award_id');
    }

    /**
     * Get all votes for this award show.
     */
    public function votes()
    {
        return $this->hasMany(AwardVote::class, 'award_id');
    }

    /**
     * Get the images for this award show.
     */
    public function images()
    {
        return $this->hasMany(AwardImage::class, 'award_id');
    }

    /* -----------------------------------------------------------------
     |  Scopes
     | -----------------------------------------------------------------
     */

    /**
     * Scope to get published awards.
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Scope to get featured awards.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to get upcoming awards (ceremony not yet happened).
     */
    public function scopeUpcoming($query)
    {
        return $query->where('ceremony_date', '>', \Illuminate\Support\Carbon::now());
    }

    /**
     * Scope to get awards currently open for voting.
     */
    public function scopeVotingOpen($query)
    {
        $now = \Illuminate\Support\Carbon::now();
        return $query->where('voting_start', '<=', $now)
                     ->where('voting_end', '>=', $now)
                     ->where('status', self::STATUS_PUBLISHED);
    }

    /* -----------------------------------------------------------------
     |  Helper Methods
     | -----------------------------------------------------------------
     */

    /**
     * Check if award is published.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Check if voting is currently open.
     */
    public function isVotingOpen(): bool
    {
        $now = \Illuminate\Support\Carbon::now();
        return $this->voting_start <= $now && 
               $this->voting_end >= $now &&
               $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Check if voting has ended.
     */
    public function isVotingClosed(): bool
    {
        return \Illuminate\Support\Carbon::now() > $this->voting_end;
    }

    /**
     * Check if ceremony has passed.
     */
    public function isCeremonyComplete(): bool
    {
        return \Illuminate\Support\Carbon::now() > $this->ceremony_date;
    }

    /**
     * Get total number of votes across all categories.
     */
    public function getTotalVotes(): int
    {
        return (int) $this->votes()
                    ->where('status', 'paid')
                    ->sum('number_of_votes');
    }

    /**
     * Get total revenue from all votes.
     */
    public function getTotalRevenue(): float
    {
        $total = 0;
        
        foreach ($this->categories as $category) {
            $categoryVotes = $category->votes()
                                      ->where('status', 'paid')
                                      ->sum('number_of_votes');
            $total += $categoryVotes * $category->cost_per_vote;
        }
        
        return $total;
    }

    /**
     * Toggle show_results flag.
     */
    public function toggleShowResults(): bool
    {
        $this->show_results = !$this->show_results;
        $this->save();
        return $this->show_results;
    }

    /**
     * Get award details formatted for frontend.
     * @param string|null $userRole User role (organizer, admin, or null for public)
     * @param int|null $userId User ID for ownership verification
     */
    public function getFullDetails(?string $userRole = null, ?int $userId = null): array
    {
        // Load relationships
        $this->load(['organizer.user', 'categories.nominees', 'images']);
        
        // Check if user is organizer or admin
        $isOrganizerOrAdmin = in_array($userRole, ['organizer', 'admin']);
        
        // If organizer, verify ownership
        if ($userRole === 'organizer' && $userId) {
            $organizer = \App\Models\Organizer::where('user_id', $userId)->first();
            $isOrganizerOrAdmin = $organizer && $this->organizer_id === $organizer->id;
        }

        $details = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'venue' => $this->venue_name,
            'location' => $this->address,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'ceremony_date' => $this->ceremony_date ? $this->ceremony_date->format('Y-m-d') : null,
            'ceremony_time' => $this->ceremony_date ? $this->ceremony_date->format('g:i A') : null,
            'voting_start' => $this->voting_start ? $this->voting_start->toIso8601String() : null,
            'voting_end' => $this->voting_end ? $this->voting_end->toIso8601String() : null,
            'is_voting_open' => $this->isVotingOpen(),
            'is_voting_closed' => $this->isVotingClosed(),
            'image' => $this->banner_image ?? '',
            'mapUrl' => $this->map_url,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'show_results' => $this->show_results,
            'views' => $this->views,
            'categories' => $this->categories->map(function ($category) {
                $categoryData = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'image' => $category->image,
                    'cost_per_vote' => (float) $category->cost_per_vote,
                    'voting_start' => $category->voting_start,
                    'voting_end' => $category->voting_end,
                    'status' => $category->status,
                    'display_order' => $category->display_order,
                    'is_voting_open' => $category->isVotingOpen(),
                    'nominees' => $category->nominees->map(function ($nominee) {
                        $nomineeData = [
                            'id' => $nominee->id,
                            'name' => $nominee->name,
                            'description' => $nominee->description,
                            'image' => $nominee->image,
                            'display_order' => $nominee->display_order,
                        ];
                        
                        // Include vote count for everyone if show_results is true
                        if ($this->show_results) {
                            $nomineeData['total_votes'] = $nominee->getTotalVotes();
                        }
                        
                        return $nomineeData;
                    })->toArray(),
                ];
                
                return $categoryData;
            })->toArray(),
            'organizer' => null,
            'contact' => [
                'phone' => $this->phone,
                'website' => $this->website,
            ],
            'socialMedia' => [
                'facebook' => $this->facebook,
                'twitter' => $this->twitter,
                'instagram' => $this->instagram,
            ],
            'videoUrl' => $this->video_url,
        ];

        // Add organizer info if available
        if ($this->organizer) {
            $details['organizer'] = [
                'id' => $this->organizer->id,
                'name' => $this->organizer->organization_name ?? ($this->organizer->user->name ?? 'Organizer'),
                'avatar' => $this->organizer->profile_image ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->organizer->organization_name ?? 'Org'),
                'bio' => $this->organizer->bio,
                'verified' => true,
            ];
        }

        // Add images if available
        if ($this->images->count() > 0) {
            $details['images'] = $this->images->pluck('image_path')->toArray();
        }

        // Add vote statistics - only for organizers/admins
        if ($isOrganizerOrAdmin) {
            // Organizers and admins always see stats
            $details['total_votes'] = $this->getTotalVotes();
            $details['total_revenue'] = $this->getTotalRevenue();
        }

        return $details;
    }

    /**
     * Get summary for list views.
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'venue' => $this->venue_name,
            'location' => $this->address,
            'ceremony_date' => $this->ceremony_date ? $this->ceremony_date->format('Y-m-d') : null,
            'ceremony_time' => $this->ceremony_date ? $this->ceremony_date->format('g:i A') : null,
            'is_voting_open' => $this->isVotingOpen(),
            'image' => $this->banner_image ?? '',
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'categories_count' => $this->categories()->count(),
            'total_votes' => $this->getTotalVotes(),
        ];
    }

    /**
     * Automatically update published awards to "completed" if ceremony date has passed.
     * This method can be called from anywhere to ensure awards have the correct status.
     * 
     * @param int|null $organizerId Optional organizer ID to limit updates to specific organizer
     * @return int Number of awards updated
     */
    public static function autoUpdateCompletedStatuses(?int $organizerId = null): int
    {
        $now = \Illuminate\Support\Carbon::now();
        
        // Build query for published awards with past ceremony dates
        $query = self::where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('ceremony_date')
            ->where('ceremony_date', '<', $now);
        
        // Optionally filter by organizer
        if ($organizerId !== null) {
            $query->where('organizer_id', $organizerId);
        }
        
        // Get awards that need updating
        $awards = $query->get();
        
        $updatedCount = 0;
        foreach ($awards as $award) {
            $award->status = self::STATUS_COMPLETED;
            $award->save();
            $updatedCount++;
        }
        
        return $updatedCount;
    }

    /* -----------------------------------------------------------------
     |  Revenue Share Methods
     | -----------------------------------------------------------------
     */

    /**
     * Get admin share percentage for this award
     */
    public function getAdminSharePercent(): float
    {
        return (float) ($this->admin_share_percent ?? PlatformSetting::getDefaultAwardAdminShare());
    }

    /**
     * Get organizer share percentage (100 - admin share)
     */
    public function getOrganizerSharePercent(): float
    {
        return 100 - $this->getAdminSharePercent();
    }

    /**
     * Calculate revenue split for an amount
     * 
     * @param float $grossAmount The total vote purchase amount
     * @return array Contains admin_amount, organizer_amount, payment_fee
     */
    public function calculateRevenueSplit(float $grossAmount): array
    {
        $adminSharePercent = $this->getAdminSharePercent();
        $organizerSharePercent = 100 - $adminSharePercent;
        $paystackFeePercent = PlatformSetting::getPaystackFeePercent();

        $organizerAmount = $grossAmount * ($organizerSharePercent / 100);
        $adminGross = $grossAmount * ($adminSharePercent / 100);
        $paymentFee = $grossAmount * ($paystackFeePercent / 100);
        $adminAmount = $adminGross - $paymentFee; // Admin absorbs payment fee

        return [
            'admin_share_percent' => $adminSharePercent,
            'organizer_amount' => round($organizerAmount, 2),
            'admin_amount' => round(max(0, $adminAmount), 2), // Ensure non-negative
            'payment_fee' => round($paymentFee, 2),
        ];
    }

    /**
     * Get payout requests for this award
     */
    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class, 'award_id');
    }

    /**
     * Get transactions for this award
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'award_id');
    }
}

