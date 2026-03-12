<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use App\Models\RefreshToken;
use App\Models\AuditLog;
use App\Models\User;

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
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default_secret';
        $this->jwtAlgorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';
        $this->jwtExpiry = (int)($_ENV['JWT_EXPIRE'] ?? 3600);
        $this->refreshTokenExpiry = (int)($_ENV['REFRESH_TOKEN_EXPIRE'] ?? 604800);
        $this->jwtIssuer = $_ENV['JWT_ISSUER'] ?? 'stringventory';
        $this->refreshTokenAlgo = $_ENV['REFRESH_TOKEN_ALGO'] ?? 'sha256';
    }

    /**
     * Generate JWT access token
     */
    public function generateAccessToken(array $payload): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->jwtExpiry;

        $tokenPayload = [
            'iss' => $this->jwtIssuer,
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'data' => $payload
        ];

        return JWT::encode($tokenPayload, $this->jwtSecret, $this->jwtAlgorithm);
    }

    /**
     * Generate refresh token (longer expiry)
     */
    public function generateRefreshToken(array $payload): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->refreshTokenExpiry;

        $tokenPayload = [
            'iss' => $this->jwtIssuer,
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'type' => 'refresh',
            'data' => $payload
        ];

        return JWT::encode($tokenPayload, $this->jwtSecret, $this->jwtAlgorithm);
    }

    /**
     * Validate and decode JWT token
     */
    public function validateToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));
        } catch (Exception $e) {
            error_log('JWT Validation Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract token from Authorization header
     */
    public function extractTokenFromHeader(?string $authHeader): ?string
    {
        if (!$authHeader) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Hash password using Argon2id
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Verify password against hash
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate user payload for JWT
     */
    public function generateUserPayload($user): array
    {
        if (is_object($user)) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName
            ];
        }

        return [
            'id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'role' => $user['role'] ?? 'salesperson',
            'status' => $user['status'] ?? 'active'
        ];
    }

    /**
     * Refresh access token using DB-backed refresh token
     */
    public function refreshAccessToken(string $refreshToken, array $metadata = []): ?array
    {
        $storedToken = $this->validateRefreshToken($refreshToken);

        if (!$storedToken) {
            return null;
        }

        $newRefreshToken = $this->rotateRefreshToken($storedToken, $metadata);

        $user = User::find($storedToken->userId);
        if (!$user) return null;

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

    // ========================================
    // DB-BACKED REFRESH TOKEN METHODS
    // ========================================

    public function createRefreshToken(int $userId, array $metadata): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash($this->refreshTokenAlgo, $plainToken);

        RefreshToken::create([
            'userId' => $userId,
            'tokenHash' => $tokenHash,
            'deviceName' => $metadata['device_name'] ?? null,
            'ipAddress' => $metadata['ip_address'] ?? null,
            'userAgent' => $metadata['user_agent'] ?? null,
            'expiresAt' => date('Y-m-d H:i:s', time() + $this->refreshTokenExpiry)
        ]);

        return $plainToken;
    }

    public function validateRefreshToken(string $plainToken): ?RefreshToken
    {
        $tokenHash = hash($this->refreshTokenAlgo, $plainToken);

        $refreshToken = RefreshToken::where('tokenHash', $tokenHash)
            ->where('revoked', false)
            ->where('expiresAt', '>', date('Y-m-d H:i:s'))
            ->first();

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
        $refreshToken = RefreshToken::where('tokenHash', $tokenHash)->first();

        if ($refreshToken) {
            $refreshToken->update([
                'revoked' => true,
                'revokedAt' => date('Y-m-d H:i:s')
            ]);
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
        $oldToken->update([
            'revoked' => true,
            'revokedAt' => date('Y-m-d H:i:s')
        ]);

        return $this->createRefreshToken($oldToken->userId, $metadata);
    }

    /**
     * Revoke all active tokens for a user (logout all devices)
     *
     * @param int $userId User ID
     * @return int Number of tokens revoked
     */
    public function revokeAllUserTokens(int $userId): int
    {
        return RefreshToken::where('userId', $userId)
            ->where('revoked', false)
            ->update([
                'revoked' => true,
                'revokedAt' => date('Y-m-d H:i:s')
            ]);
    }

    // ========================================
    // AUDIT LOGGING
    // ========================================

    public function logAuditEvent(?int $userId, string $action, array $metadata): AuditLog
    {
        return AuditLog::create([
            'userId' => $userId,
            'action' => $action,
            'ipAddress' => $metadata['ip_address'] ?? 'unknown',
            'userAgent' => $metadata['user_agent'] ?? null,
            'metadata' => $metadata['extra'] ?? null
        ]);
    }
}
