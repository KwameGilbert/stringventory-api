<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Event;
use App\Models\EventType;
use App\Models\EventImage;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\OrderItem;
use App\Models\Organizer;
use App\Services\UploadService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * EventController
 * Handles event-related operations using Eloquent ORM
 */
class EventController
{
    private UploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
    }

    /**
     * Get all events (with optional filtering)
     * GET /v1/events
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $query = Event::with(['ticketTypes', 'eventType', 'organizer.user', 'images']);

            // Filter by status (default to published for public list)
            if (isset($queryParams['status'])) {
                $query->where('status', $queryParams['status']);
            } else {
                // Default to published and completed events for public endpoint
                $query->whereIn('status', [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED]);
            }

            // Filter by event type
            if (isset($queryParams['event_type_id'])) {
                $query->where('event_type_id', $queryParams['event_type_id']);
            }

            // Filter by organizer
            if (isset($queryParams['organizer_id'])) {
                $query->where('organizer_id', $queryParams['organizer_id']);
            }

            // Filter by category slug
            if (isset($queryParams['category'])) {
                $query->whereHas('eventType', function ($q) use ($queryParams) {
                    $q->where('slug', $queryParams['category']);
                });
            }

            // Filter upcoming only
            if (isset($queryParams['upcoming']) && $queryParams['upcoming'] === 'true') {
                $query->where('start_time', '>', \Illuminate\Support\Carbon::now());
            }

            // Search by title, description, or venue
            if (isset($queryParams['search']) && !empty($queryParams['search'])) {
                $search = $queryParams['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('venue_name', 'LIKE', "%{$search}%");
                });
            }

            // Location filter - search across address, city, region, and country
            if (isset($queryParams['location']) && !empty($queryParams['location'])) {
                $location = $queryParams['location'];
                $query->where(function ($q) use ($location) {
                    $q->where('address', 'LIKE', "%{$location}%")
                        ->orWhere('city', 'LIKE', "%{$location}%")
                        ->orWhere('region', 'LIKE', "%{$location}%")
                        ->orWhere('country', 'LIKE', "%{$location}%");
                });
            }

            // Pagination
            $page = (int) ($queryParams['page'] ?? 1);
            $perPage = (int) ($queryParams['per_page'] ?? 20);
            $offset = ($page - 1) * $perPage;

            $totalCount = $query->count();
            $events = $query->orderBy('start_time', 'asc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Format events for frontend compatibility
            $formattedEvents = $events->map(function ($event) {
                return $event->getFullDetails();
            });

            return ResponseHelper::success($response, 'Events fetched successfully', [
                'events' => $formattedEvents->toArray(),
                'count' => $events->count(),
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch events', 500, $e->getMessage());
        }
    }

    /**
     * Get featured events for homepage carousel
     * GET /v1/events/featured
     */
    public function featured(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $limit = (int) ($queryParams['limit'] ?? 5);

            // First try to get featured events
            $events = Event::with(['ticketTypes', 'eventType', 'organizer.user'])
                ->where('status', Event::STATUS_PUBLISHED)
                ->where('is_featured', true)
                ->where('start_time', '>', \Illuminate\Support\Carbon::now())
                ->orderBy('start_time', 'asc')
                ->limit($limit)
                ->get();

            // If no featured events, fallback to upcoming events
            if ($events->isEmpty()) {
                $events = Event::with(['ticketTypes', 'eventType', 'organizer.user'])
                    ->where('status', Event::STATUS_PUBLISHED)
                    ->where('start_time', '>', \Illuminate\Support\Carbon::now())
                    ->orderBy('start_time', 'asc')
                    ->limit($limit)
                    ->get();
            }

            // Format for frontend carousel
            $formattedEvents = $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'eventSlug' => $event->slug,
                    'venue' => $event->venue_name . ($event->address ? ', ' . $event->address : ''),
                    'date' => $event->start_time ? $event->start_time->format('D d M Y, g:i A') : null,
                    'image' => $event->banner_image,
                    'category' => $event->eventType ? $event->eventType->name : null,
                ];
            });

            return ResponseHelper::success($response, 'Featured events fetched successfully', $formattedEvents->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch featured events', 500, $e->getMessage());
        }
    }

    /**
     * Get single event by ID or slug
     * GET /v1/events/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['id'];

            // Try to find by ID first, then by slug
            if (is_numeric($identifier)) {
                $event = Event::with(['organizer.user', 'ticketTypes', 'images', 'eventType'])->find($identifier);
            } else {
                $event = Event::with(['organizer.user', 'ticketTypes', 'images', 'eventType'])
                    ->where('slug', $identifier)
                    ->first();
            }

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            return ResponseHelper::success($response, 'Event fetched successfully', $event->getFullDetails());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch event', 500, $e->getMessage());
        }
    }

    /**
     * Increment event views
     * POST /v1/events/{id}/view
     */
    public function incrementViews(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['id'];

            // Try to find by ID first, then by slug
            if (is_numeric($identifier)) {
                $event = Event::find($identifier);
            } else {
                $event = Event::where('slug', $identifier)->first();
            }

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Increment views
            $event->increment('views');

            return ResponseHelper::success($response, 'View recorded successfully', [
                'views' => $event->views
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to record view', 500, $e->getMessage());
        }
    }

    /**
     * Create new event
     * POST /v1/events
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');
            $uploadedFiles = $request->getUploadedFiles();

            // Get organizer for the user
            $organizer = Organizer::where('user_id', $user->id)->first();
            if (!$organizer && $user->role !== 'admin') {
                return ResponseHelper::error($response, 'Only organizers can create events', 403);
            }

            // Set organizer_id from authenticated user's organizer profile
            if ($organizer) {
                $data['organizer_id'] = $organizer->id;
            }

            // Validate required fields
            $requiredFields = ['title', 'start_time', 'end_time'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ResponseHelper::error($response, "Field '$field' is required", 400);
                }
            }

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
            }

            // Set default status if not provided
            if (!isset($data['status'])) {
                $data['status'] = Event::STATUS_DRAFT;
            }

            // Validate status value
            $validStatuses = [Event::STATUS_DRAFT, Event::STATUS_PENDING, Event::STATUS_PUBLISHED, Event::STATUS_CANCELLED, Event::STATUS_COMPLETED];
            if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
                return ResponseHelper::error($response, "Invalid status value. Allowed values: draft, pending, published, cancelled, completed", 400);
            }

            // Permission check: Organizers can only set status to draft or pending
            if ($user->role !== 'admin' && isset($data['status'])) {
                $allowedOrganizerStatuses = [Event::STATUS_DRAFT, Event::STATUS_PENDING];
                if (!in_array($data['status'], $allowedOrganizerStatuses)) {
                    return ResponseHelper::error($response, "Organizers can only set status to 'draft' or 'pending'. Admins must approve and publish events.", 403);
                }
            }

            // Permission check: Only admins can mark events as featured
            if (isset($data['is_featured']) && $data['is_featured'] && $user->role !== 'admin') {
                return ResponseHelper::error($response, "Only admins can mark events as featured", 403);
            }
            // Force is_featured to false for non-admins
            if ($user->role !== 'admin') {
                $data['is_featured'] = false;
            }

            // Set default location values if not provided
            if (!isset($data['country'])) {
                $data['country'] = 'Ghana';
            }
            if (!isset($data['region'])) {
                $data['region'] = 'Greater Accra';
            }
            if (!isset($data['city'])) {
                $data['city'] = 'Accra';
            }

            // Validate tags - handle JSON string or array
            if (isset($data['tags'])) {
                if (is_string($data['tags'])) {
                    $data['tags'] = json_decode($data['tags'], true) ?? [];
                }
                if (!is_array($data['tags'])) {
                    return ResponseHelper::error($response, 'Tags must be an array', 400);
                }
            }

            // Handle banner image upload using UploadService
            if (isset($uploadedFiles['banner_image'])) {
                $bannerImage = $uploadedFiles['banner_image'];
                if ($bannerImage->getError() === UPLOAD_ERR_OK) {
                    try {
                        $data['banner_image'] = $this->uploadService->uploadFile($bannerImage, 'banner', 'events');
                    } catch (Exception $e) {
                        return ResponseHelper::error($response, $e->getMessage(), 400);
                    }
                }
            }

            $event = Event::create($data);

            // Handle event photos upload (multiple) using UploadService
            if (isset($uploadedFiles['event_photos']) && is_array($uploadedFiles['event_photos'])) {
                foreach ($uploadedFiles['event_photos'] as $photo) {
                    if ($photo->getError() === UPLOAD_ERR_OK) {
                        try {
                            $imagePath = $this->uploadService->uploadFile($photo, 'image', 'events');
                            EventImage::create([
                                'event_id' => $event->id,
                                'image_path' => $imagePath,
                            ]);
                        } catch (Exception $e) {
                            // Log error but continue with other files
                            error_log("Failed to upload event photo: " . $e->getMessage());
                        }
                    }
                }
            }

            // Handle tickets creation
            if (isset($data['tickets'])) {
                $tickets = is_string($data['tickets']) ? json_decode($data['tickets'], true) : $data['tickets'];
                if (is_array($tickets)) {
                    foreach ($tickets as $index => $ticketData) {
                        if (!empty($ticketData['name']) && isset($ticketData['quantity'])) {
                            $ticketImagePath = null;

                            // Handle ticket image upload for this specific ticket using UploadService
                            if (isset($uploadedFiles["ticket_image_{$index}"])) {
                                $ticketImage = $uploadedFiles["ticket_image_{$index}"];
                                if ($ticketImage->getError() === UPLOAD_ERR_OK) {
                                    try {
                                        $ticketImagePath = $this->uploadService->uploadFile($ticketImage, 'ticket');
                                    } catch (Exception $e) {
                                        // Log error but continue
                                        error_log("Failed to upload ticket image: " . $e->getMessage());
                                    }
                                }
                            }

                            TicketType::create([
                                'event_id' => $event->id,
                                'organizer_id' => $event->organizer_id,
                                'name' => $ticketData['name'],
                                'price' => $ticketData['price'] ?? 0,
                                'sale_price' => $ticketData['promoPrice'] ?? 0,
                                'quantity' => $ticketData['quantity'],
                                'remaining' => $ticketData['quantity'],
                                'description' => $ticketData['description'] ?? null,
                                'max_per_user' => $ticketData['maxPerOrder'] ?? 10,
                                'sale_start' => !empty($ticketData['saleStartDate']) ? $ticketData['saleStartDate'] : null,
                                'sale_end' => !empty($ticketData['saleEndDate']) ? $ticketData['saleEndDate'] : null,
                                'ticket_image' => $ticketImagePath,
                                'status' => 'active'
                            ]);
                        }
                    }
                }
            }

            return ResponseHelper::success($response, 'Event created successfully', $event->getFullDetails(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create event', 500, $e->getMessage());
        }
    }

    /**
     * Update event
     * PUT /v1/events/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            $event = Event::find($id);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Authorization: Check if user is admin or the event organizer
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $event->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this event', 403);
                }
            }

            // Update slug if title changes and slug isn't manually provided
            if (isset($data['title']) && !isset($data['slug'])) {
                $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
            }

            // Validate status value if provided
            if (isset($data['status'])) {
                $validStatuses = [Event::STATUS_DRAFT, Event::STATUS_PENDING, Event::STATUS_PUBLISHED, Event::STATUS_CANCELLED, Event::STATUS_COMPLETED];
                if (!in_array($data['status'], $validStatuses)) {
                    return ResponseHelper::error($response, "Invalid status value. Allowed values: draft, pending, published, cancelled, completed", 400);
                }

                // Permission check: Organizers can only set status to draft or pending
                // They can also move from pending back to draft
                if ($user->role !== 'admin') {
                    $allowedOrganizerStatuses = [Event::STATUS_DRAFT, Event::STATUS_PENDING];
                    if (!in_array($data['status'], $allowedOrganizerStatuses)) {
                        return ResponseHelper::error($response, "Organizers can only set status to 'draft' or 'pending'. Admins must approve and publish events.", 403);
                    }
                }
            }

            // Permission check: Only admins can mark events as featured
            if (isset($data['is_featured']) && $data['is_featured'] && $user->role !== 'admin') {
                return ResponseHelper::error($response, "Only admins can mark events as featured", 403);
            }
            // Prevent non-admins from changing is_featured value
            if ($user->role !== 'admin' && isset($data['is_featured'])) {
                unset($data['is_featured']); // Remove from update data
            }

            // Validate event_format value if provided
            if (isset($data['event_format'])) {
                $validFormats = ['ticketing', 'awards'];
                if (!in_array($data['event_format'], $validFormats)) {
                    return ResponseHelper::error($response, "Invalid event_format value. Allowed values: ticketing, awards", 400);
                }
            }

            // Validate tags - handle JSON string or array
            if (isset($data['tags'])) {
                if (is_string($data['tags'])) {
                    $data['tags'] = json_decode($data['tags'], true) ?? [];
                }
                if (!is_array($data['tags'])) {
                    return ResponseHelper::error($response, 'Tags must be an array', 400);
                }
            }

            // Handle banner image upload using UploadService
            if (isset($uploadedFiles['banner_image'])) {
                $bannerImage = $uploadedFiles['banner_image'];
                if ($bannerImage->getError() === UPLOAD_ERR_OK) {
                    try {
                        $data['banner_image'] = $this->uploadService->replaceFile(
                            $bannerImage,
                            $event->banner_image,
                            'banner',
                            'events'
                        );
                    } catch (Exception $e) {
                        return ResponseHelper::error($response, $e->getMessage(), 400);
                    }
                }
            }

            $event->update($data);

            // Handle event photos upload (multiple) using UploadService - these are added to existing photos
            if (isset($uploadedFiles['event_photos']) && is_array($uploadedFiles['event_photos'])) {
                foreach ($uploadedFiles['event_photos'] as $photo) {
                    if ($photo->getError() === UPLOAD_ERR_OK) {
                        try {
                            $imagePath = $this->uploadService->uploadFile($photo, 'image', 'events');
                            EventImage::create([
                                'event_id' => $event->id,
                                'image_path' => $imagePath,
                            ]);
                        } catch (Exception $e) {
                            // Log error but continue with other files
                            error_log("Failed to upload event photo: " . $e->getMessage());
                        }
                    }
                }
            }

            // Handle deleted tickets
            if (isset($data['deleted_tickets'])) {
                $deletedTickets = is_string($data['deleted_tickets']) ? json_decode($data['deleted_tickets'], true) : $data['deleted_tickets'];
                if (is_array($deletedTickets) && !empty($deletedTickets)) {
                    TicketType::whereIn('id', $deletedTickets)
                        ->where('event_id', $event->id)
                        ->delete();
                }
            }

            // Handle tickets (create/update)
            if (isset($data['tickets'])) {
                $tickets = is_string($data['tickets']) ? json_decode($data['tickets'], true) : $data['tickets'];
                if (is_array($tickets)) {
                    foreach ($tickets as $index => $ticketData) {
                        if (!empty($ticketData['name']) && isset($ticketData['quantity'])) {
                            if (isset($ticketData['id'])) {
                                // Update existing ticket
                                $ticket = TicketType::where('id', $ticketData['id'])
                                    ->where('event_id', $event->id)
                                    ->first();
                                if ($ticket) {
                                    $oldQuantity = $ticket->quantity;
                                    $sold = $oldQuantity - $ticket->remaining;
                                    $newRemaining = $ticketData['quantity'] - $sold;

                                    // Prevent negative remaining if sold > new quantity (edge case)
                                    if ($newRemaining < 0)
                                        $newRemaining = 0;

                                    // Handle ticket image upload for update using UploadService
                                    $ticketImagePath = $ticket->ticket_image; // Keep existing image by default
                                    
                                    if (isset($uploadedFiles["ticket_image_{$index}"])) {
                                        $ticketImage = $uploadedFiles["ticket_image_{$index}"];
                                        if ($ticketImage->getError() === UPLOAD_ERR_OK) {
                                            try {
                                                $ticketImagePath = $this->uploadService->replaceFile(
                                                    $ticketImage,
                                                    $ticket->ticket_image,
                                                    'ticket'
                                                );
                                            } catch (Exception $e) {
                                                // Log error but continue
                                                error_log("Failed to upload ticket image: " . $e->getMessage());
                                            }
                                        }
                                    }

                                    $ticket->update([
                                        'name' => $ticketData['name'],
                                        'price' => $ticketData['price'] ?? 0,
                                        'sale_price' => $ticketData['promoPrice'] ?? 0,
                                        'quantity' => $ticketData['quantity'],
                                        'remaining' => $newRemaining,
                                        'description' => $ticketData['description'] ?? null,
                                        'max_per_user' => $ticketData['maxPerOrder'] ?? 10,
                                        'sale_start' => !empty($ticketData['saleStartDate']) ? $ticketData['saleStartDate'] : null,
                                        'sale_end' => !empty($ticketData['saleEndDate']) ? $ticketData['saleEndDate'] : null,
                                        'ticket_image' => $ticketImagePath,
                                    ]);
                                }
                                // Create new ticket - handle image upload using UploadService
                                $ticketImagePath = null;

                                if (isset($uploadedFiles["ticket_image_{$index}"])) {
                                    $ticketImage = $uploadedFiles["ticket_image_{$index}"];
                                    if ($ticketImage->getError() === UPLOAD_ERR_OK) {
                                        try {
                                            $ticketImagePath = $this->uploadService->uploadFile($ticketImage, 'ticket');
                                        } catch (Exception $e) {
                                            // Log error but continue
                                            error_log("Failed to upload ticket image: " . $e->getMessage());
                                        }
                                    }
                                }

                                TicketType::create([
                                    'event_id' => $event->id,
                                    'organizer_id' => $event->organizer_id,
                                    'name' => $ticketData['name'],
                                    'price' => $ticketData['price'] ?? 0,
                                    'sale_price' => $ticketData['promoPrice'] ?? 0,
                                    'quantity' => $ticketData['quantity'],
                                    'remaining' => $ticketData['quantity'],
                                    'description' => $ticketData['description'] ?? null,
                                    'max_per_user' => $ticketData['maxPerOrder'] ?? 10,
                                    'sale_start' => !empty($ticketData['saleStartDate']) ? $ticketData['saleStartDate'] : null,
                                    'sale_end' => !empty($ticketData['saleEndDate']) ? $ticketData['saleEndDate'] : null,
                                    'ticket_image' => $ticketImagePath,
                                    'status' => 'active'
                                ]);
                            }
                        }
                    }
                }
            }

            return ResponseHelper::success($response, 'Event updated successfully', $event->getFullDetails());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update event', 500, $e->getMessage());
        }
    }

    /**
     * Delete event
     * DELETE /v1/events/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $event = Event::find($id);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Authorization: Check if user is admin or the event organizer
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $event->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this event', 403);
                }
            }

            // Validation: Check if event has tickets sold
            if (Ticket::where('event_id', $id)->exists()) {
                return ResponseHelper::error($response, 'Cannot delete event with existing tickets', 400);
            }

            // Validation: Check if event has any order items (even if no tickets generated yet)
            if (OrderItem::where('event_id', $id)->exists()) {
                return ResponseHelper::error($response, 'Cannot delete event with associated orders', 400);
            }

            $event->delete();

            return ResponseHelper::success($response, 'Event deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete event', 500, $e->getMessage());
        }
    }

    /**
     * Search events
     * GET /v1/events/search
     */
    public function search(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $query = $queryParams['query'] ?? '';

            if (empty($query)) {
                return ResponseHelper::error($response, 'Search query is required', 400);
            }

            $events = Event::with(['ticketTypes', 'eventType', 'organizer.user'])
                ->whereIn('status', [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED])
                ->where(function ($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%")
                        ->orWhere('venue_name', 'LIKE', "%{$query}%");
                })
                ->get();

            $formattedEvents = $events->map(function ($event) {
                return $event->getFullDetails();
            });

            return ResponseHelper::success($response, 'Events found', [
                'events' => $formattedEvents->toArray(),
                'count' => $events->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to search events', 500, $e->getMessage());
        }
    }

    /**
     * Get all event types (categories)
     * GET /v1/events/types
     */
    public function getEventTypes(Request $request, Response $response, array $args): Response
    {
        try {
            $eventTypes = EventType::all();

            return ResponseHelper::success($response, 'Event types fetched successfully', [
                'event_types' => $eventTypes->toArray(),
                'count' => $eventTypes->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch event types', 500, $e->getMessage());
        }
    }

    /**
     * Submit event for approval (draft -> pending)
     * PUT /v1/events/{id}/submit-for-approval
     */
    public function submitForApproval(Request $request, Response $response, array $args): Response
    {
        try {
            $eventId = (int) $args['id'];
            $user = $request->getAttribute('user');

            // Find the event
            $event = Event::find($eventId);
            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Verify organizer ownership
            if ($user->role !== 'organizer' && $user->role !== 'admin') {
                return ResponseHelper::error($response, 'Only organizers can submit events for approval', 403);
            }

            $organizer = Organizer::where('user_id', $user->id)->first();
            if ($user->role === 'organizer' && (!$organizer || $event->organizer_id !== $organizer->id)) {
                return ResponseHelper::error($response, 'You do not have permission to submit this event', 403);
            }

            // Check if event is in draft status
            if ($event->status !== Event::STATUS_DRAFT) {
                return ResponseHelper::error(
                    $response,
                    'Only draft events can be submitted for approval. Current status: ' . $event->status,
                    400
                );
            }

            // Validate that event has required data for submission
            if (empty($event->title) || empty($event->description)) {
                return ResponseHelper::error($response, 'Event must have a title and description before submission', 400);
            }

            if (empty($event->start_time) || empty($event->end_time)) {
                return ResponseHelper::error($response, 'Event must have start and end times before submission', 400);
            }

            $ticketsCount = $event->ticketTypes()->count();
            if ($ticketsCount === 0) {
                return ResponseHelper::error($response, 'Event must have at least one ticket type before submission', 400);
            }

            // Update status to pending
            $event->status = Event::STATUS_PENDING;
            $event->save();

            return ResponseHelper::success($response, 'Event submitted for admin approval successfully', [
                'event_id' => $event->id,
                'status' => $event->status,
                'message' => 'Your event has been submitted and is now pending admin approval'
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to submit event for approval', 500, $e->getMessage());
        }
    }

    /**
     * Convert image URL to base64 (bypasses CORS)
     * GET /v1/utils/image-to-base64
     */
    public function imageToBase64(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $imageUrl = $queryParams['url'] ?? null;

            if (empty($imageUrl)) {
                return ResponseHelper::error($response, 'Image URL is required', 400);
            }

            // Validate URL
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                return ResponseHelper::error($response, 'Invalid URL format', 400);
            }

            // Only allow URLs from our own domain for security
            $allowedDomains = ['app.eventic.com', 'eventic.com', 'localhost', '127.0.0.1'];
            $parsedUrl = parse_url($imageUrl);
            $host = $parsedUrl['host'] ?? '';
            
            $isAllowed = false;
            foreach ($allowedDomains as $domain) {
                if (strpos($host, $domain) !== false) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return ResponseHelper::error($response, 'Image URL must be from an allowed domain', 403);
            }

            // Fetch the image
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $imageUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Eventic/1.0');
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200 || empty($imageData)) {
                return ResponseHelper::error($response, 'Failed to fetch image', 500, $error ?: 'HTTP ' . $httpCode);
            }

            // Validate content type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $mimeType = explode(';', $contentType)[0];
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ResponseHelper::error($response, 'Invalid image type: ' . $mimeType, 400);
            }

            // Convert to base64
            $base64 = base64_encode($imageData);
            $dataUri = 'data:' . $mimeType . ';base64,' . $base64;

            return ResponseHelper::success($response, 'Image converted successfully', [
                'base64' => $dataUri,
                'mime_type' => $mimeType,
                'size' => strlen($imageData)
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to convert image', 500, $e->getMessage());
        }
    }
}
