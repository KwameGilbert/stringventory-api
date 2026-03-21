<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreatePaymentMethodsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('payment_methods', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'string', ['limit' => 50])
              ->addColumn('name', 'string', ['limit' => 100])
              ->addColumn('type', 'string', ['limit' => 50])
              ->addColumn('enabled', 'boolean', ['default' => true])
              ->addColumn('provider', 'string', ['limit' => 50, 'default' => 'internal'])
              ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->create();
              
        // Seed initial methods
        $table->insert([
            [
                'id' => 'pm_001',
                'name' => 'Credit Card',
                'type' => 'card',
                'enabled' => true,
                'provider' => 'stripe'
            ],
            [
                'id' => 'pm_002',
                'name' => 'Bank Transfer',
                'type' => 'bank',
                'enabled' => true,
                'provider' => 'internal'
            ],
            [
                'id' => 'pm_003',
                'name' => 'Cash',
                'type' => 'cash',
                'enabled' => true,
                'provider' => 'internal'
            ]
        ])->save();
    }
}
