<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class StandardizePaymentMethods extends AbstractMigration
{
    public function change(): void
    {
        // Ensure payment_methods table uses int unsigned auto_increment id if it's currently a string
        // This is to align with the schema.sql provided by the user.
        $paymentMethods = $this->table('payment_methods');
        $idColumn = $paymentMethods->getColumns();
        foreach ($idColumn as $column) {
            if ($column->getName() === 'id' && $column->getType() === 'string') {
                // If it's a string, we might need to be careful, but we'll follow the schema.sql
                $paymentMethods->changeColumn('id', 'integer', ['unsigned' => true, 'identity' => true])->update();
            }
        }

        // Add paymentMethodId to expenses
        $expenses = $this->table('expenses');
        if (!$expenses->hasColumn('paymentMethodId')) {
            $expenses->addColumn('paymentMethodId', 'integer', ['unsigned' => true, 'null' => true, 'after' => 'reference'])
                     ->addForeignKey('paymentMethodId', 'payment_methods', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                     ->update();
        }

        // Add paymentMethodId to expenseSchedules
        $expenseSchedules = $this->table('expenseSchedules');
        if (!$expenseSchedules->hasColumn('paymentMethodId')) {
            $expenseSchedules->addColumn('paymentMethodId', 'integer', ['unsigned' => true, 'null' => true, 'after' => 'isActive'])
                             ->addForeignKey('paymentMethodId', 'payment_methods', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                             ->update();
        }

        // Update purchases: change paymentMethod (string) to paymentMethodId (int)
        $purchases = $this->table('purchases');
        if ($purchases->hasColumn('paymentMethod')) {
            $purchases->removeColumn('paymentMethod')->update();
        }
        if (!$purchases->hasColumn('paymentMethodId')) {
            $purchases->addColumn('paymentMethodId', 'integer', ['unsigned' => true, 'null' => true, 'after' => 'paymentStatus'])
                      ->addForeignKey('paymentMethodId', 'payment_methods', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                      ->update();
        }

        // Update refunds: change paymentMethod (string) to paymentMethodId (int)
        $refunds = $this->table('refunds');
        if ($refunds->hasColumn('paymentMethod')) {
            $refunds->removeColumn('paymentMethod')->update();
        }
        if (!$refunds->hasColumn('paymentMethodId')) {
            $refunds->addColumn('paymentMethodId', 'integer', ['unsigned' => true, 'null' => true, 'after' => 'refundType'])
                    ->addForeignKey('paymentMethodId', 'payment_methods', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                    ->update();
        }

        // Ensure transactions table is correct
        $transactions = $this->table('transactions');
        // If it has paymentMethod string, remove it
        if ($transactions->hasColumn('paymentMethod')) {
             $transactions->removeColumn('paymentMethod')->update();
        }
        // Ensure paymentMethodId exists and has FK
        if (!$transactions->hasColumn('paymentMethodId')) {
            $transactions->addColumn('paymentMethodId', 'integer', ['unsigned' => true, 'null' => true, 'after' => 'transactionType'])
                         ->addForeignKey('paymentMethodId', 'payment_methods', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                         ->update();
        } else {
            // Ensure foreign key exists
            $foreignKeys = $transactions->getForeignKeys();
            $hasFk = false;
            foreach ($foreignKeys as $fk) {
                if ($fk->getColumns() === ['paymentMethodId']) {
                    $hasFk = true;
                    break;
                }
            }
            if (!$hasFk) {
                $transactions->addForeignKey('paymentMethodId', 'payment_methods', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                             ->update();
            }
        }
    }
}
