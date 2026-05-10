# ExpenseCategoryRoute

Source: `src/routes/v1/ExpenseCategoryRoute.php`

Base path(s): `/v1/expense-categories`
Controller(s): `App\Controllers\ExpenseCategoryController`

## Definition

Expense category routes manage categories used to classify business expenses. They are related to expense tracking, accounting summaries, and financial reports.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/expense-categories` | `ExpenseCategoryController::index` | Authenticated | Get all expense categories |
| `POST` | `/v1/expense-categories` | `ExpenseCategoryController::create` | Authenticated; roles: ceo, manager | Create expense category |
| `DELETE` | `/v1/expense-categories/{id}` | `ExpenseCategoryController::delete` | Authenticated; roles: ceo, manager | Delete expense category |
| `GET` | `/v1/expense-categories/{id}` | `ExpenseCategoryController::show` | Authenticated | Get single expense category |
| `PUT` | `/v1/expense-categories/{id}` | `ExpenseCategoryController::update` | Authenticated; roles: ceo, manager | Update expense category |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


