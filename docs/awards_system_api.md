# Awards System Controllers Documentation

This document provides a comprehensive overview of all awards system controllers, endpoints, and functionality.

## Table of Contents
1. [AwardCategoryController](#awardcategorycontroller)
2. [AwardNomineeController](#awardnomineecontroller)
3. [AwardVoteController](#awardvotecontroller)
4. [Authentication & Authorization](#authentication--authorization)
5. [Usage Examples](#usage-examples)

---

## AwardCategoryController

Manages award categories within events.

### Endpoints

#### 1. List Categories for Event
```
GET /v1/events/{eventId}/award-categories
Query Params: include_results=true|false
```
- Returns all categories for an event, ordered by display_order
- Optional: Include nominees and vote counts with `include_results=true`
- **Public Access**

#### 2. Get Category Details
```
GET /v1/award-categories/{id}
Query Params: include_results=true|false
```
- Returns single category details
- Optional: Include nominees and results
- **Public Access**

#### 3. Create Category
```
POST /v1/events/{eventId}/award-categories
Body: {
  "name": "Best Actor",
  "description": "Award for best acting performance",
  "image": "optional image URL",
  "cost_per_vote": 5.00,
  "voting_start": "2025-12-20 00:00:00",
  "voting_end": "2025-12-31 23:59:59",
  "status": "active",
  "display_order": 1
}
```
- Creates new award category
- **Requires Auth**: Organizer/Admin only
- Auto-generates display_order if not provided
- Defaults: cost_per_vote=1.00, status=active

#### 4. Update Category
```
PUT /v1/award-categories/{id}
Body: { ...updatable fields }
```
- Updates category details
- **Requires Auth**: Event owner/Admin only
- Cannot change event_id

#### 5. Delete Category
```
DELETE /v1/award-categories/{id}
```
- Deletes category
- **Requires Auth**: Event owner/Admin only
- **Restriction**: Cannot delete categories with paid votes

#### 6. Get Category Statistics
```
GET /v1/award-categories/{id}/stats
```
- Returns voting statistics for the category
- Response includes:
  - total_nominees
  - total_votes
  - total_revenue
  - paid_votes
  - pending_votes
  - is_voting_active
- **Public Access**

#### 7. Reorder Categories
```
POST /v1/award-categories/awards/{awardId}/reorder
Body: {
  "order": [3, 1, 2, 5, 4]  // Array of category IDs in new order
}
```
- Reorders categories for an award
- **Requires Auth**: Award owner/Admin only

---

## Complete documentation continues with all other controllers and examples...
