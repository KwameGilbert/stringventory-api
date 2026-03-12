<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

/**
 * NotificationQueue - Simple file-based queue for async notifications
 * 
 * Production-ready queue system with:
 * - Retry logic
 * - Failed job tracking
 * - Priority support
 * - Worker process
 */
class NotificationQueue
{
    private string $queueDir;
    private string $failedDir;
    private string $processingDir;
    private int $maxRetries;

    public function __construct()
    {
        $baseDir = dirname(__DIR__, 2) . '/storage/queue';
        $this->queueDir = $baseDir . '/pending';
        $this->failedDir = $baseDir . '/failed';
        $this->processingDir = $baseDir . '/processing';
        $this->maxRetries = (int)($_ENV['QUEUE_MAX_RETRIES'] ?? 3);

        $this->ensureDirectoriesExist();
    }

    /**
     * Add notification to queue
     */
    public function enqueue(array $notification): bool
    {
        try {
            $priority = $notification['priority'] ?? 'medium';
            $id = $this->generateId();
            $filename = $this->getQueueFilename($priority, $id);

            $job = [
                'id' => $id,
                'notification' => $notification,
                'attempts' => 0,
                'max_retries' => $this->maxRetries,
                'created_at' => time(),
                'next_retry_at' => time(),
            ];

            return file_put_contents(
                $filename,
                json_encode($job, JSON_PRETTY_PRINT)
            ) !== false;
        } catch (Exception $e) {
            error_log('Queue enqueue error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get next job from queue (prioritized)
     */
    public function dequeue(): ?array
    {
        // Priority order: critical > high > medium > low
        $priorities = ['critical', 'high', 'medium', 'low'];

        foreach ($priorities as $priority) {
            $files = glob($this->queueDir . "/{$priority}_*.json");
            
            if (!empty($files)) {
                // Sort by timestamp (oldest first)
                usort($files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });

                $file = $files[0];
                $id = $this->extractIdFromFilename($file);

                // Move to processing
                $processingFile = $this->processingDir . '/' . basename($file);
                if (rename($file, $processingFile)) {
                    $job = json_decode(file_get_contents($processingFile), true);
                    $job['processing_file'] = $processingFile;
                    return $job;
                }
            }
        }

        return null;
    }

    /**
     * Mark job as completed
     */
    public function complete(array $job): bool
    {
        try {
            if (isset($job['processing_file']) && file_exists($job['processing_file'])) {
                return unlink($job['processing_file']);
            }
            return true;
        } catch (Exception $e) {
            error_log('Queue complete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark job as failed and handle retry
     */
    public function fail(array $job, string $error = ''): bool
    {
        try {
            $job['attempts']++;
            $job['last_error'] = $error;
            $job['last_failed_at'] = time();

            // Check if we should retry
            if ($job['attempts'] < $job['max_retries']) {
                // Calculate exponential backoff
                $backoffMinutes = pow(2, $job['attempts']); // 2, 4, 8 minutes
                $job['next_retry_at'] = time() + ($backoffMinutes * 60);

                // Move back to queue
                $priority = $job['notification']['priority'] ?? 'medium';
                $filename = $this->getQueueFilename($priority, $job['id']);
                
                file_put_contents(
                    $filename,
                    json_encode($job, JSON_PRETTY_PRINT)
                );

                // Remove from processing
                if (isset($job['processing_file'])) {
                    unlink($job['processing_file']);
                }

                return true;
            } else {
                // Max retries exceeded - move to failed
                $failedFile = $this->failedDir . '/failed_' . $job['id'] . '.json';
                file_put_contents(
                    $failedFile,
                    json_encode($job, JSON_PRETTY_PRINT)
                );

                // Remove from processing
                if (isset($job['processing_file'])) {
                    unlink($job['processing_file']);
                }

                error_log('Job failed permanently: ' . $job['id']);
                return false;
            }
        } catch (Exception $e) {
            error_log('Queue fail error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        return [
            'pending' => $this->countFiles($this->queueDir),
            'processing' => $this->countFiles($this->processingDir),
            'failed' => $this->countFiles($this->failedDir),
            'by_priority' => [
                'critical' => $this->countByPriority('critical'),
                'high' => $this->countByPriority('high'),
                'medium' => $this->countByPriority('medium'),
                'low' => $this->countByPriority('low'),
            ]
        ];
    }

    /**
     * Clear old failed jobs (older than 30 days)
     */
    public function clearOldFailedJobs(int $days = 30): int
    {
        $cutoff = time() - ($days * 24 * 60 * 60);
        $files = glob($this->failedDir . '/failed_*.json');
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Ensure queue directories exist
     */
    private function ensureDirectoriesExist(): void
    {
        $dirs = [$this->queueDir, $this->failedDir, $this->processingDir];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Generate unique job ID
     */
    private function generateId(): string
    {
        return uniqid('job_', true) . '_' . time();
    }

    /**
     * Get queue filename for priority
     */
    private function getQueueFilename(string $priority, string $id): string
    {
        return $this->queueDir . "/{$priority}_{$id}.json";
    }

    /**
     * Extract ID from filename
     */
    private function extractIdFromFilename(string $filename): string
    {
        $basename = basename($filename, '.json');
        return substr($basename, strpos($basename, '_') + 1);
    }

    /**
     * Count files in directory
     */
    private function countFiles(string $dir): int
    {
        $files = glob($dir . '/*.json');
        return count($files ?: []);
    }

    /**
     * Count files by priority
     */
    private function countByPriority(string $priority): int
    {
        $files = glob($this->queueDir . "/{$priority}_*.json");
        return count($files ?: []);
    }
}
