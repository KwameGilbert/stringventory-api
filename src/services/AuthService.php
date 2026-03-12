<?php

declare(strict_types=1);

namespace App\Services;


use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use App\Models\RefreshToken;
use App\Models\AuditLog;


/**
 * AuthService
 * 
 * Handles all authentication-related operations including:
 * - JWT token generation and validation
 * - Password hashing and verification
 * - Token refresh
 * - User session management
 */
class AuthService
{
    private string $jwtSecret;
    private string $jwtAlgorithm;
    private int $jwtExpiry;
    private int $refreshTokenExpiry;
    private string $jwtIssuer;
    private string $refreshTokenAlgo;

    public function __construct()
    {
        $this->jwtSecret = $_ENV['JWT_SECRET'];
        $this->jwtAlgorithm = $_ENV['JWT_ALGORITHM'];
        $this->jwtExpiry = (int)($_ENV['JWT_EXPIRE']);
        $this->refreshTokenExpiry = (int)($_ENV['REFRESH_TOKEN_EXPIRE']);
        $this->jwtIssuer = $_ENV['JWT_ISSUER'];
        $this->refreshTokenAlgo = $_ENV['REFRESH_TOKEN_ALGO'];
    }

    /**
     * Generate JWT access token
     *
     * @param array $payload User data to encode in token
     * @return string JWT token
     */
    public function generateAccessToken(array $payload): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->jwtExpiry;

        $tokenPayload = [
            'iss' => $this->jwtIssuer,        // Issuer
            'iat' => $issuedAt,                // Issued at
            'exp' => $expirationTime,          // Expiration
            'data' => $payload                 // User data
        ];

        return JWT::encode($tokenPayload, $this->jwtSecret, $this->jwtAlgorithm);
    }

    /**
     * Generate refresh token (longer expiry)
     *
     * @param array $payload User data to encode in token
     * @return string Refresh token
     */
    public function generateRefreshToken(array $payload): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->refreshTokenExpiry;

        $tokenPayload = [
            'iss' => $this->jwtIssuer,
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'type' => 'refresh',               // Mark as refresh token
            'data' => $payload
        ];

        return JWT::encode($tokenPayload, $this->jwtSecret, $this->jwtAlgorithm);
    }

    /**
     * Validate and decode JWT token
     *
     * @param string $token JWT token to validate
     * @return object|null Decoded token payload or null if invalid
     */
    public function validateToken(string $token): ?object
    {
        try {
            error_log('JWT Validation: Attempting to decode token');
            error_log('JWT Secret (first 6 chars): ' . substr($this->jwtSecret, 0, 6));
            error_log('JWT Algorithm: ' . $this->jwtAlgorithm);
            error_log('Token length: ' . strlen($token));
            
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));
            
            return $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            error_log('JWT Validation Error: Token has EXPIRED - ' . $e->getMessage());
            return null;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            error_log('JWT Validation Error: SIGNATURE INVALID (secret mismatch?) - ' . $e->getMessage());
            return null;
        } catch (Exception $e) {
            // Token is invalid, expired, or malformed
            error_log('JWT Validation Error: ' . get_class($e) . ' - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract token from Authorization header
     *
     * @param string|null $authHeader Authorization header value
     * @return string|null Token string or null
     */
    public function extractTokenFromHeader(?string $authHeader): ?string
    {
        if (!$authHeader) {
            return null;
        }

        // Expected format: "Bearer <token>"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Hash password using Argon2id
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,  // 64 MB
            'time_cost' => 4,         // 4 iterations
            'threads' => 2            // 2 parallel threads
        ]);
    }

    /**
     * Verify password against hash
     *
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool True if password matches
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate user payload for JWT
     *
     * @param object|array $user User object/array
     * @return array User data for token
     */
    public function generateUserPayload($user): array
    {
        // Handle Eloquent model - use toArray() method instead of (array) cast
        if (is_object($user)) {
            if (method_exists($user, 'toArray')) {
                $userData = $user->toArray();
            } else {
                // Fallback: access properties directly
                $userData = [
                    'id' => $user->id ?? null,
                    'email' => $user->email ?? null,
                    'role' => $user->role ?? 'attendee',
                    'status' => $user->status ?? 'active',
                ];
            }
        } else {
            $userData = $user;
        }

        return [
            'id' => $userData['id'] ?? null,
            'email' => $userData['email'] ?? null,
            'role' => $userData['role'] ?? 'attendee',
            'status' => $userData['status'] ?? 'active'
        ];
    }

    /**
     * Refresh access token using DB-backed refresh token
     *
     * @param string $refreshToken Plain text refresh token
     * @param array $metadata Device metadata (ip, user_agent)
     * @return array|null New tokens or null if invalid
     */
    public function refreshAccessToken(string $refreshToken, array $metadata = []): ?array
    {
        // 1. Validate the refresh token against the database
        $storedToken = $this->validateRefreshToken($refreshToken);

        if (!$storedToken) {
            return null;
        }

        // 2. Rotate the refresh token (security best practice)
        $newRefreshToken = $this->rotateRefreshToken($storedToken, $metadata);

        // 3. Generate a new JWT access token
        $user = $storedToken->user;
        $userPayload = $this->generateUserPayload($user);
        $newAccessToken = $this->generateAccessToken($userPayload);

        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtExpiry
        ];
    }

    /**
     * Get JWT expiry time
     *
     * @return int Expiry time in seconds
     */
    public function getTokenExpiry(): int
    {
        return $this->jwtExpiry;
    }

    /**
     * Get refresh token expiry time
     *
     * @return int Expiry time in seconds
     */
    public function getRefreshTokenExpiry(): int
    {
        return $this->refreshTokenExpiry;
    }

    // ========================================
    // DB-BACKED REFRESH TOKEN METHODS
    // ========================================

    /**
     * Generate and store refresh token in database
     *
     * @param int $userId User ID
     * @param array $metadata Device info (ip, user_agent, device_name)
     * @return string Plain text refresh token
     */
    public function createRefreshToken(int $userId, array $metadata): string
    {
        // Generate random token
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash($this->refreshTokenAlgo, $plainToken);

        // Store in database
        RefreshToken::create([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'device_name' => $metadata['device_name'] ?? null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'user_agent' => $metadata['user_agent'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', time() + $this->refreshTokenExpiry)
        ]);

        return $plainToken;
    }

    /**
     * Validate refresh token against database
     *
     * @param string $plainToken Plain text refresh token
     * @return RefreshToken|null RefreshToken model or null if invalid
     */
    public function validateRefreshToken(string $plainToken): ?RefreshToken
    {
        $tokenHash = hash($this->refreshTokenAlgo, $plainToken);

        $refreshToken = RefreshToken::where('token_hash', $tokenHash)->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return null;
        }

        return $refreshToken;
    }

    /**
     * Revoke refresh token
     *
     * @param string $plainToken Plain text refresh token
     * @return bool Success
     */
    public function revokeRefreshToken(string $plainToken): bool
    {
        $tokenHash = hash($this->refreshTokenAlgo, $plainToken);

        $refreshToken = RefreshToken::where('token_hash', $tokenHash)->first();

        if ($refreshToken) {
            $refreshToken->revoke();
            return true;
        }

        return false;
    }

    /**
     * Rotate refresh token (revoke old, create new)
     *
     * @param RefreshToken $oldToken Old refresh token model
     * @param array $metadata Device metadata
     * @return string New plain text refresh token
     */
    public function rotateRefreshToken(RefreshToken $oldToken, array $metadata): string
    {
        // Revoke old token
        $oldToken->revoke();

        // Create new token
        return $this->createRefreshToken($oldToken->user_id, $metadata);
    }

    /**
     * Revoke all refresh tokens for a user
     *
     * @param int $userId User ID
     * @return int Number of tokens revoked
     */
    public function revokeAllUserTokens(int $userId): int
    {
        return RefreshToken::revokeAllForUser($userId);
    }

    // ========================================
    // AUDIT LOGGING
    // ========================================

    /**
     * Log authentication event
     *
     * @param int|null $userId User ID (null for failed login attempts)
     * @param string $action Action performed
     * @param array $metadata Additional data (ip, user_agent, etc.)
     * @return AuditLog Created audit log
     */
    public function logAuditEvent(?int $userId, string $action, array $metadata): AuditLog
    {
        return AuditLog::logEvent(
            $userId,
            $action,
            $metadata['ip_address'] ?? 'unknown',
            $metadata['user_agent'] ?? null,
            $metadata['extra'] ?? null
        );
    }
}
