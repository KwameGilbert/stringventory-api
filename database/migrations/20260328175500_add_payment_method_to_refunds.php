<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPaymentMethodToRefunds extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('refunds');
        if (!$table->hasColumn('paymentMethod')) {
            $table->addColumn('paymentMethod', 'string', ['limit' => 50, 'null' => true, 'after' => 'refundType'])
                  ->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('refunds');
        if ($table->hasColumn('paymentMethod')) {
            $table->removeColumn('paymentMethod')->update();
        }
    }
}
