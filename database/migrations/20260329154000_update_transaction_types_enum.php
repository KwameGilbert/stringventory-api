<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateTransactionTypesEnum extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('transactions');
        $table->changeColumn('transactionType', 'enum', [
            'values' => ['order', 'purchase', 'expense', 'adjustment', 'refunds', 'stock_loss'],
            'default' => 'order',
            'null' => false
        ])->update();
    }
}
