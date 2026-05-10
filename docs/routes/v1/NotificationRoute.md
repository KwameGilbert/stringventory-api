# NotificationRoute

Source: `src/routes/v1/NotificationRoute.php`

Base path(s): `/v1/notifications`
Controller(s): `App\Controllers\NotificationController`

## Definition

Notification routes manage user notifications and push subscription actions. They are related to in-app alerts, read status, notification cleanup, and browser/device subscriptions.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/notifications` | `NotificationController::index` | Authenticated | Get all notifications for the authenticated user |
| `DELETE` | `/v1/notifications/{id}` | `NotificationController::delete` | Authenticated | Delete a notification |
| `POST` | `/v1/notifications/{id}/read` | `NotificationController::markAsRead` | Authenticated | Mark a notification as read |
| `DELETE` | `/v1/notifications/delete-all` | `NotificationController::deleteAll` | Authenticated | Delete all notifications for the current user |
| `POST` | `/v1/notifications/read-all` | `NotificationController::markAllAsRead` | Authenticated | Mark all notifications as read for the current user |
| `POST` | `/v1/notifications/subscribe` | `NotificationController::subscribe` | Authenticated | Save a browser push subscription for the authenticated user Expected body: { endpoint, expirationTime, keys: { p256dh, auth } } |
| `DELETE` | `/v1/notifications/unsubscribe` | `NotificationController::unsubscribe` | Authenticated | Remove a browser push subscription for the authenticated user Expected body: { endpoint } |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


