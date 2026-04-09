# Modular Database Logging System

This is a complete, modular logging system that can be easily reused across PHP projects. It provides both file and database logging capabilities with a clean API.

## Features

- **Database Logging**: Store logs in MySQL/PostgreSQL with structured queries
- **File Logging**: Traditional rotating file logs as backup
- **Web Dashboard**: REST API endpoints to view and manage logs
- **Filtering & Search**: Query logs by level, channel, user, date range
- **Statistics**: Get log statistics and analytics
- **Modular Design**: Easy to copy to other projects
- **Monolog Integration**: Uses Monolog for maximum compatibility

## Files Structure

```
src/logging/
├── DatabaseHandler.php      # Monolog handler for database logging
└── LoggingService.php       # Service class for log management

src/controllers/
└── LoggingController.php    # REST API controller for log dashboard

src/routes/v1/
└── LoggingRoute.php         # Route definitions

database/migrations/
└── create_logs_table.php    # Database schema migration
```

## Setup Instructions

### 1. Copy Files to Your Project

Copy all files from this logging system to your project:

```bash
# Copy the logging classes
cp -r src/logging/ your-project/src/logging/

# Copy the controller
cp src/controllers/LoggingController.php your-project/src/controllers/

# Copy the routes
cp src/routes/v1/LoggingRoute.php your-project/src/routes/v1/

# Copy the migration (adapt for your migration system)
cp database/migrations/create_logs_table.php your-project/database/migrations/
```

### 2. Install Dependencies

Make sure you have Monolog installed:

```bash
composer require monolog/monolog
```

### 3. Database Migration

Run the migration to create the logs table:

```bash
# Using Phinx
php vendor/bin/phinx migrate

# Or run the SQL directly:
# (See the migration file for the CREATE TABLE statement)
```

### 4. Environment Configuration

Add these variables to your `.env` file:

```env
# Enable database logging
LOG_TO_DATABASE=true

# Optional: File logging path (leave empty to disable)
LOG_FILE_PATH=/var/log/yourapp/app.log

# Log level (DEBUG, INFO, WARNING, ERROR)
LOG_LEVEL=DEBUG
```

### 5. Service Registration

Register the logging service in your DI container:

```php
// In your services.php or bootstrap file
$container->set(\App\Logging\LoggingService::class, function ($container) {
    $pdo = $container->get('pdo'); // Your database connection
    $config = [
        'table' => 'logs',
        'level' => \Monolog\Logger::DEBUG,
        'file_path' => $_ENV['LOG_FILE_PATH'] ?? null,
        'additional_fields' => ['request_id', 'user_id', 'ip_address', 'user_agent'],
    ];
    return new \App\Logging\LoggingService($pdo, $config);
});

$container->set(\App\Controllers\LoggingController::class, function ($container) {
    return new \App\Controllers\LoggingController(
        $container->get(\App\Logging\LoggingService::class)
    );
});
```

### 6. Logger Factory Setup

Update your logger factory to support database logging:

```php
// In your LoggerFactory or bootstrap
$useDatabaseLogging = $_ENV['LOG_TO_DATABASE'] ?? false;
$pdo = $useDatabaseLogging ? $yourPdoConnection : null;
$loggerFactory = new LoggerFactory($appName, $useDatabaseLogging, $pdo);
```

### 7. Register Routes

Add the logging routes to your router:

```php
// In your routes file
$app->group('/v1/logs', function ($group) use ($controller) {
    $group->get('', [$controller, 'index']);
    $group->get('/stats', [$controller, 'stats']);
    $group->get('/levels', [$controller, 'levels']);
    $group->delete('/clean', [$controller, 'clean']);
})->add($authMiddleware);
```

## API Endpoints

### Get Logs
```
GET /v1/logs?level=ERROR&limit=50&date_from=2024-01-01
```

Query parameters:
- `level`: Log level (DEBUG, INFO, WARNING, ERROR, etc.)
- `level_name`: Same as level
- `channel`: Logger channel
- `request_id`: Request ID to filter
- `user_id`: User ID to filter
- `date_from`: Start date (Y-m-d)
- `date_to`: End date (Y-m-d)
- `limit`: Number of results (max 1000)
- `offset`: Pagination offset

### Get Statistics
```
GET /v1/logs/stats?date_from=2024-01-01&date_to=2024-01-31
```

### Get Log Levels
```
GET /v1/logs/levels
```

### Clean Old Logs
```
POST /v1/logs/clean
Content-Type: application/json

{
    "days_old": 30
}
```

## Usage Examples

### Basic Logging

```php
// Get logger from container
$logger = $container->get('logger');

// Log messages
$logger->info('User logged in', ['user_id' => 123, 'ip' => '192.168.1.1']);
$logger->error('Database connection failed', ['error' => $e->getMessage()]);
```

### Advanced Logging with Context

```php
$logger->warning('Payment failed', [
    'user_id' => $userId,
    'amount' => $amount,
    'request_id' => uniqid(),
    'context' => ['payment_method' => 'card', 'error_code' => 'DECLINED']
]);
```

### Querying Logs Programmatically

```php
$loggingService = $container->get(\App\Logging\LoggingService::class);

// Get recent errors
$errors = $loggingService->getLogs([
    'level_name' => 'ERROR',
    'date_from' => date('Y-m-d', strtotime('-1 day')),
    'limit' => 100
]);

// Get user activity
$userLogs = $loggingService->getLogs([
    'user_id' => $userId,
    'level' => \Monolog\Logger::INFO
]);
```

## Database Schema

The logs table structure:

```sql
CREATE TABLE logs (
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
```

## Security Considerations

- **Authentication**: Protect log endpoints with authentication middleware
- **Authorization**: Restrict log access to administrators only
- **Data Retention**: Implement log rotation/cleanup to prevent unlimited growth
- **Sensitive Data**: Avoid logging passwords, tokens, or PII in context/extra fields

## Performance Notes

- Database logging adds small overhead to each log operation
- Use appropriate indexes for your query patterns
- Consider log rotation for high-volume applications
- The system gracefully falls back to error_log() if database fails

## Customization

### Custom Fields

Add additional fields to track:

```php
$config = [
    'additional_fields' => [
        'request_id',
        'user_id',
        'ip_address',
        'user_agent',
        'session_id',
        'api_version'
    ]
];
```

### Custom Table Name

```php
$config = [
    'table' => 'application_logs'
];
```

### Multiple Loggers

Create specialized loggers for different components:

```php
$paymentLogger = $loggingService->createLogger('payment');
$apiLogger = $loggingService->createLogger('api');
```

## Troubleshooting

### Logs Not Appearing

1. Check `LOG_TO_DATABASE=true` in .env
2. Verify database connection
3. Check table exists: `SHOW TABLES LIKE 'logs'`
4. Test with simple log: `$logger->info('test')`

### Performance Issues

1. Reduce log level in production
2. Use file logging for high-volume logs
3. Implement log sampling for debug logs
4. Monitor database performance

## Contributing

To extend this system:

1. Add new methods to `LoggingService`
2. Create additional handlers in `src/logging/`
3. Add new endpoints to `LoggingController`
4. Update this README with new features

## License

This logging system is open-source and can be freely used in any project.