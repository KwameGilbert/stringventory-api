# MessagingCampaignRecipientRoute

Source: `src/routes/v1/MessagingCampaignRecipientRoute.php`

Base path(s): `/v1/messaging-campaign-recipients`
Controller(s): `App\Controllers\MessagingCampaignRecipientController`

## Definition

Messaging campaign recipient routes manage recipients attached to messaging campaigns. They are related to campaign delivery, audience tracking, and recipient-level messaging status.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/messaging-campaign-recipients` | `MessagingCampaignRecipientController::index` | Public | Get all messaging campaign recipients |
| `POST` | `/v1/messaging-campaign-recipients` | `MessagingCampaignRecipientController::create` | Public | Create messaging campaign recipient |
| `DELETE` | `/v1/messaging-campaign-recipients/{id}` | `MessagingCampaignRecipientController::delete` | Public | Delete messaging campaign recipient |
| `GET` | `/v1/messaging-campaign-recipients/{id}` | `MessagingCampaignRecipientController::show` | Public | Get single messaging campaign recipient |
| `PUT` | `/v1/messaging-campaign-recipients/{id}` | `MessagingCampaignRecipientController::update` | Public | Update messaging campaign recipient |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


