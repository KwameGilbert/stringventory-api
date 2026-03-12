<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EventReview Model
 * 
 * Represents a review/rating for an event.
 *
 * @property int $id
 * @property int $event_id
 * @property int $reviewer_id
 * @property int $rating
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class EventReview extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'event_reviews';

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

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'event_id',
        'reviewer_id',
        'rating',
        'comment',
    ];

    /**
     * The attributes that should be cast.
     * @var array
     */
    protected $casts = [
        'event_id' => 'integer',
        'reviewer_id' => 'integer',
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    /**
     * Get the event that owns the review.
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Get the user (reviewer) that owns the review.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /* -----------------------------------------------------------------
     |  Helper Methods
     | -----------------------------------------------------------------
     */

    /**
     * Check if rating is valid (1-5 stars).
     */
    public function isValidRating(): bool
    {
        return $this->rating >= 1 && $this->rating <= 5;
    }
}
