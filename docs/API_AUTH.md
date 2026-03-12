# Authentication API Reference

Quick reference guide for all authentication endpoints in the Eventic API.

---

## Base URL
```
http://localhost/v1/auth
```

---

## Endpoints

### 1. Register New User
**POST** `/register`

Creates a new user account and returns authentication tokens.

**Request Body**:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123",
  "role": "attendee"  // Optional: admin, organizer, attendee, pos, scanner
}
```

**Response** (201 Created):
```json
{
  "status": "success",
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "attendee"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "a7f8d9e3c2b1...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**Errors**:
- `400` - Validation failed
- `409` - Email already exists
- `500` - Server error

---

### 2. Login
**POST** `/login`

Authenticate user and receive tokens.

**Request Body**:
```json
{
  "email": "john@example.com",
  "password": "SecurePass123"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "attendee",
      "first_login": false
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "a7f8d9e3c2b1...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**Errors**:
- `400` - Email and password required
- `401` - Invalid credentials
- `403` - Account suspended
- `500` - Server error

**Security Features**:
- Failed login attempts are logged with IP address
- Brute force detection via audit logs
- Last login tracking (timestamp + IP)

---

### 3. Get Current User
**GET** `/me`

Get authenticated user information.

**Headers**:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "User details fetched successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "role": "attendee",
    "status": "active",
    "email_verified": false,
    "created_at": "2025-11-28T10:30:00.000000Z"
  }
}
```

**Errors**:
- `401` - Unauthorized (missing/invalid token)
- `404` - User not found
- `500` - Server error

---

### 4. Refresh Access Token
**POST** `/refresh`

Get a new access token using a refresh token.

**Request Body**:
```json
{
  "refresh_token": "a7f8d9e3c2b1..."
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Token refreshed successfully",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "b8g9e0f4d3c2...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**Errors**:
- `400` - Refresh token required
- `401` - Invalid or expired refresh token
- `500` - Server error

**Security Features**:
- Token rotation (old token is revoked, new one issued)
- Database validation
- Revoked token detection

---

### 5. Logout
**POST** `/logout`

Revoke refresh token and logout user.

**Headers**:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Request Body**:
```json
{
  "refresh_token": "a7f8d9e3c2b1..."
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Logged out successfully",
  "data": []
}
```

**Note**: The access token will continue to work until it expires (max 1 hour). The refresh token is immediately revoked.

---

## Authentication Flow

### Initial Authentication
```
1. User submits credentials to /login
2. Server validates & generates tokens
3. Client stores both tokens securely
4. Client uses access_token for API requests
```

### Making Authenticated Requests
```
Include header:
Authorization: Bearer <access_token>
```

### When Access Token Expires
```
1. API returns 401 Unauthorized
2. Client sends refresh_token to /refresh
3. Server validates & rotates tokens
4. Client updates stored tokens
5. Client retries original request with new access_token
```

### Complete Logout
```
1. Client sends refresh_token to /logout
2. Server revokes token in database
3. Client deletes stored tokens
4. Access token expires naturally
```

---

## Token Lifespans

| Token Type | Default Lifespan | Renewable? | Storage |
|------------|------------------|------------|---------|
| Access Token | 1 hour | ❌ (must use refresh) | Client memory/storage |
| Refresh Token | 7 days | ✅ (via rotation) | Database + Client |
| Password Reset | 1 hour | ❌ | Database |
| Email Verification | 24 hours | ❌ | Database |

---

## Error Response Format

All endpoints return errors in this format:

```json
{
  "status": "error",
  "message": "Human-readable error message",
  "code": 400,
  "data": {
    "field_name": "Specific validation error"
  }
}
```

---

## Security Headers

All requests should include:

```
Content-Type: application/json
Authorization: Bearer <token>  // For protected routes
User-Agent: YourApp/1.0         // For audit logging
X-Device-Name: iPhone 13        // Optional device tracking
```

---

## Rate Limiting

**Login endpoint** is rate-limited:
- Max 5 attempts per minute per IP
- 429 Too Many Requests if exceeded
- Logs all failed attempts for monitoring

---

## Best Practices

### Token Storage

**❌ Don't**:
- Store tokens in localStorage (XSS vulnerable)
- Send tokens in URL parameters
- Log tokens in console

**✅ Do**:
- Use httpOnly cookies or secure storage
- Implement token refresh logic
- Clear tokens on logout
- Use HTTPS only

### Password Requirements

- Minimum 8 characters
- No maximum (up to database limit)
- Recommend: Mix of uppercase, lowercase, numbers, symbols

### Error Handling

```javascript
// Example client-side implementation
async function makeAuthenticatedRequest(url, options = {}) {
  let token = getAccessToken();
  
  let response = await fetch(url, {
    ...options,
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      ...options.headers
    }
  });
  
  // Token expired, try refresh
  if (response.status === 401) {
    const refreshed = await refreshAccessToken();
    if (refreshed) {
      token = getAccessToken();
      response = await fetch(url, {
        ...options,
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          ...options.headers
        }
      });
    }
  }
  
  return response;
}
```

---

## Testing Examples

### Using cURL

**Register**:
```bash
curl -X POST http://localhost/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"SecurePass123"}'
```

**Login**:
```bash
curl -X POST http://localhost/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123"}'
```

**Get User (with token)**:
```bash
curl -X GET http://localhost/v1/auth/me \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN_HERE"
```

### Using Postman

1. Create a new Collection named "Eventic Auth"
2. Set Collection variables:
   - `base_url`: `http://localhost/v1/auth`
   - `access_token`: (empty initially)
   - `refresh_token`: (empty initially)
3. Add requests with:
   - URL: `{{base_url}}/login`
   - Headers: `Authorization: Bearer {{access_token}}`
4. Use Tests tab to auto-save tokens:
```javascript
if (pm.response.code === 200) {
  const data = pm.response.json().data;
  pm.collectionVariables.set("access_token", data.access_token);
  pm.collectionVariables.set("refresh_token", data.refresh_token);
}
```

---

## Audit & Monitoring

All authentication events are logged in the `audit_logs` table:

**Logged Events**:
- `register` - New user registration
- `login` - Successful login
- `login_failed` - Failed login attempt (with reason)
- `logout` - User logout
- `password_reset_requested`
- `password_reset_completed`
- `password_changed`
- `email_verified`

**Metadata Captured**:
- IP address
- User agent
- Timestamp
- Additional context (failure reason, etc.)

**Query Recent Failed Logins**:
```sql
SELECT * FROM audit_logs
WHERE action = 'login_failed'
  AND created_at > NOW() - INTERVAL 1 HOUR
ORDER BY created_at DESC;
```

---

## FAQ

**Q: How long do tokens last?**
A: Access tokens last 1 hour, refresh tokens last 7 days.

**Q: Can I change token expiry times?**
A: Yes, update `JWT_EXPIRE` and `REFRESH_TOKEN_EXPIRE` in `.env`.

**Q: What happens to refresh tokens on password change?**
A: You should manually revoke all user tokens for security.

**Q: How do I logout from all devices?**
A: Call `RefreshToken::revokeAllForUser($userId)` in your code.

**Q: Are passwords visible in the database?**
A: No, they're hashed with Argon2id (industry standard).

**Q: What if someone steals my refresh token?**
A: Tokens are revoked on use (rotation). Monitor audit logs for suspicious activity.

**Q: Can I use this with mobile apps?**
A: Yes, same flow. Store tokens securely (e.g., Keychain on iOS, Keystore on Android).

---

**Last Updated**: 2025-11-30  
**API Version**: 1.0.0
