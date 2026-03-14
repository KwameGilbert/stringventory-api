<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePurchasesAndItemsSchema extends AbstractMigration
{
    public function up(): void
    {
        // Purchase Orders Table (Restocking/Intake)
        if (!$this->hasTable('purchases')) {
            $this->table('purchases', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('supplierId', 'integer', ['signed' => false])
                ->addColumn('purchaseNumber', 'string', ['limit' => 100])
                ->addColumn('waybillNumber', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('batchNumber', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('purchaseDate', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('dueDate', 'datetime', ['null' => true])
                ->addColumn('expectedDeliveryDate', 'datetime', ['null' => true])
                ->addColumn('receivedDate', 'datetime', ['null' => true])
                ->addColumn('subtotal', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
                ->addColumn('tax', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
                ->addColumn('shippingCost', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
                ->addColumn('totalAmount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
                ->addColumn('status', 'enum', [
                    'values' => ['pending', 'ordered', 'received', 'cancelled'],
                    'default' => 'pending'
                ])
                ->addColumn('paymentStatus', 'enum', [
                    'values' => ['unpaid', 'partial', 'paid'],
                    'default' => 'unpaid'
                ])
                ->addColumn('paymentMethod', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('supplierId', 'suppliers', 'id', ['delete'=> 'RESTRICT', 'update'=> 'NO_ACTION'])
                ->addIndex(['purchaseNumber'], ['unique' => true])
                ->create();
        }

        // Purchase Items Table
        if (!$this->hasTable('purchaseItems')) {
            $this->table('purchaseItems', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('purchaseId', 'integer', ['signed' => false])
                ->addColumn('productId', 'integer', ['signed' => false])
                ->addColumn('quantity', 'integer')
                ->addColumn('costPrice', 'decimal', ['precision' => 12, 'scale' => 2]) // Price bought from supplier
                ->addColumn('sellingPrice', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true]) // Suggested new selling price
                ->addColumn('totalPrice', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('expiryDate', 'date', ['null' => true])
                ->addForeignKey('purchaseId', 'purchases', 'id', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])
                ->addForeignKey('productId', 'products', 'id', ['delete'=> 'RESTRICT', 'update'=> 'NO_ACTION'])
                ->create();
        }

        // Add missing foreign key in transactions if table exists
        $transactions = $this->table('transactions');
        if ($transactions->hasColumn('purchaseId')) {
             $transactions->addForeignKey('purchaseId', 'purchases', 'id', ['delete' => 'SET_NULL'])->update();
        }
    }

    public function down(): void
    {
        $this->table('purchaseItems')->drop()->save();
        $this->table('purchases')->drop()->save();
    }
}
