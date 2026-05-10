# PlanRoute

Source: `src/routes/v1/PlanRoute.php`

Base path(s): `/v1/plans`
Controller(s): `App\Controllers\PlanController`

## Definition

Plan routes manage subscription plan records. They are related to pricing tiers, business subscriptions, feature access, and billing configuration.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/plans` | `PlanController::index` | Public | Get all plans |
| `POST` | `/v1/plans` | `PlanController::create` | Public | Create plan |
| `DELETE` | `/v1/plans/{id}` | `PlanController::delete` | Public | Delete plan |
| `GET` | `/v1/plans/{id}` | `PlanController::show` | Public | Get single plan |
| `PUT` | `/v1/plans/{id}` | `PlanController::update` | Public | Update plan |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


