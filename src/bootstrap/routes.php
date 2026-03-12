<?php

/**
 * Application Routes
 * 
 * Defines simple status and health check routes
 */

return function ($app, $config) {
    
    // ==================== STATUS ROUTES ====================
    
    // Welcome/Status route
    $app->get('/', function ($request, $response) use ($config) {
        $data = [
            'status' => 'running',
            'message' => "Welcome to {$config['name']} API",
            'version' => $config['version'],
            'environment' => $config['env'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Health check route
    $app->get('/health', function ($request, $response) use ($config) {
        $data = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => $config['env'],
            'version' => $config['version'],
            'base_path' => $config['base_path'] ?? '',
            'log_level' => $config['log_level'] ?? 'DEBUG'
        ];
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // ==================== API ROUTES ====================
    
    // Include versioned API routes
    (require_once ROUTE . '/api.php')($app);
    
    // ==================== 404 HANDLER ====================
    
    // Add Not Found Handler - must be added after all other routes
    $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
        $data = [
            'error' => 'Not Found',
            'message' => 'The requested route does not exist.',
            'status' => 404
        ];
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
    });
    
    return $app;
};
