<?php

namespace App\Helper;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

/**
 * Logger Factory Class
 * 
 * Creates and configures Monolog loggers for the application.
 */
class LoggerFactory
{
    private string $name;
    private string $logPath;
    private $level;

    /**
     * Constructor
     * 
     * @param string $name Logger channel name
     */
    public function __construct(string $name = 'app')
    {
        $this->name = $name;
        $this->logPath = __DIR__ . '/../../src/logs';

        // Create main logs directory if it doesn't exist
        if (!file_exists($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }
        
        // Create subdirectories for different log types
        $this->createLogDirectories();

        // Get log level from environment or default to DEBUG
        $this->level = $_ENV['LOG_LEVEL'] ?? Logger::DEBUG;
    }
    
    /**
     * Create log directories for organization
     */
    private function createLogDirectories(): void
    {
        $directories = [
            $this->logPath . '/app',
            $this->logPath . '/http',
            $this->logPath . '/error'
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }

    /**
     * Get configured logger instance for application logs
     * 
     * @return Logger
     */
    public function getLogger(): Logger
    {
        // Create logger instance
        $logger = new Logger($this->name);

        // Set custom formatter for better readability
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] [%level_name%] [%channel%] [%extra.uid%]: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        // Add rotating file handler for daily log rotation
        $fileHandler = new RotatingFileHandler(
            $this->logPath . '/app/app.log',
            14, // Keep up to 14 days of logs
            $this->level
        );
        $fileHandler->setFormatter($formatter);

        // Create an error handler for more severe issues
        $errorHandler = new StreamHandler(
            $this->logPath . '/error/error.log',
            Logger::ERROR
        );
        $errorHandler->setFormatter($formatter);

        // Add unique ID to each log entry
        $logger->pushProcessor(new UidProcessor());

        // Add web request details when available
        $logger->pushProcessor(new WebProcessor());

        // Get environment for context
        $environment = $_ENV['APP_ENV'] ?? 'production';

        // Add environment context processor - compatible with both Monolog 2.x and 3.x
        $logger->pushProcessor(function ($record) use ($environment) {
            // For Monolog 3.x (LogRecord object)
            if ($record instanceof LogRecord) {
                $record->extra['environment'] = $environment;
            } 
            // For Monolog 2.x (array)
            else if (is_array($record)) {
                $record['extra']['environment'] = $environment;
            }
            
            return $record;
        });

        // Add handlers to logger
        $logger->pushHandler($fileHandler);
        $logger->pushHandler($errorHandler);

        return $logger;
    }
    
    /**
     * Get configured logger instance specifically for HTTP request/response logs
     * 
     * @return Logger
     */
    public function getHttpLogger(): Logger
    {
        // Create logger instance
        $logger = new Logger($this->name . '.Http');

        // Set custom formatter for better readability
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] [%level_name%] [%channel%] [%extra.uid%]: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        // Add rotating file handler for daily log rotation
        $fileHandler = new RotatingFileHandler(
            $this->logPath . '/http/requests.log',
            7, // Keep up to 7 days of request logs
            $this->level
        );
        $fileHandler->setFormatter($formatter);

        // Add unique ID to each log entry
        $logger->pushProcessor(new UidProcessor());

        // Add web request details when available
        $logger->pushProcessor(new WebProcessor());

        // Add environment context processor - compatible with both Monolog 2.x and 3.x
        $environment = $_ENV['APP_ENV'] ?? 'production';
        $logger->pushProcessor(function ($record) use ($environment) {
            // For Monolog 3.x (LogRecord object)
            if ($record instanceof LogRecord) {
                $record->extra['environment'] = $environment;
            } 
            // For Monolog 2.x (array)
            else if (is_array($record)) {
                $record['extra']['environment'] = $environment;
            }
            
            return $record;
        });

        // Add handlers to logger
        $logger->pushHandler($fileHandler);

        return $logger;
    }
}