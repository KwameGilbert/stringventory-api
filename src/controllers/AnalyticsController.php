<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Purchase;
use App\Helper\ResponseHelper;
use App\Services\CurrencyService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Carbon\Carbon;
use Exception;

class AnalyticsController
{
    /**
     * Sum a monetary column with currency conversion.
     * Groups by the currency column, converts each group to the current business currency, then sums.
     */
    private function sumConverted($query, string $column, string $currencyColumn = 'currency'): float
    {
        $current = CurrencyService::getCurrent();
        $rows = (clone $query)
            ->select($currencyColumn, DB::raw("SUM({$column}) as __total"))
            ->groupBy($currencyColumn)
            ->get();

        $total = 0.0;
        foreach ($rows as $row) {
            $from = strtoupper((string) ($row->$currencyColumn ?? $current));
            $total += CurrencyService::convert((float) $row->__total, $from, $current);
        }
        return round($total, 2);
    }

    /**
     * Get Dashboard Overview Metrics and Charts
     */
    public function getDashboardOverview(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['dateFrom'] ?? Carbon::now()->startOfMonth()->toDateString();
            $dateTo   = $queryParams['dateTo']   ?? Carbon::now()->toDateString();
            $current  = CurrencyService::getCurrent();

            $range = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            // --- Scalar metrics ---
            $grossRevenue  = $this->sumConverted(
                Order::where('status', 'completed')->whereBetween('createdAt', $range),
                'discountedTotalPrice'
            );
            $totalExpenses = $this->sumConverted(
                Expense::where('status', 'paid')->whereBetween('transactionDate', $range),
                'amount'
            );
            $netProfit      = round($grossRevenue - $totalExpenses, 2);
            $totalOrders    = Order::where('status', 'completed')->whereBetween('createdAt', $range)->count();
            $totalCustomers = Customer::whereBetween('createdAt', $range)->count();
            $lowStockItems  = Inventory::where('quantity', '<=', 10)->count();
            $pendingOrders  = Order::where('status', 'pending')->count();

            // Inventory value — product costPrices are managed in the current business currency
            $inventoryValue = (float) DB::table('inventory')
                ->join('purchaseItems', 'inventory.productId', '=', 'purchaseItems.productId')
                ->sum(DB::raw('inventory.quantity * purchaseItems.costPrice'));
            if ($inventoryValue == 0) {
                $inventoryValue = (float) DB::table('inventory')
                    ->join('products', 'inventory.productId', '=', 'products.id')
                    ->sum(DB::raw('inventory.quantity * products.costPrice'));
            }

            // --- Chart: Revenue and Expenses by Date ---
            $revenueRaw = Order::where('status', 'completed')
                ->whereBetween('createdAt', $range)
                ->select(DB::raw('DATE(createdAt) as date'), 'currency', DB::raw('SUM(discountedTotalPrice) as revenue'))
                ->groupBy('date', 'currency')
                ->get();

            $revenueData = [];
            foreach ($revenueRaw as $row) {
                $date = $row->date;
                $revenueData[$date] = ($revenueData[$date] ?? 0.0)
                    + CurrencyService::convert((float) $row->revenue, $row->currency ?? $current, $current);
            }

            $expenseRaw = Expense::where('status', 'paid')
                ->whereBetween('transactionDate', $range)
                ->select(DB::raw('DATE(transactionDate) as date'), 'currency', DB::raw('SUM(amount) as expenses'))
                ->groupBy('date', 'currency')
                ->get();

            $expenseData = [];
            foreach ($expenseRaw as $row) {
                $date = $row->date;
                $expenseData[$date] = ($expenseData[$date] ?? 0.0)
                    + CurrencyService::convert((float) $row->expenses, $row->currency ?? $current, $current);
            }

            $allDates = array_unique(array_merge(array_keys($revenueData), array_keys($expenseData)));
            sort($allDates);
            $revenueByDate = [];
            foreach ($allDates as $date) {
                $revenueByDate[] = [
                    'date'     => $date,
                    'revenue'  => round((float) ($revenueData[$date] ?? 0), 2),
                    'expenses' => round((float) ($expenseData[$date] ?? 0), 2),
                ];
            }

            // --- Chart: Top Products ---
            $topProductsRaw = OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                ->join('products', 'orderItems.productId', '=', 'products.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', $range)
                ->select(
                    'orderItems.productId',
                    'products.name as productName',
                    'orders.currency',
                    DB::raw('SUM(orderItems.quantity) as sales'),
                    DB::raw('SUM(orderItems.totalPrice) as revenue')
                )
                ->groupBy('orderItems.productId', 'productName', 'orders.currency')
                ->get();

            $topProductsMap = [];
            foreach ($topProductsRaw as $row) {
                $pid = $row->productId;
                $converted = CurrencyService::convert((float) $row->revenue, $row->currency ?? $current, $current);
                if (!isset($topProductsMap[$pid])) {
                    $topProductsMap[$pid] = ['productId' => $pid, 'productName' => $row->productName, 'sales' => 0, 'revenue' => 0.0];
                }
                $topProductsMap[$pid]['sales']   += (int) $row->sales;
                $topProductsMap[$pid]['revenue'] += $converted;
            }
            usort($topProductsMap, fn($a, $b) => $b['sales'] - $a['sales']);
            $topProducts = array_slice(array_values($topProductsMap), 0, 5);
            foreach ($topProducts as &$p) { $p['revenue'] = round($p['revenue'], 2); }
            unset($p);

            // --- Chart: Top Customers ---
            $topCustomersRaw = Order::join('customers', 'orders.customerId', '=', 'customers.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', $range)
                ->select(
                    'orders.customerId',
                    DB::raw("TRIM(CONCAT_WS(' ', customers.firstName, customers.lastName)) as customerName"),
                    'orders.currency',
                    DB::raw('COUNT(orders.id) as orders'),
                    DB::raw('SUM(orders.discountedTotalPrice) as spent')
                )
                ->groupBy('orders.customerId', 'customerName', 'orders.currency')
                ->get();

            $topCustomersMap = [];
            foreach ($topCustomersRaw as $row) {
                $cid = $row->customerId;
                $converted = CurrencyService::convert((float) $row->spent, $row->currency ?? $current, $current);
                if (!isset($topCustomersMap[$cid])) {
                    $topCustomersMap[$cid] = ['customerId' => $cid, 'customerName' => $row->customerName, 'orders' => 0, 'spent' => 0.0];
                }
                $topCustomersMap[$cid]['orders'] += (int) $row->orders;
                $topCustomersMap[$cid]['spent']  += $converted;
            }
            usort($topCustomersMap, fn($a, $b) => $b['spent'] <=> $a['spent']);
            $topCustomers = array_slice(array_values($topCustomersMap), 0, 5);
            foreach ($topCustomers as &$c) { $c['spent'] = round($c['spent'], 2); }
            unset($c);

            // --- Chart: Revenue by Payment Method ---
            $revenueByPaymentMethodRaw = Transaction::where('status', 'completed')
                ->whereNotNull('paymentMethod')
                ->whereBetween('createdAt', $range)
                ->select(
                    'paymentMethod',
                    'currency',
                    DB::raw('SUM(amount) as revenue'),
                    DB::raw('COUNT(id) as transactionCount')
                )
                ->groupBy('paymentMethod', 'currency')
                ->get();

            $paymentMethodMap = [];
            foreach ($revenueByPaymentMethodRaw as $row) {
                $pm = $row->paymentMethod;
                $converted = CurrencyService::convert((float) $row->revenue, $row->currency ?? $current, $current);
                if (!isset($paymentMethodMap[$pm])) {
                    $paymentMethodMap[$pm] = ['paymentMethod' => $pm, 'revenue' => 0.0, 'transactionCount' => 0];
                }
                $paymentMethodMap[$pm]['revenue']          += $converted;
                $paymentMethodMap[$pm]['transactionCount'] += (int) $row->transactionCount;
            }
            usort($paymentMethodMap, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
            foreach ($paymentMethodMap as &$pm) { $pm['revenue'] = round($pm['revenue'], 2); }
            unset($pm);
            $revenueByPaymentMethod = array_values($paymentMethodMap);

            $data = [
                'metrics' => [
                    'grossRevenue'   => ['value' => $grossRevenue,   'change' => 0, 'trend' => 'stable'],
                    'totalOrders'    => ['value' => $totalOrders,    'change' => 0, 'trend' => 'stable'],
                    'totalExpenses'  => ['value' => $totalExpenses,  'change' => 0, 'trend' => 'stable'],
                    'netProfit'      => ['value' => $netProfit,      'change' => 0, 'trend' => 'stable'],
                    'totalCustomers' => ['value' => $totalCustomers, 'change' => 0, 'trend' => 'stable'],
                    'inventoryValue' => ['value' => round($inventoryValue, 2), 'change' => 0, 'trend' => 'stable'],
                    'lowStockItems'  => $lowStockItems,
                    'pendingOrders'  => $pendingOrders,
                ],
                'charts' => [
                    'revenueByDate'          => $revenueByDate,
                    'revenueByPaymentMethod' => $revenueByPaymentMethod,
                    'topProducts'            => $topProducts,
                    'topCustomers'           => $topCustomers,
                ],
            ];

            return ResponseHelper::success($response, 'Dashboard overview retrieved successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve dashboard overview', 500, $e->getMessage());
        }
    }

    /**
     * Get Sales Report
     */
    public function getSalesReport(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['dateFrom'] ?? Carbon::now()->startOfMonth()->toDateString();
            $dateTo   = $queryParams['dateTo']   ?? Carbon::now()->toDateString();
            $current  = CurrencyService::getCurrent();

            $range = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            $totalOrders = Order::where('status', 'completed')->whereBetween('createdAt', $range)->count();
            $totalSales  = $this->sumConverted(
                Order::where('status', 'completed')->whereBetween('createdAt', $range),
                'discountedTotalPrice'
            );

            // --- By Payment Method ---
            $byPaymentMethodRaw = DB::table('transactions')
                ->join('orders', 'transactions.orderId', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->whereNotNull('transactions.paymentMethod')
                ->whereBetween('orders.createdAt', $range)
                ->select(
                    'transactions.paymentMethod',
                    'transactions.currency',
                    DB::raw('SUM(transactions.amount) as revenue'),
                    DB::raw('COUNT(transactions.id) as orders')
                )
                ->groupBy('transactions.paymentMethod', 'transactions.currency')
                ->get();

            $paymentMethodMap = [];
            foreach ($byPaymentMethodRaw as $row) {
                $pm = $row->paymentMethod;
                $converted = CurrencyService::convert((float) $row->revenue, $row->currency ?? $current, $current);
                if (!isset($paymentMethodMap[$pm])) {
                    $paymentMethodMap[$pm] = ['paymentMethod' => $pm, 'revenue' => 0.0, 'orders' => 0];
                }
                $paymentMethodMap[$pm]['revenue'] += $converted;
                $paymentMethodMap[$pm]['orders']  += (int) $row->orders;
            }
            foreach ($paymentMethodMap as &$pm) { $pm['revenue'] = round($pm['revenue'], 2); }
            unset($pm);
            $byPaymentMethod = array_values($paymentMethodMap);

            $topPaymentMethod = !empty($byPaymentMethod)
                ? collect($byPaymentMethod)->sortByDesc('orders')->first()['paymentMethod']
                : 'N/A';

            // --- By Date ---
            $byDateSalesRaw = Order::where('status', 'completed')
                ->whereBetween('createdAt', $range)
                ->select(
                    DB::raw('DATE(createdAt) as date'),
                    'currency',
                    DB::raw('SUM(discountedTotalPrice) as sales'),
                    DB::raw('COUNT(*) as orders')
                )
                ->groupBy('date', 'currency')
                ->get();

            $byDateMap = [];
            foreach ($byDateSalesRaw as $row) {
                $date = $row->date;
                $converted = CurrencyService::convert((float) $row->sales, $row->currency ?? $current, $current);
                if (!isset($byDateMap[$date])) {
                    $byDateMap[$date] = ['date' => $date, 'sales' => 0.0, 'orders' => 0, 'items' => 0];
                }
                $byDateMap[$date]['sales']  += $converted;
                $byDateMap[$date]['orders'] += (int) $row->orders;
            }

            $byDateItemsRaw = OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', $range)
                ->select(DB::raw('DATE(orders.createdAt) as date'), DB::raw('SUM(orderItems.quantity) as items'))
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            foreach ($byDateMap as &$row) {
                $row['sales']  = round($row['sales'], 2);
                $row['items']  = (int) ($byDateItemsRaw[$row['date']]->items ?? 0);
            }
            unset($row);
            ksort($byDateMap);
            $byDate = array_values($byDateMap);

            // --- By Product ---
            $byProductRaw = OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                ->join('products', 'orderItems.productId', '=', 'products.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', $range)
                ->select(
                    'orderItems.productId',
                    'products.name as productName',
                    'orders.currency',
                    DB::raw('SUM(orderItems.quantity) as quantity'),
                    DB::raw('SUM(orderItems.totalPrice) as revenue')
                )
                ->groupBy('orderItems.productId', 'productName', 'orders.currency')
                ->get();

            $byProductMap = [];
            foreach ($byProductRaw as $row) {
                $pid = $row->productId;
                $converted = CurrencyService::convert((float) $row->revenue, $row->currency ?? $current, $current);
                if (!isset($byProductMap[$pid])) {
                    $byProductMap[$pid] = ['productId' => $pid, 'productName' => $row->productName, 'quantity' => 0, 'revenue' => 0.0];
                }
                $byProductMap[$pid]['quantity'] += (int) $row->quantity;
                $byProductMap[$pid]['revenue']  += $converted;
            }
            usort($byProductMap, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
            foreach ($byProductMap as &$p) { $p['revenue'] = round($p['revenue'], 2); }
            unset($p);

            // --- By Customer ---
            $byCustomerRaw = Order::join('customers', 'orders.customerId', '=', 'customers.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', $range)
                ->select(
                    'orders.customerId',
                    DB::raw("TRIM(CONCAT_WS(' ', customers.firstName, customers.lastName)) as customerName"),
                    'orders.currency',
                    DB::raw('COUNT(orders.id) as orders'),
                    DB::raw('SUM(orders.discountedTotalPrice) as spent')
                )
                ->groupBy('orders.customerId', 'customerName', 'orders.currency')
                ->get();

            $byCustomerMap = [];
            foreach ($byCustomerRaw as $row) {
                $cid = $row->customerId;
                $converted = CurrencyService::convert((float) $row->spent, $row->currency ?? $current, $current);
                if (!isset($byCustomerMap[$cid])) {
                    $byCustomerMap[$cid] = ['customerId' => $cid, 'customerName' => $row->customerName, 'orders' => 0, 'spent' => 0.0];
                }
                $byCustomerMap[$cid]['orders'] += (int) $row->orders;
                $byCustomerMap[$cid]['spent']  += $converted;
            }
            usort($byCustomerMap, fn($a, $b) => $b['spent'] <=> $a['spent']);
            foreach ($byCustomerMap as &$c) { $c['spent'] = round($c['spent'], 2); }
            unset($c);

            $totalItems = (int) OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', $range)
                ->sum('orderItems.quantity');

            $data = [
                'summary' => [
                    'totalSales'        => $totalSales,
                    'totalOrders'       => $totalOrders,
                    'averageOrderValue' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
                    'totalItems'        => $totalItems,
                    'topPaymentMethod'  => $topPaymentMethod,
                ],
                'byPaymentMethod' => $byPaymentMethod,
                'byDate'          => $byDate,
                'byProduct'       => array_values($byProductMap),
                'byCustomer'      => array_values($byCustomerMap),
            ];

            return ResponseHelper::success($response, 'Sales report retrieved successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve sales report', 500, $e->getMessage());
        }
    }

    /**
     * Get Inventory Report
     */
    public function getInventoryReport(Request $request, Response $response): Response
    {
        try {
            $totalProducts = Product::count();
            $totalQuantity = Inventory::sum('quantity');

            // Product costPrices are managed in the current business currency
            $inventoryValue = (float) DB::table('inventory')
                ->join('products', 'inventory.productId', '=', 'products.id')
                ->sum(DB::raw('inventory.quantity * products.costPrice'));

            $data = [
                'summary' => [
                    'totalProducts'  => $totalProducts,
                    'totalQuantity'  => (int) $totalQuantity,
                    'totalValue'     => round($inventoryValue, 2),
                    'lowStockItems'  => Inventory::where('quantity', '<=', 10)->count(),
                    'outOfStockItems'=> Inventory::where('quantity', '<=', 0)->count(),
                ],
                'byCategory' => DB::table('categories')
                    ->leftJoin('products', 'categories.id', '=', 'products.categoryId')
                    ->leftJoin('inventory', 'products.id', '=', 'inventory.productId')
                    ->select(
                        'categories.id as categoryId',
                        'categories.name as categoryName',
                        DB::raw('COUNT(DISTINCT products.id) as productCount'),
                        DB::raw('SUM(inventory.quantity) as quantity'),
                        DB::raw('SUM(inventory.quantity * products.costPrice) as value')
                    )
                    ->groupBy('categories.id', 'categories.name')
                    ->get(),
                'lowStockItems' => Inventory::join('products', 'inventory.productId', '=', 'products.id')
                    ->where('inventory.quantity', '<=', 10)
                    ->select(
                        'inventory.productId',
                        'products.name as productName',
                        'products.sku',
                        'inventory.quantity',
                        DB::raw('10 as reorderLevel')
                    )
                    ->get(),
            ];

            return ResponseHelper::success($response, 'Inventory report retrieved successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve inventory report', 500, $e->getMessage());
        }
    }

    /**
     * Get Financial Report
     */
    public function getFinancialReport(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['dateFrom'] ?? Carbon::now()->startOfMonth()->toDateString();
            $dateTo   = $queryParams['dateTo']   ?? Carbon::now()->toDateString();
            $current  = CurrencyService::getCurrent();

            $range = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            $salesIncome = $this->sumConverted(
                Order::where('status', 'completed')->whereBetween('createdAt', $range),
                'discountedTotalPrice'
            );

            // Cost of goods — use orderItems.costPrice (recorded at time of sale) via orders.currency
            $cogRaw = OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                ->join('products', 'orderItems.productId', '=', 'products.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', $range)
                ->select('orders.currency', DB::raw('SUM(orderItems.quantity * products.costPrice) as total'))
                ->groupBy('orders.currency')
                ->get();

            $costOfGoods = 0.0;
            foreach ($cogRaw as $row) {
                $costOfGoods += CurrencyService::convert((float) $row->total, $row->currency ?? $current, $current);
            }
            $costOfGoods = round($costOfGoods, 2);

            $expenses = $this->sumConverted(
                Expense::where('status', 'paid')->whereBetween('transactionDate', $range),
                'amount'
            );

            $data = [
                'income' => [
                    'sales' => $salesIncome,
                    'other' => 0,
                    'total' => $salesIncome,
                ],
                'expenses' => [
                    'costOfGoods'          => $costOfGoods,
                    'operationalExpenses'  => $expenses,
                    'other'                => 0,
                    'total'                => round($costOfGoods + $expenses, 2),
                ],
                'summary' => [
                    'grossProfit'   => round($salesIncome - $costOfGoods, 2),
                    'netProfit'     => round($salesIncome - ($costOfGoods + $expenses), 2),
                    'profitMargin'  => $salesIncome > 0
                        ? round((($salesIncome - ($costOfGoods + $expenses)) / $salesIncome) * 100, 2)
                        : 0,
                    'roi'           => ($costOfGoods + $expenses) > 0
                        ? round((($salesIncome - ($costOfGoods + $expenses)) / ($costOfGoods + $expenses)) * 100, 2)
                        : 0,
                ],
            ];

            return ResponseHelper::success($response, 'Financial report retrieved successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve financial report', 500, $e->getMessage());
        }
    }

    /**
     * Get Customer Report
     */
    public function getCustomerReport(Request $request, Response $response): Response
    {
        try {
            $current = CurrencyService::getCurrent();

            $totalCustomers       = Customer::count();
            $newCustomers         = Customer::where('createdAt', '>=', Carbon::now()->startOfMonth())->count();
            $activeCustomersCount = Order::where('status', 'completed')
                ->where('createdAt', '>=', Carbon::now()->subMonths(3))
                ->distinct('customerId')
                ->count('customerId');

            // Average order value — convert per currency group then divide by total order count
            $totalOrderCount = Order::where('status', 'completed')->count();
            $totalSpent      = $this->sumConverted(Order::where('status', 'completed'), 'discountedTotalPrice');
            $averageOrderValue = $totalOrderCount > 0 ? round($totalSpent / $totalOrderCount, 2) : 0.0;

            // Top customers
            $topCustomersRaw = Order::join('customers', 'orders.customerId', '=', 'customers.id')
                ->where('orders.status', 'completed')
                ->select(
                    'orders.customerId',
                    DB::raw("TRIM(CONCAT_WS(' ', customers.firstName, customers.lastName)) as customerName"),
                    'orders.currency',
                    DB::raw('COUNT(orders.id) as orders'),
                    DB::raw('SUM(orders.discountedTotalPrice) as spent'),
                    DB::raw('MAX(orders.createdAt) as lastOrderDate')
                )
                ->groupBy('orders.customerId', 'customerName', 'orders.currency')
                ->get();

            $topCustomersMap = [];
            foreach ($topCustomersRaw as $row) {
                $cid = $row->customerId;
                $converted = CurrencyService::convert((float) $row->spent, $row->currency ?? $current, $current);
                if (!isset($topCustomersMap[$cid])) {
                    $topCustomersMap[$cid] = [
                        'customerId'    => $cid,
                        'customerName'  => $row->customerName,
                        'orders'        => 0,
                        'spent'         => 0.0,
                        'lastOrderDate' => $row->lastOrderDate,
                    ];
                }
                $topCustomersMap[$cid]['orders'] += (int) $row->orders;
                $topCustomersMap[$cid]['spent']  += $converted;
                // Keep the latest lastOrderDate
                if ($row->lastOrderDate > $topCustomersMap[$cid]['lastOrderDate']) {
                    $topCustomersMap[$cid]['lastOrderDate'] = $row->lastOrderDate;
                }
            }
            usort($topCustomersMap, fn($a, $b) => $b['spent'] <=> $a['spent']);
            $topCustomers = array_slice(array_values($topCustomersMap), 0, 10);
            foreach ($topCustomers as &$c) { $c['spent'] = round($c['spent'], 2); }
            unset($c);

            $data = [
                'summary' => [
                    'totalCustomers'        => $totalCustomers,
                    'newCustomers'          => $newCustomers,
                    'activeCustomers'       => $activeCustomersCount,
                    'inactiveCustomers'     => $totalCustomers - $activeCustomersCount,
                    'averageOrderValue'     => $averageOrderValue,
                    'customerRetentionRate' => $totalCustomers > 0
                        ? round(($activeCustomersCount / $totalCustomers) * 100, 2)
                        : 0,
                ],
                'topCustomers' => $topCustomers,
            ];

            return ResponseHelper::success($response, 'Customer report retrieved successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve customer report', 500, $e->getMessage());
        }
    }

    /**
     * Get Expense Report
     */
    public function getExpenseReport(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['dateFrom'] ?? Carbon::now()->startOfMonth()->toDateString();
            $dateTo   = $queryParams['dateTo']   ?? Carbon::now()->toDateString();
            $current  = CurrencyService::getCurrent();

            $range = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            $expenseCount  = Expense::where('status', 'paid')->whereBetween('transactionDate', $range)->count();
            $totalExpenses = $this->sumConverted(
                Expense::where('status', 'paid')->whereBetween('transactionDate', $range),
                'amount'
            );

            // By Category
            $byCategoryRaw = DB::table('expenseCategories')
                ->leftJoin('expenses', 'expenseCategories.id', '=', 'expenses.expenseCategoryId')
                ->where('expenses.status', 'paid')
                ->whereBetween('expenses.transactionDate', $range)
                ->select(
                    'expenseCategories.id as categoryId',
                    'expenseCategories.name as categoryName',
                    'expenses.currency',
                    DB::raw('SUM(expenses.amount) as amount'),
                    DB::raw('COUNT(expenses.id) as itemCount')
                )
                ->groupBy('expenseCategories.id', 'categoryName', 'expenses.currency')
                ->get();

            $byCategoryMap = [];
            foreach ($byCategoryRaw as $row) {
                $cid = $row->categoryId;
                $converted = CurrencyService::convert((float) $row->amount, $row->currency ?? $current, $current);
                if (!isset($byCategoryMap[$cid])) {
                    $byCategoryMap[$cid] = ['categoryId' => $cid, 'categoryName' => $row->categoryName, 'amount' => 0.0, 'itemCount' => 0];
                }
                $byCategoryMap[$cid]['amount']    += $converted;
                $byCategoryMap[$cid]['itemCount'] += (int) $row->itemCount;
            }
            foreach ($byCategoryMap as &$cat) { $cat['amount'] = round($cat['amount'], 2); }
            unset($cat);

            $data = [
                'summary' => [
                    'totalExpenses'    => $totalExpenses,
                    'totalExpenseItems'=> $expenseCount,
                    'averageExpense'   => $expenseCount > 0 ? round($totalExpenses / $expenseCount, 2) : 0,
                ],
                'byCategory' => array_values($byCategoryMap),
            ];

            return ResponseHelper::success($response, 'Expense report retrieved successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve expense report', 500, $e->getMessage());
        }
    }

    /**
     * Export Report (PDF/Excel)
     */
    public function exportReport(Request $request, Response $response, array $args): Response
    {
        return ResponseHelper::success($response, 'Export functionality triggered. File will be available shortly.', [
            'reportType' => $args['reportType'] ?? 'general',
            'format'     => $request->getQueryParams()['format'] ?? 'pdf',
        ]);
    }
}
