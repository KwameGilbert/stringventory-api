<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RefreshToken Model
 * 
 * Represents a JWT refresh token stored in the database
 * Enables token revocation and multi-device session management
 */
class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';
    protected $primaryKey = 'id';
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'user_id',
        'token_hash',
        'device_name',
        'ip_address',
        'user_agent',
        'expires_at',
        'revoked',
        'revoked_at'
    ];

    /**
     * The attributes that should be cast
     */
    protected $casts = [
        'revoked' => 'boolean',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if token is still valid
     */
    public function isValid(): bool
    {
        if ($this->revoked) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Revoke this refresh token
     */
    public function revoke(): void
    {
        $this->update([
            'revoked' => true,
            'revoked_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Scope to get only valid tokens
     */
    public function scopeValid($query)
    {
        return $query->where('revoked', false)
                    ->where('expires_at', '>', date('Y-m-d H:i:s'));
    }

    /**
     * Scope to get tokens for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Cleanup expired tokens (should be run via cron)
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
    }

    /**
     * Revoke all tokens for a user
     */
    public static function revokeAllForUser(int $userId): int
    {
        return static::where('user_id', $userId)
                    ->where('revoked', false)
                    ->update([
                        'revoked' => true,
                        'revoked_at' => date('Y-m-d H:i:s')
                    ]);
    }
}
