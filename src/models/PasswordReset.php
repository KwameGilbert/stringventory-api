<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PasswordReset Model
 */
class PasswordReset extends Model
{
    protected $table = 'passwordResets';
    public $timestamps = false;
    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'email',
        'token',
        'createdAt'
    ];

    protected $casts = [
        'createdAt' => 'datetime',
    ];

    /**
     * Delete all records for a specific email
     */
    public static function deleteForEmail(string $email): int
    {
        return self::where('email', $email)->delete();
    }

    /**
     * Find a valid token for an email
     * Tokens expire after 1 hour
     */
    public static function findValidToken(string $email, string $plainToken): ?self
    {
        $tokenHash = hash('sha256', $plainToken);
        $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);

        return self::where('email', $email)
            ->where('token', $tokenHash)
            ->where('createdAt', '>', $oneHourAgo)
            ->first();
    }
}
