<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PDO;
use PDOException;

/**
 * Database Handler for Monolog
 *
 * This handler writes log records to a database table.
 * Designed to be modular and reusable across projects.
 */
class DatabaseHandler extends AbstractProcessingHandler
{
    private PDO $pdo;
    private string $table;
    private array $additionalFields;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     * @param string $table Table name for logs
     * @param array $additionalFields Additional fields to store (e.g., ['request_id', 'user_id'])
     * @param int $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(
        PDO $pdo,
        string $table = 'logs',
        array $additionalFields = [],
        int $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->pdo = $pdo;
        $this->table = $table;
        $this->additionalFields = $additionalFields;
    }

    /**
     * Writes the record to the database
     */
    protected function write(LogRecord $record): void
    {
        try {
            $data = [
                'level' => $record->level->value,
                'level_name' => $record->level->getName(),
                'message' => $record->message,
                'context' => json_encode($record->context),
                'extra' => json_encode($record->extra),
                'channel' => $record->channel,
                'created_at' => $record->datetime->format('Y-m-d H:i:s'),
            ];

            // Add additional fields from context/extra
            foreach ($this->additionalFields as $field) {
                if (isset($record->context[$field])) {
                    $data[$field] = $record->context[$field];
                } elseif (isset($record->extra[$field])) {
                    $data[$field] = $record->extra[$field];
                }
            }

            $this->insertLog($data);
        } catch (PDOException $e) {
            // If database logging fails, we don't want to throw an exception
            // as it could cause infinite loops. Log to error_log as fallback.
            error_log('Database logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Insert log record into database
     *
     * @param array $data Log data to insert
     */
    private function insertLog(array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
    }

    /**
     * Create table if it doesn't exist
     * This is a utility method for initial setup
     */
    public function createTableIfNotExists(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level INT UNSIGNED NOT NULL,
                level_name VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                context JSON NULL,
                extra JSON NULL,
                channel VARCHAR(100) NOT NULL DEFAULT 'app',
                request_id VARCHAR(36) NULL,
                user_id INT UNSIGNED NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_level (level),
                INDEX idx_level_name (level_name),
                INDEX idx_channel (channel),
                INDEX idx_request_id (request_id),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at),
                INDEX idx_level_created_at (level, created_at),
                INDEX idx_channel_created_at (channel, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->pdo->exec($sql);
    }
}