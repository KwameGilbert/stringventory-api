<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUpdatedAtToCategoriesAndSuppliers extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('categories')) {
            $this->table('categories')
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'after' => 'createdAt'])
                ->update();
        }

        if ($this->hasTable('suppliers')) {
            $this->table('suppliers')
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'after' => 'createdAt'])
                ->update();
        }
    }

    public function down(): void
    {
        if ($this->hasTable('categories')) {
            $this->table('categories')
                ->removeColumn('updatedAt')
                ->update();
        }

        if ($this->hasTable('suppliers')) {
            $this->table('suppliers')
                ->removeColumn('updatedAt')
                ->update();
        }
    }
}
