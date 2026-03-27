<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUnitsOfMeasureTable extends AbstractMigration
{
    public function up(): void
    {
        // 1. Create unitsOfMeasure table
        if (!$this->hasTable('unitsOfMeasure')) {
            $this->table('unitsOfMeasure', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 100])
                ->addColumn('abbreviation', 'string', ['limit' => 20, 'null' => true])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        // 2. Add unitOfMeasureId to products
        $table = $this->table('products');
        if (!$table->hasColumn('unitOfMeasureId')) {
            $table->addColumn('unitOfMeasureId', 'integer', ['null' => true, 'signed' => false, 'after' => 'supplierId'])
                ->addForeignKey('unitOfMeasureId', 'unitsOfMeasure', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                ->update();
        }

        // 3. Data Migration: Move existing 'unit' strings to unitsOfMeasure table
        $rows = $this->fetchAll('SELECT DISTINCT unit FROM products WHERE unit IS NOT NULL AND unit != ""');
        foreach ($rows as $row) {
            $unitName = $row['unit'];
            
            // Check if it already exists in unitsOfMeasure
            $existing = $this->fetchRow("SELECT id FROM unitsOfMeasure WHERE name = '" . addslashes($unitName) . "'");
            
            if (!$existing) {
                $this->execute("INSERT INTO unitsOfMeasure (name) VALUES ('" . addslashes($unitName) . "')");
                $unitId = $this->getAdapter()->getConnection()->lastInsertId();
            } else {
                $unitId = $existing['id'];
            }
            
            // Update products with this unit
            $this->execute("UPDATE products SET unitOfMeasureId = $unitId WHERE unit = '" . addslashes($unitName) . "'");
        }

        // 4. Remove 'unit' column from products
        if ($table->hasColumn('unit')) {
            $table->removeColumn('unit')->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('products');
        
        // Add back 'unit' column
        if (!$table->hasColumn('unit')) {
            $table->addColumn('unit', 'string', ['limit' => 50, 'null' => true, 'after' => 'costPrice'])->update();
        }

        // Restore data from unitsOfMeasure back to 'unit' string
        $this->execute("UPDATE products p JOIN unitsOfMeasure u ON p.unitOfMeasureId = u.id SET p.unit = u.name");

        // Remove foreign key and column
        if ($table->hasColumn('unitOfMeasureId')) {
            $table->dropForeignKey('unitOfMeasureId')->update();
            $table->removeColumn('unitOfMeasureId')->update();
        }

        // Drop unitsOfMeasure table
        if ($this->hasTable('unitsOfMeasure')) {
            $this->table('unitsOfMeasure')->drop()->save();
        }
    }
}
