# UserRoute

Source: `src/routes/v1/UserRoute.php`

Base path(s): `/v1/users`
Controller(s): `App\Controllers\UserController`

## Definition

User routes manage application user accounts. They are related to staff access, roles, profiles, account status, and user administration.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/users` | `UserController::index` | Authenticated; roles: ceo, manager | Get all users |
| `POST` | `/v1/users` | `UserController::create` | Authenticated; roles: ceo, manager | Create new user |
| `DELETE` | `/v1/users/{id}` | `UserController::delete` | Authenticated | Delete user |
| `GET` | `/v1/users/{id}` | `UserController::show` | Authenticated | Get single user by ID |
| `PUT` | `/v1/users/{id}` | `UserController::update` | Authenticated | Update user |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


