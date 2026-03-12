<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Attendee Model
 * 
 * Represents an event attendee profile linked to a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $profile_image
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Attendee extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'attendees';

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
        'first_name',
        'last_name',
        'email',
        'phone',
        'bio',
        'profile_image',
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
     * Get the user that owns the attendee profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* -----------------------------------------------------------------
     |  Static Search Methods
     | -----------------------------------------------------------------
     */

    /**
     * Find attendee by user ID.
     * @param int $userId
     * @return Attendee|null
     */
    public static function findByUserId(int $userId): ?Attendee
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * Find attendee by email.
     * @param string $email
     * @return Attendee|null
     */
    public static function findByEmail(string $email): ?Attendee
    {
        return static::where('email', $email)->first();
    }

    /* -----------------------------------------------------------------
     |  Helper Methods
     | -----------------------------------------------------------------
     */

    /**
     * Check if attendee has a profile image.
     * @return bool
     */
    public function hasProfileImage(): bool
    {
        return !is_null($this->profile_image) && !empty($this->profile_image);
    }

    /**
     * Get full name.
     * @return string
     */
    public function getFullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Update attendee profile.
     * @param array $data
     * @return bool
     */
    public function updateProfile(array $data): bool
    {
        $allowedFields = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'bio',
            'profile_image',
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));

        return $this->update($updateData);
    }

    /**
     * Delete attendee profile and associated data.
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
     * Get attendee's public profile data.
     * @return array
     */
    public function getPublicProfile(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'bio' => $this->bio,
            'profile_image' => $this->profile_image,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Get attendee's full data including user info.
     * @return array
     */
    public function getFullProfile(): array
    {
        $profile = $this->getPublicProfile();
        $profile['email'] = $this->email;
        $profile['phone'] = $this->phone;

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
