<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PasswordReset Model
 * 
 * Represents a password reset token
 */
class PasswordReset extends Model
{
    protected $table = 'password_resets';
    public $timestamps = false; // Only has created_at
    public $incrementing = false; // No primary key

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'email',
        'token',
        'created_at'
    ];

    /**
     * The attributes that should be cast
     */
    protected $casts = [
        'created_at' => 'datetime'
    ];

    /**
     * Check if token is expired (1 hour expiry)
     */
    public function isExpired(): bool
    {
        return $this->created_at->addHour()->isPast();
    }

    /**
     * Scope to get valid (non-expired) tokens
     */
    public function scopeValid($query)
    {
        return $query->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-1 hour')));
    }

    /**
     * Scope to get tokens for specific email
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Find valid token for email
     */
    public static function findValidToken(string $email, string $token): ?self
    {
        return static::where('email', $email)
                    ->where('token', hash('sha256', $token))
                    ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-1 hour')))
                    ->first();
    }

    /**
     * Cleanup expired tokens (should be run via cron)
     */
    public static function cleanupExpired(): int
    {
        return static::where('created_at', '<', date('Y-m-d H:i:s', strtotime('-1 hour')))->delete();
    }

    /**
     * Delete all tokens for an email
     */
    public static function deleteForEmail(string $email): int
    {
        return static::where('email', $email)->delete();
    }
}
