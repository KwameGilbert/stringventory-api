<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Http\Message\ResponseInterface as Response;

class ResponseHelper
{
    /**
     * Send a JSON response.
     *
     * @param Response $response
     * @param mixed $data
     * @param int $status
     * @return Response
     */
    public static function jsonResponse(Response $response, $data, int $status = 200): Response
    {
        // Clean the buffer to remove any accidental whitespace or errors
        if (ob_get_length()) {
            ob_clean();
        }
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($json);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Send a success response.
     *
     * @param Response $response
     * @param string $message
     * @param array $data
     * @param int $status
     * @return Response
     */
    public static function success(Response $response, string $message, array $data = [], int $status = 200): Response
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        return self::jsonResponse($response, $payload, $status);
    }

    /**
     * Send an error response.
     *
     * @param Response $response
     * @param string $message
     * @param int $status
     * @param mixed $error Additional error details (e.g. validation errors or exception message)
     * @return Response
     */
    public static function error(Response $response, string $message, int $status = 400, $error = null): Response
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($error !== null) {
            // If the key is 'errors' (plural) usually for validation, or 'error' (singular) for exceptions.
            // We'll stick to a generic 'error' key or 'errors' if passed explicitly in the array, 
            // but here we assign it to 'error' or 'errors' based on convention.
            // To match AuthController usage:
            // - Validation: ['errors' => $errors]
            // - Exception: ['error' => $e->getMessage()]
            
            if (is_array($error) && !isset($error['error']) && !isset($error['errors'])) {
                 $payload['errors'] = $error;
            } else {
                 $payload['error'] = $error;
            }
        }

        return self::jsonResponse($response, $payload, $status);
    }
}