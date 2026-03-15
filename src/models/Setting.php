<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting Model
 * 
 * @property int $id
 * @property string $category
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class Setting extends Model
{
    protected $table = 'settings';
    public $timestamps = false; // Using updatedAt only via DB

    protected $fillable = [
        'category',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'updatedAt' => 'datetime',
    ];

    /**
     * Get settings by category
     */
    public static function getByCategory(string $category): ?array
    {
        $setting = self::where('category', $category)->first();
        return $setting ? $setting->data : null;
    }

    /**
     * Update settings for a category
     */
    public static function updateCategory(string $category, array $data): bool
    {
        $setting = self::where('category', $category)->first();
        if ($setting) {
            return $setting->update(['data' => $data]);
        }
        
        return (bool) self::create([
            'category' => $category,
            'data' => $data
        ]);
    }
}
