<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PaymentMethod Model
 * 
 * @property string $id
 * @property string $name
 * @property string $type
 * @property bool $enabled
 * @property string $provider
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class PaymentMethod extends Model
{
    protected $table = 'payment_methods';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false; // Using custom datetime columns via migrations

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'name',
        'type',
        'enabled',
        'provider',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];
}
