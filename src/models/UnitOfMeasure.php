<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UnitOfMeasure Model
 * 
 * @property int $id
 * @property string $name
 * @property string|null $abbreviation
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class UnitOfMeasure extends Model
{
    protected $table = 'unitsOfMeasure';
    public $timestamps = true;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'abbreviation',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'unitOfMeasureId');
    }
}
