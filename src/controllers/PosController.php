<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\PosAssignment;
use App\Models\Event;
use App\Models\Organizer;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * PosController
 * Handles POS account management
 */
class PosController
{
    /**
     * Create a new POS account
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $organizerUser = $request->getAttribute('user');
            
            // Verify logged in user is an organizer
            $organizer = Organizer::findByUserId($organizerUser->id);
            if (!$organizer) {
                return ResponseHelper::error($response, 'Only organizers can create POS accounts', 403);
            }

            // Validate required fields
            if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
                return ResponseHelper::error($response, 'Name, email, and password are required', 400);
            }

            // Check if email exists
            if (User::where('email', $data['email'])->exists()) {
                return ResponseHelper::error($response, 'Email already exists', 409);
            }

            // Create User with role 'pos'
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_ARGON2ID),
                'role' => 'pos',
                'status' => 'active'
            ]);

            return ResponseHelper::success($response, 'POS account created successfully', $user->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create POS account', 500, $e->getMessage());
        }
    }

    /**
     * Assign POS to event(s)
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

            if (empty($data['pos_user_id']) || empty($data['event_ids'])) {
                return ResponseHelper::error($response, 'POS User ID and Event IDs are required', 400);
            }

            $posUser = User::find($data['pos_user_id']);
            if (!$posUser || $posUser->role !== 'pos') {
                return ResponseHelper::error($response, 'Invalid POS user', 400);
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
                $exists = PosAssignment::where('user_id', $posUser->id)
                                           ->where('event_id', $eventId)
                                           ->exists();
                
                if (!$exists) {
                    $assignment = PosAssignment::create([
                        'user_id' => $posUser->id,
                        'event_id' => $eventId,
                        'organizer_id' => $organizer->id
                    ]);
                    $assignments[] = $assignment;
                }
            }

            return ResponseHelper::success($response, 'POS assigned to events successfully', $assignments);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to assign POS', 500, $e->getMessage());
        }
    }

    /**
     * Delete POS account
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

            $posUser = User::find($id);
            if (!$posUser) {
                return ResponseHelper::error($response, 'POS user not found', 404);
            }

            if ($posUser->role !== 'pos') {
                return ResponseHelper::error($response, 'User is not a POS account', 400);
            }

            // Authorization: Check if this POS user is assigned to any of the organizer's events
            // This acts as a proxy for ownership since we don't have created_by
            $isAssigned = PosAssignment::where('user_id', $posUser->id)
                                       ->where('organizer_id', $organizer->id)
                                       ->exists();
            
            if (!$isAssigned) {
                 return ResponseHelper::error($response, 'Unauthorized: POS user is not assigned to your organization', 403);
            }
            
            $posUser->delete();

            return ResponseHelper::success($response, 'POS account deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete POS account', 500, $e->getMessage());
        }
    }
}
