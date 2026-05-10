# OrderRoute

Source: `src/routes/v1/OrderRoute.php`

Base path(s): `/v1/orders`
Controller(s): `App\Controllers\OrderController`

## Definition

Order routes manage customer orders and fulfillment lifecycle actions. They are related to sales, customers, inventory deduction, order cancellation, item fulfillment, and complete order fulfillment.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/orders` | `OrderController::index` | Authenticated | Get all orders GET /v1/orders |
| `POST` | `/v1/orders` | `OrderController::create` | Authenticated; roles: ceo, manager, salesperson | Create a new order (Checkout) POST /v1/orders |
| `GET` | `/v1/orders/{id}` | `OrderController::show` | Authenticated | Get single order GET /v1/orders/{id} |
| `POST` | `/v1/orders/{id}/cancel` | `OrderController::cancel` | Authenticated; roles: ceo, manager | Cancel an order POST /v1/orders/{id}/cancel |
| `POST` | `/v1/orders/{id}/fulfill` | `OrderController::fulfill` | Authenticated; roles: ceo, manager, salesperson | Bulk fulfill an order POST /v1/orders/{id}/fulfill |
| `POST` | `/v1/orders/item/{itemId}/fulfill` | `OrderController::fulfillItem` | Authenticated; roles: ceo, manager, salesperson | Fulfill an order item (Partial or Full) POST /v1/orders/item/{itemId}/fulfill |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


