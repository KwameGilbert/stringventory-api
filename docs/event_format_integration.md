# Event Format Column Integration

## Summary
Successfully integrated the new `event_format` column into the Event model and EventController to support both ticketing events and awards events.

## Changes Made

### 1. Event Model (`src/models/Event.php`)
- **Added PHPDoc property**: Added `@property string $event_format` to document the new column
- **Field already in fillable array**: `event_format` was already present in the `$fillable` array
- **Added constants**:
  ```php
  const FORMAT_TICKETING = 'ticketing';
  const FORMAT_AWARDS = 'awards';
  ```
- **Updated helper methods** to use constants:
  - `isAwardsEvent()`: Returns `true` if `event_format === FORMAT_AWARDS`
  - `isTicketingEvent()`: Returns `true` if `event_format === FORMAT_TICKETING` or `null`

### 2. EventController (`src/controllers/EventController.php`)

#### Create Method
- **Added default value**: Sets `event_format` to `'ticketing'` if not provided
- **Added validation**: Validates that `event_format` must be either `'ticketing'` or `'awards'`
- **Returns error** if invalid value is provided

#### Update Method  
- **Added validation**: Validates `event_format` if provided in update data
- **Returns error** if invalid value is provided

## Database Schema
The migration file `20251213160700_add_event_format_column.php` adds:
```sql
ALTER TABLE events 
ADD COLUMN event_format ENUM('ticketing', 'awards') 
DEFAULT 'ticketing' NOT NULL 
AFTER event_type_id;
```

## Usage

### Creating an Event
**Default (Ticketing Event):**
```json
{
  "title": "My Concert",
  "start_time": "2025-12-20 19:00:00",
  "end_time": "2025-12-20 23:00:00"
  // event_format defaults to 'ticketing'
}
```

**Awards Event:**
```json
{
  "title": "Annual Awards",
  "event_format": "awards",
  "start_time": "2025-12-20 19:00:00",
  "end_time": "2025-12-20 23:00:00"
}
```

### Checking Event Format
```php
$event = Event::find($id);

if ($event->isAwardsEvent()) {
    // Handle awards event logic
    $categories = $event->awardCategories;
    $votes = $event->awardVotes;
}

if ($event->isTicketingEvent()) {
    // Handle ticketing event logic
    $ticketTypes = $event->ticketTypes;
    $tickets = $event->tickets;
}
```

## Relationships Available

### For Ticketing Events:
- `$event->ticketTypes` - Get all ticket types
- `$event->tickets` - Get all tickets sold

### For Awards Events:
- `$event->awardCategories` - Get all award categories
- `$event->awardNominees` - Get all nominees
- `$event->awardVotes` - Get all votes

## Validation
Both create and update endpoints will return error 400 if:
- Invalid `event_format` value is provided (must be 'ticketing' or 'awards')

Example error response:
```json
{
  "status": "error",
  "message": "Invalid event_format value. Allowed values: ticketing, awards",
  "code": 400
}
```

## Next Steps
The Event model and controller are now fully configured to handle the `event_format` field. The frontend can now:
1. Specify event format when creating events
2. Update event format when editing events  
3. Filter/display events based on their format
4. Show appropriate UI (ticketing vs awards) based on event format
