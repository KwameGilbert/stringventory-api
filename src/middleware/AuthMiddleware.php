<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Services\AuthService;
use App\Helper\ResponseHelper;

/**
 * Authentication Middleware
 *
 * Validates Bearer tokens in Authorization header and protects routes.
 * Adds authenticated user data to request attributes for use in controllers.
 */
class AuthMiddleware
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Process incoming request and validate authentication
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Get Authorization header
        $authHeader = $request->getHeaderLine('Authorization');

        // Debug logging
        error_log('=== Auth Middleware Debug ===');
        error_log('Request URI: ' . $request->getUri()->getPath());
        error_log('Auth Header Present: ' . (!empty($authHeader) ? 'Yes' : 'No'));

        if (empty($authHeader)) {
            error_log('Auth Error: No Authorization header');
            return $this->createUnauthorizedResponse('Authorization header is required');
        }

        // Log first 50 chars of auth header for debugging (don't log full token)
        error_log('Auth Header (first 50 chars): ' . substr($authHeader, 0, 50) . '...');

        // Check if it's a Bearer token
        $token = $this->authService->extractTokenFromHeader($authHeader);

        if (!$token) {
            error_log('Auth Error: Could not extract token from header');
            return $this->createUnauthorizedResponse('Invalid authorization header format. Expected: Bearer <token>');
        }

        error_log('Token extracted: Yes (length: ' . strlen($token) . ')');

        // Validate the JWT token
        $decoded = $this->authService->validateToken($token);

        if ($decoded === null) {
            error_log('Auth Error: Token validation failed');
            return $this->createUnauthorizedResponse('Invalid or expired token');
        }

        // Debug: Log the full decoded structure
        error_log('=== Decoded JWT Structure ===');
        error_log('Decoded type: ' . gettype($decoded));
        error_log('Decoded JSON: ' . json_encode($decoded));
        
        // Check if data property exists
        if (isset($decoded->data)) {
            error_log('decoded->data exists, type: ' . gettype($decoded->data));
            error_log('decoded->data JSON: ' . json_encode($decoded->data));
            
            // Check individual fields
            error_log('decoded->data->id: ' . ($decoded->data->id ?? 'NOT SET'));
            error_log('decoded->data->email: ' . ($decoded->data->email ?? 'NOT SET'));
            error_log('decoded->data->role: ' . ($decoded->data->role ?? 'NOT SET'));
        } else {
            error_log('WARNING: decoded->data does NOT exist!');
            error_log('Available properties: ' . implode(', ', array_keys((array)$decoded)));
        }
        
        error_log('=== End Auth Middleware Debug ===');

        // Add user data to request attributes for use in controllers
        $request = $request->withAttribute('user', $decoded->data);

        // Continue with the request
        return $handler->handle($request);
    }

    /**
     * Create an unauthorized response
     *
     * @param string $message
     * @return Response
     */
    private function createUnauthorizedResponse(string $message): Response
    {
        $response = new \Slim\Psr7\Response();
        return ResponseHelper::error($response, $message, 401);
    }
}