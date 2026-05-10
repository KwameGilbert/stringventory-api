# OrderItemRoute

Source: `src/routes/v1/OrderItemRoute.php`

Base path(s): `/v1/order-items`
Controller(s): `App\Controllers\OrderItemController`

## Definition

Order item routes manage individual line items on orders. They are related to products sold, quantities, pricing details, fulfillment, and order composition.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/order-items` | `OrderItemController::index` | Public | Get all order items |
| `POST` | `/v1/order-items` | `OrderItemController::create` | Public | Create order item |
| `DELETE` | `/v1/order-items/{id}` | `OrderItemController::delete` | Public | Delete order item |
| `GET` | `/v1/order-items/{id}` | `OrderItemController::show` | Public | Get single order item |
| `PUT` | `/v1/order-items/{id}` | `OrderItemController::update` | Public | Update order item |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


