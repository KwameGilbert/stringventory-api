<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use App\Helper\ResponseHelper;

/**
 * JsonBodyParserMiddleware
 * 
 * Parses JSON request bodies according to Slim v4 documentation
 * @see https://www.slimframework.com/docs/v4/objects/request.html#the-request-body
 */
class JsonBodyParserMiddleware
{
    /**
     * Process the request
     * PSR-15 MiddlewareInterface signature
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $contentType = $request->getHeaderLine('Content-Type');

        // Only parse if Content-Type contains application/json
        if (strpos($contentType, 'application/json') !== false) {
            // Read raw body from php://input (as per Slim v4 docs)
            $contents = file_get_contents('php://input');
            
            // Validate body is not empty
            if (empty(trim($contents))) {
                return ResponseHelper::error(
                    new SlimResponse(),
                    'Request body cannot be empty',
                    400
                );
            }
            
            // Parse JSON
            $parsed = json_decode($contents, true);
            
            // Validate JSON is valid
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ResponseHelper::error(
                    new SlimResponse(),
                    'Invalid JSON: ' . json_last_error_msg(),
                    400
                );
            }
            
            // Set parsed body on request (creates new immutable request)
            $request = $request->withParsedBody($parsed);
        }

        // Pass request to next middleware/route
        return $handler->handle($request);
    }
}
