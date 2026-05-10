# SubscriptionRoute

Source: `src/routes/v1/SubscriptionRoute.php`

Base path(s): `/v1/subscriptions`
Controller(s): `App\Controllers\SubscriptionController`

## Definition

Subscription routes manage business subscription records. They are related to plans, billing status, business access, and subscription lifecycle management.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/subscriptions` | `SubscriptionController::index` | Public | Get all subscriptions |
| `POST` | `/v1/subscriptions` | `SubscriptionController::create` | Public | Create subscription |
| `DELETE` | `/v1/subscriptions/{id}` | `SubscriptionController::delete` | Public | Delete subscription |
| `GET` | `/v1/subscriptions/{id}` | `SubscriptionController::show` | Public | Get single subscription |
| `PUT` | `/v1/subscriptions/{id}` | `SubscriptionController::update` | Public | Update subscription |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


