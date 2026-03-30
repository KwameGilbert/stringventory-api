<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BackfillRemainingQuantityForReceivedPurchases extends AbstractMigration
{
    public function change(): void
    {
        // Set remainingQuantity = quantity for all purchase items that belong to
        // a received purchase but were never initialised (remainingQuantity = 0).
        // This fixes historical records created before the FEFO batch tracking fix.
        $this->execute("
            UPDATE purchaseItems pi
            INNER JOIN purchases p ON p.id = pi.purchaseId
            SET pi.remainingQuantity = pi.quantity
            WHERE p.status = 'received'
              AND pi.remainingQuantity = 0
        ");
    }
}
