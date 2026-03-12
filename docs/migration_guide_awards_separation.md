# 🚀 Phinx Migration Guide - Awards System Separation

## ✅ Migration Fixed!

The Phinx migration has been updated to handle **both scenarios**:
1. ✅ **Databases with existing awards events** - Migrates data properly
2. ✅ **Empty/new databases** - Creates clean structure

---

## 📝 Running the Migration

### **Command:**
```bash
composer phinx-migrate
```

OR directly:
```bash
vendor/bin/phinx migrate
```

---

## 🔧 What the Migration Does

### **If Awards Events Exist:**
1. Creates `awards` table
2. Migrates awards events → awards table
3. Updates `award_categories.event_id` → `award_id`
4. Updates `award_nominees.event_id` → `award_id`
5. Updates `award_votes.event_id` → `award_id`
6. Deletes migrated events
7. Removes `event_format` column
8. Creates `awards_images` table

### **If No Awards Events:**
1. Creates `awards` table (empty)
2. Renames columns:
   - `award_categories.event_id` → `award_id`
   - `award_nominees.event_id` →` award_id`
   - `award_votes.event_id` → `award_id`
3. Removes `event_format` column
4. Creates `awards_images` table

---

## ⏮️ Rolling Back

If you need to undo the migration:

```bash
composer phinx-rollback
```

OR:
```bash
vendor/bin/phinx rollback
```

This will:
- Restore `event_format` column
- Migrate awards back to events
- Restore `event_id` columns
- Drop `awards` and `awards_images` tables

---

##  **After Migration**

### **What's Changed:**

**Events Table:**
```
✅ NO MORE event_format column
✅ Only ticketing events
```

**Awards Table:**
```
✅ NEW independent table
✅ Contains ceremony_date, voting_start, voting_end
✅ Separate from events
```

**Award Categories/Nominees/Votes:**
```
✅ Now reference award_id (not event_id)
✅ Foreign keys to awards table (not events)
```

---

## 🗂️ Next Steps After Migration

### **1. Load Seed Data**

```bash
mysql -u root -p eventic_db < database/seeds/awards_seed.sql
```

This will create:
- 4 award shows
- 19 categories
- 47 nominees
- 30+ votes

### **2. Test the Endpoints**

```bash
# Test awards endpoint
curl http://localhost:8000/v1/awards

# Test events endpoint (should only show ticketing)
curl http://localhost:8000/v1/events
```

### **3. Update Frontend**

Create separate pages:
- `/events` - For ticketing events
- `/awards` - For voting/awards shows

---

## 🔍 Verify Migration Success

Run these queries to verify:

```sql
-- Check awards table exists
SHOW TABLES LIKE 'awards';

-- Count awards
SELECT COUNT(*) as total_awards FROM awards;

-- Check award_categories references awards
DESCRIBE award_categories;

-- Verify event_format removed from events
DESCRIBE events;

-- Check if events are clean (only ticketing)
SELECT COUNT(*) as should_be_zero FROM events WHERE event_format = 'awards';
```

---

## ⚠️ Troubleshooting

### **Error: Foreign Key Constraint**
This was fixed! The migration now:
- Checks if awards events exist first
- Only adds foreign keys when data is present
- Handles empty databases gracefully

### **Error: Column Already Exists**
If you get this error, rollback first:
```bash
composer phinx-rollback
```

Then run migration again:
```bash
composer phinx-migrate
```

### **Migration Stuck**
The migration may take 10-30 seconds depending on data size. Be patient!

---

## 📊 Migration File Details

**File:** `database/migrations/20251214065054_separate_awards_from_events.php`

**Methods:**
- `up()` - Executes the separation
- `down()` - Rolls back changes

**Safe to Run:**
- ✅ On production databases
- ✅ On empty databases
- ✅ Multiple times (idempotent with rollback)

---

## ✅ Success Indicators

After successful migration:

1. ✅ No errors in console
2. ✅ `awards` table exists
3. ✅ `awards_images` table exists
4. ✅ `event_format` column removed from `events`
5. ✅ `award_categories/nominees/votes` have `award_id` column
6. ✅ No more `event_id` in awards-related tables

---

## 🎉 You're Done!

Your Awards system is now:
- ✅ Completely separate from Events
- ✅ Modular and independent
- ✅ Ready for production
- ✅ Easy to manage

**Happy coding!** 🚀
