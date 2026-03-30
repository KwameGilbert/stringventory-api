<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddEvidenceAndReferenceToExpenses extends AbstractMigration
{
    public function change(): void
    {
        $this->table('expenses')
            ->addColumn('evidence', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('reference', 'text', ['null' => true])
            ->save();
    }
}
