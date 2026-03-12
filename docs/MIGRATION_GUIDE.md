# Migration Guide - Step by Step

## Creating Migrations

### 1. Generate a New Migration File

```bash
# Basic syntax
vendor\bin\phinx create MigrationName

# Examples
vendor\bin\phinx create CreateOrdersTable
vendor\bin\phinx create AddPhoneToUsersTable
vendor\bin\phinx create CreateCustomersTable
```

This creates a timestamped file in `database/migrations/` like:
- `20250128114240_create_orders_table.php`

---

## Migration File Structure

### Basic Template

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateOrdersTable extends AbstractMigration
{
    public function change(): void
    {
        // Your migration code here
    }
}
```

---

## Creating Tables

### Example 1: Simple Table

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateOrdersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders');
        
        $table->addColumn('user_id', 'integer')
              ->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2])
              ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->create();
    }
}
```

### Example 2: Table with Foreign Keys

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateOrderItemsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('order_items');
        
        $table->addColumn('order_id', 'integer')
              ->addColumn('product_id', 'integer')
              ->addColumn('quantity', 'integer', ['default' => 1])
              ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              
              // Foreign keys
              ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->addForeignKey('product_id', 'products', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
              
              // Indexes
              ->addIndex(['order_id'])
              ->addIndex(['product_id'])
              
              ->create();
    }
}
```

### Example 3: Table with Unique Constraints

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateCustomersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('customers');
        
        $table->addColumn('first_name', 'string', ['limit' => 100])
              ->addColumn('last_name', 'string', ['limit' => 100])
              ->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
              ->addColumn('address', 'text', ['null' => true])
              ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
              ->addColumn('country', 'string', ['limit' => 100, 'null' => true])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              
              // Unique constraint
              ->addIndex(['email'], ['unique' => true])
              
              // Regular indexes
              ->addIndex(['city'])
              ->addIndex(['country'])
              
              ->create();
    }
}
```

---

## Column Data Types

### Common Column Types

```php
// String types
->addColumn('name', 'string', ['limit' => 255])
->addColumn('description', 'text')
->addColumn('bio', 'text', ['null' => true])

// Numeric types
->addColumn('age', 'integer')
->addColumn('count', 'integer', ['default' => 0])
->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
->addColumn('rating', 'float')

// Boolean
->addColumn('is_active', 'boolean', ['default' => true])
->addColumn('is_verified', 'boolean', ['default' => false])

// Date and Time
->addColumn('birth_date', 'date', ['null' => true])
->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])

// JSON (for MySQL 5.7+)
->addColumn('metadata', 'json', ['null' => true])

// Enum (use string with limited values)
->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
```

### Column Options

```php
[
    'limit' => 255,           // Max length for string
    'null' => true,           // Allow NULL values
    'default' => 'value',     // Default value
    'precision' => 10,        // Total digits for decimal
    'scale' => 2,             // Decimal places
    'signed' => false,        // Unsigned integer
    'comment' => 'User age',  // Column comment
]
```

---

## Modifying Existing Tables

### Adding Columns

```php
<?php

use Phinx\Migration\AbstractMigration;

final class AddPhoneToUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->addColumn('phone', 'string', ['limit' => 20, 'null' => true, 'after' => 'email'])
              ->update();
    }
}
```

### Removing Columns

```php
<?php

use Phinx\Migration\AbstractMigration;

final class RemovePhoneFromUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->removeColumn('phone')
              ->update();
    }
}
```

### Changing Column Type

```php
<?php

use Phinx\Migration\AbstractMigration;

final class ChangePhoneColumnType extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->changeColumn('phone', 'string', ['limit' => 50])
              ->update();
    }
}
```

### Renaming Columns

```php
<?php

use Phinx\Migration\AbstractMigration;

final class RenamePhoneColumn extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table->renameColumn('phone', 'phone_number')
              ->update();
    }
}
```

---

## Adding Indexes

### Single Column Index

```php
$table->addIndex(['email']);
```

### Multiple Column Index (Composite)

```php
$table->addIndex(['first_name', 'last_name']);
```

### Unique Index

```php
$table->addIndex(['email'], ['unique' => true]);
```

### Named Index

```php
$table->addIndex(['status'], ['name' => 'idx_user_status']);
```

---

## Foreign Keys

### Basic Foreign Key

```php
$table->addForeignKey('user_id', 'users', 'id');
```

### Foreign Key with Options

```php
$table->addForeignKey(
    'user_id',           // Column in current table
    'users',             // Referenced table
    'id',                // Referenced column
    [
        'delete' => 'CASCADE',      // ON DELETE CASCADE
        'update' => 'NO_ACTION',    // ON UPDATE NO_ACTION
        'constraint' => 'fk_order_user'  // Custom constraint name
    ]
);
```

### Foreign Key Actions

- `CASCADE` - Delete/update related records
- `RESTRICT` - Prevent delete/update if related records exist
- `SET NULL` - Set foreign key to NULL
- `NO_ACTION` - Do nothing (default)

---

## Running Migrations

### Check Migration Status

```bash
vendor\bin\phinx status
```

Output example:
```
Status  Migration ID    Migration Name
--------------------------------------------------
   up   20250128000001  CreateUsersTable
   up   20250128000002  CreateProductsTable
  down  20250128114240  CreateOrdersTable
```

### Run All Pending Migrations

```bash
vendor\bin\phinx migrate
```

### Run Specific Migration

```bash
vendor\bin\phinx migrate -t 20250128114240
```

### Rollback Last Migration

```bash
vendor\bin\phinx rollback
```

### Rollback to Specific Version

```bash
vendor\bin\phinx rollback -t 20250128000001
```

### Rollback All Migrations

```bash
vendor\bin\phinx rollback -t 0
```

### Verbose Output (for debugging)

```bash
vendor\bin\phinx migrate -vvv
```

---

## Best Practices

### 1. Always Use `change()` Method

The `change()` method is automatically reversible:

```php
public function change(): void
{
    $table = $this->table('users');
    $table->addColumn('phone', 'string')->update();
    // Phinx can automatically reverse this
}
```

### 2. Use `up()` and `down()` for Complex Migrations

For non-reversible operations:

```php
public function up(): void
{
    // Run when migrating forward
    $this->execute('UPDATE users SET status = "active"');
}

public function down(): void
{
    // Run when rolling back
    $this->execute('UPDATE users SET status = "inactive"');
}
```

### 3. Create One Migration per Logical Change

```bash
# Good
vendor\bin\phinx create CreateUsersTable
vendor\bin\phinx create AddPhoneToUsers

# Bad - don't mix unrelated changes
vendor\bin\phinx create UpdateEverything
```

### 4. Use Descriptive Names

```bash
# Good
CreateOrdersTable
AddEmailIndexToUsers
RemoveDeprecatedColumns

# Bad
Migration1
UpdateTable
Fix
```

### 5. Test Rollbacks

Always test that your migrations can be rolled back:

```bash
vendor\bin\phinx migrate    # Run migration
vendor\bin\phinx rollback   # Test rollback
vendor\bin\phinx migrate    # Run again
```

---

## Common Migration Patterns

### Pattern 1: Creating Related Tables

```bash
# Create in order (parent before child)
vendor\bin\phinx create CreateCategoriesTable
vendor\bin\phinx create CreateProductsTable  # references categories
```

### Pattern 2: Adding Relationships Later

```bash
vendor\bin\phinx create AddCategoryIdToProducts
```

```php
public function change(): void
{
    $table = $this->table('products');
    $table->addColumn('category_id', 'integer', ['null' => true])
          ->addForeignKey('category_id', 'categories', 'id', ['delete' => 'SET_NULL'])
          ->update();
}
```

### Pattern 3: Data Migrations

```php
public function up(): void
{
    // Add new column
    $table = $this->table('users');
    $table->addColumn('full_name', 'string', ['null' => true])->update();
    
    // Migrate data
    $this->execute("UPDATE users SET full_name = CONCAT(first_name, ' ', last_name)");
}
```

---

## Troubleshooting

### Error: Table Already Exists

```bash
# Check status
vendor\bin\phinx status

# If needed, rollback and try again
vendor\bin\phinx rollback
vendor\bin\phinx migrate
```

### Error: Foreign Key Constraint Fails

1. Ensure parent table exists
2. Check referenced column exists
3. Ensure data types match exactly

### Error: Cannot Drop Column

Foreign keys may prevent dropping columns:

```php
// First remove foreign key, then drop column
public function change(): void
{
    $table = $this->table('orders');
    $table->dropForeignKey('user_id')
          ->removeColumn('user_id')
          ->update();
}
```

---

## Quick Reference

```bash
# Create migration
vendor\bin\phinx create MigrationName

# Check status
vendor\bin\phinx status

# Run migrations
vendor\bin\phinx migrate

# Rollback last
vendor\bin\phinx rollback

# Rollback all
vendor\bin\phinx rollback -t 0

# Verbose output
vendor\bin\phinx migrate -vvv
```
