<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EventImage;
use App\Models\Event;
use App\Services\UploadService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * EventImageController
 * Handles event image gallery operations
 */
class EventImageController
{
    private UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get all images for an event
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $eventId = $queryParams['event_id'] ?? null;

            if (!$eventId) {
                return ResponseHelper::error($response, 'Event ID is required', 400);
            }

            $images = EventImage::where('event_id', $eventId)->get();
            
            return ResponseHelper::success($response, 'Event images fetched successfully', [
                'images' => $images,
                'count' => $images->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch event images', 500, $e->getMessage());
        }
    }

    /**
     * Upload and add images to an event
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();
            
            // Validate required fields
            if (empty($data['event_id'])) {
                return ResponseHelper::error($response, 'Event ID is required', 400);
            }
            
            // Verify event exists
            if (!($event = Event::find($data['event_id']))) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Authorization: Check if user is admin or the event organizer
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin') {
                $organizer = \App\Models\Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $event->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this event', 403);
                }
            }

            // Handle file upload
            $uploadedImages = [];
            
            if (isset($uploadedFiles['images'])) {
                $images = $uploadedFiles['images'];
                
                // Handle single or multiple files
                if (!is_array($images)) {
                    $images = [$images];
                }

                foreach ($images as $imageFile) {
                    if ($imageFile->getError() === UPLOAD_ERR_OK) {
                        try {
                            // Upload image using UploadService
                            $imagePath = $this->uploadService->uploadFile($imageFile, 'image', 'events');
                            
                            // Save to database
                            $eventImage = EventImage::create([
                                'event_id' => $data['event_id'],
                                'image_path' => $imagePath
                            ]);
                            
                            $uploadedImages[] = $eventImage->toArray();
                        } catch (Exception $e) {
                            // Log error but continue with other uploads
                            error_log("Failed to upload image: " . $e->getMessage());
                        }
                    }
                }
            }

            if (empty($uploadedImages)) {
                return ResponseHelper::error($response, 'No valid images were uploaded', 400);
            }
            
            return ResponseHelper::success($response, 'Event images added successfully', [
                'images' => $uploadedImages,
                'count' => count($uploadedImages)
            ], 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to add event images', 500, $e->getMessage());
        }
    }

    /**
     * Delete an event image
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $image = EventImage::find($id);
            
            if (!$image) {
                return ResponseHelper::error($response, 'Image not found', 404);
            }

            // Authorization: Check if user is admin or the event organizer
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin') {
                $event = Event::find($image->event_id);
                $organizer = \App\Models\Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $event->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this event', 403);
                }
            }
            
            // Delete physical file using UploadService
            if ($image->image_path) {
                $this->uploadService->deleteFile($image->image_path);
            }
            
            // Delete database record
            $image->delete();
            
            return ResponseHelper::success($response, 'Event image deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete event image', 500, $e->getMessage());
        }
    }
}
