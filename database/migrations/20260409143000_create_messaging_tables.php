<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateMessagingTables extends AbstractMigration
{
    public function change(): void
    {
        $templates = $this->table('messaging_templates', ['id' => false, 'primary_key' => ['id']]);
        $templates->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('channel', 'string', ['limit' => 20, 'null' => false, 'default' => 'multi'])
            ->addColumn('subject', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('body', 'text', ['null' => false])
            ->addColumn('isActive', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'])
            ->addIndex(['isActive'])
            ->create();

        $campaigns = $this->table('messaging_campaigns', ['id' => false, 'primary_key' => ['id']]);
        $campaigns->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('createdBy', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('templateId', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('subject', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('body', 'text', ['null' => false])
            ->addColumn('channels', 'json', ['null' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'queued'])
            ->addColumn('recipientCount', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('deliveredCount', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('failedCount', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['status'])
            ->addIndex(['createdBy'])
            ->addIndex(['createdAt'])
            ->addForeignKey('createdBy', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->addForeignKey('templateId', 'messaging_templates', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();

        $recipients = $this->table('messaging_campaign_recipients', ['id' => false, 'primary_key' => ['id']]);
        $recipients->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('campaignId', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('customerId', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('channel', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'pending'])
            ->addColumn('error', 'text', ['null' => true])
            ->addColumn('providerMessageId', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('sentAt', 'datetime', ['null' => true])
            ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['campaignId'])
            ->addIndex(['customerId'])
            ->addIndex(['status'])
            ->addIndex(['channel'])
            ->addIndex(['createdAt'])
            ->addForeignKey('campaignId', 'messaging_campaigns', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('customerId', 'customers', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }
}
