<?php

/**
 * CORS Configuration
 * 
 * Cross-Origin Resource Sharing settings
 */

return [
    // Allowed Origins
    // In production, set to specific domains: 'https://yourdomain.com,https://app.yourdomain.com'
    // In development, can use '*' for testing
    'allowed_origins' => '*',
    
    // Allowed Headers
    'allowed_headers' => 'X-Requested-With, Content-Type, Accept, Origin, Authorization',
    
    // Allowed Methods
    'allowed_methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
    
    // Max Age
    'max_age' => 86400, // 24 hours
    
    // Allow Credentials
    // Automatically set to false if allowed_origins is '*'
    'allow_credentials' => function($allowedOrigins) {
        return $allowedOrigins !== '*' ? 'true' : 'false';
    }
];
