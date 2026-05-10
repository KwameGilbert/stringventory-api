# MessagingRoute

Source: `src/routes/v1/MessagingRoute.php`

Base path(s): `/v1/messaging`
Controller(s): `App\Controllers\MessagingController`

## Definition

Messaging routes handle direct and bulk messages plus messaging templates. They are related to communication workflows, message history, reusable templates, and outbound notifications.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `POST` | `/v1/messaging/bulk-messages` | `MessagingController::bulkMessages` | Authenticated | Bulk messages |
| `GET` | `/v1/messaging/messages` | `MessagingController::messages` | Authenticated | Messages |
| `POST` | `/v1/messaging/messages` | `MessagingController::sendMessage` | Authenticated | Send message |
| `GET` | `/v1/messaging/messages/{id}` | `MessagingController::messageDetails` | Authenticated | Message details |
| `GET` | `/v1/messaging/templates` | `MessagingController::templates` | Authenticated | Templates |
| `POST` | `/v1/messaging/templates` | `MessagingController::createTemplate` | Authenticated | Create template |
| `DELETE` | `/v1/messaging/templates/{id}` | `MessagingController::deleteTemplate` | Authenticated | Delete template |
| `PUT` | `/v1/messaging/templates/{id}` | `MessagingController::updateTemplate` | Authenticated | Update template |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


