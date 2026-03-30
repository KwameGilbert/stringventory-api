<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Psr\Http\Message\ServerRequestInterface as Request;

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

    /**
     * Log an activity. Automatically extracts IP and user agent from the request.
     * Call this AFTER a successful save/commit — never inside a transaction block.
     */
    public static function log(
        Request $request,
        ?int $userId,
        string $action,
        array $extra = []
    ): void {
        $serverParams = $request->getServerParams();
        self::create([
            'userId'    => $userId,
            'action'    => $action,
            'ipAddress' => $serverParams['REMOTE_ADDR'] ?? '0.0.0.0',
            'userAgent' => $request->getHeaderLine('User-Agent'),
            'metadata'  => !empty($extra) ? $extra : null,
        ]);
    }
}
