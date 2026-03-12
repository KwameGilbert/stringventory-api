<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * User Model
 * 
 * Represents a user in the system.
 * Merges functionality for authentication, relationships, and status checks.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property string|null $remember_token
 * @property string $role
 * @property bool $email_verified
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $status
 * @property bool $first_login
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 */
class User extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'users';

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

    // Roles
    const ROLE_ADMIN = 'admin';
    const ROLE_ORGANIZER = 'organizer';
    const ROLE_ATTENDEE = 'attendee';
    const ROLE_POS = 'pos';
    const ROLE_SCANNER = 'scanner';

    // Status
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'remember_token',
        'role',
        'email_verified',
        'email_verified_at',
        'status',
        'first_login',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     * @var array
     */
    protected $casts = [
        'email_verified' => 'boolean',
        'first_login' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  Mutators & Accessors
     | -----------------------------------------------------------------
     */

    /**
     * Auto-hash password with Argon2id on set.
     * * @param string $value
     * @return void
     */
    public function setPasswordAttribute($value)
    {
        // Check if value is already hashed (starts with $argon2 or $2y$)
        if (preg_match('/^(\$argon2|\$2y\$)/', $value)) {
            $this->attributes['password'] = $value;
        } else {
            // Hash with Argon2id
            $this->attributes['password'] = password_hash($value, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,  // 64 MB
                'time_cost' => 4,        // 4 iterations
                'threads' => 2           // 2 parallel threads
            ]);
        }
    }

    /* -----------------------------------------------------------------
     |  Static Search Methods
     | -----------------------------------------------------------------
     */

    /**
     * Get user by email.
     * * @param string $email
     * @return User|null
     */
    public static function findByEmail(string $email): ?User
    {
        return static::where('email', $email)->first();
    }

    /**
     * Check if email exists.
     * * @param string $email Email to check
     * @param int|null $excludeId Optional user ID to exclude (useful for updates)
     * @return bool
     */
    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = static::where('email', $email);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    /**
     * Get all active users.
     * * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveUsers()
    {
        return static::where('status', 'active')->get();
    }

    /* -----------------------------------------------------------------
     |  Helper Methods
     | -----------------------------------------------------------------
     */

    /**
     * Check if email is verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Check if user is organizer.
     */
    public function isOrganizer(): bool
    {
        return $this->role === 'organizer';
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */
    
    public function organizer()
    {
        return $this->hasOne(Organizer::class, 'user_id');
    }

    public function attendee()
    {
        return $this->hasOne(Attendee::class, 'user_id');
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}