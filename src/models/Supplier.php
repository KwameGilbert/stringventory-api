<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Supplier Model
 * 
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $contactPerson
 * @property string|null $image
 * @property int|null $rating
 * @property \Illuminate\Support\Carbon|null $createdAt
 */
class Supplier extends Model
{
    protected $table = 'suppliers';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'contactPerson',
        'image',
        'rating',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'rating' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'supplierId');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'supplierId');
    }
}
