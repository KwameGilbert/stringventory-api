<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add Email Verification Tokens Table
 * 
 * This migration adds a table to store email verification tokens
 * for user account verification during registration.
 */
final class AddEmailVerificationTokens extends AbstractMigration
{
    public function up(): void
    {
        // =====================================================
        // EMAIL VERIFICATION TOKENS TABLE
        // =====================================================
        if (!$this->hasTable('email_verification_tokens')) {
            $this->table('email_verification_tokens', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('token', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('expires_at', 'timestamp', ['null' => false])
                ->addColumn('used', 'boolean', ['default' => false, 'null' => false])
                ->addColumn('used_at', 'timestamp', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['user_id'])
                ->addIndex(['email'])
                ->addIndex(['token'], ['unique' => true])
                ->addIndex(['expires_at'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }
    }

    public function down(): void
    {
        if ($this->hasTable('email_verification_tokens')) {
            $this->table('email_verification_tokens')->drop()->save();
        }
    }
}
