# UserSettingRoute

Source: `src/routes/v1/UserSettingRoute.php`

Base path(s): `/v1/user-settings`
Controller(s): `App\Controllers\UserSettingController`

## Definition

User setting routes manage per-user preference records. They are related to user-specific configuration, personalization, and account-level settings.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/user-settings` | `UserSettingController::index` | Public | Get all user settings |
| `POST` | `/v1/user-settings` | `UserSettingController::create` | Public | Create user setting entry |
| `DELETE` | `/v1/user-settings/{id}` | `UserSettingController::delete` | Public | Delete user setting entry |
| `GET` | `/v1/user-settings/{id}` | `UserSettingController::show` | Public | Get single user setting entry |
| `PUT` | `/v1/user-settings/{id}` | `UserSettingController::update` | Public | Update user setting entry |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


