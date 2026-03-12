<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;

/**
 * Request Response Logger Middleware
 * 
 * Specifically logs incoming HTTP requests and outgoing HTTP responses
 * with timing information and relevant metadata.
 */
class RequestResponseLoggerMiddleware
{
    /**
     * @var LoggerInterface
     */
    protected $httpLogger;

    /**
     * Constructor
     *
     * @param LoggerInterface $httpLogger Logger for HTTP requests/responses
     */
    public function __construct(LoggerInterface $httpLogger)
    {
        $this->httpLogger = $httpLogger;
    }

    /**
     * Process incoming request and log details
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Get request details
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        
        if ($query) {
            $query = "?{$query}";
        }
        
        // Log request
        $this->httpLogger->info("Request: {$method} {$uri}{$query}", [
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'headers' => $this->sanitizeHeaders($request->getHeaders()),
        ]);
        
        // Handle request
        $start = microtime(true);
        $response = $handler->handle($request);
        $time = microtime(true) - $start;
        
        // Log response
        $this->httpLogger->info("Response: {$response->getStatusCode()} - {$uri}{$query}", [
            'status' => $response->getStatusCode(),
            'time' => round($time * 1000, 2) . 'ms',
        ]);
        
        return $response;
    }

    /**
     * Remove sensitive information from headers
     *
     * @param array $headers
     * @return array
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        $sensitive = ['authorization', 'cookie', 'x-api-key'];
        
        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $sensitive)) {
                $sanitized[$name] = ['[REDACTED]'];
            } else {
                $sanitized[$name] = $values;
            }
        }
        
        return $sanitized;
    }
}