<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AddLastUpdatedToInventoryTable extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('inventory');
        if (!$table->hasColumn('lastUpdated')) {
            $table->addColumn('lastUpdated', 'datetime', ['null' => true, 'after' => 'status'])
                  ->update();
        }
    }
}
