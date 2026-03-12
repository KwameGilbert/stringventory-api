<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ticket Model
 * 
 * Represents an individual entry pass.
 *
 * @property int $id
 * @property int $order_id
 * @property int $event_id
 * @property int $ticket_type_id
 * @property string $ticket_code
 * @property string $status
 * @property int|null $attendee_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Ticket extends Model
{
    protected $table = 'tickets';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    const STATUS_ACTIVE = 'active';
    const STATUS_USED = 'used';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id',
        'event_id',
        'ticket_type_id',
        'ticket_code',
        'status',
        'attendee_id',
        'admitted_by',
        'admitted_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'event_id' => 'integer',
        'ticket_type_id' => 'integer',
        'attendee_id' => 'integer',
        'admitted_by' => 'integer',
        'admitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function attendee()
    {
        return $this->belongsTo(Attendee::class, 'attendee_id');
    }

    /**
     * Generate a unique ticket code.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 10));
        } while (self::where('ticket_code', $code)->exists());

        return $code;
    }
}
