<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLogsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('logs', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
              ->addColumn('level', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('level_name', 'string', ['limit' => 50, 'null' => false])
              ->addColumn('message', 'text', ['null' => false])
              ->addColumn('context', 'json', ['null' => true])
              ->addColumn('extra', 'json', ['null' => true])
              ->addColumn('channel', 'string', ['limit' => 100, 'null' => false, 'default' => 'app'])
              ->addColumn('request_id', 'string', ['limit' => 36, 'null' => true])
              ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
              ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
              ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['level'])
              ->addIndex(['level_name'])
              ->addIndex(['channel'])
              ->addIndex(['request_id'])
              ->addIndex(['user_id'])
              ->addIndex(['created_at'])
              ->addIndex(['level', 'created_at'])
              ->addIndex(['channel', 'created_at'])
              ->create();
    }
}
