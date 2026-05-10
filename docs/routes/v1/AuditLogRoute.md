# AuditLogRoute

Source: `src/routes/v1/AuditLogRoute.php`

Base path(s): `/v1/audit-logs`
Controller(s): `App\Controllers\AuditLogController`

## Definition

Audit log routes expose recorded system and user activity. They are related to traceability, compliance, troubleshooting, and reviewing important actions performed inside the API.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/audit-logs` | `AuditLogController::index` | Authenticated | Index |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


