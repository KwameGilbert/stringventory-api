# MessagingCampaignRoute

Source: `src/routes/v1/MessagingCampaignRoute.php`

Base path(s): `/v1/messaging-campaigns`
Controller(s): `App\Controllers\MessagingCampaignController`

## Definition

Messaging campaign routes manage outbound messaging campaigns. They are related to bulk communication, campaign setup, delivery tracking, and customer or user outreach.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/messaging-campaigns` | `MessagingCampaignController::index` | Public | Get all messaging campaigns |
| `POST` | `/v1/messaging-campaigns` | `MessagingCampaignController::create` | Public | Create/Send messaging campaign using MessagingService |
| `DELETE` | `/v1/messaging-campaigns/{id}` | `MessagingCampaignController::delete` | Public | Delete messaging campaign |
| `GET` | `/v1/messaging-campaigns/{id}` | `MessagingCampaignController::show` | Public | Get single messaging campaign |
| `PUT` | `/v1/messaging-campaigns/{id}` | `MessagingCampaignController::update` | Public | Update messaging campaign |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


