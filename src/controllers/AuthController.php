<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Helper\ResponseHelper;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\VerificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Exception;

/**
 * AuthController
 * 
 * Handles all authentication endpoints for Stringventory.
 */
class AuthController
{
    private AuthService $authService;
    private VerificationService $verificationService;
    private EmailService $emailService;

    public function __construct(
        AuthService $authService,
        VerificationService $verificationService,
        EmailService $emailService
    ) {
        $this->authService = $authService;
        $this->verificationService = $verificationService;
        $this->emailService = $emailService;
    }

    /**
     * Register a new user
     * POST /auth/register
     */
    public function register(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $metadata = $this->getRequestMetadata($request);

            // Validation
            $errors = $this->validateRegistration($data);
            if (!empty($errors)) {
                return ResponseHelper::error($response, 'Validation failed', 400, $errors);
            }

            // Check if user already exists
            $existingUser = User::where('email', $data['email'])->first();
            if ($existingUser) {
                return ResponseHelper::error($response, 'Account already exists with this email', 409);
            }

            // Create user
            $user = User::create([
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName'],
                'email' => $data['email'],
                'passwordHash' => $data['password'], // Hash is handled by model setter
                'role' => $data['role'] ?? User::ROLE_SALESPERSON,
                'status' => User::STATUS_ACTIVE,
                'emailVerified' => false,
                'phone' => $data['phone'] ?? null
            ]);

            // Log registration event
            $this->authService->logAuditEvent($user->id, 'register', $metadata);

            // Send verification email
            $this->verificationService->sendVerificationEmail($user);

            // Generate tokens
            $userPayload = $this->authService->generateUserPayload($user);
            $accessToken = $this->authService->generateAccessToken($userPayload);
            $refreshToken = $this->authService->createRefreshToken($user->id, $metadata);

            return ResponseHelper::success($response, 'User registered successfully. Please verify your email.', [
                'user' => [
                    'id' => $user->id,
                    'firstName' => $user->firstName,
                    'lastName' => $user->lastName,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->authService->getTokenExpiry()
            ], 201);

        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Registration failed', 500, $e->getMessage());
        }
    }

    /**
     * Login user
     * POST /auth/login
     */
    public function login(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $metadata = $this->getRequestMetadata($request);

            if (empty($data['email']) || empty($data['password'])) {
                return ResponseHelper::error($response, 'Email and password are required', 400);
            }

            $user = User::where('email', $data['email'])->first();

            if (!$user) {
                $this->authService->logAuditEvent(null, 'login_failed', array_merge($metadata, [
                    'extra' => ['reason' => 'user_not_found', 'email' => $data['email']]
                ]));
                return ResponseHelper::error($response, 'Invalid credentials', 401);
            }

            if (!$this->authService->verifyPassword($data['password'], $user->passwordHash)) {
                $this->authService->logAuditEvent($user->id, 'login_failed', array_merge($metadata, [
                    'extra' => ['reason' => 'invalid_password']
                ]));
                return ResponseHelper::error($response, 'Invalid credentials', 401);
            }

            if ($user->status !== User::STATUS_ACTIVE) {
                return ResponseHelper::error($response, 'Account is not active', 403);
            }

            // Update last login
            $user->update(['lastLogin' => date('Y-m-d H:i:s')]);

            // Generate tokens
            $userPayload = $this->authService->generateUserPayload($user);
            $accessToken = $this->authService->generateAccessToken($userPayload);
            $refreshToken = $this->authService->createRefreshToken($user->id, $metadata);

            $this->authService->logAuditEvent($user->id, 'login', $metadata);

            return ResponseHelper::success($response, 'Login successful', [
                'user' => [
                    'id' => $user->id,
                    'firstName' => $user->firstName,
                    'lastName' => $user->lastName,
                    'email' => $user->email,
                    'role' => $user->role,
                    'mustChangePassword' => (bool) $user->mustChangePassword,
                ],
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $this->authService->getTokenExpiry(),
                'token_type' => 'Bearer',
            ], 200);

        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Login failed', 500, $e->getMessage());
        }
    }

    /**
     * Refresh access token
     */
    public function refresh(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (empty($data['refresh_token'])) {
                return ResponseHelper::error($response, 'Refresh token is required', 400);
            }

            $metadata = $this->getRequestMetadata($request);
            $tokens = $this->authService->refreshAccessToken($data['refresh_token'], $metadata);

            if (!$tokens) {
                return ResponseHelper::error($response, 'Invalid or expired refresh token', 401);
            }

            return ResponseHelper::success($response, 'Token refreshed successfully', $tokens, 200);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Token refresh failed', 500, $e->getMessage());
        }
    }

    /**
     * Get current user
     */
    public function me(Request $request, Response $response): Response
    {
        try {
            $userData = $request->getAttribute('user');
            if (!$userData) {
                return ResponseHelper::error($response, 'User not authenticated', 401);
            }

            $user = User::find($userData->id);
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            return ResponseHelper::success($response, 'User details fetched successfully', [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'phone' => $user->phone,
                'emailVerified' => $user->emailVerified,
                'createdAt' => $user->createdAt
            ], 200);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch user data', 500, $e->getMessage());
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (!empty($data['refresh_token'])) {
                $this->authService->revokeRefreshToken($data['refresh_token']);
            }
            return ResponseHelper::success($response, 'Logged out successfully', [], 200);
        } catch (Exception $e) {
            return ResponseHelper::success($response, 'Logged out successfully', [], 200);
        }
    }

    /**
     * Change password for logged-in user
     * POST /auth/password/change
     */
    public function changePassword(Request $request, Response $response): Response
    {
        try {
            $userData = $request->getAttribute('user');
            $data = $request->getParsedBody();

            if (empty($data['current_password']) || empty($data['new_password'])) {
                return ResponseHelper::error($response, 'Current and new password are required', 400);
            }

            $user = User::find($userData->id);
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Verify current password
            if (!$this->authService->verifyPassword($data['current_password'], $user->passwordHash)) {
                return ResponseHelper::error($response, 'Invalid current password', 400);
            }

            if (strlen($data['new_password']) < 8) {
                return ResponseHelper::error($response, 'New password must be at least 8 characters', 400);
            }

            // Update password and clear first-login flag
            $user->update(['passwordHash' => $data['new_password'], 'mustChangePassword' => false]);

            // Log event
            $this->authService->logAuditEvent($user->id, 'password_change', $this->getRequestMetadata($request));

            // Send notification email
            $this->emailService->sendPasswordChangedEmail($user);

            // Revoke all other tokens for security (optional but recommended)
            $this->authService->revokeAllUserTokens($user->id);

            return ResponseHelper::success($response, 'Password changed successfully. Please login again with your new password.');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to change password', 500, $e->getMessage());
        }
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            if (empty($params['token']) || empty($params['email'])) {
                return ResponseHelper::error($response, 'Token and email are required', 400);
            }

            $result = $this->verificationService->verifyEmail($params['token'], $params['email']);

            if (!$result['success']) {
                return ResponseHelper::error($response, $result['message'], 400);
            }

            $user = $result['user'];
            
            // Log verification event
            $this->authService->logAuditEvent($user->id, 'email_verified', $this->getRequestMetadata($request));

            return ResponseHelper::success($response, $result['message'], [
                'email_verified' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Email verification failed', 500, $e->getMessage());
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (empty($data['email'])) {
                return ResponseHelper::error($response, 'Email is required', 400);
            }

            $user = User::where('email', $data['email'])->first();
            if (!$user) {
                // Return success to prevent email enumeration
                return ResponseHelper::success($response, 'If an account exists with this email, a verification link has been sent.');
            }

            if ($user->emailVerified) {
                return ResponseHelper::success($response, 'Email is already verified');
            }

            $sent = $this->verificationService->sendVerificationEmail($user);
            if (!$sent) {
                return ResponseHelper::error($response, 'Failed to send verification email', 500);
            }

            $this->authService->logAuditEvent($user->id, 'resend_verification', $this->getRequestMetadata($request));

            return ResponseHelper::success($response, 'Verification email sent successfully.');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to resend verification email', 500, $e->getMessage());
        }
    }

    private function validateRegistration(array $data): array
    {
        $errors = [];
        if (empty($data['firstName'])) $errors['firstName'] = 'First name is required';
        if (empty($data['lastName'])) $errors['lastName'] = 'Last name is required';
        if (empty($data['email']) || !v::email()->validate($data['email'])) $errors['email'] = 'Valid email is required';
        if (empty($data['password']) || strlen($data['password']) < 8) $errors['password'] = 'Password must be at least 8 characters';
        
        if (isset($data['role']) && !in_array($data['role'], [User::ROLE_CEO, User::ROLE_MANAGER, User::ROLE_SALESPERSON])) {
            $errors['role'] = 'Invalid role';
        }
        return $errors;
    }

    private function getRequestMetadata(Request $request): array
    {
        $serverParams = $request->getServerParams();
        return [
            'ip_address' => $serverParams['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'device_name' => $request->getHeaderLine('X-Device-Name')
        ];
    }
}
