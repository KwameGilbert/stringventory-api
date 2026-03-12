<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use Exception;

/**
 * VerificationService
 * 
 * Handles email verification:
 * - Generate verification URLs
 * - Send verification emails
 * - Verify emails
 */
class VerificationService
{
    private EmailService $emailService;
    private string $appUrl;
    private string $appKey;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
        $this->appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $this->appKey = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    }

    /**
     * Send verification email to user
     *
     * @param User $user User to verify
     * @return bool Success
     */
    public function sendVerificationEmail(User $user): bool
    {
        try {
            if ($user->hasVerifiedEmail()) {
                return false; // Already verified
            }

            $verificationUrl = $this->generateVerificationUrl($user);

            $this->emailService->sendEmailVerificationEmail($user, $verificationUrl);

            return true;

        } catch (Exception $e) {
            error_log('Email verification send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate signed verification URL
     *
     * @param User $user User
     * @return string Verification URL
     */
    private function generateVerificationUrl(User $user): string
    {
        $expires = time() + 86400; // 24 hours

        // Create signature
        $signature = hash_hmac('sha256', "{$user->id}|{$user->email}|{$expires}", $this->appKey);

        return "{$this->appUrl}/auth/verify-email/{$user->id}/{$signature}?expires={$expires}";
    }

    /**
     * Verify email using signed URL
     *
     * @param int $userId User ID
     * @param string $signature Signature from URL
     * @param int $expires Expiry timestamp
     * @param string $ipAddress Request IP
     * @return bool Success
     */
    public function verifyEmail(
        int $userId,
        string $signature,
        int $expires,
        string $ipAddress = 'unknown'
    ): bool {
        try {
            // Check expiry
            if (time() > $expires) {
                return false;
            }

            // Find user
            $user = User::find($userId);
            
            if (!$user) {
                return false;
            }

            // Already verified
            if ($user->hasVerifiedEmail()) {
                return true;
            }

            // Verify signature
            $expectedSignature = hash_hmac('sha256', "{$user->id}|{$user->email}|{$expires}", $this->appKey);
            
            if (!hash_equals($expectedSignature, $signature)) {
                return false;
            }

            // Mark as verified
            $user->update([
                'email_verified' => true,
                'email_verified_at' => date('Y-m-d H:i:s')
            ]);

            // Log the verification
            AuditLog::logEvent(
                $user->id,
                AuditLog::ACTION_EMAIL_VERIFIED,
                $ipAddress,
                null,
                ['email' => $user->email]
            );

            return true;

        } catch (Exception $e) {
            error_log('Email verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resend verification email
     *
     * @param int $userId User ID
     * @return bool Success
     */
    public function resendVerification(int $userId): bool
    {
        $user = User::find($userId);

        if (!$user || $user->hasVerifiedEmail()) {
            return false;
        }

        return $this->sendVerificationEmail($user);
    }
}
