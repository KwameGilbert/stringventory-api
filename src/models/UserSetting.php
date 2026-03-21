<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UserSetting Model
 * 
 * @property int $id
 * @property int $userId
 * @property string $category
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class UserSetting extends Model
{
    protected $table = 'user_settings';
    public $timestamps = false; // Using updatedAt only via DB

    protected $fillable = [
        'userId',
        'category',
        'data',
    ];

    protected $casts = [
        'userId' => 'integer',
        'data' => 'array',
        'updatedAt' => 'datetime',
    ];

    /**
     * Get settings by user and category
     */
    public static function getByUserAndCategory(int $userId, string $category): ?array
    {
        $setting = self::where('userId', $userId)->where('category', $category)->first();
        return $setting ? $setting->data : null;
    }

    /**
     * Update settings for a user and category
     */
    public static function updateByUserAndCategory(int $userId, string $category, array $data): bool
    {
        $setting = self::where('userId', $userId)->where('category', $category)->first();
        if ($setting) {
            return $setting->update(['data' => $data]);
        }
        
        return (bool) self::create([
            'userId' => $userId,
            'category' => $category,
            'data' => $data
        ]);
    }
}
