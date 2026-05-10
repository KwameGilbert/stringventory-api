# SettingsRoute

Source: `src/routes/v1/SettingsRoute.php`

Base path(s): `/v1/settings`
Controller(s): `App\Controllers\SettingsController`

## Definition

Settings routes manage business, notification, payment, API, and currency settings. They are related to application configuration, exchange rates, integrations, and operational preferences.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/settings/api` | `SettingsController::getApiSettings` | Authenticated | Get API Settings |
| `POST` | `/v1/settings/api/regenerate-key` | `SettingsController::regenerateApiKey` | Authenticated | Regenerate API Key |
| `GET` | `/v1/settings/business` | `SettingsController::getBusinessSettings` | Authenticated | Get Business Settings |
| `PUT` | `/v1/settings/business` | `SettingsController::updateBusinessSettings` | Authenticated | Update Business Settings |
| `GET` | `/v1/settings/currency` | `SettingsController::getCurrencySettings` | Authenticated | Get Currency Settings |
| `PUT` | `/v1/settings/currency` | `SettingsController::updateCurrencySettings` | Authenticated | Update Currency Settings Accepts: { currency: "USD", rates: { "GHS": { "USD": 0.065 }, ... } } Manual rates are logged to exchange_rate_history with source='manual'. |
| `POST` | `/v1/settings/currency/fetch-rates` | `SettingsController::fetchExchangeRates` | Authenticated | Force-fetch latest exchange rates from the API |
| `GET` | `/v1/settings/currency/history` | `SettingsController::getExchangeRateHistory` | Authenticated | Get Exchange Rate History Optional query params: ?base=GHS&target=USD&from=2026-01-01&to=2026-03-30 |
| `GET` | `/v1/settings/notifications` | `SettingsController::getNotificationSettings` | Authenticated | Get Notification Settings |
| `PUT` | `/v1/settings/notifications` | `SettingsController::updateNotificationSettings` | Authenticated | Update Notification Settings |
| `GET` | `/v1/settings/payment` | `SettingsController::getPaymentSettings` | Authenticated | Get Payment Settings |
| `PUT` | `/v1/settings/payment` | `SettingsController::updatePaymentSettings` | Authenticated | Update Payment Settings |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


