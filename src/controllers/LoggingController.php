<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Logging\LoggingService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * Logging Controller
 *
 * Provides endpoints for viewing and managing application logs.
 * Designed to be modular and reusable across projects.
 */
class LoggingController
{
    private LoggingService $loggingService;

    public function __construct(LoggingService $loggingService)
    {
        $this->loggingService = $loggingService;
    }

    /**
     * Get logs with filtering and pagination
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();

            $filters = [
                'level' => $queryParams['level'] ?? null,
                'level_name' => $queryParams['level_name'] ?? null,
                'channel' => $queryParams['channel'] ?? null,
                'request_id' => $queryParams['request_id'] ?? null,
                'user_id' => $queryParams['user_id'] ?? null,
                'date_from' => $queryParams['date_from'] ?? null,
                'date_to' => $queryParams['date_to'] ?? null,
                'limit' => (int)($queryParams['limit'] ?? 50),
                'offset' => (int)($queryParams['offset'] ?? 0),
            ];

            // Validate limit
            if ($filters['limit'] > 1000) {
                $filters['limit'] = 1000;
            }

            $logs = $this->loggingService->getLogs($filters);

            return ResponseHelper::success($response, 'Logs retrieved successfully', [
                'logs' => $logs,
                'filters' => $filters,
                'total' => count($logs), // Note: This is not the total count, just the returned count
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve logs', 500, $e->getMessage());
        }
    }

    /**
     * Get log statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['date_from'] ?? null;
            $dateTo = $queryParams['date_to'] ?? null;

            $stats = $this->loggingService->getLogStats($dateFrom, $dateTo);

            return ResponseHelper::success($response, 'Log statistics retrieved successfully', [
                'stats' => $stats,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve log statistics', 500, $e->getMessage());
        }
    }

    /**
     * Get available log levels
     */
    public function levels(Request $request, Response $response): Response
    {
        try {
            $levels = LoggingService::getLogLevels();

            return ResponseHelper::success($response, 'Log levels retrieved successfully', [
                'levels' => $levels,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve log levels', 500, $e->getMessage());
        }
    }

    /**
     * Clean old logs
     */
    public function clean(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $daysOld = (int)($data['days_old'] ?? 30);

            if ($daysOld < 1) {
                return ResponseHelper::error($response, 'Days old must be at least 1', 400);
            }

            $deletedCount = $this->loggingService->cleanOldLogs($daysOld);

            return ResponseHelper::success($response, "Cleaned {$deletedCount} old log entries", [
                'deleted_count' => $deletedCount,
                'days_old' => $daysOld,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to clean old logs', 500, $e->getMessage());
        }
    }

    /**
     * Get a specific log entry by ID
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)$args['id'];

            $logs = $this->loggingService->getLogs(['limit' => 1, 'offset' => 0]);
            $log = null;

            // Find the specific log by ID (inefficient but works for small datasets)
            // In production, you'd want a direct query by ID
            foreach ($logs as $entry) {
                if ((int)$entry['id'] === $id) {
                    $log = $entry;
                    break;
                }
            }

            if (!$log) {
                return ResponseHelper::error($response, 'Log entry not found', 404);
            }

            return ResponseHelper::success($response, 'Log entry retrieved successfully', $log);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve log entry', 500, $e->getMessage());
        }
    }
}