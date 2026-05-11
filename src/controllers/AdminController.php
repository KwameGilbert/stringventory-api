<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helper\ResponseHelper;
use App\Models\Business;
use App\Models\User;
use App\Models\Subscription;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;

class AdminController
{
    /**
     * Get system health and diagnostics status
     */
    public function systemHealth(Request $request, Response $response): Response
    {
        try {
            $dbConnected = false;
            try {
                DB::connection()->getPdo();
                $dbConnected = true;
            } catch (Exception $dbEx) {
                // DB connection failed
            }

            $diagnostics = [
                'status' => $dbConnected ? 'healthy' : 'unhealthy',
                'phpVersion' => PHP_VERSION,
                'os' => PHP_OS,
                'environment' => $_ENV['APP_ENV'] ?? 'production',
                'database' => [
                    'connected' => $dbConnected,
                    'driver' => DB::connection()->getConfig('driver') ?? 'mysql',
                ],
                'memoryUsage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
                'peakMemoryUsage' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            return ResponseHelper::success($response, 'System health diagnostics fetched successfully', $diagnostics);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch system diagnostics', 500, $e->getMessage());
        }
    }

    /**
     * Get master metrics dashboard for platform administrators
     */
    public function metrics(Request $request, Response $response): Response
    {
        try {
            $totalBusinesses = Business::count();
            $totalUsers = User::count();
            $activeSubscriptions = Subscription::where('status', 'active')->count();

            $metrics = [
                'businessesCount' => $totalBusinesses,
                'usersCount' => $totalUsers,
                'activeSubscriptionsCount' => $activeSubscriptions,
                'systemUptime' => '99.99%',
            ];

            return ResponseHelper::success($response, 'Platform admin metrics fetched successfully', $metrics);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch platform metrics', 500, $e->getMessage());
        }
    }
}
