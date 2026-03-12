<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ticket;
use App\Models\ScannerAssignment;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * TicketController
 * Handles ticket viewing and management
 */
class TicketController
{
    /**
     * Get user's tickets
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            
            // Get tickets where the order belongs to the user
            $tickets = Ticket::whereHas('order', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->with(['event', 'ticketType'])->orderBy('created_at', 'desc')->get();
            
            return ResponseHelper::success($response, 'Tickets fetched successfully', $tickets);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch tickets', 500, $e->getMessage());
        }
    }

    /**
     * Get single ticket details
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $user = $request->getAttribute('user');
            
            $ticket = Ticket::with(['event', 'ticketType', 'order'])->find($id);
            
            if (!$ticket) {
                return ResponseHelper::error($response, 'Ticket not found', 404);
            }
            
            // Ensure ticket belongs to user
            if ($ticket->order->user_id !== $user->id) {
                return ResponseHelper::error($response, 'Unauthorized access to ticket', 403);
            }
            
            return ResponseHelper::success($response, 'Ticket details fetched successfully', $ticket->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch ticket', 500, $e->getMessage());
        }
    }

    /**
     * Verify ticket validity (Public/Scanner endpoint)
     */
    public function verify(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            
            if (empty($data['ticket_code'])) {
                return ResponseHelper::error($response, 'Ticket code is required', 400);
            }
            
            $ticket = Ticket::where('ticket_code', $data['ticket_code'])
                            ->with(['event', 'ticketType', 'attendee'])
                            ->first();
            
            if (!$ticket) {
                return ResponseHelper::error($response, 'Invalid ticket code', 404);
            }
            
            // Check if ticket is for a specific event (optional security check)
            if (!empty($data['event_id']) && $ticket->event_id != $data['event_id']) {
                return ResponseHelper::error($response, 'Ticket does not belong to this event', 400);
            }
            
            if ($ticket->status !== Ticket::STATUS_ACTIVE) {
                return ResponseHelper::error($response, "Ticket is {$ticket->status}", 400);
            }
            
            return ResponseHelper::success($response, 'Ticket is valid', [
                'valid' => true,
                'ticket' => $ticket,
                'attendee' => $ticket->attendee,
                'event' => $ticket->event->title
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Verification failed', 500, $e->getMessage());
        }
    }

    /**
     * Admit/Check-in ticket holder (Organizer or Scanner)
     */
    public function admit(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');
            
            if (empty($data['ticket_code'])) {
                return ResponseHelper::error($response, 'Ticket code is required', 400);
            }
            
            $ticket = Ticket::where('ticket_code', $data['ticket_code'])
                            ->with(['event.organizer'])
                            ->first();
            
            if (!$ticket) {
                return ResponseHelper::error($response, 'Invalid ticket code', 404);
            }
            
            // Authorization Check
            $isAuthorized = false;

            // 1. Check if user is the organizer
            if ($ticket->event->organizer->user_id === $user->id) {
                $isAuthorized = true;
            } 
            // 2. Check if user is an assigned scanner
            else if ($user->role === 'scanner') {
                $assignment = ScannerAssignment::where('user_id', $user->id)
                                             ->where('event_id', $ticket->event_id)
                                             ->first();
                if ($assignment) {
                    $isAuthorized = true;
                }
            }

            if (!$isAuthorized) {
                return ResponseHelper::error($response, 'Unauthorized: You do not have permission to admit tickets for this event', 403);
            }
            
            if ($ticket->status === Ticket::STATUS_USED) {
                return ResponseHelper::error($response, 'Ticket already used', 409);
            }
            
            if ($ticket->status === Ticket::STATUS_CANCELLED) {
                return ResponseHelper::error($response, 'Ticket is cancelled', 400);
            }
            
            // Mark as used and record who admitted it
            $ticket->status = Ticket::STATUS_USED;
            $ticket->admitted_by = $user->id;
            $ticket->admitted_at = \Illuminate\Support\Carbon::now();
            $ticket->save();
            
            return ResponseHelper::success($response, 'Ticket admitted successfully', [
                'ticket_code' => $ticket->ticket_code,
                'status' => $ticket->status,
                'admitted_by' => $user->id,
                'admitted_at' => $ticket->admitted_at
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Admission failed', 500, $e->getMessage());
        }
    }
}
