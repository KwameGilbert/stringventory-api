<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Attendee;
use App\Models\User;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * AttendeeController
 * Handles attendee-related operations using Eloquent ORM
 */
class AttendeeController
{
    /**
     * Get all attendees
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $attendees = Attendee::all();

            return ResponseHelper::success($response, 'Attendees fetched successfully', [
                'attendees' => $attendees,
                'count' => $attendees->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch attendees', 500, $e->getMessage());
        }
    }

    /**
     * Get single attendee by ID
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $attendee = Attendee::find($id);

            if (!$attendee) {
                return ResponseHelper::error($response, 'Attendee not found', 404);
            }

            return ResponseHelper::success($response, 'Attendee fetched successfully', $attendee->getFullProfile());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch attendee', 500, $e->getMessage());
        }
    }

    /**
     * Create new attendee profile
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();

            // Validate required fields
            $requiredFields = ['user_id', 'first_name', 'last_name', 'email'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ResponseHelper::error($response, "Field '$field' is required", 400);
                }
            }

            // Check if user already has an attendee profile
            if (Attendee::findByUserId((int) $data['user_id'])) {
                return ResponseHelper::error($response, 'User already has an attendee profile', 409);
            }

            $attendee = Attendee::create($data);

            return ResponseHelper::success($response, 'Attendee profile created successfully', $attendee->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create attendee profile', 500, $e->getMessage());
        }
    }

    /**
     * Update attendee profile
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();

            $attendee = Attendee::find($id);

            if (!$attendee) {
                return ResponseHelper::error($response, 'Attendee not found', 404);
            }

            // Authorization: Check if user is admin or the profile owner
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin' && $attendee->user_id !== $user->id) {
                return ResponseHelper::error($response, 'Unauthorized: You do not own this profile', 403);
            }

            $attendee->updateProfile($data);

            return ResponseHelper::success($response, 'Attendee profile updated successfully', $attendee->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update attendee profile', 500, $e->getMessage());
        }
    }

    /**
     * Delete attendee profile
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $attendee = Attendee::find($id);

            if (!$attendee) {
                return ResponseHelper::error($response, 'Attendee not found', 404);
            }

            // Authorization: Check if user is admin or the profile owner
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin' && $attendee->user_id !== $user->id) {
                return ResponseHelper::error($response, 'Unauthorized: You do not own this profile', 403);
            }

            $attendee->deleteProfile();

            return ResponseHelper::success($response, 'Attendee profile deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete attendee profile', 500, $e->getMessage());
        }
    }

    /**
     * Get current authenticated user's attendee profile
     * Creates the profile if it doesn't exist
     */
    public function getMyProfile(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if (!$jwtUser || !isset($jwtUser->id)) {
                return ResponseHelper::error($response, 'Unauthorized', 401);
            }

            // Fetch full user from database to get all fields including name
            $user = User::find($jwtUser->id);

            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Find existing attendee profile
            $attendee = Attendee::findByUserId($user->id);

            // If no profile exists, create one from user data
            if (!$attendee) {
                // Parse name into first_name and last_name
                $name = $user->name ?? '';
                $nameParts = explode(' ', $name, 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';

                $attendee = Attendee::create([
                    'user_id' => $user->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $user->email,
                    'phone' => null,
                    'bio' => null,
                    'profile_image' => null,
                ]);
            }

            return ResponseHelper::success($response, 'Profile fetched successfully', [
                'attendee' => $attendee->getFullProfile()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch profile', 500, $e->getMessage());
        }
    }

    /**
     * Update current authenticated user's attendee profile
     */
    public function updateMyProfile(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if (!$jwtUser || !isset($jwtUser->id)) {
                return ResponseHelper::error($response, 'Unauthorized', 401);
            }

            // Fetch full user from database
            $user = User::find($jwtUser->id);

            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            $data = $request->getParsedBody();

            // Find or create attendee profile
            $attendee = Attendee::findByUserId($user->id);

            if (!$attendee) {
                // Parse name into first_name and last_name
                $name = $user->name ?? '';
                $nameParts = explode(' ', $name, 2);
                $firstName = $data['first_name'] ?? $nameParts[0] ?? '';
                $lastName = $data['last_name'] ?? $nameParts[1] ?? '';

                $attendee = Attendee::create([
                    'user_id' => $user->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $user->email,
                    'phone' => $data['phone'] ?? null,
                    'bio' => $data['bio'] ?? null,
                    'profile_image' => null,
                ]);
            } else {
                $attendee->updateProfile($data);
            }

            return ResponseHelper::success($response, 'Profile updated successfully', [
                'attendee' => $attendee->fresh()->getFullProfile()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update profile', 500, $e->getMessage());
        }
    }

    /**
     * Upload profile image for current authenticated user
     */
    public function uploadProfileImage(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if (!$jwtUser || !isset($jwtUser->id)) {
                return ResponseHelper::error($response, 'Unauthorized', 401);
            }

            // Fetch full user from database
            $user = User::find($jwtUser->id);

            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Find or create attendee profile
            $attendee = Attendee::findByUserId($user->id);

            if (!$attendee) {
                $name = $user->name ?? '';
                $nameParts = explode(' ', $name, 2);
                $attendee = Attendee::create([
                    'user_id' => $user->id,
                    'first_name' => $nameParts[0] ?? '',
                    'last_name' => $nameParts[1] ?? '',
                    'email' => $user->email,
                ]);
            }

            // Get uploaded files
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($uploadedFiles['profile_image'])) {
                return ResponseHelper::error($response, 'No image file provided', 400);
            }

            $uploadedFile = $uploadedFiles['profile_image'];

            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return ResponseHelper::error($response, 'Upload failed', 400);
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $mimeType = $uploadedFile->getClientMediaType();

            if (!in_array($mimeType, $allowedTypes)) {
                return ResponseHelper::error($response, 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP', 400);
            }

            // Validate file size (5MB max)
            if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
                return ResponseHelper::error($response, 'File size exceeds 5MB limit', 400);
            }

            // Generate unique filename
            $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
            $filename = 'attendee_' . $user->id . '_' . time() . '.' . $extension;

            // Define upload directory
            $uploadDir = __DIR__ . '/../../public/uploads/profiles/';

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Move uploaded file
            $uploadedFile->moveTo($uploadDir . $filename);

            // Update attendee profile with new image path
            $imagePath = '/uploads/profiles/' . $filename;
            $attendee->update(['profile_image' => $imagePath]);

            return ResponseHelper::success($response, 'Profile image uploaded successfully', [
                'profile_image' => $imagePath,
                'attendee' => $attendee->fresh()->getFullProfile()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to upload image', 500, $e->getMessage());
        }
    }
}
