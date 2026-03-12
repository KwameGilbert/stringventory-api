<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AuditLog Model
 * 
 * Tracks authentication and security events
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    public $timestamps = false; // Only has created_at
    const CREATED_AT = 'created_at';

    /**
     * Available actions
     */
    const ACTION_LOGIN = 'login';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_LOGOUT = 'logout';
    const ACTION_REGISTER = 'register';
    const ACTION_PASSWORD_RESET_REQUESTED = 'password_reset_requested';
    const ACTION_PASSWORD_RESET_COMPLETED = 'password_reset_completed';
    const ACTION_PASSWORD_CHANGED = 'password_changed';
    const ACTION_EMAIL_VERIFIED = 'email_verified';

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at'
    ];

    /**
     * The attributes that should be cast
     */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    /**
     * Relationship to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent logs
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>', date('Y-m-d H:i:s', strtotime("-$days days")));
    }

    /**
     * Scope to get failed login attempts
     */
    public function scopeFailedLogins($query)
    {
        return $query->where('action', self::ACTION_LOGIN_FAILED);
    }

    /**
     * Log an event (static helper)
     */
    public static function logEvent(
        ?int $userId,
        string $action,
        string $ipAddress,
        ?string $userAgent = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata
        ]);
    }

    /**
     * Get recent failed login attempts for an IP
     */
    public static function recentFailedAttemptsFromIP(string $ipAddress, int $minutes = 15): int
    {
        return static::where('action', self::ACTION_LOGIN_FAILED)
                    ->where('ip_address', $ipAddress)
                    ->where('created_at', '>', date('Y-m-d H:i:s', strtotime("-$minutes minutes")))
                    ->count();
    }

    /**
     * Cleanup old logs (should be run via cron)
     */
    public static function cleanupOld(int $days = 90): int
    {
        return static::where('created_at', '<', date('Y-m-d H:i:s', strtotime("-$days days")))->delete();
    }
}
