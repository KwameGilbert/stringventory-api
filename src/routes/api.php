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

        // Organizers (specific routes first)
        '/v1/organizers/finance' => ROUTE . 'v1/PayoutRoute.php',
        '/v1/organizers' => ROUTE . 'v1/OrganizerRoute.php',

        // Attendees
        '/v1/attendees' => ROUTE . 'v1/AttendeeRoute.php',

        // Events & related
        '/v1/event-images' => ROUTE . 'v1/EventImageRoute.php',
        '/v1/events' => ROUTE . 'v1/EventRoute.php',

        // Tickets
        '/v1/ticket-types' => ROUTE . 'v1/TicketTypeRoute.php',
        '/v1/tickets' => ROUTE . 'v1/TicketRoute.php',

        // Orders & Payments
        '/v1/orders' => ROUTE . 'v1/OrderRoute.php',
        '/v1/payment' => ROUTE . 'v1/OrderRoute.php', // Paystack webhook

        // Scanners & POS
        '/v1/scanners' => ROUTE . 'v1/ScannerRoute.php',
        '/v1/pos' => ROUTE . 'v1/PosRoute.php',

        // Awards & related
        '/v1/award-categories' => ROUTE . 'v1/AwardCategoryRoute.php',
        '/v1/awards' => ROUTE . 'v1/AwardRoute.php',
        '/v1/nominees' => ROUTE . 'v1/AwardNomineeRoute.php',
        '/v1/votes' => ROUTE . 'v1/AwardVoteRoute.php',

        // Utils
        '/v1/utils' => ROUTE . 'v1/UtilsRoute.php',

        // Admin (specific routes first)
        '/v1/admin/payouts' => ROUTE . 'v1/PayoutRoute.php',
        '/v1/admin/analytics' => ROUTE . 'v1/AdminRoute.php',
        '/v1/admin/settings' => ROUTE . 'v1/AdminRoute.php',
        '/v1/admin' => ROUTE . 'v1/AdminRoute.php',
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
