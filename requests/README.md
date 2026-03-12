# Eventic API Request Collection

This folder contains HTTP request files for testing all API endpoints in the Eventic platform.

## How to Use

These `.http` files can be used with:
- **VS Code REST Client Extension** (recommended)
- **JetBrains HTTP Client** (IntelliJ, WebStorm, PhpStorm)
- Any tool that supports `.http` file format

### Setup

1. Replace the `@authToken` variable in each file with your actual JWT token
2. Replace `@baseUrl` if your API is not running on `http://localhost:8000`
3. Run individual requests by clicking "Send Request" above each `###` block

---

## File Overview

| File | Description | Auth Required |
|------|-------------|---------------|
| `auth.http` | Authentication (register, login, password reset) | Partial |
| `admin.http` | Admin dashboard, user/event/award management | Admin only |
| `events.http` | Event CRUD, search, featured events | Partial |
| `awards.http` | Award CRUD, search, leaderboard | Partial |
| `award-categories.http` | Award category management | Partial |
| `award-nominees.http` | Nominee management | Partial |
| `award-votes.http` | Voting and vote statistics | Partial |
| `organizers.http` | Organizer profile, dashboard, finance | Partial |
| `payouts.http` | Organizer payout requests, admin payout management | Yes |
| `orders.http` | Ticket order creation, payment, verification | Yes |
| `tickets.http` | Ticket viewing, verification, check-in | Partial |
| `ticket-types.http` | Ticket type management for events | Partial |
| `attendees.http` | Attendee profile management | Yes |
| `scanners-pos.http` | Scanner and POS user management | Organizer |
| `utils.http` | Utility endpoints (image conversion) | No |

---

## API Base URL

```
Production: https://api.eventic.com/v1
Development: http://localhost:8000/v1
```

---

## Authentication

Most endpoints require a JWT token in the Authorization header:

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Getting a Token

1. Use the login endpoint in `auth.http`:
```http
POST http://localhost:8000/v1/auth/login
Content-Type: application/json

{
    "email": "your@email.com",
    "password": "yourpassword"
}
```

2. Copy the `token` from the response
3. Replace `@authToken` variable in the relevant file

---

## User Roles

| Role | Access Level |
|------|--------------|
| `super_admin` | Full access to everything |
| `admin` | Admin dashboard, user management, approvals |
| `organizer` | Create events/awards, manage own content, finance |
| `attendee` | Purchase tickets, vote, view own orders |
| `support` | Customer support access |
| `scanner` | Scan/verify tickets at events |
| `pos` | Sell tickets at point of sale |

---

## Common Response Format

All API responses follow this structure:

### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        // Response data here
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

---

## Pagination

Endpoints that return lists support pagination:

```http
GET /v1/events?page=1&per_page=20
```

Response includes:
```json
{
    "data": {
        "items": [...],
        "pagination": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 20,
            "total": 100
        }
    }
}
```

---

## File Upload

For endpoints that accept file uploads, use `multipart/form-data`:

```http
POST http://localhost:8000/v1/events
Content-Type: multipart/form-data
Authorization: Bearer {token}

title=Event Title
description=Event description
cover_image=@/path/to/image.jpg
```

---

## Quick Reference

### Most Common Endpoints

| Action | Method | Endpoint |
|--------|--------|----------|
| Login | POST | `/v1/auth/login` |
| Get current user | GET | `/v1/auth/me` |
| List events | GET | `/v1/events` |
| Get event | GET | `/v1/events/{id}` |
| Create event | POST | `/v1/events` |
| List awards | GET | `/v1/awards` |
| Cast vote | POST | `/v1/votes/nominees/{id}` |
| Create order | POST | `/v1/orders` |
| Verify ticket | POST | `/v1/tickets/verify` |
| Organizer dashboard | GET | `/v1/organizers/data/dashboard` |
| Admin dashboard | GET | `/v1/admin/dashboard` |

---

## Version

API Version: v1
Last Updated: December 2025
