<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PlatformSetting Model
 * 
 * Global platform configuration settings
 * 
 * @property int $id
 * @property string $setting_key
 * @property string $setting_value
 * @property string $setting_type
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PlatformSetting extends Model
{
    protected $table = 'platform_settings';

    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get a setting value by key
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('setting_key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return self::castValue($setting->setting_value, $setting->setting_type);
    }

    /**
     * Set a setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param string $type Setting type (string, number, boolean, json)
     * @param string|null $description Description
     * @return self
     */
    public static function set(string $key, $value, string $type = 'string', ?string $description = null): self
    {
        $setting = self::firstOrNew(['setting_key' => $key]);
        $setting->setting_value = is_array($value) ? json_encode($value) : (string) $value;
        $setting->setting_type = $type;
        
        if ($description !== null) {
            $setting->description = $description;
        }
        
        $setting->save();
        
        return $setting;
    }

    /**
     * Cast value based on type
     */
    private static function castValue(string $value, string $type)
    {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }

    /**
     * Get default event admin share percentage
     */
    public static function getDefaultEventAdminShare(): float
    {
        return (float) self::get('default_event_admin_share', 10);
    }

    /**
     * Get default award admin share percentage
     */
    public static function getDefaultAwardAdminShare(): float
    {
        return (float) self::get('default_award_admin_share', 15);
    }

    /**
     * Get payout hold days
     */
    public static function getPayoutHoldDays(): int
    {
        return (int) self::get('payout_hold_days', 7);
    }

    /**
     * Get minimum payout amount
     */
    public static function getMinPayoutAmount(): float
    {
        return (float) self::get('min_payout_amount', 50);
    }

    /**
     * Get Paystack fee percentage
     */
    public static function getPaystackFeePercent(): float
    {
        return (float) self::get('paystack_fee_percent', 1.5);
    }
}
