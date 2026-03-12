# Bootstrap Files Structure

This directory contains the application bootstrap files that initialize and configure the Eventic API.

## Files Overview

### `app.php`
Main application bootstrap file that orchestrates all initialization steps:
- Loads environment variables
- Validates required configuration
- Sets up DI container
- Bootstraps database (Eloquent ORM)
- Registers services, controllers, and middleware
- Configures Slim application
- Registers routes

### `services.php`
Service container registration:
- **Services**: EmailService, AuthService, PasswordResetService, VerificationService
- **Controllers**: AuthController, UserController
- **Middleware**: AuthMiddleware, RateLimitMiddleware

### `middleware.php`
Middleware configuration and registration:
- Error handling middleware
- HTTP request/response logging
- CORS configuration
- Content-Length middleware

### `routes.php`
Basic application routes:
- Welcome/status route (`/`)
- Health check route (`/health`)
- API routes loader (`/v1/*`)
- 404 Not Found handler

## Loading Order

1. `public/index.php` defines paths and loads autoloader
2. `bootstrap/app.php` is loaded and returns configured `$app`
3. `app.php` loads in sequence:
   - Environment variables (`.env`)
   - Configuration files (`config/*.php`)
   - Service registrations (`services.php`)
   - Middleware setup (`middleware.php`)
   - Routes registration (`routes.php`)
4. `public/index.php` calls `$app->run()`

## Benefits of This Structure

✅ **Separation of Concerns**: Each file has a single, clear responsibility  
✅ **Easy Maintenance**: Configuration changes are isolated to specific files  
✅ **Clean Entry Point**: `index.php` is minimal and easy to understand  
✅ **Testability**: Bootstrap files can be tested independently  
✅ **Scalability**: Easy to add new services or middleware  

## Adding New Components

### Adding a New Service
Edit `bootstrap/services.php`:
```php
$container->set(MyNewService::class, function ($container) {
    return new MyNewService($container->get(SomeDependency::class));
});
```

### Adding a New Middleware
Edit `bootstrap/middleware.php`:
```php
$app->add(new MyCustomMiddleware());
```

### Adding a New Configuration
Create `config/myconfig.php`:
```php
<?php
return [
    'setting1' => $_ENV['MY_SETTING'] ?? 'default',
    // ...
];
```

Then load it in `bootstrap/app.php` or relevant files.
