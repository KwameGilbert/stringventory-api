<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosAssignment extends Model
{
    protected $table = 'pos_assignments';

    protected $fillable = [
        'user_id',
        'event_id',
        'organizer_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }
}
