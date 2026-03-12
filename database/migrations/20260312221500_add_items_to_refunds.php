<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddItemsToRefunds extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('refunds');
        $table->addColumn('items', 'json', ['null' => true, 'after' => 'refundReason'])
              ->addColumn('isRestocked', 'boolean', ['default' => false, 'after' => 'items'])
              ->update();
    }
}
