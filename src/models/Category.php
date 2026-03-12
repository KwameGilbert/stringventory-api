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
 */
class Category extends Model
{
    protected $table = 'categories';
    public $timestamps = false; // createdAt only in migration

    const CREATED_AT = 'createdAt';

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
