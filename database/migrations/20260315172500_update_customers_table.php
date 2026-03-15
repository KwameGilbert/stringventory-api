<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateCustomersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('customers');
        $table->addColumn('email', 'string', ['limit' => 255, 'null' => true, 'after' => 'lastName'])
              ->addColumn('phone', 'string', ['limit' => 30, 'null' => true, 'after' => 'email'])
              ->addColumn('address', 'text', ['null' => true, 'after' => 'phone'])
              ->update();
    }
}
