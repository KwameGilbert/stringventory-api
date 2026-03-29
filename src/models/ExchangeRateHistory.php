<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ExchangeRateHistory Model
 *
 * @property int $id
 * @property string $baseCurrency
 * @property string $targetCurrency
 * @property float $rate
 * @property string $source
 * @property string $effectiveDate
 * @property \Illuminate\Support\Carbon|null $createdAt
 */
class ExchangeRateHistory extends Model
{
    protected $table = 'exchange_rate_history';
    public $timestamps = false;

    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'baseCurrency',
        'targetCurrency',
        'rate',
        'source',
        'effectiveDate',
        'createdAt',
    ];

    protected $casts = [
        'rate'      => 'float',
        'createdAt' => 'datetime',
    ];
}
