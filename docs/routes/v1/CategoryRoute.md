# CategoryRoute

Source: `src/routes/v1/CategoryRoute.php`

Base path(s): `/v1/categories`
Controller(s): `App\Controllers\CategoryController`

## Definition

Category routes manage product categories. They are related to organizing products for browsing, reporting, inventory grouping, and catalog management.

## Endpoints

| Method | Path | Controller action | Access | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/v1/categories` | `CategoryController::index` | Public | Get all categories |
| `POST` | `/v1/categories` | `CategoryController::create` | Authenticated; roles: ceo, manager | Create category |
| `DELETE` | `/v1/categories/{id}` | `CategoryController::delete` | Authenticated; roles: ceo, manager | Delete category |
| `GET` | `/v1/categories/{id}` | `CategoryController::show` | Public | Get single category |
| `PUT` | `/v1/categories/{id}` | `CategoryController::update` | Authenticated; roles: ceo, manager | Update category |

## Notes

- Authentication and role requirements are derived from route middleware declarations in the route file.
- Request and response payloads should be verified against the controller implementation and model validation rules before publishing as an external API contract.


