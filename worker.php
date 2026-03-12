<?php

declare(strict_types=1);

/**
 * Queue Worker - Process notification queue
 * 
 * Run continuously: php worker.php
 * Or as cron job: * * * * * php /path/to/worker.php
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Services\NotificationService;
use App\Services\NotificationQueue;
use App\Services\EmailService;
use App\Services\SMSService;
use App\Services\TemplateEngine;

// Get services from container
$emailService = $container->get(EmailService::class);
$smsService = $container->get(SMSService::class);
$templateEngine = new TemplateEngine();
$queue = new NotificationQueue();
$notificationService = new NotificationService(
    $emailService,
    $smsService,
    $queue,
    $templateEngine
);

// Worker configuration
$sleepSeconds = (int)($_ENV['QUEUE_SLEEP_SECONDS'] ?? 5);
$maxJobs = (int)($_ENV['QUEUE_MAX_JOBS_PER_RUN'] ?? 10);
$continuousMode = ($_ENV['QUEUE_CONTINUOUS'] ?? 'true')=== 'true';

echo "Notification Queue Worker Started\n";
echo "Sleep: {$sleepSeconds}s | Max jobs: {$maxJobs} | Continuous: " . ($continuousMode ? 'Yes' : 'No') . "\n";
echo str_repeat('-', 50) . "\n";

$processed = 0;
$failed = 0;

do {
    $stats = $queue->getStats();
    
    if ($stats['pending'] > 0) {
        echo date('Y-m-d H:i:s') . " - Processing {$stats['pending']} pending jobs...\n";
        
        $jobsProcessed = 0;
        
        while ($jobsProcessed < $maxJobs && ($job = $queue->dequeue())) {
            try {
                echo "  Processing job {$job['id']}... ";
                
                // Check if job is ready to retry
                if ($job['next_retry_at'] > time()) {
                    echo "Not ready yet (retry at " . date('H:i:s', $job['next_retry_at']) . ")\n";
                    // Put back in queue
                    $queue->fail($job, 'Not ready for retry yet');
                    continue;
                }

                // Process the notification using reflection to call sendNow
                $reflection = new ReflectionClass($notificationService);
                $method = $reflection->getMethod('sendNow');
                $method->setAccessible(true);
                
                $success = $method->invoke($notificationService, $job['notification']);

                if ($success) {
                    $queue->complete($job);
                    $processed++;
                    $jobsProcessed++;
                    echo "SUCCESS\n";
                } else {
                    throw new Exception('Send failed');
                }
            } catch (Exception $e) {
                echo "FAILED: " . $e->getMessage() . "\n";
                $queue->fail($job, $e->getMessage());
                $failed++;
                $jobsProcessed++;
            }
        }
        
        echo "  Batch complete: {$jobsProcessed} jobs processed\n\n";
    } else {
        // No jobs to process
        if (!$continuousMode) {
            echo "No pending jobs. Exiting.\n";
            break;
        }
    }

    // Show stats
    if ($processed > 0 || $failed > 0) {
        echo "Stats - Processed: {$processed} | Failed: {$failed}\n";
        echo "Queue - Pending: {$stats['pending']} | Processing: {$stats['processing']} | Failed: {$stats['failed']}\n";
        echo str_repeat('-', 50) . "\n";
    }

    // Sleep before next iteration
    if ($continuousMode) {
        sleep($sleepSeconds);
    }

} while ($continuousMode);

echo "\nWorker stopped. Total processed: {$processed}, Total failed: {$failed}\n";
