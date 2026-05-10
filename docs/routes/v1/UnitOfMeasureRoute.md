# UnitOfMeasureRoute

Source: `src/routes/v1/UnitOfMeasureRoute.php`

Base path(s): `/v1/units-of-measure`
Controller(s): `App\Controllers\UnitOfMeasureController`

## Definition

Unit of measure routes manage measurement units used by products. They are related to product quantities, inventory counts, purchasing, and sales units.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/units-of-measure` | `UnitOfMeasureController::index` | Authenticated | Get all units of measure |
| `POST` | `/v1/units-of-measure` | `UnitOfMeasureController::create` | Authenticated; roles: ceo, manager | Create a unit of measure |
| `DELETE` | `/v1/units-of-measure/{id}` | `UnitOfMeasureController::delete` | Authenticated; roles: ceo, manager | Delete a unit of measure |
| `GET` | `/v1/units-of-measure/{id}` | `UnitOfMeasureController::show` | Authenticated | Get a single unit of measure |
| `PUT` | `/v1/units-of-measure/{id}` | `UnitOfMeasureController::update` | Authenticated; roles: ceo, manager | Update a unit of measure |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


