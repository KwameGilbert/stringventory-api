<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AuditLog Model
 */
class AuditLog extends Model
{
    protected $table = 'auditLogs';
    public $timestamps = false;
    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'userId',
        'action',
        'ipAddress',
        'userAgent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'createdAt' => 'datetime',
        'userId' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
