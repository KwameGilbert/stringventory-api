<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\ScannerAssignment;
use App\Models\Event;
use App\Models\Organizer;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * ScannerController
 * Handles scanner account management
 */
class ScannerController
{
    /**
     * Create a new scanner account
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $organizerUser = $request->getAttribute('user');
            
            // Verify logged in user is an organizer
            $organizer = Organizer::findByUserId($organizerUser->id);
            if (!$organizer) {
                return ResponseHelper::error($response, 'Only organizers can create scanner accounts', 403);
            }

            // Validate required fields
            if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
                return ResponseHelper::error($response, 'Name, email, and password are required', 400);
            }

            // Create User with role 'scanner'
            // Note: In a real app, you might want to use AuthService to hash password properly.
            // Here I'll assume simple creation or use password_hash
            
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_ARGON2ID),
                'role' => 'scanner',
                'status' => 'active'
            ]);

            return ResponseHelper::success($response, 'Scanner account created successfully', $user->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create scanner account', 500, $e->getMessage());
        }
    }

    /**
     * Assign scanner to event(s)
     */
    public function assign(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $organizerUser = $request->getAttribute('user');
            
            $organizer = Organizer::findByUserId($organizerUser->id);
            if (!$organizer) {
                return ResponseHelper::error($response, 'Unauthorized', 403);
            }

            if (empty($data['scanner_user_id']) || empty($data['event_ids'])) {
                return ResponseHelper::error($response, 'Scanner User ID and Event IDs are required', 400);
            }

            $scannerUser = User::find($data['scanner_user_id']);
            if (!$scannerUser || $scannerUser->role !== 'scanner') {
                return ResponseHelper::error($response, 'Invalid scanner user', 400);
            }

            $eventIds = is_array($data['event_ids']) ? $data['event_ids'] : [$data['event_ids']];
            $assignments = [];

            foreach ($eventIds as $eventId) {
                // Verify event belongs to organizer
                $event = Event::where('id', $eventId)->where('organizer_id', $organizer->id)->first();
                if (!$event) {
                    continue; // Skip events not owned by organizer
                }

                // Check if already assigned
                $exists = ScannerAssignment::where('user_id', $scannerUser->id)
                                           ->where('event_id', $eventId)
                                           ->exists();
                
                if (!$exists) {
                    $assignment = ScannerAssignment::create([
                        'user_id' => $scannerUser->id,
                        'event_id' => $eventId,
                        'organizer_id' => $organizer->id
                    ]);
                    $assignments[] = $assignment;
                }
            }

            return ResponseHelper::success($response, 'Scanner assigned to events successfully', $assignments);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to assign scanner', 500, $e->getMessage());
        }
    }
    /**
     * Delete scanner account
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $organizerUser = $request->getAttribute('user');
            
            // Verify organizer
            $organizer = Organizer::findByUserId($organizerUser->id);
            if (!$organizer) {
                return ResponseHelper::error($response, 'Unauthorized', 403);
            }

            $scannerUser = User::find($id);
            if (!$scannerUser) {
                return ResponseHelper::error($response, 'Scanner not found', 404);
            }

            if ($scannerUser->role !== 'scanner') {
                return ResponseHelper::error($response, 'User is not a scanner', 400);
            }

            // Authorization: Check if this scanner is assigned to any of the organizer's events
            $isAssigned = ScannerAssignment::where('user_id', $scannerUser->id)
                                           ->where('organizer_id', $organizer->id)
                                           ->exists();
            
            if (!$isAssigned) {
                 return ResponseHelper::error($response, 'Unauthorized: Scanner is not assigned to your organization', 403);
            }

            
            $scannerUser->delete();

            return ResponseHelper::success($response, 'Scanner account deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete scanner', 500, $e->getMessage());
        }
    }
}
