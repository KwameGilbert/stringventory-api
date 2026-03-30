<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMustChangePasswordToUsers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('mustChangePassword', 'boolean', ['default' => false, 'null' => false])
            ->save();
    }
}
