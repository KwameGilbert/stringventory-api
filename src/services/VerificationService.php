<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\EmailVerificationToken;
use App\Services\EmailService;
use Exception;

/**
 * VerificationService
 * 
 * Handles email verification logic.
 */
class VerificationService
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(User $user): bool
    {
        try {
            // Generate token
            $tokenData = EmailVerificationToken::createWithPlainToken($user, 24);
            $plainToken = $tokenData['plainToken'];

            // Build verification URL
            $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173';
            $verificationUrl = "{$frontendUrl}/verify-email?token={$plainToken}&email=" . urlencode($user->email);

            // Send email
            return $this->emailService->sendEmailVerificationEmail($user, $verificationUrl);
        } catch (Exception $e) {
            error_log('Failed to send verification email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify a user's email with a token
     */
    public function verifyEmail(string $token, string $email): array
    {
        $verificationToken = EmailVerificationToken::findByToken($token);

        if (!$verificationToken) {
            return ['success' => false, 'message' => 'Invalid or expired verification token'];
        }

        if ($verificationToken->email !== $email) {
            return ['success' => false, 'message' => 'Email does not match the verification token'];
        }

        $user = User::find($verificationToken->userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if ($user->emailVerified) {
            return ['success' => true, 'message' => 'Email already verified', 'user' => $user];
        }

        // Mark as verified
        $verificationToken->markAsUsed();
        $user->update([
            'emailVerified' => true,
        ]);

        // Send welcome email
        try {
            $this->emailService->sendWelcomeEmail($user);
        } catch (Exception $e) {
            error_log('Failed to send welcome email: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Email verified successfully', 'user' => $user];
    }
}
