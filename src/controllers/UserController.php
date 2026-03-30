<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Helper\ResponseHelper;
use App\Services\VerificationService;
use App\Services\UploadService;
use App\Services\NotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * UserController
 * Handles user-related operations using Eloquent ORM
 */
class UserController
{
    private VerificationService $verificationService;
    private UploadService $uploadService;
    private NotificationService $notificationService;

    public function __construct(VerificationService $verificationService, UploadService $uploadService, NotificationService $notificationService)
    {
        $this->verificationService = $verificationService;
        $this->uploadService = $uploadService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get all users
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $users = User::all();
            
            return ResponseHelper::success($response, 'Users fetched successfully', [
                'users' => $users,
                'count' => $users->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch users', 500, $e->getMessage());
        }
    }

    /**
     * Get single user by ID
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $user = User::find($id);
            
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }
            
            return ResponseHelper::success($response, 'User fetched successfully', $user->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch user', 500, $e->getMessage());
        }
    }

    /**
     * Create new user
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            // Validate required fields
            if (empty($data['firstName']) || empty($data['lastName']) || empty($data['email'])) {
                return ResponseHelper::error($response, 'First Name, Last Name and email are required', 400);
            }
            
            // Check if email already exists
            if (User::where('email', $data['email'])->exists()) {
                return ResponseHelper::error($response, 'Email already exists', 409);
            }

            // Handle profile image upload
            if (!empty($uploadedFiles['profileImage'])) {
                $file = $uploadedFiles['profileImage'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['profileImage'] = $this->uploadService->uploadFile($file, 'avatar', 'users');
                }
            }
            
            // If no password is provided, generate a random one
            if (empty($data['passwordHash']) && empty($data['password'])) {
                $data['passwordHash'] = bin2hex(random_bytes(8));
            } elseif (!empty($data['password'])) {
                $data['passwordHash'] = $data['password'];
            }
            
            $user = User::create($data);

            // Send verification email
            $this->verificationService->sendVerificationEmail($user);

            // Trigger notification for admins
            $this->notificationService->notifyAdmins(
                'user',
                'New User Created',
                "A new user {$user->firstName} {$user->lastName} ({$user->role}) has been registered.",
                ['userId' => $user->id, 'email' => $user->email]
            );
            
            $requestUser = $request->getAttribute('user');
            AuditLog::log($request, $requestUser ? $requestUser->id : null, 'user_created', [
                'userId' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ]);

            return ResponseHelper::success($response, 'User created successfully and verification email sent', $user->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create user', 500, $e->getMessage());
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();
            
            $user = User::find($id);
            
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Authorization: Check if user is admin or the account owner
            $requestUser = $request->getAttribute('user');
            if ($requestUser->role !== User::ROLE_CEO && (int)$id !== (int)$requestUser->id) {
                return ResponseHelper::error($response, 'Unauthorized: You can only update your own profile', 403);
            }
            
            // Check email uniqueness if email is being updated
            if (isset($data['email']) && User::where('email', $data['email'])->where('id', '!=', $id)->exists()) {
                return ResponseHelper::error($response, 'Email already exists', 409);
            }

            // Handle profile image upload
            if (!empty($uploadedFiles['profileImage'])) {
                $file = $uploadedFiles['profileImage'];
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $data['profileImage'] = $this->uploadService->replaceFile($file, $user->profileImage, 'avatar', 'users');
                }
            }
            
            $oldEmail = $user->email;
            $user->update($data);

            // Notify user about profile update
            $message = "Your profile information has been updated.";
            if (isset($data['email']) && $data['email'] !== $oldEmail) {
                $message = "Your email address has been updated to {$data['email']}.";
            }
            $this->notificationService->notifyUser(
                $user->id,
                'profile_update',
                'Profile Updated',
                $message,
                ['userId' => $user->id]
            );
            
            AuditLog::log($request, $requestUser->id, 'user_updated', [
                'userId' => $user->id,
                'email' => $user->email,
            ]);

            return ResponseHelper::success($response, 'User updated successfully', $user->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update user', 500, $e->getMessage());
        }
    }

    /**
     * Delete user
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $user = User::find($id);
            
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Authorization: Check if user is admin or the account owner
            $requestUser = $request->getAttribute('user');
            if ($requestUser->role !== User::ROLE_CEO && (int)$id !== (int)$requestUser->id) {
                return ResponseHelper::error($response, 'Unauthorized: You can only delete your own profile', 403);
            }

            // Delete associated image
            if ($user->profileImage) {
                $this->uploadService->deleteFile($user->profileImage);
            }
            
            $deletedUserId = $user->id;
            $user->delete();

            AuditLog::log($request, $requestUser->id, 'user_deleted', [
                'deletedUserId' => $deletedUserId,
            ]);

            return ResponseHelper::success($response, 'User deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete user', 500, $e->getMessage());
        }
    }
}
