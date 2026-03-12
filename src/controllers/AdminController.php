<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\Organizer;
use App\Models\Event;
use App\Models\Award;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\AwardVote;
use App\Models\Ticket;
use App\Models\PayoutRequest;
use App\Models\OrganizerBalance;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Support\Carbon;
use Exception;

/**
 * AdminController
 * Handles administrator dashboard and management operations
 */
class AdminController
{
    /**
     * Get admin dashboard overview
     * GET /v1/admin/dashboard
     */
    public function getDashboard(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            // Verify admin role
            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            // === PLATFORM STATISTICS ===
            
            // Users
            $totalUsers = User::count();
            $organizers = Organizer::count();
            $attendees = User::where('role', 'attendee')->count();
            $newUsersThisMonth = User::where('created_at', '>=', Carbon::now()->startOfMonth())->count();

            // Events
            $totalEvents = Event::count();
            $eventsByStatus = [
                'published' => Event::where('status', 'published')->count(),
                'draft' => Event::where('status', 'draft')->count(),
                'pending' => Event::where('status', 'pending')->count(),
                'cancelled' => Event::where('status', 'cancelled')->count(),
                'completed' => Event::where('status', 'completed')->count(),
            ];

            // Awards
            $totalAwards = Award::count();
            $awardsByStatus = [
                'published' => Award::where('status', 'published')->count(),
                'draft' => Award::where('status', 'draft')->count(),
                'pending' => Award::where('status', 'pending')->count(),
                'completed' => Award::where('status', 'completed')->count(),
                'closed' => Award::where('status', 'closed')->count(),
            ];

            // Revenue
            $totalTicketRevenue = (float) OrderItem::whereHas('order', function ($query) {
                $query->where('status', 'paid');
            })->sum('total_price');
            $totalVoteRevenue = (float) AwardVote::where('status', 'paid')
                ->with('category')
                ->get()
                ->sum(function ($vote) {
                    return $vote->number_of_votes * ($vote->category->cost_per_vote ?? 5);
                });
            $totalRevenue = $totalTicketRevenue + $totalVoteRevenue;
            $platformFees = ($totalTicketRevenue * 0.015) + ($totalVoteRevenue * 0.05);

            // Orders & Sales
            $totalOrders = Order::where('status', 'paid')->count();
            $totalTicketsSold = Ticket::count();
            $totalVotesCast = AwardVote::where('status', 'paid')->sum('number_of_votes');

            // Recent Activity
            $recentOrders = Order::with(['user', 'items.event'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'user_name' => $order->user->name ?? 'N/A',
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                        'created_at' => $order->created_at->toIso8601String(),
                        'items_count' => $order->items->count(),
                    ];
                });

            $recentVotes = AwardVote::with(['category', 'nominee'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($vote) {
                    return [
                        'id' => $vote->id,
                        'reference' => $vote->reference,
                        'voter_email' => $vote->voter_email,
                        'nominee_name' => $vote->nominee->name ?? 'N/A',
                        'category_name' => $vote->category->name ?? 'N/A',
                        'number_of_votes' => $vote->number_of_votes,
                        'status' => $vote->status,
                        'created_at' => $vote->created_at->toIso8601String(),
                    ];
                });

            // Pending Approvals
            $pendingEvents = Event::where('status', 'pending')
                ->with('organizer.user')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'slug' => $event->slug,
                        'organizer_name' => $event->organizer->user->name ?? 'N/A',
                        'start_time' => $event->start_time ? $event->start_time->toIso8601String() : null,
                        'created_at' => $event->created_at->toIso8601String(),
                    ];
                });

            $pendingAwards = Award::where('status', 'pending')
                ->with('organizer.user')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($award) {
                    return [
                        'id' => $award->id,
                        'title' => $award->title,
                        'slug' => $award->slug,
                        'organizer_name' => $award->organizer->user->name ?? 'N/A',
                        'ceremony_date' => $award->ceremony_date ? $award->ceremony_date->toIso8601String() : null,
                        'created_at' => $award->created_at->toIso8601String(),
                    ];
                });

            // Monthly Growth Trends (Last 12 months)
            $monthlyTrends = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();

                $monthlyTrends[] = [
                    'month' => $month->format('M Y'),
                    'users_registered' => User::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                    'events_created' => Event::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                    'awards_created' => Award::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                    'tickets_sold' => Ticket::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                    'votes_cast' => AwardVote::whereBetween('created_at', [$monthStart, $monthEnd])
                        ->where('status', 'paid')
                        ->sum('number_of_votes'),
                ];
            }

            // Top Performers
            $topEvents = Event::withCount(['tickets as tickets_sold'])
                ->orderBy('tickets_sold', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($event) {
                    $revenue = (float) OrderItem::where('event_id', $event->id)
                        ->whereHas('order', function ($query) {
                            $query->where('status', 'paid');
                        })
                        ->sum('total_price');
                    
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'slug' => $event->slug,
                        'tickets_sold' => $event->tickets_sold,
                        'revenue' => round($revenue, 2),
                    ];
                });

            $topAwards = Award::withCount(['votes as total_votes'])
                ->orderBy('total_votes', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($award) {
                    $votes = AwardVote::where('award_id', $award->id)
                        ->where('status', 'paid')
                        ->with('category')
                        ->get();
                    
                    $revenue = (float) $votes->sum(function ($vote) {
                        return $vote->number_of_votes * ($vote->category->cost_per_vote ?? 5);
                    });
                    
                    return [
                        'id' => $award->id,
                        'title' => $award->title,
                        'slug' => $award->slug,
                        'votes' => $votes->sum('number_of_votes'),
                        'revenue' => round($revenue, 2),
                    ];
                });

            $topOrganizers = Organizer::withCount(['events', 'awards'])
                ->with('user')
                ->orderBy('events_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($organizer) {
                    return [
                        'id' => $organizer->id,
                        'name' => $organizer->user->name ?? 'N/A',
                        'email' => $organizer->user->email ?? 'N/A',
                        'events_count' => $organizer->events_count,
                        'awards_count' => $organizer->awards_count,
                    ];
                });

            $data = [
                'platform_stats' => [
                    'total_users' => $totalUsers,
                    'organizers' => $organizers,
                    'attendees' => $attendees,
                    'new_users_this_month' => $newUsersThisMonth,
                    'total_events' => $totalEvents,
                    'total_awards' => $totalAwards,
                    'total_orders' => $totalOrders,
                    'total_tickets_sold' => $totalTicketsSold,
                    'total_votes_cast' => $totalVotesCast,
                ],
                'revenue_stats' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'ticket_revenue' => round($totalTicketRevenue, 2),
                    'vote_revenue' => round($totalVoteRevenue, 2),
                    'platform_fees' => round($platformFees, 2),
                    'revenue_breakdown' => [
                        'events_percentage' => $totalRevenue > 0 ? round(($totalTicketRevenue / $totalRevenue) * 100, 1) : 0,
                        'awards_percentage' => $totalRevenue > 0 ? round(($totalVoteRevenue / $totalRevenue) * 100, 1) : 0,
                    ],
                ],
                'status_breakdown' => [
                    'events' => $eventsByStatus,
                    'awards' => $awardsByStatus,
                ],
                'pending_approvals' => [
                    'events' => $pendingEvents,
                    'awards' => $pendingAwards,
                    'total_pending' => $eventsByStatus['pending'] + $awardsByStatus['pending'],
                ],
                'recent_activity' => [
                    'orders' => $recentOrders,
                    'votes' => $recentVotes,
                ],
                'monthly_trends' => $monthlyTrends,
                'top_performers' => [
                    'events' => $topEvents,
                    'awards' => $topAwards,
                    'organizers' => $topOrganizers,
                ],
            ];

            return ResponseHelper::success($response, 'Admin dashboard data fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch admin dashboard data', 500, $e->getMessage());
        }
    }

    /**
     * Get all users with filters
     * GET /v1/admin/users
     */
    public function getUsers(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $queryParams = $request->getQueryParams();
            $role = $queryParams['role'] ?? null;
            $search = $queryParams['search'] ?? null;

            $query = User::query();

            if ($role) {
                $query->where('role', $role);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('created_at', 'desc')->get();

            return ResponseHelper::success($response, 'Users fetched successfully', ['users' => $users]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch users', 500, $e->getMessage());
        }
    }

    /**
     * Approve pending event
     * PUT /v1/admin/events/{id}/approve
     */
    public function approveEvent(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $eventId = (int) $args['id'];
            $event = Event::find($eventId);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            if ($event->status !== 'pending') {
                return ResponseHelper::error($response, 'Only pending events can be approved', 400);
            }

            $event->status = Event::STATUS_PUBLISHED;
            $event->save();

            return ResponseHelper::success($response, 'Event approved successfully', ['event' => $event]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to approve event', 500, $e->getMessage());
        }
    }

    /**
     * Reject pending event
     * PUT /v1/admin/events/{id}/reject
     */
    public function rejectEvent(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $eventId = (int) $args['id'];
            $event = Event::find($eventId);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            if ($event->status !== 'pending') {
                return ResponseHelper::error($response, 'Only pending events can be rejected', 400);
            }

            $event->status = Event::STATUS_DRAFT;
            $event->save();

            return ResponseHelper::success($response, 'Event rejected successfully', ['event' => $event]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to reject event', 500, $e->getMessage());
        }
    }

    /**
     * Approve pending award
     * PUT /v1/admin/awards/{id}/approve
     */
    public function approveAward(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $awardId = (int) $args['id'];
            $award = Award::find($awardId);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            if ($award->status !== 'pending') {
                return ResponseHelper::error($response, 'Only pending awards can be approved', 400);
            }

            $award->status = Award::STATUS_PUBLISHED;
            $award->save();

            return ResponseHelper::success($response, 'Award approved successfully', ['award' => $award]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to approve award', 500, $e->getMessage());
        }
    }

    /**
     * Reject pending award
     * PUT /v1/admin/awards/{id}/reject
     */
    public function rejectAward(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $awardId = (int) $args['id'];
            $award = Award::find($awardId);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            if ($award->status !== 'pending') {
                return ResponseHelper::error($response, 'Only pending awards can be rejected', 400);
            }

            $award->status = Award::STATUS_DRAFT;
            $award->save();

            return ResponseHelper::success($response, 'Award rejected successfully', ['award' => $award]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to reject award', 500, $e->getMessage());
        }
    }

    // ===================================================================
    // EVENT MANAGEMENT
    // ===================================================================

    /**
     * Get all events (admin)
     * GET /v1/admin/events
     */
    public function getEvents(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            // Get all events with organizer info, tickets count, and revenue
            $events = Event::with(['organizer.user', 'ticketTypes'])
                ->withCount(['tickets as tickets_sold'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($event) {
                    // Calculate total revenue for this event
                    $totalRevenue = (float) OrderItem::where('event_id', $event->id)
                        ->whereHas('order', function ($query) {
                            $query->where('status', 'paid');
                        })
                        ->sum('total_price');

                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'slug' => $event->slug,
                        'description' => $event->description,
                        'organizer_id' => $event->organizer_id,
                        'organizer_name' => $event->organizer->user->name ?? 'N/A',
                        'venue_name' => $event->venue_name,
                        'address' => $event->address,
                        'banner_image' => $event->banner_image,
                        'start_time' => $event->start_time ? $event->start_time->toIso8601String() : null,
                        'end_time' => $event->end_time ? $event->end_time->toIso8601String() : null,
                        'status' => $event->status,
                        'is_featured' => (bool) $event->is_featured,
                        'tickets_sold' => $event->tickets_sold ?? 0,
                        'total_revenue' => round($totalRevenue, 2),
                        'created_at' => $event->created_at->toIso8601String(),
                        'updated_at' => $event->updated_at->toIso8601String(),
                    ];
                });

            return ResponseHelper::success($response, 'Events fetched successfully', ['events' => $events]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch events', 500, $e->getMessage());
        }
    }

    /**
     * Update event status
     * PUT /v1/admin/events/{id}/status
     */
    public function updateEventStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $eventId = (int) $args['id'];
            $data = $request->getParsedBody();
            $status = $data['status'] ?? null;

            // Validate status
            $validStatuses = [
                Event::STATUS_DRAFT,
                Event::STATUS_PENDING,
                Event::STATUS_PUBLISHED,
                Event::STATUS_CANCELLED,
                Event::STATUS_COMPLETED
            ];

            if (!in_array($status, $validStatuses)) {
                return ResponseHelper::error($response, 'Invalid status. Must be: draft, pending, published, cancelled, or completed', 400);
            }

            $event = Event::find($eventId);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            $event->status = $status;
            $event->save();

            return ResponseHelper::success($response, "Event status updated to {$status}", ['event' => $event]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update event status', 500, $e->getMessage());
        }
    }

    /**
     * Toggle event featured status
     * PUT /v1/admin/events/{id}/feature
     */
    public function toggleEventFeatured(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $eventId = (int) $args['id'];
            $data = $request->getParsedBody();
            $isFeatured = filter_var($data['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $event = Event::find($eventId);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            $event->is_featured = $isFeatured;
            $event->save();

            $message = $isFeatured ? 'Event featured successfully' : 'Event unfeatured successfully';
            return ResponseHelper::success($response, $message, ['event' => $event]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update featured status', 500, $e->getMessage());
        }
    }

    /**
     * Delete event
     * DELETE /v1/admin/events/{id}
     */
    public function deleteEvent(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $eventId = (int) $args['id'];
            $event = Event::find($eventId);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Get counts before deletion for reporting
            $ticketsCount = Ticket::where('event_id', $eventId)->count();
            $ordersCount = Order::whereHas('items', function ($query) use ($eventId) {
                $query->where('event_id', $eventId);
            })->count();

            // Delete the event (cascade will handle related records)
            $eventTitle = $event->title;
            $event->delete();

            return ResponseHelper::success($response, 'Event deleted successfully', [
                'event_title' => $eventTitle,
                'tickets_deleted' => $ticketsCount,
                'orders_affected' => $ordersCount,
                'message' => "Event '{$eventTitle}' and all associated data has been permanently deleted."
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete event', 500, $e->getMessage());
        }
    }

    /**
     * Get single event details (admin - full details)
     * GET /v1/admin/events/{id}
     */
    public function getEventDetail(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $eventId = (int) $args['id'];
            $event = Event::with(['organizer.user', 'ticketTypes'])->find($eventId);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Calculate tickets sold and revenue
            $ticketsSold = Ticket::where('event_id', $eventId)->count();
            $totalRevenue = (float) OrderItem::where('event_id', $eventId)
                ->whereHas('order', function ($query) {
                    $query->where('status', 'paid');
                })
                ->sum('total_price');

            // Get orders count
            $ordersCount = Order::whereHas('items', function ($query) use ($eventId) {
                $query->where('event_id', $eventId);
            })->where('status', 'paid')->count();

            // Get ticket types with sold counts
            $ticketTypes = [];
            if ($event->ticketTypes) {
                foreach ($event->ticketTypes as $ticketType) {
                    $sold = Ticket::where('event_id', $eventId)
                        ->where('ticket_type_id', $ticketType->id)
                        ->count();
                    
                    $ticketTypes[] = [
                        'id' => $ticketType->id,
                        'name' => $ticketType->name,
                        'description' => $ticketType->description,
                        'price' => (float) $ticketType->price,
                        'quantity' => (int) $ticketType->quantity,
                        'sold' => $sold,
                        'max_per_order' => $ticketType->max_per_order ?? 10,
                    ];
                }
            }

            $eventData = [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'description' => $event->description,
                'organizer_id' => $event->organizer_id,
                'organizer_name' => $event->organizer->user->name ?? 'N/A',
                'organizer_email' => $event->organizer->user->email ?? null,
                'venue_name' => $event->venue_name,
                'address' => $event->address,
                'city' => $event->city,
                'country' => $event->country,
                'banner_image' => $event->banner_image,
                'start_time' => $event->start_time ? $event->start_time->toIso8601String() : null,
                'end_time' => $event->end_time ? $event->end_time->toIso8601String() : null,
                'status' => $event->status,
                'is_featured' => (bool) $event->is_featured,
                'platform_fee_percentage' => (float) ($event->admin_share_percent ?? 1.5),
                'tickets_sold' => $ticketsSold,
                'total_revenue' => round($totalRevenue, 2),
                'orders_count' => $ordersCount,
                'ticket_types' => $ticketTypes,
                'created_at' => $event->created_at->toIso8601String(),
                'updated_at' => $event->updated_at->toIso8601String(),
            ];

            return ResponseHelper::success($response, 'Event details fetched successfully', ['event' => $eventData]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch event details', 500, $e->getMessage());
        }
    }

    /**
     * Update event (admin - full update)
     * PUT /v1/admin/events/{id}
     */
    public function updateEventFull(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $eventId = (int) $args['id'];
            $data = $request->getParsedBody();

            $event = Event::find($eventId);

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Update allowed fields
            if (isset($data['title'])) $event->title = $data['title'];
            if (isset($data['description'])) $event->description = $data['description'];
            if (isset($data['venue_name'])) $event->venue_name = $data['venue_name'];
            if (isset($data['address'])) $event->address = $data['address'];
            if (isset($data['city'])) $event->city = $data['city'];
            if (isset($data['country'])) $event->country = $data['country'];
            if (isset($data['banner_image'])) $event->banner_image = $data['banner_image'];
            if (isset($data['start_time'])) $event->start_time = $data['start_time'];
            if (isset($data['end_time'])) $event->end_time = $data['end_time'];
            if (isset($data['status'])) $event->status = $data['status'];
            if (isset($data['is_featured'])) $event->is_featured = filter_var($data['is_featured'], FILTER_VALIDATE_BOOLEAN);
            if (isset($data['platform_fee_percentage'])) {
                $event->admin_share_percent = (float) $data['platform_fee_percentage'];
            }

            $event->save();

            return ResponseHelper::success($response, 'Event updated successfully', ['event' => $event]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update event', 500, $e->getMessage());
        }
    }

    // ===================================================================
    // AWARD MANAGEMENT
    // ===================================================================

    /**
     * Get all awards (admin)
     * GET /v1/admin/awards
     */
    public function getAwards(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            // Get all awards with organizer info, categories count, votes count, and revenue
            $awards = Award::with(['organizer.user', 'categories'])
                ->withCount('categories')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($award) {
                    // Calculate total votes for this award
                    $totalVotes = AwardVote::whereHas('category', function ($query) use ($award) {
                        $query->where('award_id', $award->id);
                    })
                    ->where('status', 'paid')
                    ->sum('number_of_votes');

                    // Calculate total revenue for this award
                    $votes = AwardVote::whereHas('category', function ($query) use ($award) {
                        $query->where('award_id', $award->id);
                    })
                    ->where('status', 'paid')
                    ->with('category')
                    ->get();

                    $totalRevenue = (float) $votes->sum(function ($vote) {
                        return $vote->number_of_votes * ($vote->category->cost_per_vote);
                    });

                    return [
                        'id' => $award->id,
                        'title' => $award->title,
                        'slug' => $award->slug,
                        'description' => $award->description,
                        'organizer_id' => $award->organizer_id,
                        'organizer_name' => $award->organizer->user->name ?? 'N/A',
                        'banner_image' => $award->banner_image,
                        'ceremony_date' => $award->ceremony_date ? $award->ceremony_date->toIso8601String() : null,
                        'voting_start' => $award->voting_start ? $award->voting_start->toIso8601String() : null,
                        'voting_end' => $award->voting_end ? $award->voting_end->toIso8601String() : null,
                        'status' => $award->status,
                        'is_featured' => (bool) $award->is_featured,
                        'categories_count' => $award->categories_count ?? 0,
                        'total_votes' => $totalVotes,
                        'total_revenue' => round($totalRevenue, 2),
                        'created_at' => $award->created_at->toIso8601String(),
                        'updated_at' => $award->updated_at->toIso8601String(),
                    ];
                });

            return ResponseHelper::success($response, 'Awards fetched successfully', ['awards' => $awards]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch awards', 500, $e->getMessage());
        }
    }

    /**
     * Update award status
     * PUT /v1/admin/awards/{id}/status
     */
    public function updateAwardStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $awardId = (int) $args['id'];
            $data = $request->getParsedBody();
            $status = $data['status'] ?? null;

            // Validate status
            $validStatuses = [
                Award::STATUS_DRAFT,
                Award::STATUS_PENDING,
                Award::STATUS_PUBLISHED,
                Award::STATUS_COMPLETED,
                Award::STATUS_CLOSED
            ];

            if (!in_array($status, $validStatuses)) {
                return ResponseHelper::error($response, 'Invalid status. Must be: draft, pending, published, completed, or closed', 400);
            }

            $award = Award::find($awardId);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            $award->status = $status;
            $award->save();

            return ResponseHelper::success($response, "Award status updated to {$status}", ['award' => $award]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update award status', 500, $e->getMessage());
        }
    }

    /**
     * Toggle award featured status
     * PUT /v1/admin/awards/{id}/feature
     */
    public function toggleAwardFeatured(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $awardId = (int) $args['id'];
            $data = $request->getParsedBody();
            $isFeatured = filter_var($data['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $award = Award::find($awardId);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            $award->is_featured = $isFeatured;
            $award->save();

            $message = $isFeatured ? 'Award featured successfully' : 'Award unfeatured successfully';
            return ResponseHelper::success($response, $message, ['award' => $award]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update featured status', 500, $e->getMessage());
        }
    }

    /**
     * Delete award
     * DELETE /v1/admin/awards/{id}
     */
    public function deleteAward(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $awardId = (int) $args['id'];
            $award = Award::find($awardId);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Get counts before deletion for reporting
            $categoriesCount = $award->categories()->count();
            $votesCount = AwardVote::whereHas('category', function ($query) use ($awardId) {
                $query->where('award_id', $awardId);
            })->count();

            // Delete the award (cascade will handle related records)
            $awardTitle = $award->title;
            $award->delete();

            return ResponseHelper::success($response, 'Award deleted successfully', [
                'award_title' => $awardTitle,
                'categories_deleted' => $categoriesCount,
                'votes_affected' => $votesCount,
                'message' => "Award '{$awardTitle}' and all associated data has been permanently deleted."
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete award', 500, $e->getMessage());
        }
    }

    /**
     * Get single award details (admin - full details)
     * GET /v1/admin/awards/{id}
     */
    public function getAwardDetail(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $awardId = (int) $args['id'];
            $award = Award::with(['organizer.user', 'categories.nominees'])->find($awardId);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Calculate total votes and revenue
            $totalVotes = AwardVote::whereHas('category', function ($query) use ($awardId) {
                $query->where('award_id', $awardId);
            })
            ->where('status', 'paid')
            ->sum('number_of_votes');

            $votes = AwardVote::whereHas('category', function ($query) use ($awardId) {
                $query->where('award_id', $awardId);
            })
            ->where('status', 'paid')
            ->with('category')
            ->get();

            $totalRevenue = (float) $votes->sum(function ($vote) {
                return $vote->number_of_votes * ($vote->category->cost_per_vote ?? 5);
            });

            // Build categories with nominees and vote counts
            $categoriesData = [];
            $totalNominees = 0;
            
            foreach ($award->categories as $category) {
                $categoryVotes = AwardVote::where('category_id', $category->id)
                    ->where('status', 'paid')
                    ->sum('number_of_votes');
                
                $nomineesData = [];
                foreach ($category->nominees as $nominee) {
                    $nomineeVotes = AwardVote::where('nominee_id', $nominee->id)
                        ->where('status', 'paid')
                        ->sum('number_of_votes');
                    
                    $nomineesData[] = [
                        'id' => $nominee->id,
                        'name' => $nominee->name,
                        'description' => $nominee->description,
                        'image' => $nominee->image,
                        'total_votes' => (int) $nomineeVotes,
                        'display_order' => $nominee->display_order,
                    ];
                    $totalNominees++;
                }
                
                $categoriesData[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'cost_per_vote' => (float) ($category->cost_per_vote ?? 5),
                    'total_votes' => (int) $categoryVotes,
                    'nominees' => $nomineesData,
                    'display_order' => $category->display_order,
                ];
            }

            $awardData = [
                'id' => $award->id,
                'title' => $award->title,
                'slug' => $award->slug,
                'description' => $award->description,
                'organizer_id' => $award->organizer_id,
                'organizer_name' => $award->organizer->user->name ?? 'N/A',
                'organizer_email' => $award->organizer->user->email ?? null,
                'banner_image' => $award->banner_image,
                'ceremony_date' => $award->ceremony_date ? $award->ceremony_date->toIso8601String() : null,
                'voting_start' => $award->voting_start ? $award->voting_start->toIso8601String() : null,
                'voting_end' => $award->voting_end ? $award->voting_end->toIso8601String() : null,
                'status' => $award->status,
                'is_featured' => (bool) $award->is_featured,
                'platform_fee_percentage' => (float) ($award->admin_share_percent ?? 5.0),
                'categories_count' => $award->categories->count(),
                'nominees_count' => $totalNominees,
                'total_votes' => (int) $totalVotes,
                'total_revenue' => round($totalRevenue, 2),
                'categories' => $categoriesData,
                'created_at' => $award->created_at->toIso8601String(),
                'updated_at' => $award->updated_at->toIso8601String(),
            ];

            return ResponseHelper::success($response, 'Award details fetched successfully', ['award' => $awardData]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch award details', 500, $e->getMessage());
        }
    }

    /**
     * Update award (admin - full update)
     * PUT /v1/admin/awards/{id}
     */
    public function updateAwardFull(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $awardId = (int) $args['id'];
            $data = $request->getParsedBody();

            $award = Award::find($awardId);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Update allowed fields
            if (isset($data['title'])) $award->title = $data['title'];
            if (isset($data['description'])) $award->description = $data['description'];
            if (isset($data['banner_image'])) $award->banner_image = $data['banner_image'];
            if (isset($data['ceremony_date'])) $award->ceremony_date = $data['ceremony_date'];
            if (isset($data['voting_start'])) $award->voting_start = $data['voting_start'];
            if (isset($data['voting_end'])) $award->voting_end = $data['voting_end'];
            if (isset($data['status'])) $award->status = $data['status'];
            if (isset($data['is_featured'])) $award->is_featured = filter_var($data['is_featured'], FILTER_VALIDATE_BOOLEAN);
            if (isset($data['platform_fee_percentage'])) {
                $award->admin_share_percent = (float) $data['platform_fee_percentage'];
            }

            $award->save();

            return ResponseHelper::success($response, 'Award updated successfully', ['award' => $award]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update award', 500, $e->getMessage());
        }
    }

    /**
     * Update user status (activate, suspend, deactivate)
     * PUT /v1/admin/users/{id}/status
     */
    public function updateUserStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $userId = (int) $args['id'];
            $data = $request->getParsedBody();
            $status = $data['status'] ?? null;

            // Validate status
            if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])) {
                return ResponseHelper::error($response, 'Invalid status. Must be: active, inactive, or suspended', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Prevent admin from suspending themselves
            if ($user->id === $jwtUser->id && $status !== User::STATUS_ACTIVE) {
                return ResponseHelper::error($response, 'You cannot change your own status', 400);
            }

            $user->status = $status;
            $user->save();

            return ResponseHelper::success($response, 'User status updated successfully', ['user' => $user]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update user status', 500, $e->getMessage());
        }
    }

    /**
     * Reset user password
     * POST /v1/admin/users/{id}/reset-password
     */
    public function resetUserPassword(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $userId = (int) $args['id'];
            $data = $request->getParsedBody();
            $newPassword = $data['password'] ?? null;

            if (!$newPassword || strlen($newPassword) < 6) {
                return ResponseHelper::error($response, 'Password must be at least 6 characters long', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Update password (will be auto-hashed by User model mutator)
            $user->password = $newPassword;
            $user->first_login = true; // Force password change on next login
            $user->save();

            return ResponseHelper::success($response, 'Password reset successfully', [
                'message' => 'The user will be required to change their password on next login'
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to reset password', 500, $e->getMessage());
        }
    }

    /**
     * Update user role
     * PUT /v1/admin/users/{id}/role
     */
    public function updateUserRole(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $userId = (int) $args['id'];
            $data = $request->getParsedBody();
            $role = $data['role'] ?? null;

            // Validate role
            $validRoles = [User::ROLE_ADMIN, User::ROLE_ORGANIZER, User::ROLE_ATTENDEE, User::ROLE_POS, User::ROLE_SCANNER];
            if (!in_array($role, $validRoles)) {
                return ResponseHelper::error($response, 'Invalid role', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Prevent admin from changing their own role
            if ($user->id === $jwtUser->id) {
                return ResponseHelper::error($response, 'You cannot change your own role', 400);
            }

            $oldRole = $user->role;
            $user->role = $role;
            $user->save();

            // If changing to/from organizer, handle organizer profile
            if ($role === User::ROLE_ORGANIZER && $oldRole !== User::ROLE_ORGANIZER) {
                // Create organizer profile if it doesn't exist
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer) {
                    Organizer::create([
                        'user_id' => $user->id,
                        'organization_name' => $user->name . "'s Organization",
                    ]);
                }
            }

            return ResponseHelper::success($response, 'User role updated successfully', ['user' => $user]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update user role', 500, $e->getMessage());
        }
    }

    /**
     * Delete user
     * DELETE /v1/admin/users/{id}
     */
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $userId = (int) $args['id'];
            $user = User::find($userId);

            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Prevent admin from deleting themselves
            if ($user->id === $jwtUser->id) {
                return ResponseHelper::error($response, 'You cannot delete your own account', 400);
            }

            // Check if user has associated data
            $hasOrders = false;
            $hasEvents = false;
            $hasAwards = false;

            if ($user->role === 'organizer') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if ($organizer) {
                    $hasEvents = Event::where('organizer_id', $organizer->id)->exists();
                    $hasAwards = Award::where('organizer_id', $organizer->id)->exists();
                }
            } elseif ($user->role === 'attendee') {
                $hasOrders = Order::where('user_id', $user->id)->exists();
            }

            // Warn if user has associated data (but still allow deletion)
            $warnings = [];
            if ($hasEvents) $warnings[] = 'This user has created events';
            if ($hasAwards) $warnings[] = 'This user has created awards';
            if ($hasOrders) $warnings[] = 'This user has orders';

            // Delete user (cascade deletes will handle related records)
            $user->delete();

            return ResponseHelper::success($response, 'User deleted successfully', [
                'warnings' => $warnings,
                'message' => count($warnings) > 0 
                    ? 'User deleted. Related records may have been affected.' 
                    : 'User deleted successfully'
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete user', 500, $e->getMessage());
        }
    }

    /**
     * Get comprehensive user details with role-specific data
     * GET /v1/admin/users/{id}
     */
    public function getUser(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if (!in_array($jwtUser->role, ['admin', 'super_admin'])) {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $userId = (int) $args['id'];
            $user = User::with(['organizer', 'attendee'])->find($userId);

            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 404);
            }

            // Base user data
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'avatar' => $user->avatar,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'last_login_at' => $user->last_login_at,
            ];

            // Role-specific data
            $roleData = [];
            $stats = [];

            if ($user->role === 'organizer' && $user->organizer) {
                // ===== ORGANIZER DATA =====
                $organizer = $user->organizer;
                
                $roleData['organizer'] = [
                    'id' => $organizer->id,
                    'business_name' => $organizer->business_name,
                    'business_type' => $organizer->business_type,
                    'description' => $organizer->description,
                    'logo' => $organizer->logo,
                    'banner_image' => $organizer->banner_image,
                    'website' => $organizer->website,
                    'address' => $organizer->address,
                    'city' => $organizer->city,
                    'region' => $organizer->region,
                    'country' => $organizer->country,
                    'social_links' => $organizer->social_links,
                    'verification_status' => $organizer->verification_status,
                    'is_featured' => $organizer->is_featured,
                    'created_at' => $organizer->created_at,
                ];

                // Get all events for this organizer
                $events = Event::where('organizer_id', $organizer->id)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($event) {
                        $ticketsSold = Ticket::where('event_id', $event->id)->count();
                        $revenue = OrderItem::where('event_id', $event->id)
                            ->whereHas('order', fn($q) => $q->where('status', 'paid'))
                            ->sum('total_price');
                        
                        return [
                            'id' => $event->id,
                            'title' => $event->title,
                            'slug' => $event->slug,
                            'status' => $event->status,
                            'is_featured' => $event->is_featured,
                            'start_time' => $event->start_time,
                            'end_time' => $event->end_time,
                            'venue_name' => $event->venue_name,
                            'tickets_sold' => $ticketsSold,
                            'revenue' => round((float) $revenue, 2),
                            'created_at' => $event->created_at,
                        ];
                    });

                $roleData['events'] = $events;

                // Get all awards for this organizer
                $awards = Award::where('organizer_id', $organizer->id)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($award) {
                        $totalVotes = AwardVote::where('award_id', $award->id)
                            ->where('status', 'paid')
                            ->sum('number_of_votes');
                        $revenue = AwardVote::where('award_id', $award->id)
                            ->where('status', 'paid')
                            ->sum('gross_amount');

                        return [
                            'id' => $award->id,
                            'title' => $award->title,
                            'slug' => $award->slug,
                            'status' => $award->status,
                            'is_featured' => $award->is_featured,
                            'ceremony_date' => $award->ceremony_date,
                            'voting_start' => $award->voting_start,
                            'voting_end' => $award->voting_end,
                            'total_votes' => (int) $totalVotes,
                            'revenue' => round((float) $revenue, 2),
                            'created_at' => $award->created_at,
                        ];
                    });

                $roleData['awards'] = $awards;

                // Get payout requests
                $payouts = PayoutRequest::where('organizer_id', $organizer->id)
                    ->with(['event', 'award'])
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get()
                    ->map(function ($payout) {
                        return [
                            'id' => $payout->id,
                            'amount' => (float) $payout->amount,
                            'status' => $payout->status,
                            'payment_method' => $payout->payment_method,
                            'payout_type' => $payout->payout_type,
                            'source' => $payout->payout_type === 'event' 
                                ? ($payout->event->title ?? 'Unknown Event')
                                : ($payout->award->title ?? 'Unknown Award'),
                            'created_at' => $payout->created_at,
                            'processed_at' => $payout->processed_at,
                        ];
                    });

                $roleData['payouts'] = $payouts;

                // Get organizer balance
                $balance = OrganizerBalance::where('organizer_id', $organizer->id)->first();
                if ($balance) {
                    $roleData['balance'] = [
                        'available_balance' => (float) $balance->available_balance,
                        'pending_balance' => (float) $balance->pending_balance,
                        'total_earned' => (float) $balance->total_earned,
                        'total_withdrawn' => (float) $balance->total_withdrawn,
                        'last_payout_at' => $balance->last_payout_at,
                    ];
                }

                // Calculate stats
                $totalEventsRevenue = $events->sum('revenue');
                $totalAwardsRevenue = $awards->sum('revenue');
                
                $stats = [
                    'total_events' => $events->count(),
                    'published_events' => $events->where('status', 'published')->count(),
                    'total_awards' => $awards->count(),
                    'published_awards' => $awards->where('status', 'published')->count(),
                    'total_tickets_sold' => $events->sum('tickets_sold'),
                    'total_votes' => $awards->sum('total_votes'),
                    'total_revenue' => round($totalEventsRevenue + $totalAwardsRevenue, 2),
                    'events_revenue' => round($totalEventsRevenue, 2),
                    'awards_revenue' => round($totalAwardsRevenue, 2),
                    'pending_payouts' => $payouts->where('status', 'pending')->count(),
                    'completed_payouts' => $payouts->where('status', 'completed')->count(),
                ];

            } elseif ($user->role === 'attendee' && $user->attendee) {
                // ===== ATTENDEE DATA =====
                $attendee = $user->attendee;
                
                $roleData['attendee'] = [
                    'id' => $attendee->id,
                    'date_of_birth' => $attendee->date_of_birth,
                    'gender' => $attendee->gender,
                    'interests' => $attendee->interests,
                    'created_at' => $attendee->created_at,
                ];

                // Get all orders
                $orders = Order::where('customer_email', $user->email)
                    ->with(['items.event', 'items.ticketType'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'order_number' => $order->order_number,
                            'status' => $order->status,
                            'total_amount' => (float) $order->total_amount,
                            'payment_method' => $order->payment_method,
                            'items_count' => $order->items->count(),
                            'events' => $order->items->map(fn($i) => $i->event->title ?? 'Unknown')->unique()->values()->toArray(),
                            'created_at' => $order->created_at,
                        ];
                    });

                $roleData['orders'] = $orders;

                // Get all tickets
                $tickets = Ticket::whereHas('order', function ($q) use ($user) {
                        $q->where('customer_email', $user->email);
                    })
                    ->with(['event', 'ticketType'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($ticket) {
                        return [
                            'id' => $ticket->id,
                            'ticket_code' => $ticket->ticket_code,
                            'status' => $ticket->status,
                            'event_title' => $ticket->event->title ?? 'Unknown Event',
                            'event_date' => $ticket->event->start_time ?? null,
                            'ticket_type' => $ticket->ticketType->name ?? 'Standard',
                            'price' => (float) ($ticket->ticketType->price ?? 0),
                            'checked_in_at' => $ticket->checked_in_at,
                            'created_at' => $ticket->created_at,
                        ];
                    });

                $roleData['tickets'] = $tickets;

                // Get vote purchases
                $votes = AwardVote::where('voter_email', $user->email)
                    ->where('status', 'paid')
                    ->with(['award', 'nominee', 'category'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($vote) {
                        return [
                            'id' => $vote->id,
                            'award_title' => $vote->award->title ?? 'Unknown Award',
                            'nominee_name' => $vote->nominee->name ?? 'Unknown',
                            'category_name' => $vote->category->name ?? 'Unknown',
                            'number_of_votes' => $vote->number_of_votes,
                            'amount' => (float) $vote->gross_amount,
                            'created_at' => $vote->created_at,
                        ];
                    });

                $roleData['votes'] = $votes;

                // Calculate stats
                $stats = [
                    'total_orders' => $orders->count(),
                    'completed_orders' => $orders->where('status', 'paid')->count(),
                    'total_tickets' => $tickets->count(),
                    'used_tickets' => $tickets->where('status', 'used')->count(),
                    'active_tickets' => $tickets->where('status', 'active')->count(),
                    'total_spent' => round($orders->where('status', 'paid')->sum('total_amount'), 2),
                    'total_votes_purchased' => $votes->sum('number_of_votes'),
                    'vote_purchases_amount' => round($votes->sum('amount'), 2),
                    'events_attended' => $tickets->pluck('event_title')->unique()->count(),
                ];

            } else {
                // For admin, support, or other roles
                $stats = [
                    'role' => $user->role,
                    'account_age_days' => $user->created_at ? Carbon::parse($user->created_at)->diffInDays(Carbon::now()) : 0,
                ];
            }

            return ResponseHelper::success($response, 'User details fetched successfully', [
                'user' => $userData,
                'role_data' => $roleData,
                'stats' => $stats,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch user details', 500, $e->getMessage());
        }
    }

    // ===================================================================
    // FINANCE OVERVIEW
    // ===================================================================

    /**
     * Get comprehensive finance overview for admin dashboard
     * GET /v1/admin/finance
     */
    public function getFinanceOverview(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            if ($jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
            $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();
            $startOfYear = $now->copy()->startOfYear();

            // ===== OVERALL REVENUE SUMMARY =====
            
            // Ticket Revenue (from paid orders)
            $ticketRevenue = (float) OrderItem::whereHas('order', function ($query) {
                $query->where('status', 'paid');
            })->sum('total_price');

            $ticketAdminFees = (float) OrderItem::whereHas('order', function ($query) {
                $query->where('status', 'paid');
            })->sum('admin_amount');

            $ticketOrganizerShare = (float) OrderItem::whereHas('order', function ($query) {
                $query->where('status', 'paid');
            })->sum('organizer_amount');

            // Voting Revenue (from paid votes)
            $voteRevenue = (float) AwardVote::where('status', 'paid')->sum('gross_amount');
            $voteAdminFees = (float) AwardVote::where('status', 'paid')->sum('admin_amount');
            $voteOrganizerShare = (float) AwardVote::where('status', 'paid')->sum('organizer_amount');

            $totalGrossRevenue = $ticketRevenue + $voteRevenue;
            $totalPlatformFees = $ticketAdminFees + $voteAdminFees;
            $totalOrganizerShare = $ticketOrganizerShare + $voteOrganizerShare;

            // ===== THIS MONTH REVENUE =====
            $thisMonthTicketRevenue = (float) OrderItem::whereHas('order', function ($query) use ($startOfMonth) {
                $query->where('status', 'paid')->where('paid_at', '>=', $startOfMonth);
            })->sum('total_price');

            $thisMonthVoteRevenue = (float) AwardVote::where('status', 'paid')
                ->where('created_at', '>=', $startOfMonth)
                ->sum('gross_amount');

            $thisMonthRevenue = $thisMonthTicketRevenue + $thisMonthVoteRevenue;

            $thisMonthPlatformFees = (float) OrderItem::whereHas('order', function ($query) use ($startOfMonth) {
                $query->where('status', 'paid')->where('paid_at', '>=', $startOfMonth);
            })->sum('admin_amount') + (float) AwardVote::where('status', 'paid')
                ->where('created_at', '>=', $startOfMonth)
                ->sum('admin_amount');

            // ===== LAST MONTH REVENUE (for comparison) =====
            $lastMonthTicketRevenue = (float) OrderItem::whereHas('order', function ($query) use ($startOfLastMonth, $endOfLastMonth) {
                $query->where('status', 'paid')->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth]);
            })->sum('total_price');

            $lastMonthVoteRevenue = (float) AwardVote::where('status', 'paid')
                ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
                ->sum('gross_amount');

            $lastMonthRevenue = $lastMonthTicketRevenue + $lastMonthVoteRevenue;
            $monthlyGrowth = $lastMonthRevenue > 0 
                ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
                : 0;

            // ===== MONTHLY REVENUE TREND (Last 6 months) =====
            $monthlyTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthStart = $now->copy()->subMonths($i)->startOfMonth();
                $monthEnd = $now->copy()->subMonths($i)->endOfMonth();
                $monthName = $monthStart->format('M Y');

                $monthTicketRevenue = (float) OrderItem::whereHas('order', function ($query) use ($monthStart, $monthEnd) {
                    $query->where('status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd]);
                })->sum('total_price');

                $monthVoteRevenue = (float) AwardVote::where('status', 'paid')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('gross_amount');

                $monthPlatformFees = (float) OrderItem::whereHas('order', function ($query) use ($monthStart, $monthEnd) {
                    $query->where('status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd]);
                })->sum('admin_amount') + (float) AwardVote::where('status', 'paid')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('admin_amount');

                $monthlyTrend[] = [
                    'month' => $monthName,
                    'ticket_revenue' => round($monthTicketRevenue, 2),
                    'vote_revenue' => round($monthVoteRevenue, 2),
                    'total_revenue' => round($monthTicketRevenue + $monthVoteRevenue, 2),
                    'platform_fees' => round($monthPlatformFees, 2),
                ];
            }

            // ===== TRANSACTION COUNTS =====
            $totalOrders = Order::where('status', 'paid')->count();
            $totalTicketsSold = Ticket::whereHas('order', function ($query) {
                $query->where('status', 'paid');
            })->count();
            $totalVotes = (int) AwardVote::where('status', 'paid')->sum('number_of_votes');
            $totalVoteTransactions = AwardVote::where('status', 'paid')->count();

            // ===== TOP PERFORMING EVENTS (by revenue) =====
            $topEvents = Event::select('events.id', 'events.title')
                ->leftJoin('order_items', 'events.id', '=', 'order_items.event_id')
                ->leftJoin('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', 'paid')
                ->groupBy('events.id', 'events.title')
                ->selectRaw('SUM(order_items.total_price) as total_revenue')
                ->selectRaw('SUM(order_items.admin_amount) as platform_fees')
                ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'total_revenue' => round((float) $event->total_revenue, 2),
                        'platform_fees' => round((float) $event->platform_fees, 2),
                        'orders_count' => (int) $event->orders_count,
                    ];
                });

            // ===== TOP PERFORMING AWARDS (by revenue) =====
            $topAwards = Award::select('awards.id', 'awards.title')
                ->leftJoin('award_votes', 'awards.id', '=', 'award_votes.award_id')
                ->where('award_votes.status', 'paid')
                ->groupBy('awards.id', 'awards.title')
                ->selectRaw('SUM(award_votes.gross_amount) as total_revenue')
                ->selectRaw('SUM(award_votes.admin_amount) as platform_fees')
                ->selectRaw('SUM(award_votes.number_of_votes) as total_votes')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get()
                ->map(function ($award) {
                    return [
                        'id' => $award->id,
                        'title' => $award->title,
                        'total_revenue' => round((float) $award->total_revenue, 2),
                        'platform_fees' => round((float) $award->platform_fees, 2),
                        'total_votes' => (int) $award->total_votes,
                    ];
                });

            // ===== ORGANIZER BALANCES SUMMARY =====
            $totalPendingBalance = (float) \App\Models\OrganizerBalance::sum('pending_balance');
            $totalAvailableBalance = (float) \App\Models\OrganizerBalance::sum('available_balance');
            $totalWithdrawn = (float) \App\Models\OrganizerBalance::sum('total_withdrawn');

            // ===== PAYOUT SUMMARY =====
            $pendingPayouts = \App\Models\PayoutRequest::where('status', 'pending')->count();
            $pendingPayoutAmount = (float) \App\Models\PayoutRequest::where('status', 'pending')->sum('amount');
            $processingPayouts = \App\Models\PayoutRequest::where('status', 'processing')->count();
            $processingPayoutAmount = (float) \App\Models\PayoutRequest::where('status', 'processing')->sum('amount');
            $completedPayouts = \App\Models\PayoutRequest::where('status', 'completed')->count();
            $completedPayoutAmount = (float) \App\Models\PayoutRequest::where('status', 'completed')->sum('amount');

            // ===== RECENT TRANSACTIONS (last 10) =====
            $recentOrders = Order::where('status', 'paid')
                ->with(['user'])
                ->orderBy('paid_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'type' => 'ticket_sale',
                        'reference' => $order->payment_reference,
                        'amount' => (float) $order->total_amount,
                        'customer_name' => $order->customer_name ?? ($order->user->name ?? 'Guest'),
                        'date' => $order->paid_at ? $order->paid_at->toIso8601String() : $order->created_at->toIso8601String(),
                    ];
                });

            $recentVotes = AwardVote::where('status', 'paid')
                ->with(['award'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($vote) {
                    return [
                        'id' => $vote->id,
                        'type' => 'vote_purchase',
                        'reference' => $vote->reference,
                        'amount' => (float) $vote->gross_amount,
                        'customer_name' => $vote->voter_name ?? $vote->voter_email ?? 'Anonymous',
                        'award_title' => $vote->award->title ?? 'N/A',
                        'votes' => (int) $vote->number_of_votes,
                        'date' => $vote->created_at->toIso8601String(),
                    ];
                });

            $data = [
                'summary' => [
                    'total_gross_revenue' => round($totalGrossRevenue, 2),
                    'total_platform_fees' => round($totalPlatformFees, 2),
                    'total_organizer_share' => round($totalOrganizerShare, 2),
                    'this_month_revenue' => round($thisMonthRevenue, 2),
                    'this_month_platform_fees' => round($thisMonthPlatformFees, 2),
                    'last_month_revenue' => round($lastMonthRevenue, 2),
                    'monthly_growth_percent' => $monthlyGrowth,
                ],
                'revenue_breakdown' => [
                    'tickets' => [
                        'gross_revenue' => round($ticketRevenue, 2),
                        'platform_fees' => round($ticketAdminFees, 2),
                        'organizer_share' => round($ticketOrganizerShare, 2),
                        'percentage' => $totalGrossRevenue > 0 ? round(($ticketRevenue / $totalGrossRevenue) * 100, 1) : 0,
                    ],
                    'votes' => [
                        'gross_revenue' => round($voteRevenue, 2),
                        'platform_fees' => round($voteAdminFees, 2),
                        'organizer_share' => round($voteOrganizerShare, 2),
                        'percentage' => $totalGrossRevenue > 0 ? round(($voteRevenue / $totalGrossRevenue) * 100, 1) : 0,
                    ],
                ],
                'transactions' => [
                    'total_orders' => $totalOrders,
                    'total_tickets_sold' => $totalTicketsSold,
                    'total_votes' => $totalVotes,
                    'total_vote_transactions' => $totalVoteTransactions,
                ],
                'monthly_trend' => $monthlyTrend,
                'top_events' => $topEvents,
                'top_awards' => $topAwards,
                'organizer_balances' => [
                    'total_pending' => round($totalPendingBalance, 2),
                    'total_available' => round($totalAvailableBalance, 2),
                    'total_withdrawn' => round($totalWithdrawn, 2),
                ],
                'payouts' => [
                    'pending_count' => $pendingPayouts,
                    'pending_amount' => round($pendingPayoutAmount, 2),
                    'processing_count' => $processingPayouts,
                    'processing_amount' => round($processingPayoutAmount, 2),
                    'completed_count' => $completedPayouts,
                    'completed_amount' => round($completedPayoutAmount, 2),
                ],
                'recent_transactions' => [
                    'orders' => $recentOrders,
                    'votes' => $recentVotes,
                ],
            ];

            return ResponseHelper::success($response, 'Finance overview fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch finance overview', 500, $e->getMessage());
        }
    }

    /**
     * Get comprehensive analytics data for admin dashboard
     * GET /v1/admin/analytics
     */
    public function getAnalytics(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            // Verify admin role
            if (!in_array($jwtUser->role, ['admin', 'super_admin'])) {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            // Get date range from query params (default: last 30 days)
            $params = $request->getQueryParams();
            $period = $params['period'] ?? '30days';
            
            $startDate = match ($period) {
                '7days' => Carbon::now()->subDays(7)->startOfDay(),
                '30days' => Carbon::now()->subDays(30)->startOfDay(),
                '90days' => Carbon::now()->subDays(90)->startOfDay(),
                '12months' => Carbon::now()->subMonths(12)->startOfDay(),
                'all' => Carbon::createFromDate(2020, 1, 1),
                default => Carbon::now()->subDays(30)->startOfDay(),
            };
            $endDate = Carbon::now()->endOfDay();

            // Previous period for comparison
            $periodDays = $startDate->diffInDays($endDate);
            $prevStartDate = $startDate->copy()->subDays($periodDays);
            $prevEndDate = $startDate->copy()->subSecond();

            // ==================== USER ANALYTICS ====================
            
            // Current period users
            $newUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
            $prevNewUsers = User::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
            $userGrowth = $prevNewUsers > 0 ? round((($newUsers - $prevNewUsers) / $prevNewUsers) * 100, 1) : ($newUsers > 0 ? 100 : 0);

            // User breakdown by role
            $usersByRole = User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray();

            // Daily user registrations
            $dailyUsers = User::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($item) => ['date' => $item->date, 'count' => $item->count]);

            // Active users (users who logged in during period)
            $activeUsers = User::whereBetween('last_login_at', [$startDate, $endDate])->count();

            // ==================== EVENT ANALYTICS ====================
            
            // Current period events
            $newEvents = Event::whereBetween('created_at', [$startDate, $endDate])->count();
            $prevNewEvents = Event::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
            $eventGrowth = $prevNewEvents > 0 ? round((($newEvents - $prevNewEvents) / $prevNewEvents) * 100, 1) : ($newEvents > 0 ? 100 : 0);

            // Events by status
            $eventsByStatus = Event::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Top performing events by revenue
            $topEvents = Event::select('events.id', 'events.title', 'events.start_time', 'events.banner_image')
                ->withCount('tickets')
                ->leftJoin('order_items', 'events.id', '=', 'order_items.event_id')
                ->leftJoin('orders', function ($join) {
                    $join->on('order_items.order_id', '=', 'orders.id')
                        ->where('orders.status', '=', 'paid');
                })
                ->selectRaw('COALESCE(SUM(order_items.total_price), 0) as revenue')
                ->groupBy('events.id', 'events.title', 'events.start_time', 'events.banner_image')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'date' => $e->start_time,
                    'banner_image' => $e->banner_image,
                    'tickets_sold' => $e->tickets_count ?? 0,
                    'revenue' => round((float) $e->revenue, 2),
                ]);

            // Events by category/type
            $eventsByType = Event::with('eventType')
                ->selectRaw('event_type_id, COUNT(*) as count')
                ->whereNotNull('event_type_id')
                ->groupBy('event_type_id')
                ->get()
                ->map(fn ($e) => [
                    'type' => $e->eventType->name ?? 'Other',
                    'count' => $e->count,
                ]);

            // ==================== AWARD ANALYTICS ====================
            
            // Current period awards
            $newAwards = Award::whereBetween('created_at', [$startDate, $endDate])->count();
            $prevNewAwards = Award::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
            $awardGrowth = $prevNewAwards > 0 ? round((($newAwards - $prevNewAwards) / $prevNewAwards) * 100, 1) : ($newAwards > 0 ? 100 : 0);

            // Awards by status
            $awardsByStatus = Award::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Top performing awards by votes
            $topAwards = Award::select('awards.id', 'awards.title', 'awards.ceremony_date', 'awards.banner_image')
                ->leftJoin('award_votes', function ($join) {
                    $join->on('awards.id', '=', 'award_votes.award_id')
                        ->where('award_votes.status', '=', 'paid');
                })
                ->selectRaw('COALESCE(SUM(award_votes.number_of_votes), 0) as total_votes')
                ->selectRaw('COALESCE(SUM(award_votes.gross_amount), 0) as revenue')
                ->groupBy('awards.id', 'awards.title', 'awards.ceremony_date', 'awards.banner_image')
                ->orderByDesc('total_votes')
                ->limit(10)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'date' => $a->ceremony_date,
                    'banner_image' => $a->banner_image,
                    'total_votes' => (int) $a->total_votes,
                    'revenue' => round((float) $a->revenue, 2),
                ]);

            // ==================== REVENUE ANALYTICS ====================
            
            // Current period revenue
            $ticketRevenue = (float) OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'paid')->whereBetween('created_at', [$startDate, $endDate]);
            })->sum('total_price');

            $voteRevenue = (float) AwardVote::where('status', 'paid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('gross_amount');

            $totalRevenue = $ticketRevenue + $voteRevenue;

            // Previous period revenue
            $prevTicketRevenue = (float) OrderItem::whereHas('order', function ($q) use ($prevStartDate, $prevEndDate) {
                $q->where('status', 'paid')->whereBetween('created_at', [$prevStartDate, $prevEndDate]);
            })->sum('total_price');

            $prevVoteRevenue = (float) AwardVote::where('status', 'paid')
                ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
                ->sum('gross_amount');

            $prevTotalRevenue = $prevTicketRevenue + $prevVoteRevenue;
            $revenueGrowth = $prevTotalRevenue > 0 ? round((($totalRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100, 1) : ($totalRevenue > 0 ? 100 : 0);

            // Daily revenue trend
            $dailyRevenue = collect();
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $dayEnd = $currentDate->copy()->endOfDay();
                
                $dayTicketRevenue = (float) OrderItem::whereHas('order', function ($q) use ($currentDate, $dayEnd) {
                    $q->where('status', 'paid')->whereBetween('created_at', [$currentDate, $dayEnd]);
                })->sum('total_price');

                $dayVoteRevenue = (float) AwardVote::where('status', 'paid')
                    ->whereBetween('created_at', [$currentDate, $dayEnd])
                    ->sum('gross_amount');

                $dailyRevenue->push([
                    'date' => $currentDate->format('Y-m-d'),
                    'tickets' => round($dayTicketRevenue, 2),
                    'votes' => round($dayVoteRevenue, 2),
                    'total' => round($dayTicketRevenue + $dayVoteRevenue, 2),
                ]);

                $currentDate->addDay()->startOfDay();
            }

            // ==================== TRANSACTION ANALYTICS ====================
            
            $totalOrders = Order::where('status', 'paid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            $prevOrders = Order::where('status', 'paid')
                ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
                ->count();
            $ordersGrowth = $prevOrders > 0 ? round((($totalOrders - $prevOrders) / $prevOrders) * 100, 1) : ($totalOrders > 0 ? 100 : 0);

            $totalTicketsSold = Ticket::whereBetween('created_at', [$startDate, $endDate])->count();
            $totalVotes = AwardVote::where('status', 'paid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('number_of_votes');

            // Average order value
            $avgOrderValue = $totalOrders > 0 ? round($ticketRevenue / $totalOrders, 2) : 0;

            // ==================== GEOGRAPHIC ANALYTICS ====================
            
            // Events by location (city)
            $eventsByCity = Event::selectRaw('city, COUNT(*) as count')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->groupBy('city')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn ($e) => ['city' => $e->city, 'count' => $e->count]);

            // Top organizers by revenue
            $topOrganizers = Organizer::select('organizers.id', 'organizers.organization_name', 'organizers.profile_image')
                ->join('events', 'organizers.id', '=', 'events.organizer_id')
                ->leftJoin('order_items', 'events.id', '=', 'order_items.event_id')
                ->leftJoin('orders', function ($join) {
                    $join->on('order_items.order_id', '=', 'orders.id')
                        ->where('orders.status', '=', 'paid');
                })
                ->selectRaw('COALESCE(SUM(order_items.total_price), 0) as revenue')
                ->selectRaw('COUNT(DISTINCT events.id) as events_count')
                ->groupBy('organizers.id', 'organizers.organization_name', 'organizers.profile_image')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => $o->organization_name,
                    'logo' => $o->profile_image,
                    'events_count' => $o->events_count,
                    'revenue' => round((float) $o->revenue, 2),
                ]);

            // ==================== ENGAGEMENT ANALYTICS ====================
            
            // Conversion rates
            $pendingOrders = Order::where('status', 'pending')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            $conversionRate = ($totalOrders + $pendingOrders) > 0 
                ? round(($totalOrders / ($totalOrders + $pendingOrders)) * 100, 1) 
                : 0;

            // ==================== BUILD RESPONSE ====================
            
            $data = [
                'period' => [
                    'type' => $period,
                    'start' => $startDate->toISOString(),
                    'end' => $endDate->toISOString(),
                    'days' => $periodDays,
                ],
                'overview' => [
                    'total_users' => User::count(),
                    'total_events' => Event::count(),
                    'total_awards' => Award::count(),
                    'total_organizers' => Organizer::count(),
                ],
                'users' => [
                    'new_users' => $newUsers,
                    'growth' => $userGrowth,
                    'active_users' => $activeUsers,
                    'by_role' => $usersByRole,
                    'daily' => $dailyUsers,
                ],
                'events' => [
                    'new_events' => $newEvents,
                    'growth' => $eventGrowth,
                    'by_status' => $eventsByStatus,
                    'by_type' => $eventsByType,
                    'top_performing' => $topEvents,
                ],
                'awards' => [
                    'new_awards' => $newAwards,
                    'growth' => $awardGrowth,
                    'by_status' => $awardsByStatus,
                    'top_performing' => $topAwards,
                ],
                'revenue' => [
                    'total' => round($totalRevenue, 2),
                    'growth' => $revenueGrowth,
                    'tickets' => round($ticketRevenue, 2),
                    'votes' => round($voteRevenue, 2),
                    'daily_trend' => $dailyRevenue,
                ],
                'transactions' => [
                    'total_orders' => $totalOrders,
                    'orders_growth' => $ordersGrowth,
                    'tickets_sold' => $totalTicketsSold,
                    'votes_cast' => (int) $totalVotes,
                    'avg_order_value' => $avgOrderValue,
                    'conversion_rate' => $conversionRate,
                ],
                'geographic' => [
                    'events_by_city' => $eventsByCity,
                ],
                'top_organizers' => $topOrganizers,
            ];

            return ResponseHelper::success($response, 'Analytics data fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch analytics data', 500, $e->getMessage());
        }
    }

    /**
     * Get all platform settings
     * GET /v1/admin/settings
     */
    public function getSettings(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            // Verify admin role
            if (!in_array($jwtUser->role, ['admin', 'super_admin'])) {
                return ResponseHelper::error($response, 'Unauthorized. Admin access required.', 403);
            }

            $PlatformSetting = new \App\Models\PlatformSetting();

            // Get all settings grouped by category
            $settings = [
                'general' => [
                    'site_name' => $PlatformSetting::get('site_name', 'Eventic'),
                    'site_description' => $PlatformSetting::get('site_description', 'Event and Award Management Platform'),
                    'contact_email' => $PlatformSetting::get('contact_email', 'support@eventic.com'),
                    'support_phone' => $PlatformSetting::get('support_phone', '+233 000 000 0000'),
                    'timezone' => $PlatformSetting::get('timezone', 'Africa/Accra'),
                    'currency' => $PlatformSetting::get('currency', 'GHS'),
                    'currency_symbol' => $PlatformSetting::get('currency_symbol', 'GH₵'),
                    'date_format' => $PlatformSetting::get('date_format', 'Y-m-d'),
                ],
                'payment' => [
                    'paystack_public_key' => $PlatformSetting::get('paystack_public_key', ''),
                    'paystack_secret_key' => $PlatformSetting::get('paystack_secret_key', ''),
                    'paystack_fee_percent' => $PlatformSetting::get('paystack_fee_percent', 1.5),
                    'default_event_admin_share' => $PlatformSetting::get('default_event_admin_share', 10),
                    'default_award_admin_share' => $PlatformSetting::get('default_award_admin_share', 15),
                    'min_payout_amount' => $PlatformSetting::get('min_payout_amount', 50),
                    'payout_hold_days' => $PlatformSetting::get('payout_hold_days', 7),
                ],
                'features' => [
                    'enable_event_ticketing' => $PlatformSetting::get('enable_event_ticketing', true),
                    'enable_award_voting' => $PlatformSetting::get('enable_award_voting', true),
                    'enable_organizer_registration' => $PlatformSetting::get('enable_organizer_registration', true),
                    'require_event_approval' => $PlatformSetting::get('require_event_approval', true),
                    'require_award_approval' => $PlatformSetting::get('require_award_approval', true),
                    'enable_event_reviews' => $PlatformSetting::get('enable_event_reviews', true),
                    'enable_refunds' => $PlatformSetting::get('enable_refunds', false),
                ],
                'email' => [
                    'smtp_host' => $PlatformSetting::get('smtp_host', ''),
                    'smtp_port' => $PlatformSetting::get('smtp_port', 587),
                    'smtp_username' => $PlatformSetting::get('smtp_username', ''),
                    'smtp_password' => $PlatformSetting::get('smtp_password', ''),
                    'smtp_encryption' => $PlatformSetting::get('smtp_encryption', 'tls'),
                    'from_email' => $PlatformSetting::get('from_email', 'noreply@eventic.com'),
                    'from_name' => $PlatformSetting::get('from_name', 'Eventic'),
                ],
                'notifications' => [
                    'enable_email_notifications' => $PlatformSetting::get('enable_email_notifications', true),
                    'enable_sms_notifications' => $PlatformSetting::get('enable_sms_notifications', false),
                    'notify_new_order' => $PlatformSetting::get('notify_new_order', true),
                    'notify_new_event' => $PlatformSetting::get('notify_new_event', true),
                    'notify_new_award' => $PlatformSetting::get('notify_new_award', true),
                    'notify_payout_request' => $PlatformSetting::get('notify_payout_request', true),
                ],
                'security' => [
                    'enable_2fa' => $PlatformSetting::get('enable_2fa', false),
                    'session_timeout' => $PlatformSetting::get('session_timeout', 1440),
                    'max_login_attempts' => $PlatformSetting::get('max_login_attempts', 5),
                    'lockout_duration' => $PlatformSetting::get('lockout_duration', 30),
                    'require_strong_passwords' => $PlatformSetting::get('require_strong_passwords', true),
                    'min_password_length' => $PlatformSetting::get('min_password_length', 8),
                ],
                'limits' => [
                    'max_tickets_per_order' => $PlatformSetting::get('max_tickets_per_order', 10),
                    'max_votes_per_transaction' => $PlatformSetting::get('max_votes_per_transaction', 1000),
                    'max_file_upload_size' => $PlatformSetting::get('max_file_upload_size', 5),
                    'max_images_per_event' => $PlatformSetting::get('max_images_per_event', 10),
                ],
            ];

            return ResponseHelper::success($response, 'Settings fetched successfully', $settings);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch settings', 500, $e->getMessage());
        }
    }

    /**
     * Update platform settings
     * PUT /v1/admin/settings
     */
    public function updateSettings(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            // Verify super admin role
            if ($jwtUser->role !== 'super_admin') {
                return ResponseHelper::error($response, 'Unauthorized. Super admin access required.', 403);
            }

            $data = $request->getParsedBody();

            if (empty($data)) {
                return ResponseHelper::error($response, 'No settings data provided', 400);
            }

            $PlatformSetting = new \App\Models\PlatformSetting();
            $updated = [];

            // Process each category of settings
            foreach ($data as $category => $settings) {
                if (!is_array($settings)) {
                    continue;
                }

                foreach ($settings as $key => $value) {
                    $settingKey = $key;
                    $settingType = 'string';

                    // Determine setting type
                    if (is_bool($value)) {
                        $settingType = 'boolean';
                        $value = $value ? '1' : '0';
                    } elseif (is_numeric($value)) {
                        $settingType = 'number';
                    } elseif (is_array($value)) {
                        $settingType = 'json';
                    }

                    $PlatformSetting::set($settingKey, $value, $settingType);
                    $updated[$settingKey] = $value;
                }
            }

            return ResponseHelper::success($response, 'Settings updated successfully', [
                'updated_count' => count($updated),
                'updated_keys' => array_keys($updated),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update settings', 500, $e->getMessage());
        }
    }
}

