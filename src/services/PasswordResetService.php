<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\PasswordReset;
use App\Models\AuditLog;
use Exception;

/**
 * PasswordResetService
 * 
 * Handles password reset functionality:
 * - Generate reset tokens
 * - Send reset emails
 * - Validate tokens
 * - Reset passwords
 */
class PasswordResetService
{
    private EmailService $emailService;
    private int $tokenExpiry;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
        $this->tokenExpiry = (int)($_ENV['PASSWORD_RESET_EXPIRE'] ?? 3600); // 1 hour
    }

    /**
     * Send password reset link to user's email
     *
     * @param string $email User email
     * @param string $ipAddress Request IP
     * @return bool Success
     */
    public function sendResetLink(string $email, string $ipAddress = 'unknown'): bool
    {
        try {
            // Find user
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                // Don't reveal if email exists (security measure)
                return true;
            }

            // Delete any existing tokens for this email
            PasswordReset::deleteForEmail($email);

            // Generate new token
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plainToken);

            // Store hashed token
            PasswordReset::create([
                'email' => $email,
                'token' => $tokenHash,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Log the request
            AuditLog::logEvent(
                $user->id,
                AuditLog::ACTION_PASSWORD_RESET_REQUESTED,
                $ipAddress,
                null,
                ['email' => $email]
            );

            // Send email with plain token
            $this->emailService->sendPasswordResetEmail($user, $plainToken);

            return true;

        } catch (Exception $e) {
            error_log('Password reset error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate reset token
     *
     * @param string $email User email
     * @param string $token Plain text token
     * @return bool Valid
     */
    public function validateResetToken(string $email, string $token): bool
    {
        $tokenHash = hash('sha256', $token);

        return PasswordReset::where('email', $email)
            ->where('token', $tokenHash)
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - $this->tokenExpiry))
            ->exists();
    }

    /**
     * Reset user password
     *
     * @param string $email User email
     * @param string $token Reset token
     * @param string $newPassword New password
     * @param string $ipAddress Request IP
     * @return bool Success
     */
    public function resetPassword(
        string $email,
        string $token,
        string $newPassword,
        string $ipAddress = 'unknown'
    ): bool {
        try {
            // Validate token
            if (!$this->validateResetToken($email, $token)) {
                return false;
            }

            // Find user
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return false;
            }

            // Update password (will be auto-hashed by User model)
            $user->update(['password' => $newPassword]);

            // Delete used token
            PasswordReset::deleteForEmail($email);

            // Log the password change
            AuditLog::logEvent(
                $user->id,
                AuditLog::ACTION_PASSWORD_RESET_COMPLETED,
                $ipAddress,
                null,
                ['email' => $email]
            );

            // Send confirmation email
            $this->emailService->sendPasswordChangedEmail($user);

            return true;

        } catch (Exception $e) {
            error_log('Password reset completion error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cleanup expired tokens (run via cron)
     *
     * @return int Number of tokens deleted
     */
    public function cleanupExpiredTokens(): int
    {
        return PasswordReset::cleanupExpired();
    }
}
