<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\ExchangeRateHistory;

class CurrencyService
{
    private const API_BASE      = 'https://v6.exchangerate-api.com/v6/';
    private const SUPPORTED     = ['GHS', 'GBP', 'USD', 'EUR'];
    private const DEFAULT_CURRENCY = 'GHS';

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Get the active business currency from settings.
     */
    public static function getCurrent(): string
    {
        try {
            $settings = Setting::getByCategory('business');
            $currency = $settings['currency'] ?? self::DEFAULT_CURRENCY;
            return in_array($currency, self::SUPPORTED, true) ? $currency : self::DEFAULT_CURRENCY;
        } catch (\Throwable) {
            return self::DEFAULT_CURRENCY;
        }
    }

    /**
     * Get the currency symbol for a given code.
     */
    public static function getSymbol(string $currency): string
    {
        return match(strtoupper($currency)) {
            'GHS' => 'GH₵',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => $currency,
        };
    }

    /**
     * Convert an amount between two currencies.
     * Uses the rate that was in effect on the given date (defaults to today).
     * Returns the original amount unchanged when from === to.
     */
    public static function convert(float $amount, string $from, string $to, ?string $date = null): float
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $date = $date ?? date('Y-m-d');
        $rate = self::getRateForDate($from, $to, $date);

        return round($amount * $rate, 2);
    }

    /**
     * Get the exchange rate between two currencies on a given date.
     * Looks up the most recent rate on or before that date.
     * Falls back to fetching from the API if no stored rate exists.
     */
    public static function getRateForDate(string $from, string $to, string $date): float
    {
        $record = ExchangeRateHistory::where('baseCurrency', $from)
            ->where('targetCurrency', $to)
            ->where('effectiveDate', '<=', $date)
            ->orderBy('effectiveDate', 'desc')
            ->first();

        if ($record) {
            return (float) $record->rate;
        }

        // No stored rate — fetch from API, then retry
        self::fetchAndStoreRates();

        $record = ExchangeRateHistory::where('baseCurrency', $from)
            ->where('targetCurrency', $to)
            ->orderBy('effectiveDate', 'desc')
            ->first();

        return $record ? (float) $record->rate : 1.0;
    }

    /**
     * Fetch today's rates from ExchangeRate-API and persist them.
     * Skips the network call if today's API rates are already stored.
     * Returns true on success, false on failure.
     */
    public static function fetchAndStoreRates(): bool
    {
        $today  = date('Y-m-d');
        $apiKey = $_ENV['EXCHANGE_RATE_API_KEY'] ?? '';

        if (!$apiKey) {
            return false;
        }

        // Already have today's API rates — nothing to do
        if (ExchangeRateHistory::where('effectiveDate', $today)->where('source', 'api')->exists()) {
            return true;
        }

        $url = self::API_BASE . $apiKey . '/latest/USD';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result) {
            return false;
        }

        $data = json_decode((string) $result, true);

        if (!isset($data['result']) || $data['result'] !== 'success') {
            return false;
        }

        $usdRates = $data['conversion_rates']; // All rates relative to 1 USD
        $now      = date('Y-m-d H:i:s');

        foreach (self::SUPPORTED as $base) {
            foreach (self::SUPPORTED as $target) {
                if ($base === $target) {
                    continue;
                }

                $rate = self::crossRate($usdRates, $base, $target);
                if ($rate === null) {
                    continue;
                }

                ExchangeRateHistory::create([
                    'baseCurrency'   => $base,
                    'targetCurrency' => $target,
                    'rate'           => $rate,
                    'source'         => 'api',
                    'effectiveDate'  => $today,
                    'createdAt'      => $now,
                ]);
            }
        }

        return true;
    }

    /**
     * Store a manually set rate.
     * Called when admin updates rates in settings.
     */
    public static function storeManualRate(string $base, string $target, float $rate): void
    {
        $base   = strtoupper($base);
        $target = strtoupper($target);
        $today  = date('Y-m-d');

        ExchangeRateHistory::create([
            'baseCurrency'   => $base,
            'targetCurrency' => $target,
            'rate'           => $rate,
            'source'         => 'manual',
            'effectiveDate'  => $today,
            'createdAt'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get the latest stored rate for each supported currency pair.
     * Used for displaying current rates in settings.
     */
    public static function getCurrentRates(): array
    {
        $rates = [];

        foreach (self::SUPPORTED as $base) {
            foreach (self::SUPPORTED as $target) {
                if ($base === $target) {
                    continue;
                }

                $record = ExchangeRateHistory::where('baseCurrency', $base)
                    ->where('targetCurrency', $target)
                    ->orderBy('effectiveDate', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($record) {
                    $rates[$base][$target] = [
                        'rate'          => $record->rate,
                        'source'        => $record->source,
                        'effectiveDate' => $record->effectiveDate,
                    ];
                }
            }
        }

        return $rates;
    }

    /**
     * Returns the list of supported currency codes.
     */
    public static function getSupportedCurrencies(): array
    {
        return self::SUPPORTED;
    }

    /**
     * Convert all monetary fields in a single record array from its stored currency
     * to the current business currency, using the historical rate for the record's date.
     *
     * @param array  $record       Associative array (from model->toArray())
     * @param array  $amountFields Monetary field names on the record itself
     * @param string $dateField    Key used to look up the historical rate (e.g. 'createdAt', 'transactionDate')
     * @param array  $nested       Nested relation keys and their monetary fields, e.g.
     *                             ['items' => ['costPrice', 'sellingPrice', 'totalPrice']]
     *                             Nested items inherit the parent's currency and date.
     */
    public static function convertRecord(
        array  $record,
        array  $amountFields,
        string $dateField = 'createdAt',
        array  $nested = []
    ): array {
        $current = self::getCurrent();
        $from    = isset($record['currency']) ? strtoupper((string) $record['currency']) : $current;

        // Always stamp the current currency on the record
        $record['currency'] = $current;

        if ($from === $current) {
            return $record;
        }

        $rawDate = $record[$dateField] ?? null;
        $date    = $rawDate ? substr((string) $rawDate, 0, 10) : date('Y-m-d');

        // Convert top-level monetary fields
        foreach ($amountFields as $field) {
            if (isset($record[$field]) && is_numeric($record[$field])) {
                $record[$field] = self::convert((float) $record[$field], $from, $current, $date);
            }
        }

        // Convert nested relation arrays (e.g. order items) using the parent's currency and date
        foreach ($nested as $relationKey => $itemFields) {
            if (empty($record[$relationKey]) || !is_array($record[$relationKey])) {
                continue;
            }
            foreach ($record[$relationKey] as &$item) {
                foreach ($itemFields as $field) {
                    if (isset($item[$field]) && is_numeric($item[$field])) {
                        $item[$field] = self::convert((float) $item[$field], $from, $current, $date);
                    }
                }
            }
            unset($item);
        }

        return $record;
    }

    /**
     * Convert a collection of record arrays.
     *
     * @param array  $records      Array of associative arrays
     * @param array  $amountFields Monetary field names to convert
     * @param string $dateField    Date field used for historical rate lookup
     * @param array  $nested       Nested relation keys and their monetary fields (see convertRecord)
     */
    public static function convertCollection(
        array  $records,
        array  $amountFields,
        string $dateField = 'createdAt',
        array  $nested = []
    ): array {
        return array_map(
            static fn(array $r) => self::convertRecord($r, $amountFields, $dateField, $nested),
            $records
        );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Compute a cross rate between two currencies given USD-based rates.
     * If base is USD: rate = usdRates[target]
     * Otherwise: rate = usdRates[target] / usdRates[base]
     */
    private static function crossRate(array $usdRates, string $base, string $target): ?float
    {
        $targetRate = $usdRates[$target] ?? null;

        if ($targetRate === null) {
            return null;
        }

        if ($base === 'USD') {
            return (float) $targetRate;
        }

        $baseRate = $usdRates[$base] ?? null;

        if (!$baseRate) {
            return null;
        }

        return round((float) $targetRate / (float) $baseRate, 6);
    }
}
