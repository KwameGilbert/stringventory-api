<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * User Model
 * 
 * @property int $id
 * @property string $firstName
 * @property string $lastName
 * @property string $role
 * @property string $email
 * @property string|null $phone
 * @property string $status
 * @property string $passwordHash
 * @property string|null $profileImage
 * @property bool $emailVerified
 * @property \Illuminate\Support\Carbon|null $lastLogin
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false; // Using custom datetime columns

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    // Roles
    const ROLE_CEO = 'ceo';
    const ROLE_MANAGER = 'manager';
    const ROLE_SALESPERSON = 'salesperson';

    // Status
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'firstName',
        'lastName',
        'role',
        'email',
        'phone',
        'status',
        'passwordHash',
        'profileImage',
        'emailVerified',
        'lastLogin',
        'mustChangePassword',
    ];

    protected $hidden = [
        'passwordHash',
    ];

    protected $casts = [
        'emailVerified' => 'boolean',
        'mustChangePassword' => 'boolean',
        'lastLogin' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /**
     * Auto-hash password with Argon2id on set.
     */
    public function setPasswordHashAttribute($value)
    {
        if (preg_match('/^(\$argon2|\$2y\$)/', $value)) {
            $this->attributes['passwordHash'] = $value;
        } else {
            $this->attributes['passwordHash'] = password_hash($value, PASSWORD_ARGON2ID);
        }
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */
    
    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'userId');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'userId');
    }
}