<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EventImage Model
 * 
 * Represents an image in an event's gallery.
 *
 * @property int $id
 * @property int $event_id
 * @property string $image_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class EventImage extends Model
{
    protected $table = 'event_images';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'event_id',
        'image_path',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the event that owns the image.
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
