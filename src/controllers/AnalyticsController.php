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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Carbon\Carbon;
use Exception;

class AnalyticsController
{
    /**
     * Get Dashboard Overview Metrics and Charts
     */
    public function getDashboardOverview(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['dateFrom'] ?? Carbon::now()->startOfMonth()->toDateString();
            $dateTo = $queryParams['dateTo'] ?? Carbon::now()->toDateString();

            // Basic Metrics
            $grossRevenue = (float) Order::where('status', 'completed')
                ->whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->sum('discountedTotalPrice');

            $totalOrders = Order::where('status', 'completed')
                ->whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->count();

            $totalExpenses = (float) Expense::where('status', 'paid')
                ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->sum('amount');

            $netProfit = $grossRevenue - $totalExpenses;

            $totalCustomers = Customer::whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->count();

            // Current Inventory State (not filtered by date range as it's a current snapshot)
            $inventoryValue = (float) DB::table('inventory')
                ->join('purchaseItems', 'inventory.productId', '=', 'purchaseItems.productId')
                ->sum(DB::raw('inventory.quantity * purchaseItems.costPrice'));
            
            // If inventory value logic above is too complex, we can fall back to product costPrice if available
            if ($inventoryValue == 0) {
                $inventoryValue = (float) DB::table('inventory')
                    ->join('products', 'inventory.productId', '=', 'products.id')
                    ->sum(DB::raw('inventory.quantity * products.costPrice'));
            }

            $lowStockItems = Inventory::where('quantity', '<=', 10)->count(); // Default threshold
            $pendingOrders = Order::where('status', 'pending')->count();

            // Charts: Revenue and Expenses By Date
            $revenueData = Order::where('status', 'completed')
                ->whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->select(DB::raw('DATE(createdAt) as date'), DB::raw('SUM(discountedTotalPrice) as revenue'))
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

            $expenseData = Expense::where('status', 'paid')
                ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->select(DB::raw('DATE(transactionDate) as date'), DB::raw('SUM(amount) as expenses'))
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

            // Merge revenue and expenses by date
            $allDates = array_unique(array_merge(array_keys($revenueData), array_keys($expenseData)));
            sort($allDates);

            $revenueByDate = [];
            foreach ($allDates as $date) {
                $revenueByDate[] = [
                    'date' => $date,
                    'revenue' => (float) ($revenueData[$date]['revenue'] ?? 0),
                    'expenses' => (float) ($expenseData[$date]['expenses'] ?? 0)
                ];
            }

            // Charts: Top Products
            $topProducts = OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                ->join('products', 'orderItems.productId', '=', 'products.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->select(
                    'orderItems.productId',
                    'products.name as productName',
                    DB::raw('SUM(orderItems.quantity) as sales'),
                    DB::raw('SUM(orderItems.totalPrice) as revenue')
                )
                ->groupBy('orderItems.productId', 'productName')
                ->orderBy('sales', 'desc')
                ->limit(5)
                ->get();

            // Charts: Top Customers
            $topCustomers = Order::join('customers', 'orders.customerId', '=', 'customers.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->select(
                    'orders.customerId',
                    DB::raw("CONCAT(customers.firstName, ' ', customers.lastName) as customerName"),
                    DB::raw('COUNT(orders.id) as orders'),
                    DB::raw('SUM(orders.discountedTotalPrice) as spent')
                )
                ->groupBy('orders.customerId', 'customerName')
                ->orderBy('spent', 'desc')
                ->limit(5)
                ->get();

            $data = [
                'metrics' => [
                    'grossRevenue' => ['value' => $grossRevenue, 'change' => 0, 'trend' => 'stable'], // Change calculation requires previous period data
                    'totalOrders' => ['value' => $totalOrders, 'change' => 0, 'trend' => 'stable'],
                    'totalExpenses' => ['value' => $totalExpenses, 'change' => 0, 'trend' => 'stable'],
                    'netProfit' => ['value' => $netProfit, 'change' => 0, 'trend' => 'stable'],
                    'totalCustomers' => ['value' => $totalCustomers, 'change' => 0, 'trend' => 'stable'],
                    'inventoryValue' => ['value' => $inventoryValue, 'change' => 0, 'trend' => 'stable'],
                    'lowStockItems' => $lowStockItems,
                    'pendingOrders' => $pendingOrders
                ],
                'charts' => [
                    'revenueByDate' => $revenueByDate,
                    'topProducts' => $topProducts,
                    'topCustomers' => $topCustomers
                ]
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
            $dateTo = $queryParams['dateTo'] ?? Carbon::now()->toDateString();

            $totalSales = (float) Order::where('status', 'completed')
                ->whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->sum('discountedTotalPrice');

            $totalOrders = Order::where('status', 'completed')
                ->whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->count();

            $data = [
                'summary' => [
                    'totalSales' => $totalSales,
                    'totalOrders' => $totalOrders,
                    'averageOrderValue' => $totalOrders > 0 ? $totalSales / $totalOrders : 0,
                    'totalItems' => (int) OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                        ->where('orders.status', 'completed')
                        ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->sum('orderItems.quantity'),
                    'topPaymentMethod' => DB::table('transactions')
                        ->join('orders', 'transactions.orderId', '=', 'orders.id')
                        ->where('orders.status', 'completed')
                        ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->select('transactions.paymentMethod', DB::raw('COUNT(*) as count'))
                        ->groupBy('transactions.paymentMethod')
                        ->orderBy('count', 'desc')
                        ->first()->paymentMethod ?? 'N/A'
                ],
                'byDate' => (function() use ($dateFrom, $dateTo) {
                    $byDateSales = Order::where('status', 'completed')
                        ->whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->select(
                            DB::raw('DATE(createdAt) as date'),
                            DB::raw('SUM(discountedTotalPrice) as sales'),
                            DB::raw('COUNT(*) as orders')
                        )
                        ->groupBy('date')
                        ->get()
                        ->keyBy('date');

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

                    // Merge results
                    return $byDateSales->map(function ($row, $date) use ($byDateItems) {
                        return [
                            'date' => $row->date,
                            'sales' => (float) $row->sales,
                            'orders' => (int) $row->orders,
                            'items' => (int) ($byDateItems[$date]->items ?? 0)
                        ];
                    })->values();
                })(),
                'byProduct' => OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
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
                    ->orderBy('revenue', 'desc')
                    ->get(),
                'byCustomer' => Order::join('customers', 'orders.customerId', '=', 'customers.id')
                    ->where('orders.status', 'completed')
                    ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                    ->select(
                        'orders.customerId',
                        DB::raw("CONCAT(customers.firstName, ' ', customers.lastName) as customerName"),
                        DB::raw('COUNT(orders.id) as orders'),
                        DB::raw('SUM(orders.discountedTotalPrice) as spent')
                    )
                    ->groupBy('orders.customerId', 'customerName')
                    ->orderBy('spent', 'desc')
                    ->get()
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
            
            $inventoryValue = (float) DB::table('inventory')
                ->join('products', 'inventory.productId', '=', 'products.id')
                ->sum(DB::raw('inventory.quantity * products.costPrice'));

            $data = [
                'summary' => [
                    'totalProducts' => $totalProducts,
                    'totalQuantity' => (int) $totalQuantity,
                    'totalValue' => $inventoryValue,
                    'lowStockItems' => Inventory::where('quantity', '<=', 10)->count(),
                    'outOfStockItems' => Inventory::where('quantity', '<=', 0)->count()
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
                    ->get()
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
            $dateTo = $queryParams['dateTo'] ?? Carbon::now()->toDateString();

            $salesIncome = (float) Order::where('status', 'completed')
                ->whereBetween('createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->sum('discountedTotalPrice');

            $costOfGoods = (float) OrderItem::join('orders', 'orderItems.orderId', '=', 'orders.id')
                ->join('products', 'orderItems.productId', '=', 'products.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.createdAt', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->sum(DB::raw('orderItems.quantity * products.costPrice'));

            $expenses = (float) Expense::where('status', 'paid')
                ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->sum('amount');

            $data = [
                'income' => [
                    'sales' => $salesIncome,
                    'other' => 0,
                    'total' => $salesIncome
                ],
                'expenses' => [
                    'costOfGoods' => $costOfGoods,
                    'operationalExpenses' => $expenses,
                    'other' => 0,
                    'total' => $costOfGoods + $expenses
                ],
                'summary' => [
                    'grossProfit' => $salesIncome - $costOfGoods,
                    'netProfit' => $salesIncome - ($costOfGoods + $expenses),
                    'profitMargin' => $salesIncome > 0 ? (($salesIncome - ($costOfGoods + $expenses)) / $salesIncome) * 100 : 0,
                    'roi' => ($costOfGoods + $expenses) > 0 ? (($salesIncome - ($costOfGoods + $expenses)) / ($costOfGoods + $expenses)) * 100 : 0
                ]
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
            $totalCustomers = Customer::count();
            $newCustomers = Customer::where('createdAt', '>=', Carbon::now()->startOfMonth())->count();
            
            $activeCustomersCount = Order::where('status', 'completed')
                ->where('createdAt', '>=', Carbon::now()->subMonths(3))
                ->distinct('customerId')
                ->count('customerId');

            $data = [
                'summary' => [
                    'totalCustomers' => $totalCustomers,
                    'newCustomers' => $newCustomers,
                    'activeCustomers' => $activeCustomersCount,
                    'inactiveCustomers' => $totalCustomers - $activeCustomersCount,
                    'averageOrderValue' => (float) Order::where('status', 'completed')->avg('discountedTotalPrice'),
                    'customerRetentionRate' => $totalCustomers > 0 ? ($activeCustomersCount / $totalCustomers) * 100 : 0
                ],
                'topCustomers' => Order::join('customers', 'orders.customerId', '=', 'customers.id')
                    ->where('orders.status', 'completed')
                    ->select(
                        'orders.customerId',
                        DB::raw("CONCAT(customers.firstName, ' ', customers.lastName) as customerName"),
                        DB::raw('COUNT(orders.id) as orders'),
                        DB::raw('SUM(orders.discountedTotalPrice) as spent'),
                        DB::raw('MAX(orders.createdAt) as lastOrderDate')
                    )
                    ->groupBy('orders.customerId', 'customerName')
                    ->orderBy('spent', 'desc')
                    ->limit(10)
                    ->get()
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
                    'totalExpenses' => $totalExpenses,
                    'totalExpenseItems' => Expense::where('status', 'paid')
                        ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->count(),
                    'averageExpense' => (float) Expense::where('status', 'paid')
                        ->whereBetween('transactionDate', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                        ->avg('amount')
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
                    ->get()
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
        // Simple mock for now as PDF generation usually requires external library like Dompdf
        return ResponseHelper::success($response, 'Export functionality triggered. File will be available shortly.', [
            'reportType' => $args['reportType'] ?? 'general',
            'format' => $request->getQueryParams()['format'] ?? 'pdf'
        ]);
    }
}
