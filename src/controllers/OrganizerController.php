<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Organizer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Award;
use App\Models\AwardVote;
use App\Models\OrganizerBalance;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Support\Carbon;
use Exception;

/**
 * OrganizerController
 * Handles organizer-related operations using Eloquent ORM
 */
class OrganizerController
{
    /**
     * Get dashboard data for the authenticated organizer
     * This endpoint provides all necessary data for the organizer dashboard in a single call
     */
    public function getDashboard(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            // Get organizer profile
            $organizer = Organizer::findByUserId((int) $jwtUser->id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            // Get user info for greeting
            $user = User::find($jwtUser->id);
            $firstName = explode(' ', $user->name ?? 'User')[0];

            // Get organizer's events
            $events = Event::where('organizer_id', $organizer->id)->get();
            $eventIds = $events->pluck('id')->toArray();

            // === STATS ===
            $totalEvents = $events->count();

            // Get orders for organizer's events (through order_items)
            $orderIds = OrderItem::whereIn('event_id', $eventIds)
                ->pluck('order_id')
                ->unique()
                ->toArray();

            $orders = Order::whereIn('id', $orderIds)->get();
            $paidOrders = $orders->where('status', 'paid');

            $totalOrders = $paidOrders->count();
            $totalRevenue = $paidOrders->sum('total_amount');

            // Get tickets sold for organizer's events
            $ticketsSold = Ticket::whereIn('event_id', $eventIds)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'paid');
                })
                ->count();

            // Calculate percentage changes (compare to previous period)
            $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
            $thisMonthStart = Carbon::now()->startOfMonth();

            $lastMonthOrders = Order::whereIn('id', $orderIds)
                ->where('status', 'paid')
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->count();

            $thisMonthOrders = Order::whereIn('id', $orderIds)
                ->where('status', 'paid')
                ->where('created_at', '>=', $thisMonthStart)
                ->count();

            $orderChange = $lastMonthOrders > 0
                ? round((($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100)
                : ($thisMonthOrders > 0 ? 100 : 0);

            // === AWARD STATS ===
            $awards = Award::where('organizer_id', $organizer->id)->get();
            $totalAwards = $awards->count();
            
            // Get published awards
            $publishedAwards = $awards->where('status', 'published')->count();
            
            // Get awards with active voting
            $activeVotingAwards = $awards->filter(function($award) {
                return $award->isVotingOpen();
            })->count();
            
            // Get upcoming ceremonies (future ceremony dates)
            $upcomingCeremonies = $awards->where('status', 'published')
                ->filter(function($award) {
                    return $award->ceremony_date && Carbon::parse($award->ceremony_date)->isFuture();
                })->count();
            
            // Calculate total votes and revenue across all awards
            $totalVotes = 0;
            $totalAwardRevenue = 0;
            
            foreach ($awards as $award) {
                $totalVotes += $award->getTotalVotes();
                $totalAwardRevenue += $award->getTotalRevenue();
            }
            
            // Calculate vote changes (this month vs last month)
            $lastMonthVotes = AwardVote::whereIn('award_id', $awards->pluck('id'))
                ->where('status', 'paid')
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->sum('number_of_votes');
            
            $thisMonthVotes = AwardVote::whereIn('award_id', $awards->pluck('id'))
                ->where('status', 'paid')
                ->where('created_at', '>=', $thisMonthStart)
                ->sum('number_of_votes');
            
            $voteChange = $lastMonthVotes > 0
                ? round((($thisMonthVotes - $lastMonthVotes) / $lastMonthVotes) * 100)
                : ($thisMonthVotes > 0 ? 100 : 0);

            $stats = [
                [
                    'label' => 'Total Events',
                    'value' => (string) $totalEvents,
                    'change' => '0%',
                    'trend' => 'up',
                    'ringProgress' => min(100, $totalEvents * 10)
                ],
                [
                    'label' => 'Total Orders',
                    'value' => number_format($totalOrders),
                    'change' => abs($orderChange) . '%',
                    'trend' => $orderChange >= 0 ? 'up' : 'down',
                    'ringProgress' => min(100, (int) ($totalOrders / 10))
                ],
                [
                    'label' => 'Tickets Sold',
                    'value' => number_format($ticketsSold),
                    'change' => '0%',
                    'trend' => 'up',
                    'ringProgress' => min(100, (int) ($ticketsSold / 50))
                ],
                [
                    'label' => 'Total Revenue',
                    'value' => 'GH₵' . number_format($totalRevenue, 2),
                    'change' => '0%',
                    'trend' => 'up',
                    'ringProgress' => min(100, (int) ($totalRevenue / 1000))
                ],
                // AWARD STATS
                [
                    'label' => 'Total Awards',
                    'value' => (string) $totalAwards,
                    'change' => '0%',
                    'trend' => 'up',
                    'ringProgress' => min(100, $totalAwards * 10)
                ],
                [
                    'label' => 'Active Voting',
                    'value' => (string) $activeVotingAwards,
                    'change' => '0%',
                    'trend' => 'up',
                    'ringProgress' => min(100, $activeVotingAwards * 20)
                ],
                [
                    'label' => 'Upcoming Ceremonies',
                    'value' => (string) $upcomingCeremonies,
                    'change' => '0%',
                    'trend' => 'up',
                    'ringProgress' => min(100, $upcomingCeremonies * 15)
                ],
                [
                    'label' => 'Total Votes',
                    'value' => number_format($totalVotes),
                    'change' => abs($voteChange) . '%',
                    'trend' => $voteChange >= 0 ? 'up' : 'down',
                    'ringProgress' => min(100, (int)($totalVotes / 100))
                ],
            ];

            // === TICKET SALES BY TYPE (This Week) ===
            $weekStart = Carbon::now()->startOfWeek();
            $ticketSalesByType = OrderItem::whereIn('event_id', $eventIds)
                ->whereHas('order', function ($q) use ($weekStart) {
                    $q->where('status', 'paid')
                        ->where('created_at', '>=', $weekStart);
                })
                ->with('ticketType')
                ->get()
                ->groupBy(function ($item) {
                    return $item->ticketType->name ?? 'Unknown';
                })
                ->map(function ($items, $name) {
                    return [
                        'name' => $name,
                        'value' => $items->sum('quantity')
                    ];
                })
                ->values()
                ->toArray();

            // If no data, provide some structure
            if (empty($ticketSalesByType)) {
                $ticketSalesByType = [
                    ['name' => 'VIP', 'value' => 0],
                    ['name' => 'Regular', 'value' => 0],
                    ['name' => 'Early Bird', 'value' => 0],
                ];
            }

            // === WEEKLY REVENUE DATA ===
            $weeklyRevenueData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayName = $date->format('D');

                $dayRevenue = Order::whereIn('id', $orderIds)
                    ->where('status', 'paid')
                    ->whereDate('created_at', $date->toDateString())
                    ->sum('total_amount');

                $weeklyRevenueData[] = [
                    'day' => $dayName,
                    'revenue' => round($dayRevenue / 1000, 1) // In thousands
                ];
            }

            // === MONTHLY REVENUE DATA ===
            $monthlyRevenueData = [];
            for ($i = 7; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $monthName = $date->format('M');

                $monthRevenue = Order::whereIn('id', $orderIds)
                    ->where('status', 'paid')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->sum('total_amount');

                $monthlyRevenueData[] = [
                    'month' => $monthName,
                    'revenue' => round($monthRevenue / 1000, 1) // In thousands
                ];
            }

            // === RECENT ACTIVITIES ===
            // Get recent refund requests (orders with status changes)
            $recentRefunds = Order::whereIn('id', $orderIds)
                ->where('status', 'refunded')
                ->orderBy('updated_at', 'desc')
                ->limit(1)
                ->first();

            // Get recent feedback (placeholder - would need reviews table)
            $activities = [];

            if ($recentRefunds) {
                $activities[] = [
                    'type' => 'refund',
                    'title' => '1 customer',
                    'description' => 'requested a refund',
                    'time' => Carbon::parse($recentRefunds->updated_at)->format('D, M d · g:i A')
                ];
            }

            // Get recent high-demand events
            $highDemandEvents = Event::where('organizer_id', $organizer->id)
                ->whereHas('ticketTypes', function ($q) {
                    $q->whereRaw('remaining < quantity * 0.2'); // Less than 20% remaining
                })
                ->count();

            if ($highDemandEvents > 0) {
                $activities[] = [
                    'type' => 'signup',
                    'title' => $highDemandEvents . ' event' . ($highDemandEvents > 1 ? 's' : ''),
                    'description' => 'are nearly sold out',
                    'time' => Carbon::now()->format('D, M d · g:i A')
                ];
            }

            // Get recent ticket sales count
            $recentSales = Ticket::whereIn('event_id', $eventIds)
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->count();

            if ($recentSales > 0) {
                $activities[] = [
                    'type' => 'feedback',
                    'title' => $recentSales . ' ticket' . ($recentSales > 1 ? 's' : ''),
                    'description' => 'sold in the last 24 hours',
                    'time' => Carbon::now()->format('D, M d · g:i A')
                ];
            }

            // === RECENT ORDERS ===
            $recentOrders = Order::whereIn('id', $orderIds)
                ->with(['user', 'items.event', 'items.ticketType'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    $firstEvent = $order->items->first()?->event;
                    return [
                        'customer' => $order->customer_name ?? $order->user?->name ?? 'Guest',
                        'event' => $firstEvent?->title ?? 'Unknown Event',
                        'tickets' => $order->items->sum('quantity'),
                        'amount' => (float) $order->total_amount,
                        'status' => ucfirst($order->status),
                        'time' => $this->getRelativeTime($order->created_at)
                    ];
                })
                ->toArray();

            // === UPCOMING EVENT ===
            $upcomingEvent = Event::where('organizer_id', $organizer->id)
                ->where('start_time', '>', Carbon::now())
                ->where('status', 'published')
                ->orderBy('start_time', 'asc')
                ->with(['eventType', 'images'])
                ->first();

            $upcomingEventData = null;
            if ($upcomingEvent) {
                $upcomingEventData = [
                    'id' => $upcomingEvent->id,
                    'title' => $upcomingEvent->title,
                    'slug' => $upcomingEvent->slug,
                    'category' => $upcomingEvent->eventType?->name ?? 'Event',
                    'venue' => $upcomingEvent->venue,
                    'location' => $upcomingEvent->location . ', ' . $upcomingEvent->country,
                    'description' => substr($upcomingEvent->description ?? '', 0, 100) . '...',
                    'date' => Carbon::parse($upcomingEvent->start_time)->format('M d, Y'),
                    'time' => Carbon::parse($upcomingEvent->start_time)->format('g:i A') . ' - ' .
                        Carbon::parse($upcomingEvent->end_time)->format('g:i A'),
                    'image' => $upcomingEvent->images->first()?->image_url ??
                        'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=200&fit=crop'
                ];
            }

            // === CALENDAR EVENTS ===
            $calendarEvents = Event::where('organizer_id', $organizer->id)
                ->where('start_time', '>=', Carbon::now()->startOfMonth())
                ->where('start_time', '<=', Carbon::now()->addMonths(2))
                ->orderBy('start_time', 'asc')
                ->limit(5)
                ->with('eventType')
                ->get()
                ->map(function ($event) {
                    $date = Carbon::parse($event->start_time);
                    return [
                        'day' => $date->format('j'),
                        'dayName' => $date->format('D'),
                        'name' => $event->title,
                        'category' => $event->eventType?->name ?? 'Event',
                        'time' => Carbon::parse($event->start_time)->format('g:i A') . ' - ' .
                            Carbon::parse($event->end_time)->format('g:i A')
                    ];
                })
                ->toArray();

            // Auto-update awards to "completed" if ceremony date has passed
            Award::autoUpdateCompletedStatuses($organizer->id);
            
            $upcomingAward = Award::where('organizer_id', $organizer->id)
                ->where('ceremony_date', '>', Carbon::now())
                ->where('status', 'published')
                ->orderBy('ceremony_date', 'asc')
                ->with(['categories'])
                ->first();

            $upcomingAwardData = null;
            if ($upcomingAward) {
                $totalVotes = $upcomingAward->getTotalVotes();
                $revenue = $upcomingAward->getTotalRevenue();

                $upcomingAwardData = [
                    'id' => $upcomingAward->id,
                    'title' => $upcomingAward->title,
                    'slug' => $upcomingAward->slug,
                    'description' => substr($upcomingAward->description ?? '', 0, 150) . '...',
                    'banner_image' => $upcomingAward->banner_image ??
                        'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=800&h=450&fit=crop',
                    'ceremony_date' => Carbon::parse($upcomingAward->ceremony_date)->format('M d, Y'),
                    'venue_name' => $upcomingAward->venue_name ?? 'TBD',
                    'total_votes' => $totalVotes,
                    'revenue' => $revenue,
                ];
            }

            // === AWARD ANALYTICS ===
            
            // Weekly votes data (last 7 days)
            $weeklyVotesData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayName = $date->format('D');

                $dayVotes = AwardVote::whereIn('award_id', $awards->pluck('id'))
                    ->where('status', 'paid')
                    ->whereDate('created_at', $date->toDateString())
                    ->sum('number_of_votes');

                $weeklyVotesData[] = [
                    'day' => $dayName,
                    'votes' => (int) $dayVotes
                ];
            }

            // Monthly award revenue data (last 8 months)
            $monthlyAwardRevenueData = [];
            for ($i = 7; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $monthName = $date->format('M');

                $monthRevenue = 0;
                $awardIds = $awards->pluck('id');
                
                if ($awardIds->isNotEmpty()) {
                    $monthVotes = \App\Models\AwardVote::whereIn('award_id', $awardIds)
                        ->where('status', 'paid')
                        ->whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->with('category')
                        ->get();

                    foreach ($monthVotes as $vote) {
                        if ($vote->category) {
                            $monthRevenue += $vote->number_of_votes * $vote->category->cost_per_vote;
                        }
                    }
                }

                $monthlyAwardRevenueData[] = [
                    'month' => $monthName,
                    'revenue' => round($monthRevenue / 1000, 1) // In thousands
                ];
            }

            // Top performing awards (last 30 days)
            $topAwards = [];
            if ($awards->isNotEmpty()) {
                $topAwards = $awards->map(function($award) {
                    $votes = \App\Models\AwardVote::where('award_id', $award->id)
                        ->where('status', 'paid')
                        ->where('created_at', '>=', Carbon::now()->subDays(30))
                        ->sum('number_of_votes');
                    
                    return [
                        'id' => $award->id,
                        'title' => $award->title,
                        'votes' => $votes,
                        'revenue' => $award->getTotalRevenue(),
                        'status' => $award->status,
                    ];
                })
                ->sortByDesc('votes')
                ->take(5)
                ->values()
                ->toArray();
            }

            // Recent award votes
            $recentAwardVotes = [];
            if ($awards->isNotEmpty()) {
                $recentAwardVotes = \App\Models\AwardVote::whereIn('award_id', $awards->pluck('id'))
                    ->where('status', 'paid')
                    ->with(['nominee', 'category', 'award'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function($vote) {
                        return [
                            'id' => $vote->id,
                            'voter_name' => $vote->voter_name ?? 'Anonymous',
                            'voter_email' => $vote->voter_email,
                            'nominee' => $vote->nominee ? $vote->nominee->name : 'Unknown',
                            'category' => $vote->category ? $vote->category->name : 'Unknown',
                            'award' => $vote->award ? $vote->award->title : 'Unknown',
                            'votes' => $vote->number_of_votes,
                            'amount' => $vote->getTotalAmount(),
                            'time' => $this->getRelativeTime($vote->created_at),
                            'created_at' => Carbon::parse($vote->created_at)->format('M d, Y g:i A'),
                        ];
                    })
                    ->toArray();
            }

            // Award category breakdown (for donut chart)
            $awardCategoryBreakdown = [];
            if ($awards->isNotEmpty()) {
                $awardIds = $awards->pluck('id');
                $categories = \App\Models\AwardCategory::whereIn('award_id', $awardIds)
                    ->with('votes')
                    ->get();

                if ($categories->isNotEmpty()) {
                    $awardCategoryBreakdown = $categories
                        ->groupBy('name')
                        ->map(function($cats, $name) {
                            $totalVotes = 0;
                            foreach($cats as $category) {
                                $totalVotes += $category->votes()->where('status', 'paid')->sum('number_of_votes');
                            }
                            return [
                                'name' => $name,
                                'value' => $totalVotes
                            ];
                        })
                        ->sortByDesc('value')
                        ->take(8)
                        ->values()
                        ->toArray();
                }
            }

            // If no data, provide structure
            if (empty($awardCategoryBreakdown)) {
                $awardCategoryBreakdown = [
                    ['name' => 'Music', 'value' => 0],
                    ['name' => 'Film', 'value' => 0],
                    ['name' => 'Arts', 'value' => 0],
                ];
            }

            // === ASSEMBLE DASHBOARD DATA ===
            $dashboardData = [
                'user' => [
                    'name' => $user->name,
                    'firstName' => $firstName,
                    'email' => $user->email,
                ],
                'organizer' => [
                    'id' => $organizer->id,
                    'organizationName' => $organizer->organization_name,
                    'profileImage' => $organizer->profile_image,
                ],
                'stats' => $stats,
                'ticketSalesData' => $ticketSalesByType,
                'weeklyRevenueData' => $weeklyRevenueData,
                'monthlyRevenueData' => $monthlyRevenueData,
                'activities' => $activities,
                'recentOrders' => $recentOrders,
                'upcomingEvent' => $upcomingEventData,
                'upcomingAward' => $upcomingAwardData,
                'calendarEvents' => $calendarEvents,
                // Award analytics data
                'awardStats' => [
                    'totalAwards' => $totalAwards,
                    'publishedAwards' => $publishedAwards,
                    'activeVoting' => $activeVotingAwards,
                    'upcomingCeremonies' => $upcomingCeremonies,
                    'totalVotes' => $totalVotes,
                    'totalRevenue' => $totalAwardRevenue,
                ],
                'weeklyVotesData' => $weeklyVotesData,
                'monthlyAwardRevenueData' => $monthlyAwardRevenueData,
                'topAwards' => $topAwards,
                'recentAwardVotes' => $recentAwardVotes,
                'awardCategoryBreakdown' => $awardCategoryBreakdown,
                'awardStatusCounts' => [
                    'all' => $totalAwards,
                    'published' => $publishedAwards,
                    'draft' => $awards->where('status', 'draft')->count(),
                    'pending' => $awards->where('status', 'pending')->count(),
                    'closed' => $awards->where('status', 'closed')->count(),
                    'completed' => $awards->where('status', 'completed')->count(),
                ],
            ];

            return ResponseHelper::success($response, 'Dashboard data fetched successfully', $dashboardData);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch dashboard data', 500, $e->getMessage());
        }
    }

    /**
     * Get relative time string (e.g., "2 min ago", "1 hr ago")
     */
    private function getRelativeTime($datetime): string
    {
        $now = Carbon::now();
        $time = Carbon::parse($datetime);
        $diffInMinutes = $now->diffInMinutes($time);

        if ($diffInMinutes < 1) {
            return 'Just now';
        } elseif ($diffInMinutes < 60) {
            return $diffInMinutes . ' min ago';
        } elseif ($diffInMinutes < 1440) {
            $hours = floor($diffInMinutes / 60);
            return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($diffInMinutes / 1440);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }

    /**
     * Get all events for the authenticated organizer
     * Returns events with stats, status counts, and event details
     */
    public function getEvents(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            // Get organizer profile
            $organizer = Organizer::findByUserId((int) $jwtUser->id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            // Get all events for this organizer with related data
            $events = Event::where('organizer_id', $organizer->id)
                ->with(['eventType', 'images', 'ticketTypes'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate status counts
            $statusCounts = [
                'all' => $events->count(),
                'published' => $events->where('status', 'published')->count(),
                'draft' => $events->where('status', 'draft')->count(),
                'pending' => $events->where('status', 'pending')->count(),
                'cancelled' => $events->where('status', 'cancelled')->count(),
                'completed' => $events->filter(function ($event) {
                    return $event->status === 'published' &&
                        $event->end_time &&
                        Carbon::parse($event->end_time)->isPast();
                })->count(),
            ];

            // Format events for frontend
            $formattedEvents = $events->map(function ($event) {
                // Calculate tickets sold and total
                $ticketTypes = $event->ticketTypes;
                $totalTickets = $ticketTypes->sum('quantity');
                $remainingTickets = $ticketTypes->sum('remaining');
                $ticketsSold = $totalTickets - $remainingTickets;

                // Calculate revenue from paid orders for this event
                $revenue = OrderItem::where('event_id', $event->id)
                    ->whereHas('order', function ($q) {
                        $q->where('status', 'paid');
                    })
                    ->sum('total_price');

                // Determine effective status (check if completed)
                $status = $event->status;
                if ($status === 'published' && $event->end_time && Carbon::parse($event->end_time)->isPast()) {
                    $status = 'completed';
                }

                return [
                    'id' => $event->id,
                    'name' => $event->title,
                    'slug' => $event->slug,
                    'image' => $event->banner_image ?? $event->images->first()?->image_path ??
                        'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=300&h=200&fit=crop',
                    'date' => $event->start_time ? Carbon::parse($event->start_time)->format('M d, Y') : null,
                    'time' => $event->start_time ? Carbon::parse($event->start_time)->format('g:i A') : null,
                    'location' => $event->address ?? $event->venue_name ?? 'TBD',
                    'venue' => $event->venue_name,
                    'category' => $event->eventType?->name ?? 'Event',
                    'status' => ucfirst($status),
                    'ticketsSold' => $ticketsSold,
                    'totalTickets' => $totalTickets,
                    'revenue' => (float) $revenue,
                    'createdAt' => $event->created_at->format('M d, Y'),
                ];
            })->toArray();

            // Build stats array
            $stats = [
                ['label' => 'Total Events', 'value' => (string) $statusCounts['all'], 'icon' => 'Calendar', 'color' => '#3b82f6'],
                ['label' => 'Published', 'value' => (string) $statusCounts['published'], 'icon' => 'TrendingUp', 'color' => '#22c55e'],
                ['label' => 'Draft', 'value' => (string) $statusCounts['draft'], 'icon' => 'Edit', 'color' => '#f59e0b'],
                ['label' => 'Completed', 'value' => (string) $statusCounts['completed'], 'icon' => 'TicketCheck', 'color' => '#8b5cf6'],
            ];

            // Build tabs array with counts
            $tabs = [
                ['id' => 'all', 'label' => 'All Events', 'count' => $statusCounts['all']],
                ['id' => 'published', 'label' => 'Published', 'count' => $statusCounts['published']],
                ['id' => 'pending', 'label' => 'Pending', 'count' => $statusCounts['pending']],
                ['id' => 'draft', 'label' => 'Draft', 'count' => $statusCounts['draft']],
                ['id' => 'completed', 'label' => 'Completed', 'count' => $statusCounts['completed']],
            ];

            return ResponseHelper::success($response, 'Events fetched successfully', [
                'events' => $formattedEvents,
                'stats' => $stats,
                'tabs' => $tabs,
                'statusCounts' => $statusCounts,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch events', 500, $e->getMessage());
        }
    }

    /**
     * Get all organizers
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $organizers = Organizer::all();

            return ResponseHelper::success($response, 'Organizers fetched successfully', [
                'organizers' => $organizers,
                'count' => $organizers->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch organizers', 500, $e->getMessage());
        }
    }

    /**
     * Get single organizer by ID
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $organizer = Organizer::find($id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer not found', 404);
            }

            return ResponseHelper::success($response, 'Organizer fetched successfully', $organizer->getFullProfile());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch organizer', 500, $e->getMessage());
        }
    }

    /**
     * Create new organizer
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();

            // Validate required fields
            if (empty($data['user_id']) || empty($data['organization_name'])) {
                return ResponseHelper::error($response, 'User ID and Organization Name are required', 400);
            }

            // Check if user already has an organizer profile
            if (Organizer::findByUserId((int) $data['user_id'])) {
                return ResponseHelper::error($response, 'User already has an organizer profile', 409);
            }

            $organizer = Organizer::create($data);

            return ResponseHelper::success($response, 'Organizer created successfully', $organizer->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create organizer', 500, $e->getMessage());
        }
    }

    /**
     * Update organizer
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();

            $organizer = Organizer::find($id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer not found', 404);
            }

            // Authorization: Check if user is admin or the profile owner
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin' && $organizer->user_id !== $user->id) {
                return ResponseHelper::error($response, 'Unauthorized: You do not own this profile', 403);
            }

            $organizer->updateProfile($data);

            return ResponseHelper::success($response, 'Organizer updated successfully', $organizer->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update organizer', 500, $e->getMessage());
        }
    }

    /**
     * Delete organizer
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $organizer = Organizer::find($id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer not found', 404);
            }

            // Authorization: Check if user is admin or the profile owner
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin' && $organizer->user_id !== $user->id) {
                return ResponseHelper::error($response, 'Unauthorized: You do not own this profile', 403);
            }

            $organizer->deleteProfile();

            return ResponseHelper::success($response, 'Organizer deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete organizer', 500, $e->getMessage());
        }
    }

    /**
     * Search organizers by name
     */
    public function search(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $query = $queryParams['query'] ?? '';

            if (empty($query)) {
                return ResponseHelper::error($response, 'Search query is required', 400);
            }

            $organizers = Organizer::searchByName($query);

            return ResponseHelper::success($response, 'Organizers found', [
                'organizers' => $organizers,
                'count' => $organizers->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to search organizers', 500, $e->getMessage());
        }
    }

    /**
     * Get detailed event data for the organizer's View Event page
     * Includes stats, ticket types with sales, attendees, etc.
     */
    public function getEventDetails(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');
            $eventId = $args['id'];

            // Get organizer profile
            $organizer = Organizer::findByUserId((int) $jwtUser->id);
            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            // Get the event with relationships
            $event = Event::with(['organizer.user', 'ticketTypes', 'images', 'eventType'])
                ->where('id', $eventId)
                ->first();

            if (!$event) {
                return ResponseHelper::error($response, 'Event not found', 404);
            }

            // Authorization: Check if organizer owns this event
            if ($jwtUser->role !== 'admin' && $event->organizer_id !== $organizer->id) {
                return ResponseHelper::error($response, 'Unauthorized: You do not own this event', 403);
            }

            // === GET BASE EVENT DETAILS FROM MODEL ===
            $eventData = $event->getFullDetails();

            // === ADD ORGANIZER-SPECIFIC STATS ===
            // Get tickets sold from TicketType (quantity - remaining)
            $ticketsSold = 0;
            $totalTickets = 0;
            foreach ($event->ticketTypes as $ticketType) {
                $ticketsSold += ($ticketType->quantity - $ticketType->remaining);
                $totalTickets += $ticketType->quantity;
            }

            // Get revenue from paid orders
            $revenue = OrderItem::where('event_id', $eventId)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'paid');
                })
                ->sum('total_price');

            // Get order count
            $ordersCount = Order::whereHas('items', function ($q) use ($eventId) {
                $q->where('event_id', $eventId);
            })
                ->where('status', 'paid')
                ->count();

            $stats = [
                'totalRevenue' => (float) $revenue,
                'ticketsSold' => $ticketsSold,
                'totalTickets' => $totalTickets,
                'orders' => $ordersCount,
                'views' => $event->views,
            ];

            // === ADD ORGANIZER-SPECIFIC DATA TO EVENT ===
            $eventData['stats'] = $stats;
            
            // Format timestamps for organizer view
            $eventData['createdAt'] = $event->created_at ? Carbon::parse($event->created_at)->format('Y-m-d') : null;
            $eventData['updatedAt'] = $event->updated_at ? Carbon::parse($event->updated_at)->format('Y-m-d') : null;

            return ResponseHelper::success($response, 'Event details fetched successfully', $eventData);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch event details', 500, $e->getMessage());
        }
    }

    /**
     * Get all orders for organizer's events
     * GET /v1/organizer/orders
     */
    public function getOrders(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');
            $queryParams = $request->getQueryParams();

            // Get organizer profile
            $organizer = Organizer::findByUserId((int) $jwtUser->id);
            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            // Get organizer's events
            $eventIds = Event::where('organizer_id', $organizer->id)->pluck('id')->toArray();

            if (empty($eventIds)) {
                return ResponseHelper::success($response, 'No orders found', [
                    'orders' => [],
                    'stats' => [
                        'totalOrders' => 0,
                        'totalRevenue' => 0,
                        'completed' => 0,
                        'pending' => 0,
                        'cancelled' => 0,
                        'refunded' => 0,
                    ],
                    'pagination' => [
                        'page' => 1,
                        'perPage' => 20,
                        'total' => 0,
                    ]
                ]);
            }

            // Build query for orders related to organizer's events
            $query = Order::whereHas('items', function ($q) use ($eventIds) {
                $q->whereIn('event_id', $eventIds);
            })->with(['user', 'items.event', 'items.ticketType']);

            // Filter by status
            if (isset($queryParams['status']) && $queryParams['status'] !== 'all') {
                $query->where('status', $queryParams['status']);
            }

            // Search functionality
            if (isset($queryParams['search']) && !empty($queryParams['search'])) {
                $search = $queryParams['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%");
                        });
                });
            }

            // Get stats before pagination
            $allOrders = Order::whereHas('items', function ($q) use ($eventIds) {
                $q->whereIn('event_id', $eventIds);
            })->get();

            $stats = [
                'totalOrders' => $allOrders->count(),
                'totalRevenue' => $allOrders->where('status', 'paid')->sum('total_amount'),
                'completed' => $allOrders->where('status', 'paid')->count(),
                'pending' => $allOrders->where('status', 'pending')->count(),
                'cancelled' => $allOrders->where('status', 'cancelled')->count(),
                'refunded' => $allOrders->where('status', 'refunded')->count(),
            ];

            // Pagination
            $page = (int) ($queryParams['page'] ?? 1);
            $perPage = (int) ($queryParams['per_page'] ?? 20);
            $offset = ($page - 1) * $perPage;

            $total = $query->count();
            $orders = $query->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Format orders for frontend
            $formattedOrders = $orders->map(function ($order) {
                $customer = $order->user;
                $orderItems = $order->items;

                // Group tickets by event
                $tickets = $orderItems->map(function ($item) {
                    return [
                        'name' => $item->ticketType ? $item->ticketType->name : 'Unknown',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                })->toArray();

                // Get primary event (first one)
                $primaryEvent = $orderItems->first()->event ?? null;

                return [
                    'id' => $order->id,
                    'orderId' => $order->id,
                    'reference' => $order->reference,
                    'customer' => [
                        'name' => $customer ? $customer->name : 'Unknown',
                        'email' => $customer ? $customer->email : 'N/A',
                        'avatar' => $customer && $customer->name
                            ? 'https://ui-avatars.com/api/?name=' . urlencode($customer->name) . '&background=3b82f6&color=fff'
                            : 'https://ui-avatars.com/api/?name=U&background=gray&color=fff'
                    ],
                    'event' => [
                        'id' => $primaryEvent ? $primaryEvent->id : null,
                        'name' => $primaryEvent ? $primaryEvent->title : 'Multiple Events',
                        'date' => $primaryEvent && $primaryEvent->start_time
                            ? Carbon::parse($primaryEvent->start_time)->format('M d, Y')
                            : 'N/A',
                    ],
                    'tickets' => $tickets,
                    'totalAmount' => $order->total_amount,
                    'status' => ucfirst($order->status),
                    'paymentMethod' => $order->payment_method ?? 'N/A',
                    'orderDate' => $order->created_at ? Carbon::parse($order->created_at)->format('Y-m-d H:i') : null,
                ];
            });

            return ResponseHelper::success($response, 'Orders fetched successfully', [
                'orders' => $formattedOrders->toArray(),
                'stats' => $stats,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch orders', 500, $e->getMessage());
        }
    }

     /**
     * Get single order details for organizer
     * GET /v1/organizers/data/orders/{id}
     */
    public function getOrderDetails(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');
            $orderId = $args['id'];

            // Get organizer profile
            $organizer = Organizer::findByUserId((int) $jwtUser->id);
            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            // Get the order with all related data including nested relationships
            $order = (object) Order::with([
                'user',
                'items.event.eventType',
                'items.event.images',
                'items.event.organizer',
                'items.ticketType',
                'tickets.ticketType',
                'tickets.event'
            ])->find($orderId);

            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }

            // Verify organizer owns the events in this order
            $eventIds = $order->items->pluck('event_id')->unique()->toArray();
            $organizerEventIds = Event::where('organizer_id', $organizer->id)->pluck('id')->toArray();
            
            $hasAccess = !empty(array_intersect($eventIds, $organizerEventIds));
            if (!$hasAccess && $jwtUser->role !== 'admin') {
                return ResponseHelper::error($response, 'Unauthorized: This order is not for your events', 403);
            }

            // Format complete customer data
            $customer = $order->user;
            $customerData = [
                'id' => $customer ? $customer->id : null,
                'name' => $order->customer_name ?? ($customer ? $customer->name : null),
                'email' => $order->customer_email ?? ($customer ? $customer->email : null),
                'phone' => $order->customer_phone ?? ($customer ? $customer->phone : null),
                'avatar' => $customer && $customer->name
                    ? 'https://ui-avatars.com/api/?name=' . urlencode($customer->name) . '&background=3b82f6&color=fff'
                    : null
            ];

            // Format all events (not just primary event)
            $eventsData = $order['items']->map(function ($item) {
                $event = $item['event'];
                if (!$event) {
                    return null;
                }

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'eventType' => $event->eventType ? [
                        'id' => $event->eventType->id,
                        'name' => $event->eventType->name,
                        'slug' => $event->eventType->slug
                    ] : null,
                    'venueName' => $event->venue_name,
                    'address' => $event->address,
                    'mapUrl' => $event->map_url,
                    'bannerImage' => $event->banner_image,
                    'images' => $event->images ? $event->images->map(function ($img) {
                        return [
                            'id' => $img->id,
                            'path' => $img->image_path
                        ];
                    })->toArray() : [],
                    'startTime' => $event->start_time ? Carbon::parse($event->start_time)->toIso8601String() : null,
                    'endTime' => $event->end_time ? Carbon::parse($event->end_time)->toIso8601String() : null,
                    'status' => $event->status,
                    'isFeatured' => (bool) $event->is_featured,
                    'audience' => $event->audience,
                    'language' => $event->language,
                    'tags' => $event->tags,
                    'createdAt' => $event->created_at ? $event->created_at->toIso8601String() : null,
                    'updatedAt' => $event->updated_at ? $event->updated_at->toIso8601String() : null
                ];
            })->filter()->unique('id')->values()->toArray();

            // Get primary event (first one)
            $primaryEvent = !empty($eventsData) ? $eventsData[0] : null;

            // Format complete order items with full ticket type information
            $orderItems = $order['items']->map(function ($item) use ($order) {
                $ticketType = $item['ticketType'];
                
                // Get all tickets for this order item
                $itemTickets = $order->tickets
                    ->where('ticket_type_id', $item->ticket_type_id)
                    ->where('event_id', $item->event_id)
                    ->map(function ($ticket) use ($ticketType) {
                        return [
                            'id' => $ticket->id,
                            'ticketCode' => $ticket->ticket_code,
                            'ticketTypeId' => $ticket->ticket_type_id,
                            'ticketTypeName' => $ticketType ? $ticketType->name : null,
                            'ticketTypeImage' => $ticketType ? $ticketType->ticket_image : null,
                            'status' => $ticket->status,
                            'admittedBy' => $ticket->admitted_by,
                            'admittedAt' => $ticket->admitted_at ? Carbon::parse($ticket->admitted_at)->toIso8601String() : null,
                            'createdAt' => $ticket->created_at ? $ticket->created_at->toIso8601String() : null
                        ];
                    })->values()->toArray();

                return [
                    'id' => $item->id,
                    'eventId' => $item->event_id,
                    'ticketTypeId' => $item->ticket_type_id,
                    'ticketType' => $ticketType ? [
                        'id' => $ticketType->id,
                        'name' => $ticketType->name,
                        'description' => $ticketType->description ?? null,
                        'price' => (float) $ticketType->price,
                        'salePrice' => $ticketType->sale_price ? (float) $ticketType->sale_price : null,
                        'quantity' => $ticketType->quantity,
                        'remaining' => $ticketType->remaining,
                        'dynamicFee' => (float) $ticketType->dynamic_fee,
                        'saleStart' => $ticketType->sale_start ? Carbon::parse($ticketType->sale_start)->toIso8601String() : null,
                        'saleEnd' => $ticketType->sale_end ? Carbon::parse($ticketType->sale_end)->toIso8601String() : null,
                        'maxPerUser' => $ticketType->max_per_user,
                        'ticketImage' => $ticketType->ticket_image,
                        'status' => $ticketType->status
                    ] : null,
                    'quantity' => $item->quantity,
                    'unitPrice' => (float) $item->unit_price,
                    'totalPrice' => (float) $item->total_price,
                    'tickets' => $itemTickets,
                    'createdAt' => $item->created_at ? $item->created_at->toIso8601String() : null,
                    'updatedAt' => $item->updated_at ? $item->updated_at->toIso8601String() : null
                ];
            })->values()->toArray();

            // Build order timeline - only real events
            $timeline = [
                [
                    'action' => 'Order placed',
                    'date' => $order->created_at ? $order->created_at->toIso8601String() : null,
                    'status' => 'completed'
                ]
            ];

            if ($order->status === 'paid' && $order->paid_at) {
                $timeline[] = [
                    'action' => 'Payment received',
                    'date' => Carbon::parse($order->paid_at)->toIso8601String(),
                    'status' => 'completed'
                ];
            }

            // Format complete order details with all database fields
            $orderDetails = [
                'id' => $order->id,
                'userId' => $order->user_id,
                'posUserId' => $order->pos_user_id,
                'paymentReference' => $order->payment_reference,
                'customer' => $customerData,
                'events' => $eventsData,
                'primaryEvent' => $primaryEvent,
                'orderItems' => $orderItems,
                'subtotal' => (float) $order->subtotal,
                'fees' => (float) $order->fees,
                'totalAmount' => (float) $order->total_amount,
                'status' => $order->status,
                'createdAt' => $order->created_at ? $order->created_at->toIso8601String() : null,
                'updatedAt' => $order->updated_at ? $order->updated_at->toIso8601String() : null,
                'paidAt' => $order->paid_at ? Carbon::parse($order->paid_at)->toIso8601String() : null,
                'timeline' => $timeline
            ];

            return ResponseHelper::success($response, 'Order details fetched successfully', $orderDetails);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch order details', 500, $e->getMessage());
        }
    }

    /**
     * Get all awards for the authenticated organizer
     * Returns awards with stats, status counts, and award details
     * GET /v1/organizers/data/awards
     */
    public function getAwards(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');

            // Get organizer profile
            $organizer = Organizer::findByUserId((int) $jwtUser->id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            // Get all awards for this organizer with related data
            $awards = Award::where('organizer_id', $organizer->id)
                ->with(['categories', 'images', 'votes'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Auto-update awards to "completed" if ceremony date has passed
            // Using the reusable static method from Award model
            Award::autoUpdateCompletedStatuses($organizer->id);

            // Calculate status counts
            // Statuses are enum values: draft, pending, published, completed, closed
            $now = Carbon::now();
            $statusCounts = [
                'all' => $awards->count(),
                'published' => $awards->where('status', 'published')->count(),
                'pending' => $awards->where('status', 'pending')->count(),
                'draft' => $awards->where('status', 'draft')->count(),
                'completed' => $awards->where('status', 'completed')->count(),
                'closed' => $awards->where('status', 'closed')->count(),
                // Voting Open is a computed count (published awards with active voting)
                'voting_open' => $awards->filter(function ($award) use ($now) {
                    return $award->status === 'published' &&
                        $award->voting_start &&
                        $award->voting_end &&
                        $award->voting_start <= $now &&
                        $award->voting_end >= $now;
                })->count(),
            ];

            // Format awards for frontend
            $formattedAwards = $awards->map(function ($award) use ($now) {
                // Count categories
                $categoriesCount = $award->categories->count();

                // Calculate total votes
                $totalVotes = $award->getTotalVotes();

                // Calculate revenue
                $revenue = $award->getTotalRevenue();

                // Determine voting status ONLY (separate from status)
                // Voting status is only relevant for published awards
                $votingStatus = null;
                if ($award->status === 'published') {
                    if ($award->voting_start && $award->voting_end) {
                        if ($award->voting_start <= $now && $award->voting_end >= $now) {
                            $votingStatus = 'Voting Open';
                        } elseif ($now < $award->voting_start) {
                            $votingStatus = 'Not Started';
                        } elseif ($now > $award->voting_end) {
                            $votingStatus = 'Voting Closed';
                        }
                    }
                }

                // Keep status as-is from database (enum: draft, completed, published, closed)
                // DO NOT modify the status based on dates
                $status = $award->status;

                // Get banner image with fallback
                $bannerImage = $award->banner_image;
                if (!$bannerImage && $award->images && $award->images->count() > 0) {
                    $bannerImage = $award->images->first()->image_path ?? null;
                }
                if (!$bannerImage) {
                    $bannerImage = 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=800&h=450&fit=crop';
                }

                return [
                    'id' => $award->id,
                    'title' => $award->title,
                    'slug' => $award->slug,
                    'banner_image' => $bannerImage,
                    'image' => $bannerImage, // Fallback field for frontend compatibility
                    'status' => ucfirst($status), // Capitalize enum value: Draft, Completed, Published, Closed
                    'voting_status' => $votingStatus, // Voting Open, Not Started, Voting Closed, or null
                    'ceremony_date' => $award->ceremony_date ? Carbon::parse($award->ceremony_date)->format('M d, Y') : null,
                    'venue_name' => $award->venue_name ?? 'TBD',
                    'address' => $award->address,
                    'categories_count' => $categoriesCount,
                    'total_votes' => $totalVotes,
                    'revenue' => (float) $revenue,
                    'createdAt' => $award->created_at->format('M d, Y'),
                ];
            })->toArray();

            // Build stats array
            $stats = [
                ['label' => 'Total Awards', 'value' => (string) $statusCounts['all'], 'icon' => 'Trophy', 'color' => '#8b5cf6'],
                ['label' => 'Published', 'value' => (string) $statusCounts['published'], 'icon' => 'Calendar', 'color' => '#10b981'],
                ['label' => 'Voting Open', 'value' => (string) $statusCounts['voting_open'], 'icon' => 'Award', 'color' => '#06b6d4'],
                ['label' => 'Completed', 'value' => (string) $statusCounts['completed'], 'icon' => 'TrendingUp', 'color' => '#f59e0b'],
            ];

            // Build tabs array with counts
            $tabs = [
                ['id' => 'all', 'label' => 'All', 'count' => $statusCounts['all']],
                ['id' => 'published', 'label' => 'Published', 'count' => $statusCounts['published']],
                ['id' => 'pending', 'label' => 'Pending', 'count' => $statusCounts['pending']],
                ['id' => 'draft', 'label' => 'Draft', 'count' => $statusCounts['draft']],
                ['id' => 'voting open', 'label' => 'Voting Open', 'count' => $statusCounts['voting_open']], // Computed
                ['id' => 'completed', 'label' => 'Completed', 'count' => $statusCounts['completed']],
                ['id' => 'closed', 'label' => 'Closed', 'count' => $statusCounts['closed']],
            ];

            return ResponseHelper::success($response, 'Awards fetched successfully', [
                'awards' => $formattedAwards,
                'stats' => $stats,
                'tabs' => $tabs,
                'statusCounts' => $statusCounts,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch awards', 500, $e->getMessage());
        }
    }

    /**
     * Get detailed award data for the organizer's View Award page
     * Includes stats, categories, nominees, voting analytics, etc.
     * GET /v1/organizers/data/awards/{id}
     */
    public function getAwardDetails(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');
            $awardId = $args['id'];

            // Get organizer profile
            $organizer = Organizer::findByUserId((int) $jwtUser->id);
            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            // Auto-update awards to "completed" if ceremony date has passed
            Award::autoUpdateCompletedStatuses($organizer->id);

            // Get the award with relationships
            $award = Award::with(['organizer.user', 'categories.nominees', 'images'])
                ->where('id', $awardId)
                ->first();

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Authorization: Check if organizer owns this award
            if ($jwtUser->role !== 'admin' && $award->organizer_id !== $organizer->id) {
                return ResponseHelper::error($response, 'Unauthorized: You do not own this award', 403);
            }

            // === GET BASE AWARD DETAILS FROM MODEL ===
            $awardData = $award->getFullDetails('organizer', $jwtUser->id);

            // === ADD ORGANIZER-SPECIFIC STATS ===
            $totalCategories = $award->categories->count();
            $totalNominees = $award->nominees()->count();
            $totalVotes = $award->getTotalVotes();
            $revenue = $award->getTotalRevenue();

            // Count unique voters (by email since award_votes doesn't have user_id)
            $uniqueVoters = AwardVote::where('award_id', $awardId)
                ->where('status', 'paid')
                ->whereNotNull('voter_email')
                ->distinct()
                ->count('voter_email');

            $stats = [
                'total_categories' => $totalCategories,
                'total_nominees' => $totalNominees,
                'total_votes' => $totalVotes,
                'revenue' => $revenue,
                'unique_voters' => $uniqueVoters,
            ];

            // === GET CATEGORIES WITH STATS ===
            $categories = $award->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'image' => $category->image,
                    'cost_per_vote' => (float) $category->cost_per_vote,
                    'nominees_count' => $category->nominees->count(),
                    'total_votes' => $category->getTotalVotes(),
                    'revenue' => $category->getTotalVotes() * $category->cost_per_vote,
                    'voting_start' => $category->voting_start ? Carbon::parse($category->voting_start)->toIso8601String() : null,
                    'voting_end' => $category->voting_end ? Carbon::parse($category->voting_end)->toIso8601String() : null,
                    'is_voting_open' => $category->isVotingOpen(),
                    // Include nominees array
                    'nominees' => $category->nominees->map(function ($nominee) {
                        return [
                            'id' => $nominee->id,
                            'name' => $nominee->name,
                            'description' => $nominee->description,
                            'image' => $nominee->image,
                            'total_votes' => $nominee->getTotalVotes(),
                            'display_order' => $nominee->display_order,
                        ];
                    })->toArray(),
                ];
            })->toArray();

            // === GET RECENT VOTES ===
            $recentVotes = \App\Models\AwardVote::where('award_id', $awardId)
                ->where('status', 'paid')
                ->with(['nominee.category'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($vote) {
                    return [
                        'id' => $vote->id,
                        'voter' => $vote->voter_name ?? 'Anonymous',
                        'nominee' => $vote->nominee ? $vote->nominee->name : 'Unknown',
                        'category' => $vote->nominee && $vote->nominee->category ? $vote->nominee->category->name : 'Unknown',
                        'votes' => $vote->number_of_votes,
                        'amount' => $vote->getTotalAmount(),
                        'created_at' => $vote->created_at ? $vote->created_at->format('M d, Y g:i A') : null,
                    ];
                })->toArray();

            // === VOTE ANALYTICS (Last 7 days) ===
            $voteAnalytics = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayName = $date->format('D');

                $dayVotes = \App\Models\AwardVote::where('award_id', $awardId)
                    ->where('status', 'paid')
                    ->whereDate('created_at', $date->toDateString())
                    ->sum('number_of_votes');

                $voteAnalytics[] = [
                    'day' => $dayName,
                    'votes' => (int) $dayVotes
                ];
            }

            // === ASSEMBLE COMPREHENSIVE DATA ===
            $awardData['stats'] = $stats;
            $awardData['categories'] = $categories;
            $awardData['recent_votes'] = $recentVotes;
            $awardData['vote_analytics'] = $voteAnalytics;

            return ResponseHelper::success($response, 'Award details fetched successfully', $awardData);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch award details', 500, $e->getMessage());
        }
    }

    /**
     * Get financial overview for organizer
     * GET /v1/organizers/finance/overview
     */
    public function getFinanceOverview(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');
            $organizer = Organizer::findByUserId((int) $jwtUser->id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            $now = Carbon::now();

            // Get all events and awards
            $events = Event::where('organizer_id', $organizer->id)->get();
            $eventIds = $events->pluck('id')->toArray();
            $awards = Award::where('organizer_id', $organizer->id)->get();
            $awardIds = $awards->pluck('id')->toArray();

            // === EVENTS REVENUE ===
            $eventsRevenue = $this->calculateEventsRevenue($eventIds);

            // === AWARDS REVENUE ===
            $awardsRevenue = $this->calculateAwardsRevenue($awardIds);

            // === SUMMARY CALCULATIONS ===
            $totalGrossRevenue = $eventsRevenue['total_gross'] + $awardsRevenue['total_gross'];
            $totalPlatformFees = $eventsRevenue['total_fees'] + $awardsRevenue['total_fees'];
            $totalNetRevenue = $eventsRevenue['total_net'] + $awardsRevenue['total_net'];

            // Calculate available balance (events/awards completed > 7 days ago)
            $availableBalance = $this->calculateAvailableBalance($organizer->id);

            // Get completed payouts (would come from payouts table - placeholder for now)
            $completedPayouts = 0; // TODO: Sum from payouts table when implemented

            // Calculate pending balance
            $pendingBalance = $totalNetRevenue - $availableBalance - $completedPayouts;

            // === MONTHLY TREND (Last 12 months) ===
            $monthlyTrend = $this->getMonthlyRevenueTrend($organizer->id);

            // === TOP PERFORMERS ===
            $topEvent = $events->sortByDesc(function ($event) {
                return OrderItem::where('event_id', $event->id)
                    ->whereHas('order', function ($query) {
                        $query->where('status', 'paid');
                    })
                    ->sum('total_price');
            })->first();

            $topAward = $awards->sortByDesc(function ($award) {
                return AwardVote::where('award_id', $award->id)
                    ->where('status', 'paid')
                    ->sum('number_of_votes');
            })->first();

            $topEventRevenue = $topEvent ? OrderItem::where('event_id', $topEvent->id)
                ->whereHas('order', function ($query) {
                    $query->where('status', 'paid');
                })
                ->sum('total_price') : 0;

            $topAwardVotes = $topAward ? AwardVote::where('award_id', $topAward->id)
                ->where('status', 'paid')
                ->sum('number_of_votes') : 0;

            $data = [
                'summary' => [
                    'total_gross_revenue' => round(floatval($totalGrossRevenue), 2),
                    'total_platform_fees' => round(floatval($totalPlatformFees), 2),
                    'total_net_revenue' => round(floatval($totalNetRevenue), 2),
                    'available_balance' => round(floatval($availableBalance), 2),
                    'pending_balance' => round(floatval($pendingBalance), 2),
                    'completed_payouts' => round(floatval($completedPayouts), 2),
                    'lifetime_earnings' => round(floatval($totalNetRevenue), 2),
                ],
                'revenue_breakdown' => [
                    'events_revenue' => [
                        'gross' => round(floatval($eventsRevenue['total_gross']), 2),
                        'fees' => round(floatval($eventsRevenue['total_fees']), 2),
                        'net' => round(floatval($eventsRevenue['total_net']), 2),
                        'percentage' => $totalGrossRevenue > 0 ? round((floatval($eventsRevenue['total_gross']) / floatval($totalGrossRevenue)) * 100, 1) : 0,
                    ],
                    'awards_revenue' => [
                        'gross' => round(floatval($awardsRevenue['total_gross']), 2),
                        'fees' => round(floatval($awardsRevenue['total_fees']), 2),
                        'net' => round(floatval($awardsRevenue['total_net']), 2),
                        'percentage' => $totalGrossRevenue > 0 ? round((floatval($awardsRevenue['total_gross']) / floatval($totalGrossRevenue)) * 100, 1) : 0,
                    ],
                ],
                'monthly_trend' => $monthlyTrend,
                'top_performers' => [
                    'top_event' => $topEvent ? [
                        'id' => $topEvent->id,
                        'name' => $topEvent->title,
                        'revenue' => round(floatval($topEventRevenue), 2),
                    ] : null,
                    'top_award' => $topAward ? [
                        'id' => $topAward->id,
                        'name' => $topAward->title,
                        'votes' => intval($topAwardVotes),
                        'revenue' => round(floatval($topAwardVotes) * 5, 2), // Assuming $5 per vote average
                    ] : null,
                ],
            ];

            return ResponseHelper::success($response, 'Financial overview fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch financial overview', 500, $e->getMessage());
        }
    }

    /**
     * Get events revenue details
     * GET /v1/organizers/finance/events
     */
    public function getEventsRevenue(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');
            $organizer = Organizer::findByUserId((int) $jwtUser->id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            $events = Event::where('organizer_id', $organizer->id)
                ->with(['ticketTypes'])
                ->orderBy('start_time', 'desc')
                ->get();

            $eventsData = [];
            $totals = ['total_gross' => 0, 'total_fees' => 0, 'total_net' => 0];

            foreach ($events as $event) {
                // Get order items for paid orders only
                $orderItems = OrderItem::where('event_id', $event->id)
                    ->whereHas('order', function ($query) {
                        $query->where('status', 'paid');
                    })
                    ->with('order')
                    ->get();

                $orders = Order::whereIn('id', $orderItems->pluck('order_id')->unique())
                    ->where('status', 'paid')
                    ->get();

                $ticketsSold = Ticket::where('event_id', $event->id)->count();
                $grossRevenue = $orderItems->sum('total_price');
                $platformFee = $grossRevenue * 0.015; // 1.5%
                $netRevenue = $grossRevenue - $platformFee;

                $eventsData[] = [
                    'event_id' => $event->id,
                    'event_name' => $event->title,
                    'event_slug' => $event->slug,
                    'event_date' => $event->start_time ? $event->start_time->toDateString() : null,
                    'status' => $event->status,
                    'total_orders' => $orders->count(),
                    'tickets_sold' => $ticketsSold,
                    'gross_revenue' => round($grossRevenue, 2),
                    'platform_fee' => round($platformFee, 2),
                    'net_revenue' => round($netRevenue, 2),
                    'payout_status' => 'pending', // TODO: Check from payouts table
                    'payout_id' => null,
                    'payout_date' => null,
                    'is_eligible_for_payout' => $this->isEligibleForPayout($event),
                ];

                $totals['total_gross'] += $grossRevenue;
                $totals['total_fees'] += $platformFee;
                $totals['total_net'] += $netRevenue;
            }

            $data = [
                'events' => $eventsData,
                'totals' => [
                    'total_gross' => round($totals['total_gross'], 2),
                    'total_fees' => round($totals['total_fees'], 2),
                    'total_net' => round($totals['total_net'], 2),
                ],
            ];

            return ResponseHelper::success($response, 'Events revenue fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch events revenue', 500, $e->getMessage());
        }
    }

    /**
     * Get awards revenue details
     * GET /v1/organizers/finance/awards
     */
    public function getAwardsRevenue(Request $request, Response $response, array $args): Response
    {
        try {
            $jwtUser = $request->getAttribute('user');
            $organizer = Organizer::findByUserId((int) $jwtUser->id);

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            $awards = Award::where('organizer_id', $organizer->id)
                ->with(['categories'])
                ->orderBy('ceremony_date', 'desc')
                ->get();

            $awardsData = [];
            $totals = ['total_votes' => 0, 'total_gross' => 0, 'total_fees' => 0, 'total_net' => 0];

            foreach ($awards as $award) {
                $votes = AwardVote::where('award_id', $award->id)
                    ->where('status', 'paid')
                    ->get();

                $totalVotes = $votes->sum('number_of_votes');
                
                // Calculate revenue (cost_per_vote comes from categories)
                $grossRevenue = 0;
                $categoryBreakdown = [];
                
                foreach ($award->categories as $category) {
                    $categoryVotes = AwardVote::where('category_id', $category->id)
                        ->where('status', 'paid')
                        ->sum('number_of_votes');
                    
                    $categoryRevenue = $categoryVotes * ($category->cost_per_vote ?? 5);
                    $grossRevenue += $categoryRevenue;
                    
                    if ($categoryVotes > 0) {
                        $categoryBreakdown[] = [
                            'category_name' => $category->name,
                            'votes' => $categoryVotes,
                            'revenue' => round($categoryRevenue, 2),
                        ];
                    }
                }

                $platformFee = $grossRevenue * 0.05; // 5% for voting
                $netRevenue = $grossRevenue - $platformFee;

                $totalVoters = $votes->unique('voter_email')->count();

                $awardsData[] = [
                    'award_id' => $award->id,
                    'award_title' => $award->title,
                    'award_slug' => $award->slug,
                    'ceremony_date' => $award->ceremony_date ? $award->ceremony_date->toDateString() : null,
                    'status' => $award->status,
                    'total_votes' => $totalVotes,
                    'total_voters' => $totalVoters,
                    'average_cost_per_vote' => $totalVotes > 0 ? round($grossRevenue / $totalVotes, 2) : 0,
                    'gross_revenue' => round($grossRevenue, 2),
                    'platform_fee' => round($platformFee, 2),
                    'net_revenue' => round($netRevenue, 2),
                    'payout_status' => 'pending', // TODO: Check from payouts table
                    'payout_id' => null,
                    'payout_date' => null,
                    'breakdown_by_category' => $categoryBreakdown,
                    'is_eligible_for_payout' => $this->isAwardEligibleForPayout($award),
                ];

                $totals['total_votes'] += $totalVotes;
                $totals['total_gross'] += $grossRevenue;
                $totals['total_fees'] += $platformFee;
                $totals['total_net'] += $netRevenue;
            }

            $data = [
                'awards' => $awardsData,
                'totals' => [
                    'total_votes' => $totals['total_votes'],
                    'total_gross' => round($totals['total_gross'], 2),
                    'total_fees' => round($totals['total_fees'], 2),
                    'total_net' => round($totals['total_net'], 2),
                ],
            ];

            return ResponseHelper::success($response, 'Awards revenue fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch awards revenue', 500, $e->getMessage());
        }
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function calculateEventsRevenue($eventIds): array
    {
        // Get order items only from paid orders
        $orderItems = OrderItem::whereIn('event_id', $eventIds)
            ->whereHas('order', function ($query) {
                $query->where('status', 'paid');
            })
            ->get();

        // Use stored financial data if available, otherwise fall back to calculation
        $grossRevenue = $orderItems->sum('total_price');
        $adminFees = $orderItems->sum('admin_amount');
        $organizerNet = $orderItems->sum('organizer_amount');
        
        // If no stored data, calculate with default rates
        if ($organizerNet == 0 && $grossRevenue > 0) {
            $platformFee = $grossRevenue * 0.015; // 1.5% legacy fallback
            $organizerNet = $grossRevenue - $platformFee;
            $adminFees = $platformFee;
        }

        return [
            'total_gross' => $grossRevenue,
            'total_fees' => $adminFees,
            'total_net' => $organizerNet,
        ];
    }

    private function calculateAwardsRevenue($awardIds): array
    {
        $votes = AwardVote::whereIn('award_id', $awardIds)
            ->where('status', 'paid')
            ->get();

        // Use stored financial data if available
        $grossRevenue = $votes->sum('gross_amount');
        $adminFees = $votes->sum('admin_amount');
        $organizerNet = $votes->sum('organizer_amount');
        
        // If no stored data, fall back to calculation
        if ($grossRevenue == 0 && $votes->count() > 0) {
            $grossRevenue = $votes->sum(function ($vote) {
                return $vote->number_of_votes * ($vote->cost_per_vote ?? 5);
            });
            $platformFee = $grossRevenue * 0.05; // 5% legacy fallback
            $organizerNet = $grossRevenue - $platformFee;
            $adminFees = $platformFee;
        }

        return [
            'total_gross' => $grossRevenue,
            'total_fees' => $adminFees,
            'total_net' => $organizerNet,
        ];
    }

    private function calculateAvailableBalance($organizerId): float
    {
        // First try to get from OrganizerBalance table
        $balance = OrganizerBalance::where('organizer_id', $organizerId)->first();
        if ($balance) {
            $balance->recalculateFromTransactions();
            return (float) $balance->available_balance;
        }

        // Legacy calculation for backwards compatibility
        $sevenDaysAgo = Carbon::now()->subDays(7);
        $availableBalance = 0;

        // Events completed > 7 days ago
        $eligibleEvents = Event::where('organizer_id', $organizerId)
            ->where(function ($query) use ($sevenDaysAgo) {
                $query->where('end_time', '<', $sevenDaysAgo)
                    ->orWhere('status', 'completed');
            })
            ->get();

        foreach ($eligibleEvents as $event) {
            $orderItems = OrderItem::where('event_id', $event->id)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'paid');
                })
                ->get();
            
            // Try stored data first
            $net = $orderItems->sum('organizer_amount');
            if ($net == 0) {
                $gross = $orderItems->sum('total_price');
                $net = $gross * 0.985; // Net after 1.5% fee
            }
            $availableBalance += $net;
        }

        // Awards with voting ended > 7 days ago
        $eligibleAwards = Award::where('organizer_id', $organizerId)
            ->where(function ($query) use ($sevenDaysAgo) {
                $query->where('voting_end', '<', $sevenDaysAgo)
                    ->orWhere('status', 'completed');
            })
            ->get();

        foreach ($eligibleAwards as $award) {
            $votes = AwardVote::where('award_id', $award->id)
                ->where('status', 'paid')
                ->get();
            
            // Try stored data first
            $net = $votes->sum('organizer_amount');
            if ($net == 0) {
                $gross = $votes->sum(function ($vote) {
                    return $vote->number_of_votes * ($vote->cost_per_vote ?? 5);
                });
                $net = $gross * 0.95; // Net after 5% fee
            }
            $availableBalance += $net;
        }

        return $availableBalance;
    }

    private function getMonthlyRevenueTrend($organizerId): array
    {
        $trend = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            // Events revenue for this month
            $eventIds = Event::where('organizer_id', $organizerId)
                ->whereBetween('start_time', [$monthStart, $monthEnd])
                ->pluck('id')->toArray();
            
            $eventsRevenue = OrderItem::whereIn('event_id', $eventIds)
                ->whereHas('order', function ($query) {
                    $query->where('status', 'paid');
                })
                ->sum('total_price');

            // Awards revenue for this month
            $awardIds = Award::where('organizer_id', $organizerId)
                ->whereBetween('ceremony_date', [$monthStart, $monthEnd])
                ->pluck('id')->toArray();
            
            $votes = AwardVote::whereIn('award_id', $awardIds)
                ->where('status', 'paid')
                ->with('category')
                ->get();
            
            $awardsRevenue = $votes->sum(function ($vote) {
                return $vote->number_of_votes * ($vote->category->cost_per_vote ?? 5);
            });

            $trend[] = [
                'month' => $month->format('M Y'),
                'events' => round(floatval($eventsRevenue), 2),
                'awards' => round(floatval($awardsRevenue), 2),
            ];
        }

        return $trend;
    }

    private function isEligibleForPayout($event): bool
    {
        // Event must be completed or ended > 7 days ago
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        if ($event->status === 'completed') {
            return true;
        }
        
        if ($event->end_time && $event->end_time < $sevenDaysAgo) {
            return true;
        }

        return false;
    }

    private function isAwardEligibleForPayout($award): bool
    {
        // Award voting must have ended > 7 days ago
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        if ($award->status === 'completed') {
            return true;
        }
        
        if ($award->voting_end && $award->voting_end < $sevenDaysAgo) {
            return true;
        }

        return false;
    }

    /**
     * Get all attendees for organizer's events
     * GET /v1/organizers/data/attendees
     */
    public function getAttendees(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            $organizer = Organizer::where('user_id', $user->id)->first();

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            $queryParams = $request->getQueryParams();
            $eventId = $queryParams['event_id'] ?? null;
            $status = $queryParams['status'] ?? null; // 'checked-in', 'not-checked-in'
            $search = $queryParams['search'] ?? null;

            // Get all organizer's event IDs
            $eventIds = Event::where('organizer_id', $organizer->id)->pluck('id')->toArray();

            if (empty($eventIds)) {
                return ResponseHelper::success($response, 'Attendees retrieved successfully', [
                    'attendees' => [],
                    'stats' => [
                        'total_attendees' => 0,
                        'checked_in' => 0,
                        'not_checked_in' => 0,
                        'total_events' => 0,
                    ],
                    'events' => [],
                ]);
            }

            // Build tickets query with relationships
            $ticketsQuery = Ticket::whereIn('event_id', $eventIds)
                ->with(['order', 'event', 'ticketType']);

            // Filter by specific event if provided
            if ($eventId) {
                $ticketsQuery->where('event_id', $eventId);
            }

            // Filter by check-in status
            if ($status === 'checked-in') {
                $ticketsQuery->where('status', 'used');
            } elseif ($status === 'not-checked-in') {
                $ticketsQuery->where('status', 'active');
            }

            // Get tickets
            $tickets = $ticketsQuery->orderBy('created_at', 'desc')->get();

            // Apply search filter after getting results (for customer info from order)
            $attendees = $tickets->map(function ($ticket) {
                $order = $ticket->order;
                $event = $ticket->event;
                $ticketType = $ticket->ticketType;

                return [
                    'id' => $ticket->id,
                    'ticket_code' => $ticket->ticket_code,
                    'name' => $order->customer_name ?? 'Unknown',
                    'email' => $order->customer_email ?? '',
                    'phone' => $order->customer_phone ?? '',
                    'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($order->customer_name ?? 'U') . '&background=random&color=fff',
                    'event' => $event->name ?? 'Unknown Event',
                    'event_id' => $ticket->event_id,
                    'ticket_type' => $ticketType->name ?? 'Standard',
                    'ticket_count' => 1, // Each ticket row represents 1 ticket
                    'order_id' => $order->id,
                    'order_reference' => 'ORD-' . str_pad((string)$order->id, 6, '0', STR_PAD_LEFT),
                    'order_date' => $order->created_at ? $order->created_at->format('Y-m-d') : null,
                    'checked_in' => $ticket->status === 'used',
                    'check_in_time' => $ticket->admitted_at ? $ticket->admitted_at : null,
                    'status' => $ticket->status,
                ];
            });

            // Apply search filter
            if ($search) {
                $searchLower = strtolower($search);
                $attendees = $attendees->filter(function ($attendee) use ($searchLower) {
                    return str_contains(strtolower($attendee['name']), $searchLower) ||
                           str_contains(strtolower($attendee['email']), $searchLower) ||
                           str_contains(strtolower($attendee['order_reference']), $searchLower) ||
                           str_contains(strtolower($attendee['ticket_code']), $searchLower);
                });
            }

            // Calculate stats
            $allTickets = Ticket::whereIn('event_id', $eventIds)->get();
            $stats = [
                'total_attendees' => $allTickets->count(),
                'checked_in' => $allTickets->where('status', 'used')->count(),
                'not_checked_in' => $allTickets->where('status', 'active')->count(),
                'total_events' => count($eventIds),
            ];

            // Get events for filter dropdown
            $events = Event::whereIn('id', $eventIds)
                ->select('id', 'name', 'start_date')
                ->orderBy('start_date', 'desc')
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'name' => $event->name,
                    ];
                });

            return ResponseHelper::success($response, 'Attendees retrieved successfully', [
                'attendees' => $attendees->values()->toArray(),
                'stats' => $stats,
                'events' => $events->toArray(),
                'count' => $attendees->count(),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch attendees', 500, $e->getMessage());
        }
    }

    /**
     * Send bulk email to attendees
     * POST /v1/organizers/data/attendees/send-email
     */
    public function sendBulkEmail(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            $organizer = Organizer::where('user_id', $user->id)->first();

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            $data = $request->getParsedBody();

            // Validate required fields
            if (empty($data['subject'])) {
                return ResponseHelper::error($response, 'Email subject is required', 400);
            }

            if (empty($data['message'])) {
                return ResponseHelper::error($response, 'Email message is required', 400);
            }

            if (empty($data['attendee_ids']) || !is_array($data['attendee_ids'])) {
                return ResponseHelper::error($response, 'Attendee IDs are required', 400);
            }

            // Get all organizer's event IDs for authorization
            $eventIds = Event::where('organizer_id', $organizer->id)->pluck('id')->toArray();

            if (empty($eventIds)) {
                return ResponseHelper::error($response, 'No events found', 404);
            }

            // Get tickets for the specified attendees, ensuring they belong to organizer's events
            $tickets = Ticket::whereIn('id', $data['attendee_ids'])
                ->whereIn('event_id', $eventIds)
                ->with(['order'])
                ->get();

            if ($tickets->isEmpty()) {
                return ResponseHelper::error($response, 'No valid attendees found', 404);
            }

            // Initialize email service
            $emailService = new \App\Services\EmailService();

            // Collect unique email addresses (some orders might have multiple tickets)
            $emailAddresses = [];
            $successCount = 0;
            $failCount = 0;
            $errors = [];

            foreach ($tickets as $ticket) {
                $order = $ticket->order;
                if (!$order || !$order->customer_email) {
                    $failCount++;
                    continue;
                }

                $customerEmail = $order->customer_email;
                
                // Avoid sending duplicate emails to the same address
                if (in_array($customerEmail, $emailAddresses)) {
                    continue;
                }

                $emailAddresses[] = $customerEmail;

                // Personalize the message
                $customerName = $order->customer_name ?? 'Valued Customer';
                $personalizedMessage = str_replace(
                    ['{name}', '{NAME}'],
                    $customerName,
                    $data['message']
                );

                // Create HTML email body
                $htmlBody = $this->getBulkEmailTemplate(
                    $customerName,
                    $data['subject'],
                    $personalizedMessage,
                    $organizer
                );

                // Send email
                try {
                    $sent = $emailService->send(
                        $customerEmail,
                        $data['subject'],
                        $htmlBody,
                        $organizer->business_name ?? 'Eventic'
                    );

                    if ($sent) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $errors[] = "Failed to send to {$customerEmail}";
                    }
                } catch (Exception $e) {
                    $failCount++;
                    $errors[] = "Error sending to {$customerEmail}: " . $e->getMessage();
                }
            }

            // Return result
            $message = "Email sent successfully to {$successCount} attendee" . ($successCount !== 1 ? 's' : '');
            if ($failCount > 0) {
                $message .= ". {$failCount} failed.";
            }

            return ResponseHelper::success($response, $message, [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'total_attempted' => $successCount + $failCount,
                'errors' => array_slice($errors, 0, 5), // Return first 5 errors only
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to send emails', 500, $e->getMessage());
        }
    }

    /**
     * Get HTML email template for bulk emails
     */
    private function getBulkEmailTemplate(string $name, string $subject, string $message, Organizer $organizer): string
    {
        $businessName = $organizer->business_name ?? 'Eventic';
        $businessEmail = $organizer->email ?? getenv('MAIL_FROM_ADDRESS');

        // Convert line breaks to <br> tags
        $formattedMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .content p { margin: 0 0 15px 0; }
        .greeting { font-size: 18px; font-weight: bold; color: #f97316; }
        .message { background: #f9fafb; padding: 20px; border-left: 4px solid #f97316; margin: 20px 0; }
        .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$businessName}</h1>
        </div>
        <div class="content">
            <p class="greeting">Hello {$name},</p>
            <div class="message">
                {$formattedMessage}
            </div>
            <p>If you have any questions, please don't hesitate to reach out to us.</p>
            <p>Best regards,<br><strong>{$businessName}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; " . date('Y') . " {$businessName}. All rights reserved.</p>
            <p>This email was sent to you as an attendee of one of our events.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Resend order confirmation email
     * POST /v1/organizers/data/orders/{orderId}/resend-confirmation
     */
    public function resendOrderConfirmation(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            $organizer = Organizer::where('user_id', $user->id)->first();

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            $orderId = (int) $args['orderId'];

            // Get order and verify it belongs to organizer's event
            $order = Order::with(['event', 'items.ticketType'])
                ->whereHas('event', function ($query) use ($organizer) {
                    $query->where('organizer_id', $organizer->id);
                })
                ->where('id', $orderId)
                ->first();

            if (!$order) {
                return ResponseHelper::error($response, 'Order not found or does not belong to your events', 404);
            }

            // Only resend for paid orders
            if ($order->status !== 'paid') {
                return ResponseHelper::error($response, 'Can only resend confirmation for paid orders', 400);
            }

            // Initialize email service
            $emailService = new \App\Services\EmailService();

            // Generate order confirmation email
            $customerName = $order->customer_name ?? 'Valued Customer';
            $customerEmail = $order->customer_email;

            if (!$customerEmail) {
                return ResponseHelper::error($response, 'Customer email not found', 400);
            }

            // Build email content
            $subject = "Order Confirmation - {$order->event->title}";
            $emailBody = $this->getOrderConfirmationEmailTemplate($order, $organizer);

            // Send email
            $sent = $emailService->send($customerEmail, $subject, $emailBody, $organizer->business_name ?? 'Eventic');

            if ($sent) {
                return ResponseHelper::success($response, 'Confirmation email sent successfully', [
                    'order_id' => $order->id,
                    'email' => $customerEmail,
                    'sent_at' => Carbon::now()->toIso8601String(),
                ]);
            } else {
                return ResponseHelper::error($response, 'Failed to send confirmation email', 500);
            }
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to resend confirmation', 500, $e->getMessage());
        }
    }

    /**
     * Process order refund
     * POST /v1/organizers/data/orders/{orderId}/refund
     */
    public function processOrderRefund(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            $organizer = Organizer::where('user_id', $user->id)->first();

            if (!$organizer) {
                return ResponseHelper::error($response, 'Organizer profile not found', 404);
            }

            $orderId = (int) $args['orderId'];
            $data = $request->getParsedBody();

            // Get order and verify it belongs to organizer's event
            $order = Order::with(['event', 'items'])
                ->whereHas('event', function ($query) use ($organizer) {
                    $query->where('organizer_id', $organizer->id);
                })
                ->where('id', $orderId)
                ->first();

            if (!$order) {
                return ResponseHelper::error($response, 'Order not found or does not belong to your events', 404);
            }

            // Validate order can be refunded
            if ($order->status !== 'paid') {
                return ResponseHelper::error($response, 'Only paid orders can be refunded', 400);
            }

            if ($order->status === 'refunded') {
                return ResponseHelper::error($response, 'Order has already been refunded', 400);
            }

            // Validate refund amount
            $refundAmount = isset($data['amount']) ? (float) $data['amount'] : $order->total;
            $reason = $data['reason'] ?? 'Refund requested by organizer';

            if ($refundAmount <= 0 || $refundAmount > $order->total) {
                return ResponseHelper::error($response, 'Invalid refund amount', 400);
            }

            // In a real implementation, you would integrate with payment gateway here
            // For now, we'll just update the order status
            
            // TODO: Integrate with payment gateway (Stripe, PayPal, etc.)
            // Example:
            // $paymentGateway = new PaymentGatewayService();
            // $refundResult = $paymentGateway->processRefund($order->payment_intent_id, $refundAmount);
            
            // Update order status
            $order->status = 'refunded';
            $order->refunded_at = Carbon::now();
            $order->refund_amount = $refundAmount;
            $order->refund_reason = $reason;
            $order->save();

            // Send refund notification email
            $emailService = new \App\Services\EmailService();
            $customerEmail = $order->customer_email;

            if ($customerEmail) {
                $subject = "Refund Processed - Order #{$order->id}";
                $emailBody = $this->getRefundEmailTemplate($order, $refundAmount, $reason, $organizer);
                $emailService->send($customerEmail, $subject, $emailBody, $organizer->business_name ?? 'Eventic');
            }

            return ResponseHelper::success($response, 'Refund processed successfully', [
                'order_id' => $order->id,
                'refund_amount' => $refundAmount,
                'refunded_at' => $order->refunded_at->toIso8601String(),
                'reason' => $reason,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to process refund', 500, $e->getMessage());
        }
    }

    /**
     * Get order confirmation email template
     */
    private function getOrderConfirmationEmailTemplate(Order $order, Organizer $organizer): string
    {
        $businessName = $organizer->business_name ?? 'Eventic';
        $customerName = $order->customer_name ?? 'Valued Customer';
        $eventTitle = $order->event->title ?? 'Event';
        $eventDate = $order->event->start_time ? Carbon::parse($order->event->start_time)->format('F d, Y \a\t g:i A') : 'TBA';
        $eventVenue = $order->event->venue_name ?? 'TBA';

        // Build tickets list
        $ticketsList = '';
        foreach ($order->items as $item) {
            $ticketName = $item->ticketType->name ?? 'Ticket';
            $quantity = $item->quantity;
            $price = number_format($item->price, 2);
            $ticketsList .= "<li>{$quantity}x {$ticketName} - \${$price}</li>";
        }

        $totalAmount = number_format($order->total, 2);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .order-details { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .order-details h2 { margin-top: 0; color: #f97316; }
        .tickets { list-style: none; padding: 0; }
        .tickets li { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .total { font-size: 18px; font-weight: bold; color: #f97316; margin-top: 15px; }
        .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
        </div>
        <div class="content">
            <p>Hello {$customerName},</p>
            <p>Thank you for your purchase! Your order has been confirmed.</p>
            
            <div class="order-details">
                <h2>Order #{$order->id}</h2>
                <p><strong>Event:</strong> {$eventTitle}</p>
                <p><strong>Date:</strong> {$eventDate}</p>
                <p><strong>Venue:</strong> {$eventVenue}</p>
                
                <h3>Tickets:</h3>
                <ul class="tickets">
                    {$ticketsList}
                </ul>
                
                <p class="total">Total: \${$totalAmount}</p>
            </div>
            
            <p>Your tickets have been sent to this email address. Please check your inbox for your ticket details.</p>
            <p>If you have any questions, please don't hesitate to contact us.</p>
            <p>Best regards,<br><strong>{$businessName}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; " . date('Y') . " {$businessName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get refund notification email template
     */
    private function getRefundEmailTemplate(Order $order, float $refundAmount, string $reason, Organizer $organizer): string
    {
        $businessName = $organizer->business_name ?? 'Eventic';
        $customerName = $order->customer_name ?? 'Valued Customer';
        $formattedAmount = number_format($refundAmount, 2);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Processed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .refund-box { background: #eff6ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; margin: 20px 0; }
        .amount { font-size: 24px; font-weight: bold; color: #3b82f6; }
        .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Refund Processed</h1>
        </div>
        <div class="content">
            <p>Hello {$customerName},</p>
            <p>Your refund has been processed successfully.</p>
            
            <div class="refund-box">
                <p><strong>Order ID:</strong> #{$order->id}</p>
                <p><strong>Refund Amount:</strong></p>
                <p class="amount">\${$formattedAmount}</p>
                <p><strong>Reason:</strong> {$reason}</p>
            </div>
            
            <p>The refund will be credited to your original payment method within 5-10 business days.</p>
            <p>If you have any questions about this refund, please don't hesitate to contact us.</p>
            <p>Best regards,<br><strong>{$businessName}</strong></p>
        </div>
        <div class="footer">
            <p>&copy; " . date('Y') . " {$businessName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}



