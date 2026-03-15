<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddFulfillmentToOrderItems extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orderItems');
        $table->addColumn('fulfilledQuantity', 'integer', ['default' => 0, 'after' => 'quantity'])
              ->addColumn('fulfillmentStatus', 'enum', [
                  'values' => ['pending', 'partial', 'fulfilled'],
                  'default' => 'pending',
                  'after' => 'fulfilledQuantity'
              ])
              ->update();
    }
}
