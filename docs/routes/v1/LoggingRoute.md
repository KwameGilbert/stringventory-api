# LoggingRoute

Source: `src/routes/v1/LoggingRoute.php`

Base path(s): `/v1/logs`
Controller(s): `App\Controllers\LoggingController`

## Definition

Logging routes expose application log records and log maintenance actions. They are related to diagnostics, system observability, log filtering, statistics, and cleanup.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/logs` | `LoggingController::index` | Authenticated | Get logs with filtering and pagination |
| `GET` | `/v1/logs/{id}` | `LoggingController::show` | Authenticated | Get a specific log entry by ID |
| `POST` | `/v1/logs/clean` | `LoggingController::clean` | Authenticated; roles: ceo, manager | Clean old logs |
| `GET` | `/v1/logs/levels` | `LoggingController::levels` | Authenticated | Get available log levels |
| `GET` | `/v1/logs/stats` | `LoggingController::stats` | Authenticated | Get log statistics |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


