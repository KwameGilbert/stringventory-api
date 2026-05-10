# InventoryRoute

Source: `src/routes/v1/InventoryRoute.php`

Base path(s): `/v1/inventory`
Controller(s): `App\Controllers\InventoryController`

## Definition

Inventory routes expose stock-level records and stock adjustment actions. They are related to products, purchases, order fulfillment, and inventory control.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/inventory` | `InventoryController::index` | Authenticated | Get all inventory levels |
| `GET` | `/v1/inventory/{id}` | `InventoryController::show` | Authenticated | Get inventory for specific product |
| `POST` | `/v1/inventory/adjust` | `InventoryController::adjust` | Authenticated; roles: ceo, manager | Adjust stock level |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


