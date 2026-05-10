# EmailVerificationTokenRoute

Source: `src/routes/v1/EmailVerificationTokenRoute.php`

Base path(s): `/v1/email-verification-tokens`
Controller(s): `App\Controllers\EmailVerificationTokenController`

## Definition

Email verification token routes manage verification token records. They are related to account verification, user onboarding, and email ownership confirmation.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/email-verification-tokens` | `EmailVerificationTokenController::index` | Public | Get all email verification tokens |
| `POST` | `/v1/email-verification-tokens` | `EmailVerificationTokenController::create` | Public | Create/Send email verification token |
| `DELETE` | `/v1/email-verification-tokens/{id}` | `EmailVerificationTokenController::delete` | Public | Delete email verification token |
| `GET` | `/v1/email-verification-tokens/{id}` | `EmailVerificationTokenController::show` | Public | Get single email verification token |
| `PUT` | `/v1/email-verification-tokens/{id}` | `EmailVerificationTokenController::update` | Public | Update email verification token |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


