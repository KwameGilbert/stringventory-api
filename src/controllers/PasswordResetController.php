<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\PasswordReset;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * PasswordResetController
 * 
 * Handles password reset functionality:
 * - Request password reset (send email)
 * - Verify token and reset password
 */
class PasswordResetController
{
    private AuthService $authService;
    private EmailService $emailService;

    public function __construct(AuthService $authService, EmailService $emailService)
    {
        $this->authService = $authService;
        $this->emailService = $emailService;
    }

    /**
     * Request password reset (send email)
     * POST /auth/password/forgot
     */
    public function requestReset(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $metadata = $this->getRequestMetadata($request);
            
            if (empty($data['email'])) {
                return ResponseHelper::error($response, 'Email is required', 400);
            }

            $user = User::findByEmail($data['email']);
            
            // Always return success (security: don't reveal if email exists)
            if (!$user) {
                return ResponseHelper::success(
                    $response, 
                    'If that email exists, a password reset link has been sent',
                    []
                );
            }

            // Generate reset token
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plainToken);

            // Delete old tokens for this email
            PasswordReset::deleteForEmail($user->email);

            // Create new token
            PasswordReset::create([
                'email' => $user->email,
                'token' => $tokenHash,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Send email with token
            $emailSent = $this->emailService->sendPasswordResetEmail($user, $plainToken);

            // Log audit event
            $this->authService->logAuditEvent(
                $user->id, 
                'password_reset_requested', 
                array_merge($metadata, ['extra' => ['email_sent' => $emailSent]])
            );

            return ResponseHelper::success(
                $response, 
                'If that email exists, a password reset link has been sent',
                []
            );

        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return ResponseHelper::error($response, 'Password reset request failed', 500, $e->getMessage());
        }
    }

    /**
     * Reset password with token
     * POST /auth/password/reset
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $metadata = $this->getRequestMetadata($request);
            
            // Validate input
            if (empty($data['email']) || empty($data['token']) || empty($data['password'])) {
                return ResponseHelper::error($response, 'Email, token, and new password are required', 400);
            }

            if (strlen($data['password']) < 8) {
                return ResponseHelper::error($response, 'Password must be at least 8 characters', 400);
            }

            // Find valid token
            $resetToken = PasswordReset::findValidToken($data['email'], $data['token']);

            if (!$resetToken) {
                return ResponseHelper::error($response, 'Invalid or expired reset token', 400);
            }

            // Find user
            $user = User::findByEmail($data['email']);
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Update password (automatically hashed by User model)
            $user->password = $data['password'];
            $user->save();

            // Delete all password reset tokens for this email
            PasswordReset::deleteForEmail($data['email']);

            // Log audit event
            $this->authService->logAuditEvent($user->id, 'password_reset_completed', $metadata);

            // Revoke all refresh tokens (force re-login on all devices for security)
            $this->authService->revokeAllUserTokens($user->id);

            // Send password changed confirmation email
            try {
                $this->emailService->sendPasswordChangedEmail($user);
            } catch (Exception $e) {
                // Log but don't fail - notification email is not critical
                error_log('Failed to send password changed email: ' . $e->getMessage());
            }

            return ResponseHelper::success($response, 'Password reset successful. Please login with your new password.', []);

        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ResponseHelper::error($response, 'Password reset failed', 500, $e->getMessage());
        }
    }

    /**
     * Extract metadata from request
     */
    private function getRequestMetadata(Request $request): array
    {
        $serverParams = $request->getServerParams();
        
        return [
            'ip_address' => $serverParams['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $request->getHeaderLine('User-Agent')
        ];
    }
}
