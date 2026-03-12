<?php

/**
 * Application Configuration
 * 
 * Central configuration file for application settings
 */

return [
    // Application Environment
    'env' => $_ENV['APP_ENV'] ?? 'production',
    
    // Application Name
    'name' => $_ENV['APP_NAME'] ?? 'Eventic',
    
    // Application Version
    'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
    
    // Base Path (for subfolder installations)
    'base_path' => $_ENV['BASE_PATH'] ?? '',
    
    // Logging
    'log_level' => $_ENV['LOG_LEVEL'] ?? 'DEBUG',
    
    // Required Environment Variables
    'required_env_vars' => [
        'JWT_SECRET',
        'JWT_ALGORITHM',
        'JWT_EXPIRE',
        'REFRESH_TOKEN_EXPIRE',
        'REFRESH_TOKEN_ALGO',
        'APP_ENV',
        'APP_NAME',
        'APP_VERSION',
        'BASE_PATH',
        'LOG_LEVEL'
    ]
];
