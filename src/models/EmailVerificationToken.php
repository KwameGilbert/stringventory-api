<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EmailVerificationToken Model
 */
class EmailVerificationToken extends Model
{
    protected $table = 'emailVerificationTokens';
    public $timestamps = false;
    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'userId',
        'email',
        'token',
        'expiresAt',
        'used'
    ];

    protected $casts = [
        'expiresAt' => 'datetime',
        'createdAt' => 'datetime',
        'used' => 'boolean'
    ];

    /**
     * Create a new verification token for a user
     */
    public static function createWithPlainToken(User $user, int $expiryHours = 24): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $token = self::create([
            'userId' => $user->id,
            'email' => $user->email,
            'token' => $tokenHash,
            'expiresAt' => date('Y-m-d H:i:s', time() + ($expiryHours * 3600)),
            'used' => false
        ]);

        return [
            'token' => $token,
            'plainToken' => $plainToken
        ];
    }

    /**
     * Find a valid token by its plain text version
     */
    public static function findByToken(string $plainToken): ?self
    {
        $tokenHash = hash('sha256', $plainToken);

        return self::where('token', $tokenHash)
            ->where('used', false)
            ->where('expiresAt', '>', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): bool
    {
        return $this->update(['used' => true]);
    }
}
