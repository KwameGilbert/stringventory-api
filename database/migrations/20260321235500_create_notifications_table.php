<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateNotificationsTable extends AbstractMigration
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
        $table = $this->table('notifications', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
              ->addColumn('userId', 'integer', ['signed' => false, 'null' => true])
              ->addColumn('type', 'string', ['limit' => 50, 'null' => false])
              ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('message', 'text', ['null' => false])
              ->addColumn('data', 'json', ['null' => true])
              ->addColumn('isRead', 'boolean', ['default' => false])
              ->addColumn('readAt', 'datetime', ['null' => true])
              ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['userId'])
              ->addIndex(['type'])
              ->addForeignKey('userId', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
