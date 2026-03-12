<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Helper\ResponseHelper;

use Slim\Psr7\Response as SlimResponse;

/**
 * RateLimitMiddleware
 * 
 * Prevents brute force attacks by limiting request rate
 * Uses file-based storage 
 * Todo: Will be upgraded to Redis for production
 */
class RateLimitMiddleware
{
    private int $maxAttempts;
    private int $decayMinutes;
    private string $storageDir;

    /**
     * @param int|null $maxAttempts Maximum attempts allowed (defaults to env RATE_LIMIT or 5)
     * @param int|null $decayMinutes Time window in minutes (defaults to env RATE_LIMIT_WINDOW or 1)
     */
    public function __construct(?int $maxAttempts = null, ?int $decayMinutes = null)
    {
        $this->maxAttempts = $maxAttempts ?? (int)($_ENV['RATE_LIMIT'] ?? 5);
        $this->decayMinutes = $decayMinutes ?? (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 1);
        $this->storageDir = sys_get_temp_dir() . '/rate_limits';
        
        // Create storage directory if it doesn't exist
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Invoke middleware
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->tooManyAttempts($key)) {
            return $this->buildRateLimitResponse($key);
        }

        $this->hit($key);

        return $handler->handle($request);
    }

    /**
     * Get request signature (IP + route)
     */
    private function resolveRequestSignature(Request $request): string
    {
        $ip = $this->getClientIP($request);
        $route = $request->getUri()->getPath();
        
        return hash('sha256', $ip . '|' . $route);
    }

    /**
     * Get client IP address
     */
    private function getClientIP(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        // Check for forwarded IP (behind proxy)
        if (isset($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        
        if (isset($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        }
        
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Check if too many attempts
     */
    private function tooManyAttempts(string $key): bool
    {
        $file = $this->getStorageFile($key);

        if (!file_exists($file)) {
            return false;
        }

        $data = json_decode(file_get_contents($file), true);

        if ($data['expires_at'] < time()) {
            // Expired, delete file
            unlink($file);
            return false;
        }

        return $data['attempts'] >= $this->maxAttempts;
    }

    /**
     * Increment attempt counter
     */
    private function hit(string $key): void
    {
        $file = $this->getStorageFile($key);
        $expiresAt = time() + ($this->decayMinutes * 60);

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            // Reset if expired
            if ($data['expires_at'] < time()) {
                $data = ['attempts' => 1, 'expires_at' => $expiresAt];
            } else {
                $data['attempts']++;
            }
        } else {
            $data = ['attempts' => 1, 'expires_at' => $expiresAt];
        }

        file_put_contents($file, json_encode($data));
    }

    /**
     * Get storage file path
     */
    private function getStorageFile(string $key): string
    {
        return $this->storageDir . '/' . $key . '.json';
    }

    /**
     * Get seconds until next attempt allowed
     */
    private function availableIn(string $key): int
    {
        $file = $this->getStorageFile($key);

        if (!file_exists($file)) {
            return 0;
        }

        $data = json_decode(file_get_contents($file), true);
        $remaining = $data['expires_at'] - time();

        return max(0, $remaining);
    }

    /**
     * Build rate limit exceeded response using PSR-7
     */
    private function buildRateLimitResponse(string $key): Response
    {
        // Use PSR-7 response (Slim's implementation, but via interface)
        $retryAfter = $this->availableIn($key);
        $response = new SlimResponse();

        $response = ResponseHelper::error(
            $response, 
            'Rate limit exceeded. Please try again later.', 
            429, 
            ['retry_after' => $retryAfter]
        );

        return $response
            ->withHeader('Retry-After', (string)$retryAfter)
            ->withHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
    }

    /**
     * Cleanup old rate limit files (should be run via cron)
     */
    public static function cleanup(string $storageDir = null): void
    {
        $dir = $storageDir ?? (sys_get_temp_dir() . '/rate_limits');

        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.json');
        $now = time();

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && $data['expires_at'] < $now) {
                unlink($file);
            }
        }
    }
}
