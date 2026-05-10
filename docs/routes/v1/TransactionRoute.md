# TransactionRoute

Source: `src/routes/v1/TransactionRoute.php`

Base path(s): `/v1/transactions`
Controller(s): `App\Controllers\TransactionController`

## Definition

Transaction routes expose transaction records. They are related to payments, refunds, orders, purchases, and financial activity tracking.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/transactions` | `TransactionController::index` | Authenticated | Index |
| `GET` | `/v1/transactions/{id}` | `TransactionController::show` | Authenticated | Show |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


