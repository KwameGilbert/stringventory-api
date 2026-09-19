<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Refund;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;

/**
 * Single source of truth for revenue, refund and profit figures.
 *
 * All figures cover completed orders, and refunds are dated when they were
 * completed (the refund transaction's createdAt), so past periods never move.
 *
 *   Net Revenue  = Gross Sales - Discounts - Refunds
 *   Net COGS     = COGS - cost of refunded units that were restocked
 *   Gross Profit = Net Revenue - Net COGS
 *   Net Profit   = Gross Profit - paid operating expenses
 *
 * Refunded units that were NOT restocked stay inside COGS, which is exactly the
 * stock loss. `stockLoss` is therefore a memo figure and must never be
 * subtracted a second time.
 *
 * Every $from / $to is a Y-m-d date. Passing null leaves that side open.
 */
class RevenueMetrics
{
    /**
     * Restrict $column to the [$from, $to] days, widening to the whole day.
     *
     * @param Builder|\Illuminate\Database\Eloquent\Builder $query
     */
    private static function between($query, string $column, ?string $from, ?string $to)
    {
        if ($from !== null) {
            $query->where($column, '>=', $from . ' 00:00:00');
        }
        if ($to !== null) {
            $query->where($column, '<=', $to . ' 23:59:59');
        }

        return $query;
    }

    /** Round to 2 dp and avoid a JSON-visible -0.0. */
    private static function money(float $value): float
    {
        $value = round($value, 2);

        return $value == 0.0 ? 0.0 : $value;
    }

    /**
     * Completed refund payouts on completed orders: one row per refund
     * transaction (t.amount is negative). This is the single definition of
     * "a refund counts". Cancelled orders are already excluded from Gross
     * Sales, and OrderController::cancel() cancels their transactions, so they
     * must not be deducted a second time here.
     */
    public static function refundQuery(?string $from, ?string $to): Builder
    {
        $query = DB::table('transactions as t')
            ->join('orders as o', 'o.id', '=', 't.orderId')
            ->where('t.transactionType', 'refunds')
            ->where('t.status', 'completed')
            ->where('o.status', 'completed');

        return self::between($query, 't.createdAt', $from, $to);
    }

    /** Total refunded in the period, as a positive number. */
    public static function totalRefunds(?string $from, ?string $to): float
    {
        return self::money(0.0 - (float) self::refundQuery($from, $to)->sum('t.amount'));
    }

    /** @return array<string, float> refunded amount keyed by Y-m-d */
    public static function refundsByDate(?string $from, ?string $to): array
    {
        $rows = self::refundQuery($from, $to)
            ->selectRaw('DATE(t.createdAt) AS date, SUM(0 - t.amount) AS refunds')
            ->groupBy('date')
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->date] = self::money((float) $row->refunds);
        }

        return $byDate;
    }

    /** @return array<int, float> refunded amount keyed by customerId */
    public static function refundsByCustomer(?string $from, ?string $to, ?int $customerId = null): array
    {
        $query = self::refundQuery($from, $to)->whereNotNull('o.customerId');
        if ($customerId !== null) {
            $query->where('o.customerId', $customerId);
        }

        $rows = $query
            ->selectRaw('o.customerId AS customerId, SUM(0 - t.amount) AS refunds')
            ->groupBy('o.customerId')
            ->get();

        $byCustomer = [];
        foreach ($rows as $row) {
            $byCustomer[(int) $row->customerId] = self::money((float) $row->refunds);
        }

        return $byCustomer;
    }

    /**
     * Gross Sales, Discounts, Refunds and Net Revenue for the period.
     * Gross Sales - Discounts equals SUM(discountedTotalPrice) exactly.
     *
     * @return array{orderCount: int, grossSales: float, discounts: float, refunds: float, netRevenue: float}
     */
    public static function revenue(?string $from, ?string $to): array
    {
        $orders = self::between(DB::table('orders')->where('status', 'completed'), 'createdAt', $from, $to)
            ->selectRaw(
                'COUNT(*) AS orderCount, '
                . 'COALESCE(SUM(COALESCE(discountedTotalPrice, 0) + COALESCE(discountAmount, 0)), 0) AS grossSales, '
                . 'COALESCE(SUM(COALESCE(discountAmount, 0)), 0) AS discounts'
            )
            ->first();

        $grossSales = self::money((float) $orders->grossSales);
        $discounts = self::money((float) $orders->discounts);
        $refunds = self::totalRefunds($from, $to);

        return [
            'orderCount' => (int) $orders->orderCount,
            'grossSales' => $grossSales,
            'discounts'  => $discounts,
            'refunds'    => $refunds,
            'netRevenue' => self::money($grossSales - $discounts - $refunds),
        ];
    }

    /** @return array<string, array{grossSales: float, discounts: float, orders: int}> keyed by Y-m-d */
    public static function salesByDate(?string $from, ?string $to): array
    {
        $rows = self::between(DB::table('orders')->where('status', 'completed'), 'createdAt', $from, $to)
            ->selectRaw(
                'DATE(createdAt) AS date, COUNT(*) AS orders, '
                . 'SUM(COALESCE(discountedTotalPrice, 0) + COALESCE(discountAmount, 0)) AS grossSales, '
                . 'SUM(COALESCE(discountAmount, 0)) AS discounts'
            )
            ->groupBy('date')
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->date] = [
                'grossSales' => self::money((float) $row->grossSales),
                'discounts'  => self::money((float) $row->discounts),
                'orders'     => (int) $row->orders,
            ];
        }

        return $byDate;
    }

    /** Cost of the goods on completed orders, at the unit cost captured when they were sold. */
    public static function costOfGoodsSold(?string $from, ?string $to): float
    {
        $query = DB::table('orderItems as oi')
            ->join('orders as o', 'o.id', '=', 'oi.orderId')
            ->leftJoin('products as p', 'p.id', '=', 'oi.productId')
            ->where('o.status', 'completed');

        return self::money((float) self::between($query, 'o.createdAt', $from, $to)
            ->sum(DB::raw('oi.quantity * COALESCE(oi.costPrice, p.costPrice, 0)')));
    }

    public static function operatingExpenses(?string $from, ?string $to): float
    {
        return self::money((float) self::between(Expense::where('status', 'paid'), 'transactionDate', $from, $to)
            ->sum('amount'));
    }

    /**
     * Item-level effect of the period's completed refunds, read from the
     * refunds.items JSON [{orderItemId, quantity, restock}]. Done in PHP rather
     * than JSON_TABLE so it does not depend on MySQL 8.
     *
     * A unit counts as restocked only when RefundController would have put it
     * back on the shelf (restock true, product still exists); anything else is
     * lost stock.
     *
     * @return array{
     *     restockedCost: float,
     *     lostCost: float,
     *     byProduct: array<int, array{units: int, value: float}>,
     *     byDate: array<string, int>
     * } byProduct.value is units x sellingPrice; byDate is refunded units per Y-m-d
     */
    public static function refundedItems(?string $from, ?string $to): array
    {
        $result = ['restockedCost' => 0.0, 'lostCost' => 0.0, 'byProduct' => [], 'byDate' => []];

        $refundDates = self::refundQuery($from, $to)
            ->whereNotNull('t.refundId')
            ->selectRaw('t.refundId AS refundId, DATE(t.createdAt) AS date')
            ->get()
            ->pluck('date', 'refundId');

        if ($refundDates->isEmpty()) {
            return $result;
        }

        $entries = [];
        foreach (array_chunk($refundDates->keys()->all(), 500) as $refundIds) {
            foreach (Refund::whereIn('id', $refundIds)->get(['id', 'items']) as $refund) {
                foreach ((array) $refund->items as $item) {
                    $orderItemId = (int) ($item['orderItemId'] ?? 0);
                    $quantity = (int) ($item['quantity'] ?? 0);
                    if ($orderItemId <= 0 || $quantity <= 0) {
                        continue;
                    }

                    $entries[] = [
                        'orderItemId' => $orderItemId,
                        'quantity'    => $quantity,
                        'restock'     => (bool) ($item['restock'] ?? true),
                        'date'        => (string) $refundDates[$refund->id],
                    ];
                }
            }
        }

        if (!$entries) {
            return $result;
        }

        $orderItems = DB::table('orderItems as oi')
            ->leftJoin('products as p', 'p.id', '=', 'oi.productId')
            ->whereIn('oi.id', array_values(array_unique(array_column($entries, 'orderItemId'))))
            ->selectRaw('oi.id AS id, oi.productId AS productId, oi.sellingPrice AS sellingPrice, COALESCE(oi.costPrice, p.costPrice, 0) AS unitCost')
            ->get()
            ->keyBy('id');

        foreach ($entries as $entry) {
            $orderItem = $orderItems->get($entry['orderItemId']);
            if (!$orderItem) {
                continue;
            }

            $cost = $entry['quantity'] * (float) $orderItem->unitCost;
            if ($entry['restock'] && $orderItem->productId !== null) {
                $result['restockedCost'] += $cost;
            } else {
                $result['lostCost'] += $cost;
            }

            if ($orderItem->productId !== null) {
                $productId = (int) $orderItem->productId;
                $result['byProduct'][$productId]['units'] = ($result['byProduct'][$productId]['units'] ?? 0) + $entry['quantity'];
                $result['byProduct'][$productId]['value'] = ($result['byProduct'][$productId]['value'] ?? 0.0)
                    + $entry['quantity'] * (float) $orderItem->sellingPrice;
            }

            $result['byDate'][$entry['date']] = ($result['byDate'][$entry['date']] ?? 0) + $entry['quantity'];
        }

        $result['restockedCost'] = self::money($result['restockedCost']);
        $result['lostCost'] = self::money($result['lostCost']);
        foreach ($result['byProduct'] as $productId => $row) {
            $result['byProduct'][$productId]['value'] = self::money($row['value']);
        }

        return $result;
    }

    /**
     * The full income statement for the period.
     *
     * @param array|null $refundedItems a refundedItems() result, to avoid recomputing it
     * @return array{
     *     orderCount: int, grossSales: float, discounts: float, refunds: float, netRevenue: float,
     *     costOfGoodsGross: float, costRecovered: float, costOfGoods: float,
     *     grossProfit: float, expenses: float, netProfit: float, stockLoss: float,
     *     grossMargin: float, netMargin: float
     * }
     */
    public static function summary(?string $from, ?string $to, ?array $refundedItems = null): array
    {
        $revenue = self::revenue($from, $to);
        $refundedItems ??= self::refundedItems($from, $to);
        $cogs = self::costOfGoodsSold($from, $to);
        $expenses = self::operatingExpenses($from, $to);

        $netRevenue = $revenue['netRevenue'];
        $netCogs = self::money($cogs - $refundedItems['restockedCost']);
        $grossProfit = self::money($netRevenue - $netCogs);
        $netProfit = self::money($grossProfit - $expenses);

        return $revenue + [
            'costOfGoodsGross' => $cogs,
            'costRecovered'    => $refundedItems['restockedCost'],
            'costOfGoods'      => $netCogs,
            'grossProfit'      => $grossProfit,
            'expenses'         => $expenses,
            'netProfit'        => $netProfit,
            'stockLoss'        => $refundedItems['lostCost'],
            'grossMargin'      => $netRevenue > 0 ? self::money($grossProfit / $netRevenue * 100) : 0.0,
            'netMargin'        => $netRevenue > 0 ? self::money($netProfit / $netRevenue * 100) : 0.0,
        ];
    }

    /**
     * Net revenue by payment method: order payments plus (negative) refund
     * payouts, on completed orders. Purchases and expenses are not revenue and
     * are excluded. A refund with no payment method is attributed to the order's
     * original method.
     *
     * @return list<array{paymentMethod: string, revenue: float, transactionCount: int}> highest revenue first
     */
    public static function revenueByPaymentMethod(?string $from, ?string $to): array
    {
        $orderMethods = DB::table('transactions')
            ->select('orderId')
            ->selectRaw('MIN(paymentMethod) AS paymentMethod')
            ->where('transactionType', 'order')
            ->whereNotNull('paymentMethod')
            ->groupBy('orderId');

        $query = DB::table('transactions as t')
            ->join('orders as o', 'o.id', '=', 't.orderId')
            ->leftJoinSub($orderMethods, 'om', 'om.orderId', '=', 't.orderId')
            ->where('o.status', 'completed')
            ->where('t.status', 'completed')
            ->whereIn('t.transactionType', ['order', 'refunds']);

        $rows = self::between($query, 't.createdAt', $from, $to)
            ->selectRaw(
                'COALESCE(t.paymentMethod, om.paymentMethod) AS payMethod, '
                . 'SUM(t.amount) AS revenue, '
                . "SUM(CASE WHEN t.transactionType = 'order' THEN 1 ELSE 0 END) AS transactionCount"
            )
            ->groupBy('payMethod')
            ->get();

        $byMethod = [];
        foreach ($rows as $row) {
            if ($row->payMethod === null) {
                continue;
            }
            $byMethod[] = [
                'paymentMethod'    => (string) $row->payMethod,
                'revenue'          => self::money((float) $row->revenue),
                'transactionCount' => (int) $row->transactionCount,
            ];
        }

        usort($byMethod, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        return $byMethod;
    }
}
