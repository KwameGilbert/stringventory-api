<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Customer Model
 * 
 * @property int $id
 * @property string|null $firstName
 * @property string|null $lastName
 * @property string|null $businessName
 * @property string|null $customerType
 * @property int $loyaltyPoints
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property \Illuminate\Support\Carbon|null $createdAt
 */
class Customer extends Model
{
    protected $table = 'customers';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'firstName',
        'lastName',
        'businessName',
        'customerType',
        'loyaltyPoints',
    ];

    protected $casts = [
        'loyaltyPoints' => 'integer',
        'updatedAt' => 'datetime',
        'createdAt' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'customerId');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'customerId');
    }

    public function getFullNameAttribute(): string
    {
        if ($this->businessName) {
            return $this->businessName;
        }
        return "{$this->firstName} {$this->lastName}";
    }
}
