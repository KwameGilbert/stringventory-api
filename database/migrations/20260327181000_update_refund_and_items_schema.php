<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateRefundAndItemsSchema extends AbstractMigration
{
    public function up(): void
    {
        // 1. Update orderItems table
        $orderItems = $this->table('orderItems');
        if (!$orderItems->hasColumn('refundedQuantity')) {
            $orderItems->addColumn('refundedQuantity', 'integer', ['default' => 0, 'after' => 'fulfilledQuantity'])
                       ->update();
        }

        // 2. Update refunds table
        $refunds = $this->table('refunds');
        if (!$refunds->hasColumn('refundType')) {
            $refunds->addColumn('refundType', 'string', ['limit' => 20, 'default' => 'partial', 'after' => 'customerId'])
                    ->update();
        }
        if (!$refunds->hasColumn('notes')) {
            $refunds->addColumn('notes', 'text', ['null' => true, 'after' => 'items'])
                    ->update();
        }
    }

    public function down(): void
    {
        $orderItems = $this->table('orderItems');
        if ($orderItems->hasColumn('refundedQuantity')) {
            $orderItems->removeColumn('refundedQuantity')->update();
        }

        $refunds = $this->table('refunds');
        if ($refunds->hasColumn('refundType')) {
            $refunds->removeColumn('refundType')->update();
        }
        if ($refunds->hasColumn('notes')) {
            $refunds->removeColumn('notes')->update();
        }
    }
}
