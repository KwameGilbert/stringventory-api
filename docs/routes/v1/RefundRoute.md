# RefundRoute

Source: `src/routes/v1/RefundRoute.php`

Base path(s): `/v1/refunds`
Controller(s): `App\Controllers\RefundController`

## Definition

Refund routes manage refunds and refund status changes. They are related to orders, customers, payments, financial adjustments, and sales reconciliation.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/refunds` | `RefundController::index` | Authenticated; roles: ceo, manager | Get all refunds |
| `POST` | `/v1/refunds` | `RefundController::create` | Authenticated | Create refund request |
| `GET` | `/v1/refunds/{id}` | `RefundController::show` | Authenticated; roles: ceo, manager | Get single refund |
| `PUT` | `/v1/refunds/{id}/status` | `RefundController::updateStatus` | Authenticated; roles: ceo, manager | Update refund status (Approve/Reject) |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


