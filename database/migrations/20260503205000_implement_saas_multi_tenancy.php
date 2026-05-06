<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class ImplementSaasMultiTenancy extends AbstractMigration
{
    public function change(): void
    {
        // 1. Create SaaS Global & Billing Tables
        if (!$this->hasTable('plans')) {
            $this->table('plans', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 100])
                ->addColumn('description', 'text')
                ->addColumn('themeColor', 'string', ['limit' => 20, 'default' => '#000000'])
                ->addColumn('isPopular', 'boolean', ['default' => false])
                ->addColumn('monthlyPrice', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('yearlyPrice', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('trialDays', 'integer', ['default' => 14])
                ->addColumn('maxUsers', 'integer', ['default' => -1])
                ->addColumn('maxProducts', 'integer', ['default' => -1])
                ->addColumn('maxOrdersPerMonth', 'integer', ['default' => -1])
                ->addColumn('maxCategories', 'integer', ['default' => -1])
                ->addColumn('maxSuppliers', 'integer', ['default' => -1])
                ->addColumn('maxCustomers', 'integer', ['default' => -1])
                ->addColumn('maxLocations', 'integer', ['default' => 1])
                ->addColumn('maxStorageMb', 'integer', ['default' => 1024])
                ->addColumn('marketingFeatures', 'json', ['null' => true])
                ->addColumn('systemCapabilities', 'json', ['null' => true])
                ->addColumn('status', 'enum', ['values' => ['active', 'archived'], 'default' => 'active'])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        if (!$this->hasTable('businesses')) {
            $this->table('businesses', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('email', 'string', ['limit' => 255])
                ->addColumn('domain', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('status', 'enum', ['values' => ['active', 'on_trial', 'suspended', 'canceled'], 'default' => 'on_trial'])
                ->addColumn('usedStorageMb', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0.00])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['domain'], ['unique' => true])
                ->create();
        }

        if (!$this->hasTable('subscriptions')) {
            $this->table('subscriptions', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('businessId', 'integer', ['signed' => false])
                ->addColumn('planId', 'integer', ['signed' => false])
                ->addColumn('billingCycle', 'enum', ['values' => ['monthly', 'yearly'], 'default' => 'monthly'])
                ->addColumn('mrr', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('status', 'enum', ['values' => ['trialing', 'active', 'past_due', 'canceled', 'unpaid'], 'default' => 'trialing'])
                ->addColumn('trialEndsAt', 'datetime', ['null' => true])
                ->addColumn('currentPeriodStart', 'datetime', ['null' => true])
                ->addColumn('currentPeriodEnd', 'datetime', ['null' => true])
                ->addColumn('cancelAtPeriodEnd', 'boolean', ['default' => false])
                ->addColumn('gatewayCustomerId', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('gatewaySubscriptionId', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('paymentMethodBrand', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('paymentMethodLast4', 'string', ['limit' => 4, 'null' => true])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('businessId', 'businesses', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('planId', 'plans', 'id', ['delete' => 'RESTRICT'])
                ->create();
        }

        // 2. Update Users table for SaaS
        $users = $this->table('users');
        if (!$users->hasColumn('businessId')) {
            $users->addColumn('businessId', 'integer', ['signed' => false, 'null' => true, 'after' => 'id'])
                  ->addColumn('isSuperAdmin', 'boolean', ['default' => false, 'after' => 'businessId'])
                  ->changeColumn('role', 'enum', ['values' => ['owner', 'ceo', 'manager', 'salesperson'], 'default' => 'salesperson'])
                  ->addForeignKey('businessId', 'businesses', 'id', ['delete' => 'CASCADE'])
                  ->update();
        }

        // 3. Add businessId to all tenant-scoped tables
        $tablesToUpdate = [
            'settings', 'categories', 'unitsOfMeasure', 'suppliers', 'customers',
            'products', 'inventory', 'discounts', 'orders', 'purchases',
            'refunds', 'expenseCategories', 'expenseSchedules', 'expenses',
            'payment_methods', 'transactions', 'notifications', 'messaging_templates',
            'messaging_campaigns', 'messaging_campaign_recipients', 'auditLogs', 'logs'
        ];

        foreach ($tablesToUpdate as $tableName) {
            $table = $this->table($tableName);
            if (!$table->hasColumn('businessId')) {
                // Determine placement
                $after = 'id';
                
                $table->addColumn('businessId', 'integer', ['signed' => false, 'null' => ($tableName === 'auditLogs' || $tableName === 'logs'), 'after' => $after])
                      ->addForeignKey('businessId', 'businesses', 'id', ['delete' => 'CASCADE'])
                      ->update();
            }
        }

        // 4. Update unique indices for SaaS Isolation
        // Categories: unique name per business
        $categories = $this->table('categories');
        if ($categories->hasIndex(['name'])) {
            $categories->removeIndex(['name'])->update();
        }
        $categories->addIndex(['businessId', 'name'], ['unique' => true])->update();

        // Products: unique SKU per business
        $products = $this->table('products');
        if ($products->hasIndex(['sku'])) {
            $products->removeIndex(['sku'])->update();
        }
        $products->addIndex(['businessId', 'sku'], ['unique' => true])->update();

        // Inventory: unique product per business (already exists but ensures businessId is included)
        $inventory = $this->table('inventory');
        if ($inventory->hasIndex(['productId'])) {
            $inventory->removeIndex(['productId'])->update();
        }
        $inventory->addIndex(['businessId', 'productId'], ['unique' => true])->update();

        // Discounts: unique code per business
        $discounts = $this->table('discounts');
        if ($discounts->hasIndex(['discountCode'])) {
            $discounts->removeIndex(['discountCode'])->update();
        }
        $discounts->addIndex(['businessId', 'discountCode'], ['unique' => true])->update();

        // Orders: unique number per business
        $orders = $this->table('orders');
        if ($orders->hasIndex(['orderNumber'])) {
            $orders->removeIndex(['orderNumber'])->update();
        }
        $orders->addIndex(['businessId', 'orderNumber'], ['unique' => true])->update();

        // Purchases: unique number per business
        $purchases = $this->table('purchases');
        if ($purchases->hasIndex(['purchaseNumber'])) {
            $purchases->removeIndex(['purchaseNumber'])->update();
        }
        $purchases->addIndex(['businessId', 'purchaseNumber'], ['unique' => true])->update();

        // Payment Methods: unique code per business
        $paymentMethods = $this->table('payment_methods');
        $paymentMethods->addIndex(['businessId', 'methodCode'], ['unique' => true])->update();
        
        // Users: unique email per business
        $users = $this->table('users');
        if ($users->hasIndex(['email'])) {
            $users->removeIndex(['email'])->update();
        }
        $users->addIndex(['businessId', 'email'], ['unique' => true])->update();
    }
}
