# Authentication Service - Complete Guide

## 🎯 Overview

The authentication service provides a secure, JWT-based authentication system that's **decoupled and reusable** across your application.

### Key Features
- ✅ JWT (JSON Web Token) authentication
- ✅ Access & Refresh token system
- ✅ Password hashing with bcrypt
- ✅ Role-based access control (RBAC)
- ✅ Token expiry management
- ✅ Middleware for route protection

---

## 📁 File Structure

```
src/
├── services/
│   └── AuthService.php          # Core authentication logic
├── controllers/
│   └── AuthController.php       # Auth endpoints (login, register, etc.)
├── middleware/
│   └── AuthMiddleware.php       # JWT validation middleware
├── models/
│   └── User.php                 # User Eloquent model
└── routes/
    └── auth.php                 # Authentication routes
```

---

## 🔧 Setup

### 1. Environment Variables

Add these to your `.env` file:

```env
# JWT Configuration
JWT_SECRET=your-super-secret-key-change-this-in-production
JWT_ALGORITHM=HS256
JWT_EXPIRE=3600              # Access token expiry (1 hour)
REFRESH_TOKEN_EXPIRE=604800  # Refresh token expiry (7 days)
JWT_ISSUER=eventic-api
```

> **Important:** Generate a strong `JWT_SECRET` in production!

```bash
# Generate a random secret
php -r "echo bin2hex(random_bytes(32));"
```

### 2. Register Services in DI Container

In your `public/index.php` or bootstrap file, register the auth service:

```php
use App\Services\AuthService;
use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;

// Register AuthService
$container->set(AuthService::class, function () {
    return new AuthService();
});

// Register AuthController
$container->set(AuthController::class, function ($container) {
    return new AuthController($container->get(AuthService::class));
});

// Register AuthMiddleware
$container->set(AuthMiddleware::class, function ($container) {
    return new AuthMiddleware($container->get(AuthService::class));
});
```

### 3. Load Authentication Routes

In your main routes file:

```php
// Include auth routes
(require __DIR__ . '/../src/routes/auth.php')($app);
```

---

## 🚀 API Endpoints

### 1. Register User
**POST** `/auth/register`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123",
  "role": "attendee"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "attendee"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eX powerAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

---

### 2. Login
**POST** `/auth/login`

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePass123"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "attendee",
      "first_login": true
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

---

### 3. Refresh Token
**POST** `/auth/refresh`

**Request Body:**
```json
{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

---

### 4. Get Current User
**GET** `/auth/me`

**Headers:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": null,
    "role": "attendee",
    "status": "active",
    "email_verified": false,
    "created_at": "2025-01-28T12:00:00.000000Z"
  }
}
```

---

### 5. Logout
**POST** `/auth/logout`

**Headers:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## 🔒 Protecting Routes

To protect any route with authentication:

```php
use App\Middleware\AuthMiddleware;

// Protect a single route
$app->get('/api/protected', [ProtectedController::class, 'index'])
    ->add($container->get(AuthMiddleware::class));

// Protect a group of routes
$app->group('/api/protected', function ($group) {
    $group->get('/users', [UserController::class, 'index']);
    $group->get('/events', [EventController::class, 'index']);
})->add($container->get(AuthMiddleware::class));
```

---

## 📝 Using Authenticated User Data

In your controllers, access the authenticated user:

```php
public function getProfile(Request $request, Response $response): Response
{
    // User data is added by AuthMiddleware
    $user = $request->getAttribute('user');
    
    $userId = $user->id;
    $userEmail = $user->email;
    $userRole = $user->role;
    
    // Your logic here...
}
```

---

## 🧪 Testing with cURL

### Register
```bash
curl -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePass123",
    "role": "attendee"
  }'
```

### Login
```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123"
  }'
```

### Get Current User
```bash
curl -X GET http://localhost:8080/auth/me \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

---

## 🛡️ Security Best Practices

1. **Use HTTPS in production** - Never send tokens over HTTP
2. **Store tokens securely** - Use httpOnly cookies or secure storage
3. **Implement token blacklisting** - For user logout/token revocation
4. **Rate limiting** - Prevent brute force attacks on login
5. **Strong JWT Secret** - Use a long, random string
6. **Short token expiry** - Keep access tokens short-lived (15-60 min)
7. **Password requirements** - Enforce strong passwords (min 8 chars)

---

## 🔄 Token Lifecycle

```
1. User logs in
   ↓
2. Server generates Access Token (short expiry: 1 hour)
   Server generates Refresh Token (long expiry: 7 days)
   ↓
3. Client stores both tokens
   ↓
4. Client uses Access Token for API requests
   ↓
5. Access Token expires
   ↓
6. Client uses Refresh Token to get new Access Token
   ↓
7. Repeat steps 4-6 until Refresh Token expires
   ↓
8. User must log in again
```

---

## 🎭 User Roles

Available roles:
- `admin` - Full system access
- `organizer` - Event management
- `attendee` - Default role, can attend events
- `pos` - Point of sale operations
- `scanner` - Ticket scanning

Check user role in your code:

```php
$user = $request->getAttribute('user');

if ($user->role === 'admin') {
    // Admin-only logic
}

if (in_array($user->role, ['admin', 'organizer'])) {
    // Organizer and admin logic
}
```

---

## 🐛 Common Errors

### 401 Unauthorized - No token provided
**Cause:** Missing Authorization header
**Solution:** Include `Authorization: Bearer <token>` header

### 401 Unauthorized - Invalid or expired token
**Cause:** Token is expired or malformed
**Solution:** Use refresh token to get a new access token

### 409 Conflict - Email already registered
**Cause:** User with email already exists
**Solution:** Use different email or login instead

### 400 Bad Request - Validation errors
**Cause:** Invalid input data
**Solution:** Check request body matches requirements

---

## 📊 Database Schema

The auth system uses the `users` table:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'organizer', 'attendee', 'pos', 'scanner') DEFAULT 'attendee',
    email_verified BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'suspended') DEFAULT 'active',
    first_login BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 🚀 Next Steps

1. **Run migrations** to create the users table
2. **Update `.env`** with your JWT secret
3. **Register services** in your DI container
4. **Test endpoints** using the examples above
5. **Protect your routes** with AuthMiddleware
6. **Build additional features** (email verification, password reset, etc.)

---

## 📚 Additional Resources

- [JWT.io](https://jwt.io/) - JWT debugger and information
- [Slim Framework Docs](https://www.slimframework.com/)
- [Eloquent ORM Docs](https://laravel.com/docs/eloquent)
- [Firebase JWT Library](https://github.com/firebase/php-jwt)

---

Happy coding! 🎉
