<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TicketType Model
 * 
 * Represents a type of ticket available for an event.
 *
 * @property int $id
 * @property int $event_id
 * @property int $organizer_id
 * @property string $name
 * @property float $price
 * @property int $quantity
 * @property int $remaining
 * @property float $dynamic_fee
 * @property \Illuminate\Support\Carbon|null $sale_start
 * @property \Illuminate\Support\Carbon|null $sale_end
 * @property int $max_per_user
 * @property string|null $ticket_image
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TicketType extends Model
{
    protected $table = 'ticket_types';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    const STATUS_ACTIVE = 'active';
    const STATUS_DEACTIVATED = 'deactivated';

    protected $fillable = [
        'event_id',
        'organizer_id',
        'name',
        'price',
        'sale_price',
        'quantity',
        'remaining',
        'dynamic_fee',
        'sale_start',
        'sale_end',
        'max_per_user',
        'ticket_image',
        'status',
        'description',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'organizer_id' => 'integer',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'quantity' => 'integer',
        'remaining' => 'integer',
        'dynamic_fee' => 'decimal:2',
        'max_per_user' => 'integer',
        'sale_start' => 'datetime',
        'sale_end' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the event that owns the ticket type.
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Get the organizer that owns the ticket type.
     */
    public function organizer()
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    /**
     * Check if ticket is available for sale.
     */
    public function isAvailable(): bool
    {
        $now = \Illuminate\Support\Carbon::now();
        return $this->status === self::STATUS_ACTIVE 
            && $this->remaining > 0
            && ($this->sale_start === null || $this->sale_start <= $now)
            && ($this->sale_end === null || $this->sale_end >= $now);
    }

    /**
     * Check if ticket is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
