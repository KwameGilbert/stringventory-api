# ProductRoute

Source: `src/routes/v1/ProductRoute.php`

Base path(s): `/v1/products`
Controller(s): `App\Controllers\ProductController`

## Definition

Product routes manage product catalog records. They are related to categories, suppliers, units of measure, inventory levels, low-stock checks, expiry checks, purchases, and orders.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/products` | `ProductController::index` | Authenticated | Get all products with relations |
| `POST` | `/v1/products` | `ProductController::create` | Authenticated; roles: ceo, manager | Create product |
| `DELETE` | `/v1/products/{id}` | `ProductController::delete` | Authenticated; roles: ceo, manager | Delete product |
| `GET` | `/v1/products/{id}` | `ProductController::show` | Authenticated | Get single product |
| `PUT` | `/v1/products/{id}` | `ProductController::update` | Authenticated; roles: ceo, manager | Update product |
| `GET` | `/v1/products/expiring` | `ProductController::expiring` | Authenticated | Get expiring products |
| `GET` | `/v1/products/low-stock` | `ProductController::lowStock` | Authenticated | Get low stock products |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


