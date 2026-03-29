<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePushSubscriptionsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('push_subscriptions', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
              ->addColumn('userId', 'integer', ['signed' => false, 'null' => false])
              ->addColumn('endpoint', 'text', ['null' => false])
              ->addColumn('p256dhKey', 'text', ['null' => false])
              ->addColumn('authKey', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['userId'])
              ->addForeignKey('userId', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
