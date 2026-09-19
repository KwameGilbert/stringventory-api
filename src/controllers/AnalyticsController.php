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
use App\Models\AuditLog;
use App\Models\User;
use App\Helper\ResponseHelper;
use App\Services\RevenueMetrics;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

class AnalyticsController
{
    /**
     * Get Dashboard Overview Metrics and Charts
     *
     * Revenue figures come from RevenueMetrics: Net Revenue = Gross Sales - Discounts - Refunds,
     * and Net Profit also deducts net cost of goods and paid expenses.
     */
    public function getDashboardOverview(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['dateFrom'] ?? Carbon::now()->startOfMonth()->toDateString();
            $dateTo = $queryParams['dateTo'] ?? Carbon::now()->toDateString();

            $refundedItems = RevenueMetrics::refundedItems($dateFrom, $dateTo);
            $summary = RevenueMetrics::summary($dateFrom, $dateTo, $refundedItems);

            $totalCustomers = Customer::whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->count();

            $inventoryValue = Inventory::totalValue();

            $lowStockItems = Inventory::lowStock()->count();
            $pendingOrders = Order::where('status', 'pending')->count();

            $salesByDate = RevenueMetrics::salesByDate($dateFrom, $dateTo);
            $refundsByDate = RevenueMetrics::refundsByDate($dateFrom, $dateTo);

            $expenseData = Expense::where('status', 'paid')
                ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->select(DB::raw('DATE(transactionDate) as date'), DB::raw('SUM(amount) as expenses'))
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

            $allDates = array_unique(array_merge(
                array_keys($salesByDate),
                array_keys($refundsByDate),
                array_keys($expenseData)
            ));
            sort($allDates);

            // `revenue` is NET (gross sales - discounts - refunds) so the trend charts reflect refunds.
            $revenueByDate = [];
            foreach ($allDates as $date) {
                $grossSales = $salesByDate[$date]['grossSales'] ?? 0.0;
                $discounts = $salesByDate[$date]['discounts'] ?? 0.0;
                $refunds = $refundsByDate[$date] ?? 0.0;

                $revenueByDate[] = [
                    'date'       => $date,
                    'revenue'    => round($grossSales - $discounts - $refunds, 2),
                    'grossSales' => $grossSales,
                    'discounts'  => $discounts,
                    'refunds'    => $refunds,
                    'expenses'   => (float) ($expenseData[$date]['expenses'] ?? 0),
                ];
            }

            $topProducts = $this->netProductSales($dateFrom, $dateTo, $refundedItems['byProduct'])
                ->sortByDesc('quantity')
                ->take(5)
                ->map(fn (array $row) => [
                    'productId'   => $row['productId'],
                    'productName' => $row['productName'],
                    'sales'       => $row['quantity'],
                    'revenue'     => $row['revenue'],
                ])
                ->values();

            $topCustomers = $this->netCustomerSales($dateFrom, $dateTo)
                ->sortByDesc('spent')
                ->take(5)
                ->values();

            $revenueByPaymentMethod = RevenueMetrics::revenueByPaymentMethod($dateFrom, $dateTo);

            $data = [
                'metrics' => [
                    'grossSales'     => ['value' => $summary['grossSales'],   'change' => 0, 'trend' => 'stable'],
                    'discounts'      => ['value' => $summary['discounts'],    'change' => 0, 'trend' => 'stable'],
                    'totalRefunds'   => ['value' => $summary['refunds'],      'change' => 0, 'trend' => 'stable'],
                    'netRevenue'     => ['value' => $summary['netRevenue'],   'change' => 0, 'trend' => 'stable'],
                    'totalOrders'    => ['value' => $summary['orderCount'],   'change' => 0, 'trend' => 'stable'],
                    'totalExpenses'  => ['value' => $summary['expenses'],     'change' => 0, 'trend' => 'stable'],
                    'netProfit'      => ['value' => $summary['netProfit'],    'change' => 0, 'trend' => 'stable'],
                    'stockLoss'      => ['value' => $summary['stockLoss'],    'change' => 0, 'trend' => 'stable'],
                    'totalCustomers' => ['value' => $totalCustomers,          'change' => 0, 'trend' => 'stable'],
                    'inventoryValue' => ['value' => $inventoryValue,          'change' => 0, 'trend' => 'stable'],
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
     *
     * `totalSales` / `netSales` are net of discounts and refunds; `grossSales` is before both.
     */
    public function getSalesReport(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['dateFrom'] ?? Carbon::now()->startOfMonth()->toDateString();
            $dateTo = $queryParams['dateTo'] ?? Carbon::now()->toDateString();

            $revenue = RevenueMetrics::revenue($dateFrom, $dateTo);
            $refundedItems = RevenueMetrics::refundedItems($dateFrom, $dateTo);

            $totalSales = $revenue['netRevenue'];
            $totalOrders = $revenue['orderCount'];

            $unitsSold = (int) OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->sum('orderItems.quantity');
            $unitsRefunded = (int) array_sum(array_column($refundedItems['byProduct'], 'units'));

            $data = [
                'summary' => [
                    'totalSales'        => $totalSales,
                    'grossSales'        => $revenue['grossSales'],
                    'discounts'         => $revenue['discounts'],
                    'totalRefunds'      => $revenue['refunds'],
                    'netSales'          => $totalSales,
                    'totalOrders'       => $totalOrders,
                    'averageOrderValue' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
                    'totalItems'        => $unitsSold - $unitsRefunded,
                    'topPaymentMethod'  => DB::table('transactions')
                        ->join('orders', 'transactions.orderId', '=', 'orders.id')
                        ->where('orders.status', 'completed')
                        ->where('transactions.transactionType', 'order')
                        ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->select('transactions.paymentMethod', DB::raw('COUNT(*) as count'))
                        ->groupBy('transactions.paymentMethod')
                        ->orderBy('count', 'desc')
                        ->first()->paymentMethod ?? 'N/A',
                ],
                'byPaymentMethod' => array_map(
                    fn (array $row) => [
                        'paymentMethod' => $row['paymentMethod'],
                        'revenue'       => $row['revenue'],
                        'orders'        => $row['transactionCount'],
                    ],
                    RevenueMetrics::revenueByPaymentMethod($dateFrom, $dateTo)
                ),
                'byDate' => (function () use ($dateFrom, $dateTo, $refundedItems) {
                    $salesByDate = RevenueMetrics::salesByDate($dateFrom, $dateTo);
                    $refundsByDate = RevenueMetrics::refundsByDate($dateFrom, $dateTo);

                    $byDateItems = OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                        ->where('orders.status', 'completed')
                        ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->select(
                            DB::raw('DATE(orders.createdAt) as date'),
                            DB::raw('SUM(orderItems.quantity) as items')
                        )
                        ->groupBy('date')
                        ->get()
                        ->keyBy('date');

                    $dates = array_unique(array_merge(
                        array_keys($salesByDate),
                        array_keys($refundsByDate),
                        array_keys($refundedItems['byDate'])
                    ));
                    sort($dates);

                    $rowFor = function ($date) use ($salesByDate, $refundsByDate, $byDateItems, $refundedItems) {
                        $grossSales = $salesByDate[$date]['grossSales'] ?? 0.0;
                        $discounts = $salesByDate[$date]['discounts'] ?? 0.0;
                        $refunds = $refundsByDate[$date] ?? 0.0;
                        $itemsSold = (int) ($byDateItems[$date]->items ?? 0);
                        $itemsRefunded = $refundedItems['byDate'][$date] ?? 0;

                        return [
                            'date'       => $date,
                            'sales'      => round($grossSales - $discounts - $refunds, 2),
                            'grossSales' => $grossSales,
                            'discounts'  => $discounts,
                            'refunds'    => $refunds,
                            'orders'     => (int) ($salesByDate[$date]['orders'] ?? 0),
                            'items'      => $itemsSold - $itemsRefunded,
                        ];
                    };

                    return Collection::make($dates)->map($rowFor)->values();
                })(),
                'byProduct' => $this->netProductSales($dateFrom, $dateTo, $refundedItems['byProduct'])
                    ->sortByDesc('revenue')
                    ->values(),
                'byCustomer' => $this->netCustomerSales($dateFrom, $dateTo)
                    ->sortByDesc('spent')
                    ->values(),
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

            $inventoryValue = Inventory::totalValue();

            $data = [
                'summary' => [
                    'totalProducts'   => $totalProducts,
                    'totalQuantity'   => (int) $totalQuantity,
                    'totalValue'      => $inventoryValue,
                    'lowStockItems'   => Inventory::lowStock()->count(),
                    'outOfStockItems' => Inventory::where('quantity', '<=', 0)->count(),
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
                    ->lowStock()
                    ->select(
                        'inventory.productId',
                        'products.name as productName',
                        'products.sku',
                        'inventory.quantity',
                        DB::raw('COALESCE(products.reorderLevel, ' . Product::DEFAULT_REORDER_LEVEL . ') as reorderLevel')
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
     *
     * Income statement: Net Revenue = Gross Sales - Discounts - Refunds; Gross Profit = Net Revenue -
     * net cost of goods (COGS less the cost of refunded units that were restocked); Net Profit =
     * Gross Profit - paid expenses. `stockLoss` is a memo (refunded units that were not restocked)
     * and is already inside `costOfGoods`, so it is not deducted again.
     */
    public function getFinancialReport(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['dateFrom'] ?? Carbon::now()->startOfMonth()->toDateString();
            $dateTo = $queryParams['dateTo'] ?? Carbon::now()->toDateString();

            $summary = RevenueMetrics::summary($dateFrom, $dateTo);
            $totalCosts = $summary['costOfGoods'] + $summary['expenses'];

            $data = [
                'income' => [
                    'grossSales' => $summary['grossSales'],
                    'discounts'  => $summary['discounts'],
                    'refunds'    => $summary['refunds'],
                    'other'      => 0,
                    'total'      => $summary['netRevenue'],
                ],
                'expenses' => [
                    'costOfGoods'         => $summary['costOfGoods'],
                    'costOfGoodsGross'    => $summary['costOfGoodsGross'],
                    'costRecovered'       => $summary['costRecovered'],
                    'operationalExpenses' => $summary['expenses'],
                    'other'               => 0,
                    'total'               => round($totalCosts, 2),
                ],
                'summary' => [
                    'grossProfit'  => $summary['grossProfit'],
                    'netProfit'    => $summary['netProfit'],
                    'grossMargin'  => $summary['grossMargin'],
                    'profitMargin' => $summary['netMargin'],
                    'roi'          => $totalCosts > 0 ? round($summary['netProfit'] / $totalCosts * 100, 2) : 0,
                    'stockLoss'    => $summary['stockLoss'],
                ],
            ];

            return ResponseHelper::success($response, 'Financial report retrieved successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve financial report', 500, $e->getMessage());
        }
    }

    /**
     * Get Customer Report
     *
     * Order values are net of refunds (see RevenueMetrics).
     */
    public function getCustomerReport(Request $request, Response $response): Response
    {
        try {
            $totalCustomers = Customer::count();
            $newCustomers   = Customer::where('createdAt', '>=', Carbon::now()->startOfMonth())->count();

            $activeCustomersCount = Order::where('status', 'completed')
                ->where('createdAt', '>=', Carbon::now()->subMonths(3))
                ->distinct('customerId')
                ->count('customerId');

            $revenue = RevenueMetrics::revenue(null, null);

            $data = [
                'summary' => [
                    'totalCustomers'        => $totalCustomers,
                    'newCustomers'          => $newCustomers,
                    'activeCustomers'       => $activeCustomersCount,
                    'inactiveCustomers'     => $totalCustomers - $activeCustomersCount,
                    'averageOrderValue'     => $revenue['orderCount'] > 0
                        ? round($revenue['netRevenue'] / $revenue['orderCount'], 2)
                        : 0.0,
                    'customerRetentionRate' => $totalCustomers > 0
                        ? ($activeCustomersCount / $totalCustomers) * 100
                        : 0,
                ],
                'topCustomers' => $this->netCustomerSales(null, null)
                    ->sortByDesc('spent')
                    ->take(10)
                    ->values(),
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
            $dateTo = $queryParams['dateTo'] ?? Carbon::now()->toDateString();

            $totalExpenses = (float) Expense::where('status', 'paid')
                ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->sum('amount');

            $data = [
                'summary' => [
                    'totalExpenses'     => $totalExpenses,
                    'totalExpenseItems' => Expense::where('status', 'paid')
                        ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->count(),
                    'averageExpense'    => (float) Expense::where('status', 'paid')
                        ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->avg('amount'),
                ],
                'byCategory' => DB::table('expenseCategories')
                    ->leftJoin('expenses', 'expenseCategories.id', '=', 'expenses.expenseCategoryId')
                    ->where('expenses.status', 'paid')
                    ->whereBetween('expenses.transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                    ->select(
                        'expenseCategories.id as categoryId',
                        'expenseCategories.name as categoryName',
                        DB::raw('SUM(expenses.amount) as amount'),
                        DB::raw('COUNT(expenses.id) as itemCount')
                    )
                    ->groupBy('expenseCategories.id', 'expenseCategories.name')
                    ->get(),
            ];

            return ResponseHelper::success($response, 'Expense report retrieved successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve expense report', 500, $e->getMessage());
        }
    }

    /**
     * Get Activity Logs with Summary and Pagination
     */
    public function getActivityLogs(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $page = (int)($queryParams['page'] ?? 1);
            $limit = (int)($queryParams['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            // 1. Summary Statistics
            $totalActions = AuditLog::count();
            
            // Active users in the last 30 days
            $activeUsersCount = AuditLog::where('createdAt', '>=', Carbon::now()->subDays(30))
                ->distinct('userId')
                ->count('userId');

            // Most active user
            $mostActiveUserRaw = AuditLog::select('userId', DB::raw('COUNT(*) as actionCount'))
                ->whereNotNull('userId')
                ->groupBy('userId')
                ->orderBy('actionCount', 'desc')
                ->first();

            $mostActiveUser = null;
            if ($mostActiveUserRaw) {
                $user = User::find($mostActiveUserRaw->userId);
                if ($user) {
                    // Find their primary module (the one they have the most logs in)
                    $primaryModuleRaw = AuditLog::where('userId', $user->id)
                        ->select('action', DB::raw('COUNT(*) as moduleCount'))
                        ->groupBy('action')
                        ->orderBy('moduleCount', 'desc')
                        ->first();
                    
                    $primaryModule = 'System';
                    if ($primaryModuleRaw) {
                        $tempLog = new AuditLog(['action' => $primaryModuleRaw->action]);
                        $primaryModule = $tempLog->getModule();
                    }
                    
                    $mostActiveUser = [
                        'name' => $user->firstName . ' ' . $user->lastName,
                        'actionCount' => (int)$mostActiveUserRaw->actionCount,
                        'primaryModule' => $primaryModule
                    ];
                }
            }

            // 2. Fetch Logs
            $logsRaw = AuditLog::with('user')
                ->orderBy('createdAt', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            $logs = $logsRaw->map(function ($log) {
                $metadata = $log->metadata ?? [];
                
                // Transform metadata for inventory adjustments to match user example
                if ($log->action === 'inventory_adjusted' && isset($metadata['adjustment'], $metadata['newQuantity'])) {
                    $metadata['previousValue'] = $metadata['newQuantity'] - $metadata['adjustment'];
                    $metadata['newValue'] = $metadata['newQuantity'];
                    $metadata['reason'] = $metadata['reason'] ?? 'Manual adjustment';
                    // Clean up internal fields
                    unset($metadata['adjustment'], $metadata['newQuantity']);
                }

                return [
                    'id' => 'log_' . str_pad((string)$log->id, 5, '0', STR_PAD_LEFT) . strtoupper(substr(md5((string)$log->id), 0, 1)),
                    'time' => $log->createdAt ? $log->createdAt->toIso8601String() : null,
                    'user' => [
                        'id' => $log->user ? 'usr_' . $log->user->id : 'usr_system',
                        'name' => $log->user ? $log->user->firstName . ' ' . $log->user->lastName : 'System',
                        'role' => $log->user ? ucfirst($log->user->role) : 'Automated'
                    ],
                    'module' => $log->getModule(),
                    'action' => $log->getFormattedAction(),
                    'details' => $log->getDetails(),
                    'severity' => $log->getSeverity(),
                    'metadata' => !empty($metadata) ? $metadata : new \stdClass()
                ];
            });

            $data = [
                'summary' => [
                    'activeUsers' => $activeUsersCount,
                    'totalActions' => $totalActions,
                    'mostActiveUser' => $mostActiveUser
                ],
                'logs' => $logs,
                'pagination' => [
                    'total' => $totalActions,
                    'page' => $page,
                    'limit' => $limit
                ]
            ];

            return ResponseHelper::jsonResponse($response, [
                'status' => 'success',
                'data' => $data
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve activity logs', 500, $e->getMessage());
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

    /**
     * Per-product sales for completed orders in the period, net of refunds: units kept and revenue
     * after refunded units are taken off (units x selling price, the same pre-order-discount basis
     * as orderItems.totalPrice). Products refunded in the period but not sold in it appear with
     * negative figures, because refunds are counted when they were completed.
     *
     * @param array<int, array{units: int, value: float}> $refundedByProduct RevenueMetrics::refundedItems()['byProduct']
     * @return Collection<int, array{productId: int, productName: string, quantity: int, revenue: float}>
     */
    private function netProductSales(string $dateFrom, string $dateTo, array $refundedByProduct): Collection
    {
        $sold = OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
            ->join('products', 'orderItems.productId', '=', 'products.id')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select(
                'orderItems.productId',
                'products.name as productName',
                DB::raw('SUM(orderItems.quantity) as quantity'),
                DB::raw('SUM(orderItems.totalPrice) as revenue')
            )
            ->groupBy('orderItems.productId', 'productName')
            ->get();

        $rows = [];
        foreach ($sold as $row) {
            $rows[(int) $row->productId] = [
                'productId'   => (int) $row->productId,
                'productName' => $row->productName,
                'quantity'    => (int) $row->quantity,
                'revenue'     => (float) $row->revenue,
            ];
        }

        $missing = array_diff(array_keys($refundedByProduct), array_keys($rows));
        if ($missing) {
            $names = DB::table('products')->whereIn('id', $missing)->pluck('name', 'id');
            foreach ($missing as $productId) {
                $rows[$productId] = [
                    'productId'   => $productId,
                    'productName' => $names[$productId] ?? 'Unknown',
                    'quantity'    => 0,
                    'revenue'     => 0.0,
                ];
            }
        }

        return Collection::make($rows)->map(function (array $row) use ($refundedByProduct) {
            $refunded = $refundedByProduct[$row['productId']] ?? ['units' => 0, 'value' => 0.0];
            $row['quantity'] -= $refunded['units'];
            $row['revenue'] = round($row['revenue'] - $refunded['value'], 2);

            return $row;
        })->values();
    }

    /**
     * Per-customer completed-order spend, net of refunds, for orders in the period (null = all
     * time). Refunds are counted when they were completed, so a customer refunded in the period for
     * an earlier order appears with a negative figure.
     *
     * @return Collection<int, array{customerId: int, customerName: string, orders: int, spent: float, lastOrderDate: ?string}>
     */
    private function netCustomerSales(?string $dateFrom, ?string $dateTo): Collection
    {
        $query = Order::join('customers', 'orders.customerId', '=', 'customers.id')
            ->where('orders.status', 'completed');

        if ($dateFrom !== null && $dateTo !== null) {
            $query->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        }

        $sales = $query->select(
            'orders.customerId',
            DB::raw("TRIM(CONCAT_WS(' ', customers.firstName, customers.lastName)) as customerName"),
            DB::raw('COUNT(orders.id) as orders'),
            DB::raw('SUM(orders.discountedTotalPrice) as spent'),
            DB::raw('MAX(orders.createdAt) as lastOrderDate')
        )
            ->groupBy('orders.customerId', 'customerName')
            ->get();

        $rows = [];
        foreach ($sales as $row) {
            $rows[(int) $row->customerId] = [
                'customerId'    => (int) $row->customerId,
                'customerName'  => $row->customerName,
                'orders'        => (int) $row->orders,
                'spent'         => (float) $row->spent,
                'lastOrderDate' => $row->lastOrderDate,
            ];
        }

        $refunds = RevenueMetrics::refundsByCustomer($dateFrom, $dateTo);

        $missing = array_diff(array_keys($refunds), array_keys($rows));
        if ($missing) {
            $names = DB::table('customers')
                ->whereIn('id', $missing)
                ->selectRaw("id, TRIM(CONCAT_WS(' ', firstName, lastName)) AS customerName")
                ->pluck('customerName', 'id');
            foreach ($missing as $customerId) {
                $rows[$customerId] = [
                    'customerId'    => $customerId,
                    'customerName'  => $names[$customerId] ?? '',
                    'orders'        => 0,
                    'spent'         => 0.0,
                    'lastOrderDate' => null,
                ];
            }
        }

        return Collection::make($rows)->map(function (array $row) use ($refunds) {
            $row['spent'] = round($row['spent'] - ($refunds[$row['customerId']] ?? 0.0), 2);

            return $row;
        })->values();
    }
}
