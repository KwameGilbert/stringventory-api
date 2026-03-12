<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Organizer Model
 * 
 * Represents an event organizer profile linked to a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $organization_name
 * @property string|null $bio
 * @property string|null $profile_image
 * @property string|null $social_facebook
 * @property string|null $social_instagram
 * @property string|null $social_twitter
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Organizer extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'organizers';

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
        'user_id',
        'organization_name',
        'bio',
        'profile_image',
        'social_facebook',
        'social_instagram',
        'social_twitter',
    ];

    /**
     * The attributes that should be cast.
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    /**
     * Get the user that owns the organizer profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the events for this organizer.
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    /**
     * Get the awards for this organizer.
     */
    public function awards()
    {
        return $this->hasMany(Award::class, 'organizer_id');
    }

    /* -----------------------------------------------------------------
     |  Static Search Methods
     | -----------------------------------------------------------------
     */

    /**
     * Find organizer by user ID.
     * @param int $userId
     * @return Organizer|null
     */
    public static function findByUserId(int $userId): ?Organizer
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * Search organizers by organization name.
     * @param string $query
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function searchByName(string $query)
    {
        return static::where('organization_name', 'LIKE', "%{$query}%")->get();
    }

    /**
     * Get all organizers with complete profiles.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getCompleteProfiles()
    {
        return static::whereNotNull('organization_name')
                    ->whereNotNull('bio')
                    ->whereNotNull('profile_image')
                    ->get();
    }

    /**
     * Get organizers with social media presence.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getWithSocialMedia()
    {
        return static::where(function($query) {
            $query->whereNotNull('social_facebook')
                  ->orWhereNotNull('social_instagram')
                  ->orWhereNotNull('social_twitter');
        })->get();
    }

    /* -----------------------------------------------------------------
     |  Query Scopes
     | -----------------------------------------------------------------
     */

    /**
     * Scope to get organizers with profile images.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithProfileImage($query)
    {
        return $query->whereNotNull('profile_image');
    }

    /**
     * Scope to get organizers with complete profiles.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeComplete($query)
    {
        return $query->whereNotNull('organization_name')
                    ->whereNotNull('bio')
                    ->whereNotNull('profile_image');
    }

    /**
     * Scope to get organizers with social media.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithSocial($query)
    {
        return $query->where(function($q) {
            $q->whereNotNull('social_facebook')
              ->orWhereNotNull('social_instagram')
              ->orWhereNotNull('social_twitter');
        });
    }

    /* -----------------------------------------------------------------
     |  Helper Methods
     | -----------------------------------------------------------------
     */

    /**
     * Check if organizer has a profile image.
     * @return bool
     */
    public function hasProfileImage(): bool
    {
        return !is_null($this->profile_image) && !empty($this->profile_image);
    }

    /**
     * Check if organizer profile is complete.
     * @return bool
     */
    public function hasCompletedProfile(): bool
    {
        return !is_null($this->organization_name)
            && !is_null($this->bio)
            && !is_null($this->profile_image);
    }

    /**
     * Check if organizer has any social media links.
     * @return bool
     */
    public function hasSocialLinks(): bool
    {
        return !is_null($this->social_facebook)
            || !is_null($this->social_instagram)
            || !is_null($this->social_twitter);
    }

    /**
     * Get social media links as an array.
     * @return array
     */
    public function getSocialLinks(): array
    {
        return array_filter([
            'facebook' => $this->social_facebook,
            'instagram' => $this->social_instagram,
            'twitter' => $this->social_twitter,
        ]);
    }

    /**
     * Get the count of social media platforms connected.
     * @return int
     */
    public function getSocialLinksCount(): int
    {
        return count($this->getSocialLinks());
    }

    /**
     * Update organizer profile.
     * @param array $data
     * @return bool
     */
    public function updateProfile(array $data): bool
    {
        $allowedFields = [
            'organization_name',
            'bio',
            'profile_image',
            'social_facebook',
            'social_instagram',
            'social_twitter',
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        return $this->update($updateData);
    }

    /**
     * Get profile completion percentage.
     * @return int
     */
    public function getProfileCompletionPercentage(): int
    {
        $fields = [
            'organization_name',
            'bio',
            'profile_image',
            'social_facebook',
            'social_instagram',
            'social_twitter',
        ];

        $completedFields = 0;
        foreach ($fields as $field) {
            if (!is_null($this->$field) && !empty($this->$field)) {
                $completedFields++;
            }
        }

        return (int) (($completedFields / count($fields)) * 100);
    }

    /**
     * Delete organizer profile and associated data.
     * @return bool|null
     * @throws \Exception
     */
    public function deleteProfile(): ?bool
    {
        // Delete profile image if exists
        if ($this->hasProfileImage()) {
            $this->deleteProfileImage();
        }

        return $this->delete();
    }

    /**
     * Delete profile image (placeholder for actual file deletion logic).
     * @return void
     */
    protected function deleteProfileImage(): void
    {
        // TODO: Implement actual file deletion logic
        // Example: Storage::delete($this->profile_image);
        // For now, just unset the field
        $this->update(['profile_image' => null]);
    }

    /**
     * Get organizer's public profile data.
     * @return array
     */
    public function getPublicProfile(): array
    {
        return [
            'id' => $this->id,
            'organization_name' => $this->organization_name,
            'bio' => $this->bio,
            'profile_image' => $this->profile_image,
            'social_links' => $this->getSocialLinks(),
            'profile_complete' => $this->hasCompletedProfile(),
            'profile_completion_percentage' => $this->getProfileCompletionPercentage(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Get organizer's full data including user info.
     * @return array
     */
    public function getFullProfile(): array
    {
        $profile = $this->getPublicProfile();
        
        if ($this->user) {
            $profile['user'] = [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role,
            ];
        }

        return $profile;
    }
}