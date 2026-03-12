<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RefreshToken Model
 */
class RefreshToken extends Model
{
    protected $table = 'refreshTokens';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'tokenHash',
        'deviceName',
        'ipAddress',
        'userAgent',
        'expiresAt',
        'revoked',
        'revokedAt',
    ];

    protected $casts = [
        'userId' => 'integer',
        'revoked' => 'boolean',
        'expiresAt' => 'datetime',
        'revokedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
