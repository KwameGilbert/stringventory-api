<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\EventReview;

/**
 * Event Model
 * 
 * Represents an event created by an organizer.
 *
 * @property int $id
 * @property int $organizer_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int|null $event_type_id

 * @property string|null $venue_name
 * @property string|null $address
 * @property string|null $map_url
 * @property string|null $banner_image
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon $end_time
 * @property string $status
 * @property bool $is_featured
 * @property float $admin_share_percent
 * @property string|null $audience
 * @property string|null $language
 * @property array|null $tags
 * @property string|null $website
 * @property string|null $facebook
 * @property string|null $twitter
 * @property string|null $instagram
 * @property string|null $phone
 * @property string|null $video_url
 * @property string $country
 * @property string $region
 * @property string $city
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Event extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'events';

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

    // Event Status Constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PUBLISHED = 'published';
    const STATUS_CANCELLED = 'cancelled';
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
        'event_type_id',
        'venue_name',
        'address',
        'map_url',
        'banner_image',
        'start_time',
        'end_time',
        'status',
        'is_featured',
        'admin_share_percent',
        'audience',
        'language',
        'tags',
        'website',
        'facebook',
        'twitter',
        'instagram',
        'phone',
        'video_url',
        'country',
        'region',
        'city',
        'views',
    ];

    /**
     * The attributes that should be cast.
     * @var array
     */
    protected $casts = [
        'organizer_id' => 'integer',
        'event_type_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'tags' => 'array',
        'is_featured' => 'boolean',
        'admin_share_percent' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    /**
     * Get the organizer that owns the event.
     */
    public function organizer()
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    /**
     * Get the event type.
     */
    public function eventType()
    {
        return $this->belongsTo(EventType::class, 'event_type_id');
    }

    /**
     * Get the event images.
     */
    public function images()
    {
        return $this->hasMany(EventImage::class, 'event_id');
    }

    /**
     * Get the ticket types for this event.
     */
    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class, 'event_id');
    }

    /**
     * Get the reviews for this event.
     */
    public function reviews()
    {
        return $this->hasMany(EventReview::class, 'event_id');
    }

    /**
     * Get the tickets for this event.
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'event_id');
    }



    /* -----------------------------------------------------------------
     |  Scopes
     | -----------------------------------------------------------------
     */

    /**
     * Scope to get published events.
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Scope to get featured events.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to get upcoming events.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', \Illuminate\Support\Carbon::now());
    }

    /* -----------------------------------------------------------------
     |  Helper Methods
     | -----------------------------------------------------------------
     */

    /**
     * Check if event is published.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }



    /**
     * Get formatted price from lowest ticket type
     */
    public function getLowestPrice(): ?float
    {
        $lowestTicket = $this->ticketTypes()
            ->where('status', TicketType::STATUS_ACTIVE)
            ->orderBy('price', 'asc')
            ->first();

        return $lowestTicket ? (float) $lowestTicket->price : null;
    }

    /**
     * Get event details formatted for frontend (matching mock data structure)
     */
    public function getFullDetails(): array
    {
        // Load relationships
        $this->load(['organizer.user', 'ticketTypes', 'images', 'eventType']);

        $details = [
            'id' => $this->id,
            'title' => $this->title,
            'eventSlug' => $this->slug,
            'description' => $this->description,
            'venue' => $this->venue_name,
            'location' => $this->address,
            'country' => $this->country ?? '',
            'region' => $this->region ?? '',
            'city' => $this->city ?? '',
            'date' => $this->start_time ? $this->start_time->format('Y-m-d') : null,
            'time' => $this->start_time ? $this->start_time->format('g:i A') : null,
            'price' => $this->getLowestPrice() ? 'GH₵' . number_format($this->getLowestPrice(), 2) : 'Free',
            'numericPrice' => $this->getLowestPrice() ?? 0,
            'category' => $this->eventType ? $this->eventType->id : null,
            'categorySlug' => $this->eventType ? $this->eventType->slug : null,
            'categoryName' => $this->eventType ? $this->eventType->name : null,
            'audience' => $this->audience,
            'language' => $this->language,
            'image' => $this->banner_image,
            'mapUrl' => $this->map_url,
            'tags' => $this->tags ?? [],
            'ticketTypes' => $this->ticketTypes->map(function ($tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'price' => (float) $tt->price,
                    'salePrice' => $tt->sale_price ? (float) $tt->sale_price : null,
                    'onSale' => $tt->isAvailable(),
                    'availableQuantity' => $tt->remaining,
                    'maxPerAttendee' => $tt->max_per_user,
                    'description' => $tt->description,
                    'ticketImage' => $tt->ticket_image,
                    'saleStartDate' => $tt->sale_start,
                    'saleEndDate' => $tt->sale_end,
                    'quantity' => $tt->quantity,
                    'sold' => $tt->quantity - $tt->remaining,
                ];
            })->toArray(),
            'organizer' => null,
            'contact' => [
                'email' => $this->organizer ? ($this->organizer->user->email ?? null) : null,
                'phone' => $this->phone,
                'website' => $this->website,
            ],
            'socialMedia' => [
                'facebook' => $this->facebook,
                'twitter' => $this->twitter,
                'instagram' => $this->instagram,
            ],
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'website' => $this->website,
            'facebook' => $this->facebook,
            'twitter' => $this->twitter,
            'instagram' => $this->instagram,
            'phone' => $this->phone,
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
                'followers' => 0, // Could add followers count
                'eventsOrganized' => $this->organizer->events()->count(),
                'rating' => 4.5, // Could calculate from reviews
            ];
        }

        // Add images if available
        if ($this->images->count() > 0) {
            $details['images'] = $this->images->pluck('image_path')->toArray();
        }

        return $details;
    }

    /**
     * Get summary for list views (less data than full details)
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'eventSlug' => $this->slug,
            'venue' => $this->venue_name,
            'location' => $this->address,
            'date' => $this->start_time ? $this->start_time->format('Y-m-d') : null,
            'time' => $this->start_time ? $this->start_time->format('g:i A') : null,
            'price' => $this->getLowestPrice() ? 'GH₵' . number_format($this->getLowestPrice(), 2) : 'Free',
            'numericPrice' => $this->getLowestPrice() ?? 0,
            'category' => $this->eventType ? $this->eventType->name : null,
            'image' => $this->banner_image,
            'status' => $this->status,
        ];
    }

    /* -----------------------------------------------------------------
     |  Revenue Share Methods
     | -----------------------------------------------------------------
     */

    /**
     * Get admin share percentage for this event
     */
    public function getAdminSharePercent(): float
    {
        return (float) ($this->admin_share_percent ?? PlatformSetting::getDefaultEventAdminShare());
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
     * @param float $grossAmount The total sale amount
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
     * Get payout requests for this event
     */
    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class, 'event_id');
    }

    /**
     * Get transactions for this event
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'event_id');
    }
}

