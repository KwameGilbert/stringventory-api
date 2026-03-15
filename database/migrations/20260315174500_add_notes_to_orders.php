<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNotesToOrders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders');
        $table->addColumn('notes', 'text', ['null' => true, 'after' => 'discountedTotalPrice'])
              ->update();
    }
}
