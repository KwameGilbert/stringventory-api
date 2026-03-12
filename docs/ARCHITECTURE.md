# Eventic API Architecture

> A comprehensive overview of the Eventic API system architecture, design patterns, and component organization.

---

## Table of Contents

- [Overview](#overview)
- [Architectural Layers](#architectural-layers)
- [Design Patterns](#design-patterns)
- [Request Lifecycle](#request-lifecycle)
- [Component Details](#component-details)
- [Data Flow](#data-flow)
- [Security Architecture](#security-architecture)

---

## Overview

Eventic follows a **layered architecture** with **separation of concerns**, inspired by modern PHP frameworks like Laravel and Symfony, while maintaining the simplicity and performance of Slim Framework.

### Core Principles

1. **Separation of Concerns** - Each layer has a single, clear responsibility
2. **Dependency Injection** - Loose coupling through DI container
3. **PSR Compliance** - Follows PHP-FIG standards (PSR-4, PSR-7, PSR-11)
4. **Environment-based Configuration** - 12-factor app methodology
5. **Security First** - Built-in protection at every layer

---

## Architectural Layers

```
┌─────────────────────────────────────────────────────┐
│              HTTP REQUEST (Client)                   │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  ENTRY POINT LAYER (public/index.php)              │
│  - Path definitions                                 │
│  - Autoloader initialization                        │
│  - Bootstrap delegation                             │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  BOOTSTRAP LAYER (src/bootstrap/)                   │
│  ┌───────────────────────────────────────────────┐ │
│  │ app.php - Main orchestrator                   │ │
│  │ services.php - DI container registration      │ │
│  │ middleware.php - Middleware pipeline          │ │
│  │ routes.php - Route registration               │ │
│  └───────────────────────────────────────────────┘ │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  MIDDLEWARE LAYER (src/middleware/)                 │
│  - CORS handling                                    │
│  - Authentication (JWT validation)                  │
│  - Rate limiting                                    │
│  - Request/Response logging                         │
│  - Error handling                                   │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  ROUTING LAYER (src/routes/)                        │
│  - Route definitions                                │
│  - Route grouping                                   │
│  - Middleware attachment                            │
│  - Controller delegation                            │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  CONTROLLER LAYER (src/controllers/)                │
│  - Request validation                               │
│  - Business logic delegation                        │
│  - Response formatting                              │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  SERVICE LAYER (src/services/)                      │
│  - Business logic                                   │
│  - Data processing                                  │
│  - External integrations (email, etc.)              │
│  - Token management                                 │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  MODEL LAYER (src/models/)                          │
│  - Data structure                                   │
│  - Database interactions (Eloquent ORM)             │
│  - Relationships                                    │
│  - Validation rules                                 │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│  DATABASE LAYER                                     │
│  - MySQL / PostgreSQL                               │
│  - Eloquent Query Builder                           │
│  - Migrations (Phinx)                               │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│              HTTP RESPONSE (Client)                 │
└─────────────────────────────────────────────────────┘
```

---

## Design Patterns

### 1. **Dependency Injection (DI)**

All components receive their dependencies through constructor injection, managed by PHP-DI container.

**Example:**
```php
// Service depends on EmailService
class AuthService {
    private EmailService $emailService;
    
    public function __construct(EmailService $emailService) {
        $this->emailService = $emailService;
    }
}

// Registered in container
$container->set(AuthService::class, function($c) {
    return new AuthService($c->get(EmailService::class));
});
```

### 2. **Repository Pattern** (via Eloquent)

Models act as repositories, abstracting database operations.

```php
// Instead of raw SQL
User::where('email', $email)->first();
User::create($data);
```

### 3. **Service Layer Pattern**

Business logic is encapsulated in service classes, keeping controllers thin.

```php
class AuthController {
    private AuthService $authService;
    
    public function login(Request $req, Response $res): Response {
        // Controller delegates to service
        $tokens = $this->authService->generateTokens($user);
        return ResponseHelper::success($res, 'Login successful', $tokens);
    }
}
```

### 4. **Middleware Pattern**

Cross-cutting concerns implemented as middleware pipeline.

```php
Request → CORS → Rate Limit → Auth → Controller → Response
```

### 5. **Factory Pattern**

Used for object creation (LoggerFactory, AppFactory).

```php
$logger = LoggerFactory::create('App');
$app = AppFactory::create();
```

---

## Request Lifecycle

### Complete Flow

```
1. HTTP Request arrives at public/index.php
   ↓
2. Constants defined (BASE, ROUTE, etc.)
   ↓
3. Composer autoloader loaded
   ↓
4. Bootstrap (src/bootstrap/app.php) called
   ├── 4a. Environment variables loaded (.env)
   ├── 4b. Configuration loaded (config/AppConfig.php)
   ├── 4c. Environment validation (required vars check)
   ├── 4d. DI Container created
   ├── 4e. Database bootstrapped (Eloquent)
   ├── 4f. Loggers initialized
   ├── 4g. Services registered (services.php)
   ├── 4h. Slim App created
   ├── 4i. Base path set
   ├── 4j. Middleware registered (middleware.php)
   └── 4k. Routes registered (routes.php)
   ↓
5. App returned to index.php
   ↓
6. Request enters middleware pipeline
   ├── 6a. Content-Length middleware
   ├── 6b. CORS middleware
   ├── 6c. HTTP Logger middleware
   ├── 6d. Error Handler middleware
   └── 6e. Auth middleware (if protected route)
   ↓
7. Route matched
   ↓
8. Controller method invoked
   ↓
9. Service layer called
   ↓
10. Model/Database interaction
   ↓
11. Response created (via ResponseHelper)
   ↓
12. Response passes back through middleware
   ↓
13. HTTP Response sent to client
   ↓
14. Request logged
```

### Example: Login Request

```
POST /v1/auth/login
{
  "email": "user@example.com",
  "password": "password123"
}

 1. index.php receives request
 ↓
 2. Bootstrap initializes app
 ↓
 3. Middleware pipeline:
    - CORS headers added
    - Rate limit checked (5 attempts/min)
    - Request logged
 ↓
 4. Route matched: POST /v1/auth/login → AuthController::login
 ↓
 5. AuthController::login() called
    - Validates input
    - Calls AuthService::authenticate()
 ↓
 6. AuthService::authenticate()
    - Finds user via User model
    - Verifies password (Argon2id)
    - Generates JWT access token
    - Creates refresh token in DB
    - Logs event to audit_logs
 ↓
 7. AuthController returns ResponseHelper::success()
 ↓
 8. Response logged
 ↓
 9. JSON response sent:
    {
      "success": true,
      "message": "Login successful",
      "data": {
        "access_token": "eyJ0eXAi...",
        "refresh_token": "a3f8b2d...",
        "user": { ... }
      }
    }
```

---

## Component Details

### Entry Point (`public/index.php`)

**Responsibilities:**
- Define path constants
- Load Composer autoloader
- Delegate to bootstrap
- Run the application

**Size:** ~24 lines (minimal, clean)

### Bootstrap Layer (`src/bootstrap/`)

#### `app.php` - Main Orchestrator
- Loads environment variables
- Validates configuration
- Creates DI container
- Bootstraps database
- Registers services
- Configures middleware
- Registers routes

#### `services.php` - Service Registration
- EmailService
- AuthService
- PasswordResetService
- VerificationService
- Controllers (AuthController, UserController)
- Middleware (AuthMiddleware, RateLimitMiddleware)

#### `middleware.php` - Middleware Pipeline
- Error handling
- HTTP logging
- CORS configuration
- Content-length

#### `routes.php` - Route Registration
- Welcome route (`/`)
- Health check (`/health`)
- API route loader (`/v1/*`)
- 404 handler

### Controller Layer (`src/controllers/`)

**Thin controllers** - Minimal logic, delegates to services

**Responsibilities:**
- Request validation
- Service delegation
- Response formatting (via ResponseHelper)

**Example:**
```php
public function create(Request $req, Response $res): Response {
    $data = $req->getBody();
    
    // Validate
    if (empty($data['email'])) {
        return ResponseHelper::error($res, 'Email required', 400);
    }
    
    // Delegate to service
    $user = User::create($data);
    
    // Format response
    return ResponseHelper::success($res, 'Created', $user->toArray(), 201);
}
```

### Service Layer (`src/services/`)

**Fat services** - Contains all business logic

**Examples:**
- **AuthService**: Token generation, password hashing, user authentication
- **EmailService**: Email sending, template rendering
- **PasswordResetService**: Token generation, validation, password update
- **VerificationService**: Email verification logic

### Model Layer (`src/models/`)

**Eloquent models** - Database representation

**Key Models:**
- **User**: User authentication and profile
- **RefreshToken**: Database-backed refresh tokens
- **PasswordReset**: Password reset tokens
- **AuditLog**: Security event logging

**Features:**
- Relationships (hasMany, belongsTo)
- Scopes (active(), verified())
- Mutators (setPasswordAttribute)
- Casts (dates, booleans, JSON)

### Middleware Layer (`src/middleware/`)

**Cross-cutting concerns**

- **AuthMiddleware**: JWT token validation
- **RateLimitMiddleware**: Brute force protection
- **RequestResponseLoggerMiddleware**: HTTP logging
- **CORS**: Cross-origin request handling (inline middleware)

---

## Data Flow

### Authentication Flow

```
┌──────────┐
│  Client  │
└────┬─────┘
     │ POST /auth/login
     │ {email, password}
     ▼
┌─────────────────┐
│ AuthController  │
└────┬────────────┘
     │ validate & delegate
     ▼
┌─────────────────┐
│  AuthService    │ ──► User::findByEmail()
└────┬────────────┘      │
     │ ◄───────────────── User model
     │ verifyPassword()
     │ generateTokens()
     ▼
┌──────────────────┐
│ RefreshToken     │ ◄── Create DB entry
│ Model            │
└──────────────────┘
     │
     ▼
┌──────────────────┐
│ AuditLog Model   │ ◄── Log login event
└──────────────────┘
     │
     ▼
┌─────────────────┐
│ Response        │
│ {tokens, user}  │
└────┬────────────┘
     │
     ▼
┌──────────┐
│  Client  │
└──────────┘
```

### Protected Resource Access

```
┌──────────┐
│  Client  │
└────┬─────┘
     │ GET /api/protected
     │ Authorization: Bearer <token>
     ▼
┌─────────────────────┐
│  AuthMiddleware     │
└────┬────────────────┘
     │ Extract token
     │ Validate via AuthService
     ▼
┌─────────────────────┐
│  AuthService        │ ──► JWT::decode()
└────┬────────────────┘
     │ token valid?
     ├── Yes ──► Add user to request
     │           ▼
     │      ┌─────────────┐
     │      │ Controller  │
     │      └─────────────┘
     │
     └── No ──► 401 Unauthorized
```

---

## Security Architecture

### Defense in Depth

```
Layer 1: Network
├── HTTPS/TLS encryption
└── Firewall rules

Layer 2: Application Entry
├── Rate limiting
├── CORS policy
└── Input sanitization

Layer 3: Authentication
├── JWT token validation
├── Argon2id password hashing
└── Refresh token rotation

Layer 4: Authorization
├── Role-based access control
├── Resource ownership checks
└── Permission validation

Layer 5: Data
├── Parameterized queries (Eloquent)
├── Input validation
└── Output encoding

Layer 6: Monitoring
├── Audit logging
├── Error logging
└── Failed login tracking
```

### Security Components

1. **Token Security**
   - Access tokens (JWT) - Short-lived (1 hour)
   - Refresh tokens (DB-backed) - Long-lived (7 days), revocable
   - Token rotation on refresh

2. **Password Security**
   - Argon2id hashing (64MB memory, 4 iterations)
   - Minimum complexity requirements
   - Secure reset tokens (1 hour expiry)

3. **Rate Limiting**
   - File-based storage (upgrade to Redis for production)
   - Per-IP + per-route tracking
   - Configurable limits via environment

4. **Audit Trail**
   - All authentication events logged
   - IP address and User-Agent tracking
   - Failed attempt monitoring

---

## Configuration Management

### Environment-Based Config

```
Development (.env with APP_ENV=development)
    ↓
Uses LOCAL_DB_* variables
    ↓
Debug logging enabled
    ↓
Detailed error messages

Production (.env with APP_ENV=production)
    ↓
Uses PROD_DB_* variables
    ↓
Error logging only
    ↓
Generic error messages
```

### Config Loading Order

1. Environment variables loaded from `.env`
2. Config files load and process env vars
3. Services read config via dependency injection
4. Runtime: Services access via `$_ENV` or injected config

---

## Scalability Considerations

### Horizontal Scaling

- **Stateless architecture** - No session state on app servers
- **Database connection pooling** - Reuse connections
- **Token-based auth** - No shared session storage needed
- **Load balancer ready** - Any server can handle any request

### Performance Optimizations

- **Opcode caching** - PHP opcache recommended
- **Database query optimization** - Eloquent N+1 prevention
- **Response caching** - Cache-Control headers
- **Log rotation** - Prevent disk space issues

### Future Enhancements

- **Redis integration** - For rate limiting and caching
- **Queue system** - For email sending, background jobs
- **CDN integration** - For static assets
- **Microservices split** - Separate auth service, event service

---

## Development Workflow

```
1. Feature Request
   ↓
2. Create Migration (if DB change)
   ↓
3. Create/Update Model
   ↓
4. Create Service Layer Logic
   ↓
5. Create Controller
   ↓
6. Register Service in DI Container
   ↓
7. Create Routes
   ↓
8. Write Tests
   ↓
9. Documentation
   ↓
10. Code Review
   ↓
11. Merge to Main
   ↓
12. Deploy
```

---

## Conclusion

Eventic's architecture prioritizes:

✅ **Maintainability** - Clear separation, easy to modify  
✅ **Scalability** - Stateless, horizontally scalable  
✅ **Security** - Defense in depth, secure by default  
✅ **Testability** - DI makes unit testing easy  
✅ **Performance** - Optimized at every layer  

This architecture supports rapid development while maintaining production-grade quality.
