<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialSchemaSetup extends AbstractMigration
{
    public function up(): void
    {
        // Users Table
        if (!$this->hasTable('users')) {
            $this->table('users', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('firstName', 'string', ['limit' => 100])
                ->addColumn('lastName', 'string', ['limit' => 100])
                ->addColumn('role', 'enum', [
                    'values' => ['ceo', 'manager', 'salesperson'],
                    'default' => 'salesperson',
                    'null' => false
                ])
                ->addColumn('email', 'string', ['limit' => 255])
                ->addColumn('phone', 'string', ['limit' => 30, 'null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'inactive', 'suspended'],
                    'default' => 'active',
                    'null' => false
                ])
                ->addColumn('passwordHash', 'text')
                ->addColumn('profileImage', 'text', ['null' => true])
                ->addColumn('emailVerified', 'boolean', ['default' => false])
                ->addColumn('lastLogin', 'datetime', ['null' => true])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['email'], ['unique' => true])
                ->create();
        }

        // Categories Table
        if (!$this->hasTable('categories')) {
            $this->table('categories', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 150])
                ->addColumn('image', 'text', ['null' => true])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'inactive'],
                    'default' => 'active',
                    'null' => false
                ])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        // Suppliers Table
        if (!$this->hasTable('suppliers')) {
            $this->table('suppliers', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 200])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('phone', 'string', ['limit' => 30, 'null' => true])
                ->addColumn('address', 'text', ['null' => true])
                ->addColumn('contactPerson', 'string', ['limit' => 150, 'null' => true])
                ->addColumn('image', 'text', ['null' => true])
                ->addColumn('rating', 'integer', ['null' => true])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        // Products Table
        if (!$this->hasTable('products')) {
            $this->table('products', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('sku', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('categoryId', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('supplierId', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('sellingPrice', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('costPrice', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('unit', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('barcode', 'string', ['limit' => 120, 'null' => true])
                ->addColumn('image', 'text', ['null' => true])
                ->addColumn('reorderLevel', 'integer', ['null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'inactive'],
                    'default' => 'active',
                    'null' => false
                ])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['sku'], ['unique' => true])
                ->addForeignKey('categoryId', 'categories', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                ->addForeignKey('supplierId', 'suppliers', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                ->create();
        }

        // Discounts Table
        if (!$this->hasTable('discounts')) {
            $this->table('discounts', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('discount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('discountAmount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('discountType', 'enum', [
                    'values' => ['percentage', 'fixed'],
                    'default' => 'percentage',
                    'null' => false
                ])
                ->addColumn('discountCode', 'string', ['limit' => 50])
                ->addColumn('startDate', 'datetime', ['null' => true])
                ->addColumn('endDate', 'datetime', ['null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'inactive'],
                    'default' => 'active',
                    'null' => false
                ])
                ->addColumn('maxUses', 'integer', ['null' => true])
                ->addColumn('uses', 'integer', ['default' => 0])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

       // Expense Categories
        if (!$this->hasTable('expenseCategories')) {
            $this->table('expenseCategories', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('status', 'enum', ['values' => ['active', 'inactive'], 'default' => 'active'])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        // Expense Schedules (The "Recurring" Logic)
        if (!$this->hasTable('expenseSchedules')) {
            $this->table('expenseSchedules', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('expenseCategoryId', 'integer', ['signed' => false])
                ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
                
                // Frequency Logic
                ->addColumn('frequency', 'enum', [
                    'values' => ['none', 'daily', 'weekly', 'monthly', 'yearly'],
                    'default' => 'none' // 'none' means a one-time expense that doesn't repeat
                ])
                ->addColumn('startDate', 'date')
                ->addColumn('nextDueDate', 'date', ['null' => true])
                ->addColumn('endDate', 'date', ['null' => true]) // Stop recurring after this date
                
                ->addColumn('isActive', 'boolean', ['default' => true])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('expenseCategoryId', 'expenseCategories', 'id', ['delete'=> 'CASCADE'])
                ->create();
        }

        // Expenses (The "Actual" Transactions)
        if (!$this->hasTable('expenses')) {
            $this->table('expenses', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('expenseScheduleId', 'integer', ['signed' => false, 'null' => true]) // Links back to the parent schedule
                ->addColumn('expenseCategoryId', 'integer', ['signed' => false])
                ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('transactionDate', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('status', 'enum', ['values' => ['paid', 'pending', 'cancelled'], 'default' => 'paid'])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                
                ->addForeignKey('expenseCategoryId', 'expenseCategories', 'id', ['delete'=> 'CASCADE'])
                ->addForeignKey('expenseScheduleId', 'expenseSchedules', 'id', ['delete'=> 'SET_NULL'])
                ->create();
        }

        // Inventory Table
        if (!$this->hasTable('inventory')) {
            $this->table('inventory', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('productId', 'integer', ['signed' => false])
                ->addColumn('quantity', 'integer', ['default' => 0])
                ->addColumn('warehouseLocation', 'string', ['limit' => 150, 'null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['in_stock', 'low_stock', 'out_of_stock'],
                    'default' => 'in_stock',
                    'null' => false
                ])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('productId', 'products', 'id', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])
                ->create();
        }

        // Customers Table
        if (!$this->hasTable('customers')) {
            $this->table('customers', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('firstName', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('lastName', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('businessName', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('customerType', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('loyaltyPoints', 'integer', ['default' => 0])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        // Orders Table
        if (!$this->hasTable('orders')) {
            $this->table('orders', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('orderNumber', 'string', ['limit' => 100])
                ->addColumn('customerId', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('status', 'enum', [
                    'values' => ['pending', 'completed', 'cancelled', 'partially_fulfilled'],
                    'default' => 'pending',
                    'null' => false
                ])
                ->addColumn('discountId', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('discountPercentage', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('discountAmount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('discountType', 'enum', [
                    'values' => ['percentage', 'fixed'],
                    'default' => 'percentage',
                    'null' => false
                ])
                ->addColumn('discountedPrice', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('discountedTotalPrice', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['orderNumber'], ['unique' => true])
                ->addForeignKey('customerId', 'customers', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                ->addForeignKey('discountId', 'discounts', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                ->create();
        }

        // Refunds
        if (!$this->hasTable('refunds')){
            $this->table('refunds', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('orderId', 'integer', ['signed' => false])
                ->addColumn('customerId', 'integer', ['signed' => false])
                ->addColumn('refundAmount', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('refundDate', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('refundReason', 'text', ['null' => true])
                ->addColumn('refundStatus', 'enum', [
                    'values' => ['pending', 'completed', 'cancelled'],
                    'default' => 'pending',
                    'null' => false
                ])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('orderId', 'orders', 'id', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])
                ->addForeignKey('customerId', 'customers', 'id', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])
                ->create();
        }

        // Order Items
        if (!$this->hasTable('orderItems')) {
            $this->table('orderItems', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('orderId', 'integer', ['signed' => false])
                ->addColumn('productId', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('costPrice', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('sellingPrice', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('quantity', 'integer', ['default' => 0])
                ->addColumn('totalPrice', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addForeignKey('orderId', 'orders', 'id', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])
                ->addForeignKey('productId', 'products', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                ->create();
        }

        // Transactions
        if (!$this->hasTable('transactions')) {
            $this->table('transactions', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('orderId', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('expenseId', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('purchaseId', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('adjustmentId', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('refundId', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('transactionType', 'enum', [
                    'values' => ['order', 'purchase', 'expense', 'adjustment', 'refunds'],
                    'default' => 'order',
                    'null' => false
                ])
                ->addColumn('paymentMethod', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['pending', 'completed', 'cancelled', 'failed'],
                    'default' => 'pending',
                    'null' => false
                ])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('orderId', 'orders', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                ->addForeignKey('expenseId', 'expenses', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                ->addForeignKey('refundId', 'refunds', 'id', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])
                ->create();
        }

        // Audit Logs
        if (!$this->hasTable('auditLogs')) {
            $this->table('auditLogs', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('userId', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('action', 'string', ['limit' => 50])
                ->addColumn('ipAddress', 'string', ['limit' => 45])
                ->addColumn('userAgent', 'text', ['null' => true])
                ->addColumn('metadata', 'json', ['null' => true])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['userId'])
                ->addForeignKey('userId', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                ->create();
        }

        // Refresh Tokens
        if (!$this->hasTable('refreshTokens')) {
            $this->table('refreshTokens', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('userId', 'integer', ['signed' => false])
                ->addColumn('tokenHash', 'string', ['limit' => 255])
                ->addColumn('deviceName', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('ipAddress', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('userAgent', 'text', ['null' => true])
                ->addColumn('expiresAt', 'datetime')
                ->addColumn('revoked', 'boolean', ['default' => false])
                ->addColumn('revokedAt', 'datetime', ['null' => true])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['userId'])
                ->addForeignKey('userId', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                ->create();
        }

        // Password Resets
        if (!$this->hasTable('passwordResets')) {
            $this->table('passwordResets', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('email', 'string', ['limit' => 255])
                ->addColumn('token', 'string', ['limit' => 255])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['email'])
                ->create();
        }

        // Email Verification Tokens
        if (!$this->hasTable('emailVerificationTokens')) {
            $this->table('emailVerificationTokens', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('userId', 'integer', ['signed' => false])
                ->addColumn('email', 'string', ['limit' => 255])
                ->addColumn('token', 'string', ['limit' => 255])
                ->addColumn('expiresAt', 'datetime')
                ->addColumn('used', 'boolean', ['default' => false])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['userId'])
                ->addIndex(['email'])
                ->addForeignKey('userId', 'users', 'id', ['delete' => 'CASCADE'])
                ->create();
        }
    }

    /**
     * Rollback Method.
     */
    public function down(): void
    {
        // Drop tables with foreign keys first
        $this->table('expenses')->drop()->save();
        $this->table('purchaseItems')->drop()->save();
        $this->table('purchases')->drop()->save();
        $this->table('saleItems')->drop()->save();
        $this->table('sales')->drop()->save();
        $this->table('orderItems')->drop()->save();
        $this->table('orders')->drop()->save();
        $this->table('inventoryBatches')->drop()->save();
        $this->table('inventory')->drop()->save();
        $this->table('products')->drop()->save();

        // Drop standalone/parent tables last
        $this->table('expenseCategories')->drop()->save();
        $this->table('customers')->drop()->save();
        $this->table('suppliers')->drop()->save();
        $this->table('categories')->drop()->save();
        $this->table('users')->drop()->save();
    }
}