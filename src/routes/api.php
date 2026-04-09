<?php
return function ($app): void {
    // Define API routes here. This file is responsible for registering all API endpoints.
    // Get the request URI
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';

    // Map route prefixes to their router files
    // IMPORTANT: More specific prefixes MUST come before less specific ones
    // e.g., '/v1/organizers/finance' must come before '/v1/organizers'
    $routeMap = [
        // Auth & Users
        '/v1/auth' => ROUTE . 'v1/AuthRoute.php',
        '/v1/users' => ROUTE . 'v1/UserRoute.php',
        '/v1/admin' => ROUTE . 'v1/AdminRoute.php',
        '/v1/analytics' => ROUTE . 'v1/AnalyticsRoute.php',
        '/v1/settings' => ROUTE . 'v1/SettingsRoute.php',

        // Settings & Metadata
        '/v1/categories' => ROUTE . 'v1/CategoryRoute.php',
        '/v1/suppliers' => ROUTE . 'v1/SupplierRoute.php',
        '/v1/expense-categories' => ROUTE . 'v1/ExpenseCategoryRoute.php',
        '/v1/discounts' => ROUTE . 'v1/DiscountRoute.php',
        '/v1/units-of-measure' => ROUTE . 'v1/UnitOfMeasureRoute.php',

        // Inventory & Products
        '/v1/products' => ROUTE . 'v1/ProductRoute.php',
        '/v1/inventory' => ROUTE . 'v1/InventoryRoute.php',
        '/v1/purchases' => ROUTE . 'v1/PurchaseRoute.php',

        // Sales & Customers
        '/v1/customers' => ROUTE . 'v1/CustomerRoute.php',
        '/v1/orders' => ROUTE . 'v1/OrderRoute.php',
        '/v1/refunds' => ROUTE . 'v1/RefundRoute.php',

        // Expenses
        '/v1/expenses' => ROUTE . 'v1/ExpenseRoute.php',
        '/v1/expense-schedules' => ROUTE . 'v1/ExpenseScheduleRoute.php',

        // System & Utils
        '/v1/transactions' => ROUTE . 'v1/TransactionRoute.php',
        '/v1/audit-logs' => ROUTE . 'v1/AuditLogRoute.php',
        '/v1/notifications' => ROUTE . 'v1/NotificationRoute.php',
        '/v1/messaging' => ROUTE . 'v1/MessagingRoute.php',
        '/v1/logs' => ROUTE . 'v1/LoggingRoute.php',
        '/v1/utils' => ROUTE . 'v1/UtilsRoute.php',
    ];

    $loadedFiles = [];
    $loaded = false;

    // Check if the request matches any of our defined prefixes
    foreach ($routeMap as $prefix => $routerFile) {
        if (strpos($requestUri, $prefix) === 0) {
            // Load only the matching router
            if (file_exists($routerFile) && !in_array($routerFile, $loadedFiles)) {
                $routeLoader = require $routerFile;
                if (is_callable($routeLoader)) {
                    $routeLoader($app);
                    $loadedFiles[] = $routerFile;
                    $loaded = true;
                }
            }
        }
    }

    // If no specific router was loaded, load all routers as fallback
    if (!$loaded) {
        foreach ($routeMap as $routerFile) {
            if (file_exists($routerFile) && !in_array($routerFile, $loadedFiles)) {
                $routeLoader = require $routerFile;
                if (is_callable($routeLoader)) {
                    $routeLoader($app);
                    $loadedFiles[] = $routerFile;
                }
            }
        }
    }
};
