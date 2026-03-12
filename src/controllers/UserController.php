<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * UserController
 * Handles user-related operations using Eloquent ORM
 */
class UserController
{
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
            
            // Validate required fields
            if (empty($data['name']) || empty($data['email'])) {
                return ResponseHelper::error($response, 'Name and email are required', 400);
            }
            
            // Check if email already exists
            if (User::emailExists($data['email'])) {
                return ResponseHelper::error($response, 'Email already exists', 409);
            }
            
            $user = User::create($data);
            
            return ResponseHelper::success($response, 'User created successfully', $user->toArray(), 201);
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
            
            $user = User::find($id);
            
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Authorization: Check if user is admin or the account owner
            $requestUser = $request->getAttribute('user');
            if ($requestUser->role !== 'admin' && (int)$id !== $requestUser->id) {
                return ResponseHelper::error($response, 'Unauthorized: You can only update your own profile', 403);
            }
            
            // Check email uniqueness if email is being updated
            if (isset($data['email']) && User::emailExists($data['email'], (int)$id)) {
                return ResponseHelper::error($response, 'Email already exists', 409);
            }
            
            $user->update($data);
            
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
            if ($requestUser->role !== 'admin' && (int)$id !== $requestUser->id) {
                return ResponseHelper::error($response, 'Unauthorized: You can only delete your own profile', 403);
            }
            
            $user->delete();
            
            return ResponseHelper::success($response, 'User deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete user', 500, $e->getMessage());
        }
    }
}
