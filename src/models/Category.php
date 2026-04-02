<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Category Model
 * 
 * @property int $id
 * @property string $name
 * @property string|null $image
 * @property string|null $description
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class Category extends Model
{
    protected $table = 'categories';
    public $timestamps = true;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'image',
        'description',
        'status',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'categoryId');
    }
}
