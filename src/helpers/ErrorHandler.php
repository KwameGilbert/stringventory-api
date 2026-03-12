<?php

namespace App\Helper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

/**
 * Custom Error Handler
 * 
 * This class handles exceptions and errors in a consistent way,
 * returning JSON responses instead of HTML and logging errors appropriately.
 */
class ErrorHandler implements ErrorHandlerInterface
{
    /**
     * Logger for recording error details
     */
    private LoggerInterface $logger;

    /**
     * Environment setting to control error detail exposure
     */
    private string $environment;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger The logger instance
     * @param string $environment Current environment (development/production)
     */
    public function __construct(LoggerInterface $logger, string $environment = 'production')
    {
        $this->logger = $logger;
        $this->environment = $environment;
    }

    /**
     * Handle the error/exception and return a JSON-formatted response
     *
     * @param ServerRequestInterface $request The current request
     * @param Throwable $exception The exception that was thrown
     * @param bool $displayErrorDetails Whether to display error details
     * @param bool $logErrors Whether to log errors
     * @param bool $logErrorDetails Whether to log error details
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        // Extract information from the request
        $requestId = $request->getAttribute('requestId') ?? uniqid();
        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();

        // Get status code from exception or default to 500
        $statusCode = $exception instanceof HttpException 
            ? $exception->getCode() 
            : 500;
        
        // Handle HTTP codes less than 100 or greater than 599
        if ($statusCode < 100 || $statusCode > 599) {
            $statusCode = 500;
        }

        // Log the error with context information
        if ($logErrors) {
            $logContext = [
                'request_id' => $requestId,
                'method' => $method,
                'uri' => $uri,
                'status' => $statusCode,
                'exception' => get_class($exception),
            ];

            // Add trace in development mode or if logErrorDetails is true
            if ($logErrorDetails || $this->environment === 'development') {
                $logContext['trace'] = $exception->getTraceAsString();
            }

            $this->logger->error($exception->getMessage(), $logContext);
        }

        // Create response object from app container
        $response = new \Slim\Psr7\Response();
        $response = $response->withStatus($statusCode);
        $response = $response->withHeader('Content-Type', 'application/json');

        // Prepare error response data
        $errorData = [
            'status' => 'error',
            'message' => $this->getExceptionMessage($exception, $displayErrorDetails),
            'code' => $statusCode,
            'request_id' => $requestId,
        ];

        // Add exception details if in development mode or displayErrorDetails is true
        if ($displayErrorDetails || $this->environment === 'development') {
            $errorData['exception'] = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        // Write JSON to the response body
        $responseBody = json_encode($errorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($responseBody);

        return $response;
    }

    /**
     * Get the appropriate exception message based on settings
     *
     * @param Throwable $exception The exception
     * @param bool $displayErrorDetails Whether to display detailed error info
     * @return string The formatted error message
     */
    private function getExceptionMessage(Throwable $exception, bool $displayErrorDetails): string
    {
        if ($displayErrorDetails || $this->environment === 'development') {
            return $exception->getMessage();
        }

        // Generic messages for production to avoid exposing sensitive details
        if ($exception instanceof HttpException) {
            return $this->getHttpExceptionMessage($exception->getCode());
        }

        return 'An unexpected error occurred. Please try again later.';
    }

    /**
     * Get a user-friendly message for HTTP error codes
     *
     * @param int $statusCode HTTP status code
     * @return string User-friendly error message
     */
    private function getHttpExceptionMessage(int $statusCode): string
    {
        $messages = [
            400 => 'Bad request. Please check your input and try again.',
            401 => 'Authentication required. Please login to continue.',
            403 => 'You do not have permission to access this resource.',
            404 => 'The requested resource was not found.',
            405 => 'This method is not allowed for the requested resource.',
            408 => 'Request timeout. Please try again later.',
            409 => 'Request conflict. The resource might have been modified.',
            413 => 'Request entity too large.',
            429 => 'Too many requests. Please try again later.',
            500 => 'Internal server error. Please try again later.',
            502 => 'Bad gateway. The server received an invalid response.',
            503 => 'Service unavailable. The server is currently overloaded.',
            504 => 'Gateway timeout. The server took too long to respond.',
        ];

        return $messages[$statusCode] ?? 'An unexpected error occurred. Please try again later.';
    }
}