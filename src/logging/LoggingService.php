<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use PDO;

/**
 * Logging Service
 *
 * Centralized logging service that can be easily configured and reused.
 * Supports both file and database logging with a simple interface.
 */
class LoggingService
{
    private PDO $pdo;
    private string $table;
    private array $config;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     * @param array $config Configuration array
     */
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->table = $config['table'] ?? 'logs';
        $this->config = array_merge([
            'level' => Logger::DEBUG,
            'file_path' => null,
            'additional_fields' => ['request_id', 'user_id', 'ip_address', 'user_agent'],
            'max_files' => 30,
            'file_size' => 10485760, // 10MB
        ], $config);
    }

    /**
     * Create a configured logger instance
     *
     * @param string $channel Logger channel name
     * @return Logger
     */
    public function createLogger(string $channel = 'app'): Logger
    {
        $logger = new Logger($channel);

        // Add PSR-3 message processor
        $logger->pushProcessor(new PsrLogMessageProcessor());

        // Add database handler
        $dbHandler = new DatabaseHandler(
            $this->pdo,
            $this->table,
            $this->config['additional_fields'],
            $this->config['level']
        );
        $logger->pushHandler($dbHandler);

        // Add file handler if configured
        if ($this->config['file_path']) {
            $fileHandler = new StreamHandler(
                $this->config['file_path'],
                $this->config['level']
            );
            $logger->pushHandler($fileHandler);
        }

        return $logger;
    }

    /**
     * Get logs from database with filtering
     *
     * @param array $filters Available filters: level, level_name, channel, request_id, user_id, date_from, date_to, limit, offset
     * @return array
     */
    public function getLogs(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['level'])) {
            $where[] = 'level = ?';
            $params[] = $filters['level'];
        }

        if (!empty($filters['level_name'])) {
            $where[] = 'level_name = ?';
            $params[] = $filters['level_name'];
        }

        if (!empty($filters['channel'])) {
            $where[] = 'channel = ?';
            $params[] = $filters['channel'];
        }

        if (!empty($filters['request_id'])) {
            $where[] = 'request_id = ?';
            $params[] = $filters['request_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;

        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($logs as &$log) {
            $log['context'] = json_decode($log['context'] ?? '[]', true);
            $log['extra'] = json_decode($log['extra'] ?? '[]', true);
        }

        return $logs;
    }

    /**
     * Get log statistics
     *
     * @param string $dateFrom Start date (Y-m-d format)
     * @param string $dateTo End date (Y-m-d format)
     * @return array
     */
    public function getLogStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $params = [];
        $where = '';

        if ($dateFrom && $dateTo) {
            $where = 'WHERE DATE(created_at) BETWEEN ? AND ?';
            $params = [$dateFrom, $dateTo];
        } elseif ($dateFrom) {
            $where = 'WHERE DATE(created_at) >= ?';
            $params = [$dateFrom];
        } elseif ($dateTo) {
            $where = 'WHERE DATE(created_at) <= ?';
            $params = [$dateTo];
        }

        $sql = "
            SELECT
                COUNT(*) as total_logs,
                level_name,
                COUNT(*) as count
            FROM {$this->table}
            {$where}
            GROUP BY level_name
            ORDER BY count DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clean old logs
     *
     * @param int $daysOld Delete logs older than this many days
     * @return int Number of deleted logs
     */
    public function cleanOldLogs(int $daysOld = 30): int
    {
        $sql = "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$daysOld]);

        return $stmt->rowCount();
    }

    /**
     * Get available log levels
     *
     * @return array
     */
    public static function getLogLevels(): array
    {
        return [
            'DEBUG' => Logger::DEBUG,
            'INFO' => Logger::INFO,
            'NOTICE' => Logger::NOTICE,
            'WARNING' => Logger::WARNING,
            'ERROR' => Logger::ERROR,
            'CRITICAL' => Logger::CRITICAL,
            'ALERT' => Logger::ALERT,
            'EMERGENCY' => Logger::EMERGENCY,
        ];
    }

    /**
     * Initialize the logging table
     * Call this during application setup
     */
    public function initializeTable(): void
    {
        $handler = new DatabaseHandler($this->pdo, $this->table);
        $handler->createTableIfNotExists();
    }
}