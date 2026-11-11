<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAdjustmentDetailsToTransactions extends AbstractMigration
{
    public function change(): void
    {
        $this->table('transactions')
            ->addColumn('adjustmentQuantity', 'integer', ['null' => true, 'after' => 'adjustmentId'])
            ->addColumn('reason', 'string', ['limit' => 255, 'null' => true, 'after' => 'adjustmentQuantity'])
            ->addColumn('notes', 'text', ['null' => true, 'after' => 'reason'])
            ->save();
    }
}
