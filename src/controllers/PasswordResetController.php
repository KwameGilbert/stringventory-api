<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helper\ResponseHelper;
use App\Services\AuthService;
use App\Services\PasswordResetService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * PasswordResetController
 * 
 * Handles password reset flow for Stringventory.
 */
class PasswordResetController
{
    private AuthService $authService;
    private PasswordResetService $passwordResetService;

    public function __construct(AuthService $authService, PasswordResetService $passwordResetService)
    {
        $this->authService = $authService;
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Request a password reset link
     * POST /auth/password/reset-request
     */
    public function requestReset(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (empty($data['email'])) {
                return ResponseHelper::error($response, 'Email is required', 400);
            }

            $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
            $this->passwordResetService->sendResetLink($data['email'], $ipAddress);

            // Always return success to prevent email enumeration
            return ResponseHelper::success($response, 'If an account exists with this email, an OTP has been sent.');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to process request', 500, $e->getMessage());
        }
    }

    /**
     * Reset password using token
     * POST /auth/password/reset
     */
    public function reset(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $required = ['email', 'otp', 'password'];
            
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ResponseHelper::error($response, ucfirst($field) . ' is required', 400);
                }
            }

            if (strlen($data['password']) < 8) {
                return ResponseHelper::error($response, 'Password must be at least 8 characters', 400);
            }

            $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
            $success = $this->passwordResetService->resetPassword(
                $data['email'],
                $data['otp'],
                $data['password'],
                $ipAddress
            );

            if (!$success) {
                return ResponseHelper::error($response, 'Invalid or expired OTP', 400);
            }

            // Revoke all existing tokens for this user for security
            $user = \App\Models\User::where('email', $data['email'])->first();
            if ($user) {
                $this->authService->revokeAllUserTokens($user->id);
            }

            return ResponseHelper::success($response, 'Password has been reset successfully. You can now login with your new password.');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to reset password', 500, $e->getMessage());
        }
    }
}
