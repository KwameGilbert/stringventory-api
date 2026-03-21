<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateUserSettingsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('user_settings', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
              ->addColumn('userId', 'integer', ['signed' => false])
              ->addColumn('category', 'string', ['limit' => 50])
              ->addColumn('data', 'json')
              ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addForeignKey('userId', 'users', 'id', ['delete'=> 'CASCADE'])
              ->addIndex(['userId', 'category'], ['unique' => true])
              ->create();
    }
}
