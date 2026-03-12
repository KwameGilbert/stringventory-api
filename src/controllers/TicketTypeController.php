<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TicketType;
use App\Models\Event;
use App\Models\Organizer;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * TicketTypeController
 * Handles ticket type management
 */
class TicketTypeController
{
    /**
     * Get all ticket types for an event
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $eventId = $queryParams['event_id'] ?? null;

            if (!$eventId) {
                return ResponseHelper::error($response, 'Event ID is required', 400);
            }

            $ticketTypes = TicketType::where('event_id', $eventId)->get();

            return ResponseHelper::success($response, 'Ticket types fetched successfully', [
                'ticket_types' => $ticketTypes,
                'count' => $ticketTypes->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch ticket types', 500, $e->getMessage());
        }
    }

    /**
     * Get single ticket type
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $ticketType = TicketType::find($id);

            if (!$ticketType) {
                return ResponseHelper::error($response, 'Ticket type not found', 404);
            }

            return ResponseHelper::success($response, 'Ticket type fetched successfully', $ticketType->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch ticket type', 500, $e->getMessage());
        }
    }

    /**
     * Create new ticket type
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');
            $uploadedFiles = $request->getUploadedFiles();

            // Validate required fields (organizer_id is now optional - we'll get it from user or event)
            $requiredFields = ['event_id', 'name', 'price', 'quantity'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    return ResponseHelper::error($response, "Field '$field' is required", 400);
                }
            }

            // Verify event exists and get organizer_id from it
            $event = Event::find($data['event_id']);
            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Set organizer_id from authenticated user's organizer profile or from the event
            if (!isset($data['organizer_id']) || empty($data['organizer_id'])) {
                // First try to get from the event
                if ($event->organizer_id) {
                    $data['organizer_id'] = $event->organizer_id;
                } else {
                    // Fallback: get from authenticated user's organizer profile
                    $organizer = Organizer::where('user_id', $user->id)->first();
                    if ($organizer) {
                        $data['organizer_id'] = $organizer->id;
                    } else {
                        return ResponseHelper::error($response, 'Organizer profile not found', 400);
                    }
                }
            }

            // Authorization: Verify user owns the event they're creating tickets for
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $event->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this event', 403);
                }
            }

            // Set initial remaining quantity equal to total quantity
            $data['remaining'] = $data['quantity'];

            // Set default status
            if (!isset($data['status'])) {
                $data['status'] = TicketType::STATUS_ACTIVE;
            }

            // Handle ticket image upload
            if (isset($uploadedFiles['ticket_image'])) {
                $ticketImage = $uploadedFiles['ticket_image'];

                if ($ticketImage->getError() === UPLOAD_ERR_OK) {
                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $mimeType = $ticketImage->getClientMediaType();

                    if (!in_array($mimeType, $allowedTypes)) {
                        return ResponseHelper::error($response, 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP', 400);
                    }

                    // Validate file size (max 2MB)
                    if ($ticketImage->getSize() > 2 * 1024 * 1024) {
                        return ResponseHelper::error($response, 'Image size must be less than 2MB', 400);
                    }

                    // Create upload directory if it doesn't exist
                    $uploadDir = dirname(__DIR__, 2) . '/public/uploads/tickets';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Generate unique filename
                    $extension = pathinfo($ticketImage->getClientFilename(), PATHINFO_EXTENSION);
                    $filename = 'ticket_' . uniqid() . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . '/' . $filename;

                    // Move uploaded file
                    $ticketImage->moveTo($filepath);

                    // Store relative path in database
                    $data['ticket_image'] = rtrim($_ENV['APP_URL'] ?? 'http://app.eventic.com', '/') . '/uploads/tickets/' . $filename;
                }
            }

            $ticketType = TicketType::create($data);

            return ResponseHelper::success($response, 'Ticket type created successfully', $ticketType->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create ticket type', 500, $e->getMessage());
        }
    }


    /**
     * Update ticket type
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            $ticketType = TicketType::find($id);

            if (!$ticketType) {
                return ResponseHelper::error($response, 'Ticket type not found', 404);
            }

            // Authorization: Check if user is admin or the event organizer
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $ticketType->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this ticket type', 403);
                }
            }

            // If quantity is updated, adjust remaining accordingly
            if (isset($data['quantity'])) {
                $diff = $data['quantity'] - $ticketType->quantity;
                $data['remaining'] = $ticketType->remaining + $diff;

                if ($data['remaining'] < 0) {
                    return ResponseHelper::error($response, 'Cannot reduce quantity below sold amount', 400);
                }
            }

            // Handle ticket image upload
            if (isset($uploadedFiles['ticket_image'])) {
                $ticketImage = $uploadedFiles['ticket_image'];

                if ($ticketImage->getError() === UPLOAD_ERR_OK) {
                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $mimeType = $ticketImage->getClientMediaType();

                    if (!in_array($mimeType, $allowedTypes)) {
                        return ResponseHelper::error($response, 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP', 400);
                    }

                    // Validate file size (max 2MB)
                    if ($ticketImage->getSize() > 2 * 1024 * 1024) {
                        return ResponseHelper::error($response, 'Image size must be less than 2MB', 400);
                    }

                    // Create upload directory if it doesn't exist
                    $uploadDir = dirname(__DIR__, 2) . '/public/uploads/tickets';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Delete old image if exists
                    if ($ticketType->ticket_image && file_exists(dirname(__DIR__, 2) . '/public' . $ticketType->ticket_image)) {
                        unlink(dirname(__DIR__, 2) . '/public' . $ticketType->ticket_image);
                    }

                    // Generate unique filename
                    $extension = pathinfo($ticketImage->getClientFilename(), PATHINFO_EXTENSION);
                    $filename = 'ticket_' . uniqid() . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . '/' . $filename;

                    // Move uploaded file
                    $ticketImage->moveTo($filepath);

                    // Store relative path in database
                    $data['ticket_image'] = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/') . '/uploads/tickets/' . $filename;
                }
            }

            $ticketType->update($data);

            return ResponseHelper::success($response, 'Ticket type updated successfully', $ticketType->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update ticket type', 500, $e->getMessage());
        }
    }

    /**
     * Delete ticket type
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $ticketType = TicketType::find($id);

            if (!$ticketType) {
                return ResponseHelper::error($response, 'Ticket type not found', 404);
            }

            // Authorization: Check if user is admin or the event organizer
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $ticketType->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this ticket type', 403);
                }
            }

            // Check if any tickets have been sold (logic depends on TicketOrder model, skipping for now or assuming 0 sold allows delete)
            if ($ticketType->quantity != $ticketType->remaining) {
                return ResponseHelper::error($response, 'Cannot delete ticket type with sold tickets. Deactivate it instead.', 400);
            }

            $ticketType->delete();

            return ResponseHelper::success($response, 'Ticket type deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete ticket type', 500, $e->getMessage());
        }
    }
}
