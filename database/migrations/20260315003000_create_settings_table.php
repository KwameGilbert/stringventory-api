<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSettingsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('settings', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
              ->addColumn('category', 'string', ['limit' => 50])
              ->addColumn('data', 'json')
              ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['category'], ['unique' => true])
              ->create();
              
        // Seed initial business settings
        $initialData = json_encode([
            'businessName' => 'StringVentory Store',
            'businessType' => 'retail',
            'currency' => 'GHS',
            'timezone' => 'UTC',
            'language' => 'en',
            'email' => 'admin@stringventory.com'
        ]);
        
        $this->execute("INSERT INTO settings (category, data) VALUES ('business', '$initialData')");
    }
}
