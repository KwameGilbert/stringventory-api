<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class UpdateBusinessAndPaymentSettings extends AbstractMigration
{
    public function up(): void
    {
        // Update global business settings with the full 18-field structure
        $businessData = json_encode([
            'businessId' => 'business_' . bin2hex(random_bytes(4)),
            'businessName' => 'StringVentory Store',
            'businessType' => 'retail',
            'email' => 'contact@stringventory.com',
            'phone' => '+1234567890',
            'address' => '123 Main Street, City, State 12345',
            'city' => 'City',
            'state' => 'State',
            'postalCode' => '12345',
            'country' => 'Country',
            'taxId' => 'TAX-123456',
            'website' => 'https://stringventory.com',
            'logo' => '',
            'currency' => 'GHS',
            'timezone' => 'UTC',
            'dateFormat' => 'MM/DD/YYYY',
            'timeFormat' => '12h',
            'language' => 'en'
        ]);

        $this->execute("UPDATE settings SET data = '$businessData' WHERE category = 'business'");

        // Seed initial payment settings (global config)
        $paymentConfig = json_encode([
            'businessId' => 'business_88234',
            'defaultPaymentMethod' => 'pm_001',
            'autoReconciliation' => true,
            'receiptEmail' => 'accounting@stringventory.com'
        ]);

        $this->execute("INSERT IGNORE INTO settings (category, data) VALUES ('payment', '$paymentConfig')");
    }

    public function down(): void
    {
        // No need to revert specifically for this seed update
    }
}
