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
 * Handles password reset functionality for Stringventory.
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
     */
    public function sendResetLink(string $email, string $ipAddress = 'unknown'): bool
    {
        try {
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return true; // Honey pots
            }

            PasswordReset::where('email', $email)->delete();

            $plainOtp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $tokenHash = hash('sha256', $plainOtp);

            PasswordReset::create([
                'email' => $email,
                'token' => $tokenHash,
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            // Log the request
            AuditLog::create([
                'userId' => $user->id,
                'action' => 'password_reset_requested',
                'ipAddress' => $ipAddress,
                'metadata' => ['email' => $email]
            ]);

            $this->emailService->sendPasswordResetEmail($user, $plainOtp);

            return true;
        } catch (Exception $e) {
            error_log('Password reset error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate and reset user password
     */
    public function resetPassword(string $email, string $otp, string $newPassword, string $ipAddress = 'unknown'): bool
    {
        try {
            $tokenHash = hash('sha256', $otp);
            $resetToken = PasswordReset::where('email', $email)
                ->where('token', $tokenHash)
                ->where('createdAt', '>', date('Y-m-d H:i:s', time() - $this->tokenExpiry))
                ->first();

            if (!$resetToken) {
                return false;
            }

            $user = User::where('email', $email)->first();
            if (!$user) {
                return false;
            }

            $user->update(['passwordHash' => $newPassword]);
            PasswordReset::where('email', $email)->delete();

            AuditLog::create([
                'userId' => $user->id,
                'action' => 'password_reset_completed',
                'ipAddress' => $ipAddress,
                'metadata' => ['email' => $email]
            ]);

            $this->emailService->sendPasswordChangedEmail($user);

            return true;
        } catch (Exception $e) {
            error_log('Password reset completion error: ' . $e->getMessage());
            return false;
        }
    }
}
