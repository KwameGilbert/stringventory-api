<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EnableBatchTrackingAndMissingCreatedBy extends AbstractMigration
{
    public function change(): void
    {
        // 1. Add remainingQuantity to purchaseItems for batch tracking
        $purchaseItems = $this->table('purchaseItems');
        if (!$purchaseItems->hasColumn('remainingQuantity')) {
            $purchaseItems->addColumn('remainingQuantity', 'integer', [
                'null' => false, 
                'default' => 0, 
                'after' => 'quantity',
                'comment' => 'Tracks current stock available for this specific batch'
            ])
            ->update();
            
            // Initialize remainingQuantity with existing quantity for historical data
            $this->execute("UPDATE purchaseItems SET remainingQuantity = quantity");
        }

        // 2. Add createdBy to refunds
        $refunds = $this->table('refunds');
        if (!$refunds->hasColumn('createdBy')) {
            $refunds->addColumn('createdBy', 'integer', ['null' => true, 'signed' => false])
                   ->addForeignKey('createdBy', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                   ->update();
        }

        // 3. Add createdBy to expenses
        $expenses = $this->table('expenses');
        if (!$expenses->hasColumn('createdBy')) {
            $expenses->addColumn('createdBy', 'integer', ['null' => true, 'signed' => false])
                   ->addForeignKey('createdBy', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                   ->update();
        }
    }
}
