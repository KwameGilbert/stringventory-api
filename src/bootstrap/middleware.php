<?php

/**
 * Middleware Configuration
 * 
 * Registers all application middleware
 */
use Slim\Middleware\ContentLengthMiddleware;
use App\Helper\ErrorHandler as ErrorHandler;
use App\Middleware\RequestResponseLoggerMiddleware;
use App\Middleware\JsonBodyParserMiddleware as JsonBodyParserMiddleware;
use App\Middleware\RateLimitMiddleware as RateLimitMiddleware;

return function ($app, $container, $config) {

    // Get configurations
    $environment = $config['env'];
    // Only ever expose error details in development, and only when APP_DEBUG
    // is also on - so a stray APP_DEBUG=true left in a non-development .env
    // can't leak stack traces.
    $debug = $environment === 'development'
        && filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $corsConfig = require CONFIG . '/Cors.php';

    // ==================== HTTP LOGGING ====================
    
    // Add HTTP logger middleware
    if ($container->has('httpLogger')) {
        $app->add(new RequestResponseLoggerMiddleware($container->get('httpLogger')));
    }
    
    // ==================== RATE LIMITING ====================
    
    // Add Rate Limit middleware
    $app->add(new RateLimitMiddleware());

    // ==================== CORS ====================
    
    // Add CORS middleware
    $app->add(function ($request, $handler) use ($corsConfig) {
        $response = $handler->handle($request);
        $allowedOrigins = $corsConfig['allowed_origins'];
        $allowCredentials = is_callable($corsConfig['allow_credentials']) 
            ? $corsConfig['allow_credentials']($allowedOrigins) 
            : $corsConfig['allow_credentials'];
            
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', $corsConfig['allowed_headers'])
            ->withHeader('Access-Control-Allow-Methods', $corsConfig['allowed_methods'])
            ->withHeader('Access-Control-Allow-Credentials', $allowCredentials)
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Access-Control-Max-Age', (string)$corsConfig['max_age'])
            ->withHeader('Content-Type', 'application/json');
    });
    
    // Handle preflight OPTIONS requests
    $app->options('/{routes:.+}', function ($request, $response) use ($corsConfig) {
        $allowedOrigins = $corsConfig['allowed_origins'];
        $allowCredentials = is_callable($corsConfig['allow_credentials']) 
            ? $corsConfig['allow_credentials']($allowedOrigins) 
            : $corsConfig['allow_credentials'];
            
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', $corsConfig['allowed_headers'])
            ->withHeader('Access-Control-Allow-Methods', $corsConfig['allowed_methods'])
            ->withHeader('Access-Control-Allow-Credentials', $allowCredentials)
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Access-Control-Max-Age', (string)$corsConfig['max_age'])
            ->withHeader('Content-Type', 'application/json');
    });
    
    // ==================== JSON BODY PARSING ====================

    $app->add($container->get(JsonBodyParserMiddleware::class));

    // ==================== CONTENT LENGTH ====================

    // $app->add(new ContentLengthMiddleware());

    // ==================== ERROR HANDLING ====================
    // Added last so it's the outermost middleware and can catch exceptions
    // thrown by any of the middleware above (CORS, rate limiting, JSON body
    // parsing, HTTP logging), not just ones thrown by routes/controllers.

    $errorMiddleware = $app->addErrorMiddleware(
        displayErrorDetails: $debug,
        logErrors: true,
        logErrorDetails: $debug,
        logger: $container->get('logger')
    );

    $errorHandler = new ErrorHandler(
        $container->get('logger'),
        $debug
    );
    $errorMiddleware->setDefaultErrorHandler($errorHandler);

    return $app;
};
