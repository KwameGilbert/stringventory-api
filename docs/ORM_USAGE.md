# Eloquent ORM Usage Guide

This application uses **Eloquent ORM** (from Laravel) as a standalone package to manage database operations. Eloquent provides an elegant ActiveRecord implementation for working with your database.

## Table of Contents
1. [Basic CRUD Operations](#basic-crud-operations)
2. [Query Builder](#query-builder)
3. [Relationships](#relationships)
4. [Migrations](#migrations)
5. [Best Practices](#best-practices)

---

## Basic CRUD Operations

### Creating Records

```php
// Method 1: Using create()
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// Method 2: Using new and save()
$user = new User();
$user->name = 'Jane Doe';
$user->email = 'jane@example.com';
$user->save();
```

### Reading Records

```php
// Get all records
$users = User::all();

// Find by primary key
$user = User::find(1);

// Find or fail (throws exception if not found)
$user = User::findOrFail(1);

// Get first record matching a condition
$user = User::where('email', 'john@example.com')->first();

// Get all records matching a condition
$users = User::where('status', 'active')->get();
```

### Updating Records

```php
// Method 1: Find and update
$user = User::find(1);
$user->name = 'Updated Name';
$user->save();

// Method 2: Update directly
$user = User::find(1);
$user->update(['name' => 'Updated Name']);

// Method 3: Mass update
User::where('status', 'inactive')->update(['status' => 'active']);
```

### Deleting Records

```php
// Delete a specific record
$user = User::find(1);
$user->delete();

// Delete by ID
User::destroy(1);

// Delete multiple records
User::destroy([1, 2, 3]);

// Delete records matching a condition
User::where('status', 'inactive')->delete();
```

---

## Query Builder

Eloquent provides a powerful query builder for complex queries:

### Where Clauses

```php
// Simple where
$users = User::where('status', 'active')->get();

// Multiple where conditions
$users = User::where('status', 'active')
             ->where('role', 'admin')
             ->get();

// Or where
$users = User::where('role', 'admin')
             ->orWhere('role', 'moderator')
             ->get();

// Where In
$users = User::whereIn('role', ['admin', 'moderator'])->get();

// Where Not In
$users = User::whereNotIn('status', ['banned', 'suspended'])->get();

// Where Null
$users = User::whereNull('email_verified_at')->get();

// Where Not Null
$users = User::whereNotNull('email_verified_at')->get();

// Where Between
$products = Product::whereBetween('price', [10, 100])->get();

// Where Like
$users = User::where('name', 'LIKE', '%John%')->get();
```

### Ordering and Limiting

```php
// Order by
$users = User::orderBy('created_at', 'desc')->get();

// Multiple order by
$users = User::orderBy('role', 'asc')
             ->orderBy('name', 'asc')
             ->get();

// Latest (shorthand for orderBy created_at desc)
$users = User::latest()->get();

// Oldest
$users = User::oldest()->get();

// Limit
$users = User::limit(10)->get();

// Skip and take (pagination)
$users = User::skip(10)->take(5)->get();
```

### Aggregates

```php
// Count
$count = User::count();
$activeCount = User::where('status', 'active')->count();

// Max, Min, Average, Sum
$maxPrice = Product::max('price');
$minPrice = Product::min('price');
$avgPrice = Product::avg('price');
$totalRevenue = Order::sum('total_amount');
```

### Pagination

```php
// Simple pagination (15 per page by default)
$users = User::paginate();

// Custom per page
$users = User::paginate(20);

// Pagination with conditions
$users = User::where('status', 'active')->paginate(20);
```

---

## Relationships

Eloquent makes it easy to work with relationships:

### One-to-Many

```php
// In User model
class User extends BaseModel
{
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

// In Order model
class Order extends BaseModel
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// Usage
$user = User::find(1);
$orders = $user->orders; // Get all orders for a user

$order = Order::find(1);
$user = $order->user; // Get the user who made the order
```

### Many-to-Many

```php
// In User model
class User extends BaseModel
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles; // Get all roles for a user

// Attach a role
$user->roles()->attach($roleId);

// Detach a role
$user->roles()->detach($roleId);

// Sync roles (replace all)
$user->roles()->sync([1, 2, 3]);
```

### Eager Loading (Avoid N+1 Problem)

```php
// Without eager loading (N+1 problem)
$users = User::all();
foreach ($users as $user) {
    echo $user->orders->count(); // Runs a query for each user
}

// With eager loading (2 queries total)
$users = User::with('orders')->get();
foreach ($users as $user) {
    echo $user->orders->count(); // No additional queries
}

// Multiple relationships
$users = User::with(['orders', 'roles'])->get();

// Nested relationships
$users = User::with('orders.items')->get();
```

---

## Migrations

### Running Migrations

```bash
# Run all pending migrations
vendor\\bin\\phinx migrate

# Rollback the last migration
vendor\\bin\\phinx rollback

# Rollback all migrations
vendor\\bin\\phinx rollback -t 0

# Check migration status
vendor\\bin\\phinx status
```

### Creating Migrations

```bash
# Create a new migration
vendor\\bin\\phinx create MyNewMigration
```

### Migration Example

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
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
              ->addIndex(['status'])
              ->create();
    }
}
```

---

## Best Practices

### 1. Use Mass Assignment Protection

Always define `$fillable` or `$guarded` in your models:

```php
class User extends BaseModel
{
    // Option 1: Specify fillable fields
    protected $fillable = ['name', 'email', 'password'];
    
    // Option 2: Specify guarded fields (everything else is fillable)
    protected $guarded = ['id', 'is_admin'];
}
```

### 2. Hide Sensitive Data

Use `$hidden` to exclude sensitive fields from JSON responses:

```php
class User extends BaseModel
{
    protected $hidden = ['password', 'api_token'];
}
```

### 3. Use Type Casting

Cast attributes to native PHP types:

```php
class Product extends BaseModel
{
    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'is_featured' => 'boolean',
        'metadata' => 'array', // Automatically converts to/from JSON
    ];
}
```

### 4. Use Scopes for Reusable Queries

```php
class User extends BaseModel
{
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }
}

// Usage
$activeUsers = User::active()->get();
$activeAdmins = User::active()->admins()->get();
```

### 5. Use Accessors and Mutators

```php
class User extends BaseModel
{
    // Accessor (get)
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    // Mutator (set)
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }
}

// Usage
$user = User::find(1);
echo $user->full_name; // Uses accessor

$user->password = 'new_password'; // Uses mutator
$user->save();
```

### 6. Use Transactions for Multiple Operations

```php
use Illuminate\Database\Capsule\Manager as DB;

DB::transaction(function () {
    $user = User::create([...]);
    $order = Order::create([...]);
    // If any operation fails, all changes are rolled back
});
```

### 7. Use Eager Loading to Avoid N+1

Always load relationships when you know you'll need them:

```php
// Bad
$users = User::all();
foreach ($users as $user) {
    echo $user->orders->count();
}

// Good
$users = User::with('orders')->all();
foreach ($users as $user) {
    echo $user->orders->count();
}
```

---

## Additional Resources

- [Eloquent Official Documentation](https://laravel.com/docs/eloquent)
- [Query Builder Documentation](https://laravel.com/docs/queries)
- [Migrations Documentation](https://laravel.com/docs/migrations)
- [Phinx Documentation](https://book.cakephp.org/phinx)
