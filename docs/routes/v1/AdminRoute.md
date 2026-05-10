# AdminRoute

Source: `src/routes/v1/AdminRoute.php`

Base path(s): `/v1/admin`
Controller(s): `App\Controllers\AdminController`

## Definition

Admin routes expose operational system endpoints for administrators. They are related to platform health, runtime metrics, and backend monitoring rather than day-to-day inventory records.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/admin/health` | `AdminController::systemHealth` | Public | Get system health and diagnostics status |
| `GET` | `/v1/admin/metrics` | `AdminController::metrics` | Public | Get master metrics dashboard for platform administrators |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


