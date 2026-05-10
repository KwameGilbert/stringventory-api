# PurchaseItemRoute

Source: `src/routes/v1/PurchaseItemRoute.php`

Base path(s): `/v1/purchase-items`
Controller(s): `App\Controllers\PurchaseItemController`

## Definition

Purchase item routes manage line items on purchases. They are related to supplier purchases, received products, costs, quantities, and inventory restocking.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/purchase-items` | `PurchaseItemController::index` | Public | Get all purchase items |
| `POST` | `/v1/purchase-items` | `PurchaseItemController::create` | Public | Create purchase item |
| `DELETE` | `/v1/purchase-items/{id}` | `PurchaseItemController::delete` | Public | Delete purchase item |
| `GET` | `/v1/purchase-items/{id}` | `PurchaseItemController::show` | Public | Get single purchase item |
| `PUT` | `/v1/purchase-items/{id}` | `PurchaseItemController::update` | Public | Update purchase item |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


