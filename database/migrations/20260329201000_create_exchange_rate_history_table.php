<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateExchangeRateHistoryTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('exchange_rate_history', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
              ->addColumn('baseCurrency', 'string', ['limit' => 3, 'null' => false])
              ->addColumn('targetCurrency', 'string', ['limit' => 3, 'null' => false])
              ->addColumn('rate', 'decimal', ['precision' => 15, 'scale' => 6, 'null' => false])
              ->addColumn('source', 'enum', ['values' => ['api', 'manual'], 'default' => 'api'])
              ->addColumn('effectiveDate', 'date', ['null' => false])
              ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['baseCurrency', 'targetCurrency', 'effectiveDate'])
              ->create();
    }
}
