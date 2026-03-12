<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OrderItem Model
 * 
 * Represents a line item in an order.
 *
 * @property int $id
 * @property int $order_id
 * @property int $event_id
 * @property int $ticket_type_id
 * @property int $quantity
 * @property float $unit_price
 * @property float $total_price
 * @property float $admin_share_percent
 * @property float $admin_amount
 * @property float $organizer_amount
 * @property float $payment_fee
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OrderItem extends Model
{
    protected $table = 'order_items';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'order_id',
        'event_id',
        'ticket_type_id',
        'quantity',
        'unit_price',
        'total_price',
        'admin_share_percent',
        'admin_amount',
        'organizer_amount',
        'payment_fee',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'event_id' => 'integer',
        'ticket_type_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'admin_share_percent' => 'decimal:2',
        'admin_amount' => 'decimal:2',
        'organizer_amount' => 'decimal:2',
        'payment_fee' => 'decimal:2',
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
}
