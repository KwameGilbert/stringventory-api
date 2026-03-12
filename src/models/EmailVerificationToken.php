<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EmailVerificationToken Model
 * 
 * Stores email verification tokens for account verification
 * 
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $token
 * @property \Carbon\Carbon $expires_at
 * @property bool $used
 * @property \Carbon\Carbon|null $used_at
 * @property \Carbon\Carbon $created_at
 */
class EmailVerificationToken extends Model
{
    protected $table = 'email_verification_tokens';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'token',
        'expires_at',
        'used',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that owns the verification token
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the token is valid (not used and not expired)
     */
    public function isValid(): bool
    {
        return !$this->used && !$this->isExpired();
    }

    /**
     * Mark the token as used
     */
    public function markAsUsed(): bool
    {
        return $this->update([
            'used' => true,
            'used_at' => Carbon::now(),
        ]);
    }

    /**
     * Generate a new verification token for a user
     * 
     * @param User $user The user to generate the token for
     * @param int $expiryHours Number of hours until the token expires (default: 24)
     * @return self
     */
    public static function generateForUser(User $user, int $expiryHours = 24): self
    {
        // Invalidate any existing unused tokens for this user
        self::where('user_id', $user->id)
            ->where('used', false)
            ->update(['used' => true, 'used_at' => Carbon::now()]);

        // Generate a secure random token
        $token = bin2hex(random_bytes(32));

        return self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => hash('sha256', $token),
            'expires_at' => Carbon::now()->addHours($expiryHours),
            'used' => false,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Find a valid token by the unhashed token string
     * 
     * @param string $token The unhashed token
     * @return self|null
     */
    public static function findByToken(string $token): ?self
    {
        $hashedToken = hash('sha256', $token);
        
        return self::where('token', $hashedToken)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    /**
     * Get the plain text token (only available at creation time)
     * This method is used to get the token for URL generation before hashing
     * 
     * @param User $user
     * @param int $expiryHours
     * @return array ['model' => EmailVerificationToken, 'plainToken' => string]
     */
    public static function createWithPlainToken(User $user, int $expiryHours = 24): array
    {
        // Invalidate any existing unused tokens for this user
        self::where('user_id', $user->id)
            ->where('used', false)
            ->update(['used' => true, 'used_at' => Carbon::now()]);

        // Generate a secure random token
        $plainToken = bin2hex(random_bytes(32));

        $model = self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addHours($expiryHours),
            'used' => false,
            'created_at' => Carbon::now(),
        ]);

        return [
            'model' => $model,
            'plainToken' => $plainToken,
        ];
    }
}
