<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCreatedByToPurchasesAndOrders extends AbstractMigration
{
    public function change(): void
    {
        $purchases = $this->table('purchases');
        if (!$purchases->hasColumn('createdBy')) {
            $purchases->addColumn('createdBy', 'integer', ['null' => true, 'signed' => false, 'after' => 'supplierId'])
                      ->addForeignKey('createdBy', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                      ->update();
        }

        $orders = $this->table('orders');
        if (!$orders->hasColumn('createdBy')) {
            $orders->addColumn('createdBy', 'integer', ['null' => true, 'signed' => false, 'after' => 'customerId'])
                   ->addForeignKey('createdBy', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                   ->update();
        }
    }
}
