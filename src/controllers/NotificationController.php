<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * NotificationController
 * Handles user notifications
 */
class NotificationController
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            if (!$user) {
                return ResponseHelper::error($response, 'Unauthorized', 401);
            }

            $notifications = Notification::where('userId', $user->id)
                ->orderBy('createdAt', 'desc')
                ->get();

            $unreadCount = Notification::where('userId', $user->id)
                ->where('isRead', false)
                ->count();

            return ResponseHelper::success($response, 'Notifications fetched successfully', [
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
                'totalCount' => $notifications->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch notifications', 500, $e->getMessage());
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            $id = $args['id'];

            $notification = Notification::where('id', $id)
                ->where('userId', $user->id)
                ->first();

            if (!$notification) {
                return ResponseHelper::error($response, 'Notification not found', 404);
            }

            $notification->update([
                'isRead' => true,
                'readAt' => date('Y-m-d H:i:s')
            ]);

            return ResponseHelper::success($response, 'Notification marked as read');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update notification', 500, $e->getMessage());
        }
    }

    /**
     * Mark all notifications as read for the current user
     */
    public function markAllAsRead(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');

            Notification::where('userId', $user->id)
                ->where('isRead', false)
                ->update([
                    'isRead' => true,
                    'readAt' => date('Y-m-d H:i:s')
                ]);

            return ResponseHelper::success($response, 'All notifications marked as read');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update notifications', 500, $e->getMessage());
        }
    }

    /**
     * Delete a notification
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            $id = $args['id'];

            $notification = Notification::where('id', $id)
                ->where('userId', $user->id)
                ->first();

            if (!$notification) {
                return ResponseHelper::error($response, 'Notification not found', 404);
            }

            $notification->delete();

            return ResponseHelper::success($response, 'Notification deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete notification', 500, $e->getMessage());
        }
    }
}
