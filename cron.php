<?php

declare(strict_types=1);

/**
 * Cron Job Manager
 * 
 * Handles periodic tasks for the Stringventory API
 */

require_once __DIR__ . '/src/config/Constants.php';
require_once BASE . 'vendor/autoload.php';

// Bootstrap the application (but don't run Slim)
// We only need the container and services
use App\Config\EloquentBootstrap;
use App\Services\ExpenseService;
use App\Services\NotificationService;
use App\Services\NotificationQueue;
use DI\Container;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(BASE);
$dotenv->safeLoad();

// Boostrap Eloquent
EloquentBootstrap::boot();

// Setup Container manually since we don't want to boot the full Slim App for cron
$container = new Container();
$registerServices = require BOOTSTRAP . 'services.php';
$container = $registerServices($container);

// Get the command from arguments
$command = $argv[1] ?? 'help';

echo "[" . date('Y-m-d H:i:s') . "] Starting task: $command\n";

try {
    switch ($command) {
        case 'expenses':
            echo "Processing scheduled expenses...\n";
            $expenseService = $container->get(ExpenseService::class);
            $result = $expenseService->processScheduledExpenses();
            echo "Done. Processed: " . count($result['processed']) . " schedules. Errors: " . count($result['errors']) . "\n";
            if (!empty($result['errors'])) {
                print_r($result['errors']);
            }
            break;

        case 'notifications':
            echo "Processing notification queue...\n";
            $queue = $container->get(NotificationQueue::class);
            $service = $container->get(NotificationService::class);
            
            $limit = 100; // Process 100 notifications per run
            $processed = 0;
            $failed = 0;

            while ($job = $queue->dequeue()) {
                if ($processed >= $limit) break;
                
                try {
                    if ($service->processJob($job['notification'])) {
                        $queue->complete($job);
                        $processed++;
                    } else {
                        $queue->fail($job, "Failed to send via any channel");
                        $failed++;
                    }
                } catch (Exception $e) {
                    $queue->fail($job, $e->getMessage());
                    $failed++;
                }
            }
            echo "Queue processing complete. Processed: $processed, Failed: $failed\n";
            break;

        case 'help':
        default:
            echo "Available commands: expenses, notifications\n";
            break;
    }
} catch (Exception $e) {
    echo "CRON ERROR: " . $e->getMessage() . "\n";
    error_log("Cron Error ($command): " . $e->getMessage());
}

echo "[" . date('Y-m-d H:i:s') . "] Task $command finished.\n";
