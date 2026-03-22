<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notification Model
 * 
 * @property int $id
 * @property int|null $userId
 * @property string $type
 * @property string $title
 * @property string $message
 * @property array|null $data
 * @property bool $isRead
 * @property \Illuminate\Support\Carbon|null $readAt
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'type',
        'title',
        'message',
        'data',
        'isRead',
        'readAt',
    ];

    protected $casts = [
        'data' => 'array',
        'isRead' => 'boolean',
        'readAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */
    
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
