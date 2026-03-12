<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ScannerAssignment Model
 * 
 * Represents the assignment of a scanner user to an event.
 *
 * @property int $id
 * @property int $user_id
 * @property int $event_id
 * @property int $organizer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ScannerAssignment extends Model
{
    protected $table = 'scanner_assignments';
    protected $primaryKey = 'id';
    public $timestamps = false; 

    protected $fillable = [
        'user_id',
        'event_id',
        'organizer_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'event_id' => 'integer',
        'organizer_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }
}
