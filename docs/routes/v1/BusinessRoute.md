# BusinessRoute

Source: `src/routes/v1/BusinessRoute.php`

Base path(s): `/v1/businesses`
Controller(s): `App\Controllers\BusinessController`

## Definition

Business routes manage business records in the system. They are related to tenant or company-level setup data used by users, subscriptions, settings, and operational configuration.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/businesses` | `BusinessController::index` | Public | Get all businesses |
| `POST` | `/v1/businesses` | `BusinessController::create` | Public | Create business |
| `DELETE` | `/v1/businesses/{id}` | `BusinessController::delete` | Public | Delete business |
| `GET` | `/v1/businesses/{id}` | `BusinessController::show` | Public | Get single business |
| `PUT` | `/v1/businesses/{id}` | `BusinessController::update` | Public | Update business |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


