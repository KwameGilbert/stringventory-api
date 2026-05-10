# ExchangeRateHistoryRoute

Source: `src/routes/v1/ExchangeRateHistoryRoute.php`

Base path(s): `/v1/exchange-rate-histories`
Controller(s): `App\Controllers\ExchangeRateHistoryController`

## Definition

Exchange rate history routes manage stored currency conversion history. They are related to currency settings, financial reporting, and exchange-rate audit trails.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/exchange-rate-histories` | `ExchangeRateHistoryController::index` | Public | Get all exchange rate histories |
| `POST` | `/v1/exchange-rate-histories` | `ExchangeRateHistoryController::create` | Public | Create exchange rate history entry |
| `DELETE` | `/v1/exchange-rate-histories/{id}` | `ExchangeRateHistoryController::delete` | Public | Delete exchange rate history entry |
| `GET` | `/v1/exchange-rate-histories/{id}` | `ExchangeRateHistoryController::show` | Public | Get single exchange rate history entry |
| `PUT` | `/v1/exchange-rate-histories/{id}` | `ExchangeRateHistoryController::update` | Public | Update exchange rate history entry |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


