<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCurrencyToFinancialTables extends AbstractMigration
{
    public function change(): void
    {
        // Orders
        $this->table('orders')
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'GHS', 'after' => 'status'])
            ->save();

        // Purchases
        $this->table('purchases')
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'GHS', 'after' => 'status'])
            ->save();

        // Expenses
        $this->table('expenses')
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'GHS', 'after' => 'status'])
            ->save();

        // Refunds
        $this->table('refunds')
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'GHS', 'after' => 'refundStatus'])
            ->save();

        // Transactions
        $this->table('transactions')
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'GHS', 'after' => 'status'])
            ->save();
    }
}
