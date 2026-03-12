<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Complete Database Schema Migration
 * 
 * This is the canonical migration file that creates the complete database schema
 * as of December 25, 2025. It includes all tables, columns, indexes, and foreign keys.
 * 
 * Tables created:
 * - users, organizers, attendees
 * - events, event_types, event_images, event_reviews
 * - awards, awards_images, award_categories, award_nominees, award_votes
 * - orders, order_items, tickets, ticket_types
 * - organizers, organizer_balances, organizer_followers
 * - payout_requests, transactions, platform_settings
 * - password_resets, refresh_tokens, audit_logs
 * - scanner_assignments, pos_assignments
 */
final class FullDatabaseSchema extends AbstractMigration
{
    public function up(): void
    {
        // =====================================================
        // 1. USERS TABLE
        // =====================================================
        if (!$this->hasTable('users')) {
            $this->table('users', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('phone', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('password', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('remember_token', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('role', 'enum', [
                    'values' => ['admin', 'organizer', 'attendee', 'pos', 'scanner'],
                    'default' => 'attendee',
                    'null' => false
                ])
                ->addColumn('email_verified', 'boolean', ['default' => false, 'null' => true])
                ->addColumn('email_verified_at', 'timestamp', ['null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'suspended'],
                    'default' => 'active',
                    'null' => false
                ])
                ->addColumn('first_login', 'boolean', ['default' => false, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('last_login_at', 'timestamp', ['null' => true])
                ->addColumn('last_login_ip', 'string', ['limit' => 45, 'null' => true])
                ->addIndex(['email'])
                ->addIndex(['phone'])
                ->create();
        }

        // =====================================================
        // 2. ORGANIZERS TABLE
        // =====================================================
        if (!$this->hasTable('organizers')) {
            $this->table('organizers', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('organization_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('bio', 'text', ['null' => true])
                ->addColumn('profile_image', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('social_facebook', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('social_instagram', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('social_twitter', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['user_id'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 3. ATTENDEES TABLE
        // =====================================================
        if (!$this->hasTable('attendees')) {
            $this->table('attendees', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('first_name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('last_name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('phone', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('bio', 'text', ['null' => true])
                ->addColumn('profile_image', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['user_id'])
                ->addIndex(['email'])
                ->addIndex(['phone'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 4. EVENT TYPES TABLE
        // =====================================================
        if (!$this->hasTable('event_types')) {
            $this->table('event_types', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('slug', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->create();
        }

        // =====================================================
        // 5. EVENTS TABLE
        // =====================================================
        if (!$this->hasTable('events')) {
            $this->table('events', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('organizer_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('slug', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('event_type_id', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('venue_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('address', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('map_url', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('banner_image', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('start_time', 'datetime', ['null' => false])
                ->addColumn('end_time', 'datetime', ['null' => false])
                ->addColumn('status', 'enum', [
                    'values' => ['draft', 'completed', 'pending', 'published', 'cancelled'],
                    'default' => 'draft',
                    'null' => false
                ])
                ->addColumn('is_featured', 'boolean', ['default' => false, 'null' => false])
                ->addColumn('admin_share_percent', 'decimal', [
                    'precision' => 5,
                    'scale' => 2,
                    'default' => 10.00,
                    'null' => false,
                    'comment' => 'Admin/platform share percentage (0-100). Organizer gets remainder.'
                ])
                ->addColumn('audience', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('language', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('tags', 'json', ['null' => true])
                ->addColumn('website', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('facebook', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('twitter', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('instagram', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('phone', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('video_url', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('country', 'string', ['limit' => 255, 'null' => false, 'default' => 'Ghana'])
                ->addColumn('region', 'string', ['limit' => 255, 'null' => false, 'default' => 'Greater Accra'])
                ->addColumn('city', 'string', ['limit' => 255, 'null' => false, 'default' => 'Accra'])
                ->addColumn('views', 'integer', ['default' => 0, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['event_type_id'])
                ->addIndex(['organizer_id'])
                ->addIndex(['is_featured'])
                ->addForeignKey('event_type_id', 'event_types', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('organizer_id', 'organizers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 6. EVENT IMAGES TABLE
        // =====================================================
        if (!$this->hasTable('event_images')) {
            $this->table('event_images', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('event_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('image_path', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['event_id'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 7. AWARDS TABLE
        // =====================================================
        if (!$this->hasTable('awards')) {
            $this->table('awards', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('organizer_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('slug', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('banner_image', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('venue_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('address', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('map_url', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('ceremony_date', 'datetime', ['null' => false, 'comment' => 'Awards ceremony date'])
                ->addColumn('voting_start', 'datetime', ['null' => false, 'comment' => 'Global voting start'])
                ->addColumn('voting_end', 'datetime', ['null' => false, 'comment' => 'Global voting end'])
                ->addColumn('status', 'enum', [
                    'values' => ['draft', 'completed', 'pending', 'published', 'cancelled'],
                    'default' => 'draft',
                    'null' => true
                ])
                ->addColumn('show_results', 'boolean', ['default' => true, 'null' => false, 'comment' => 'Whether to show voting results publicly'])
                ->addColumn('is_featured', 'boolean', ['default' => false, 'null' => false])
                ->addColumn('admin_share_percent', 'decimal', [
                    'precision' => 5,
                    'scale' => 2,
                    'default' => 15.00,
                    'null' => false,
                    'comment' => 'Admin/platform share percentage (0-100). Organizer gets remainder.'
                ])
                ->addColumn('country', 'string', ['limit' => 255, 'null' => false, 'default' => 'Ghana'])
                ->addColumn('region', 'string', ['limit' => 255, 'null' => false, 'default' => 'Greater Accra'])
                ->addColumn('city', 'string', ['limit' => 255, 'null' => false, 'default' => 'Accra'])
                ->addColumn('phone', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('website', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('facebook', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('twitter', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('instagram', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('video_url', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('views', 'integer', ['default' => 0, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['slug'], ['unique' => true])
                ->addIndex(['organizer_id'])
                ->addIndex(['status'])
                ->addIndex(['is_featured'])
                ->addIndex(['voting_start'])
                ->addIndex(['voting_end'])
                ->addForeignKey('organizer_id', 'organizers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 8. AWARDS IMAGES TABLE
        // =====================================================
        if (!$this->hasTable('awards_images')) {
            $this->table('awards_images', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('award_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('image_path', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['award_id'])
                ->addForeignKey('award_id', 'awards', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 9. AWARD CATEGORIES TABLE
        // =====================================================
        if (!$this->hasTable('award_categories')) {
            $this->table('award_categories', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('award_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('image', 'text', ['null' => true])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('cost_per_vote', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 1.00, 'null' => false])
                ->addColumn('voting_start', 'datetime', ['null' => true])
                ->addColumn('voting_end', 'datetime', ['null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'deactivated'],
                    'default' => 'active',
                    'null' => false
                ])
                ->addColumn('display_order', 'integer', ['default' => 0, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['award_id'])
                ->addForeignKey('award_id', 'awards', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 10. AWARD NOMINEES TABLE
        // =====================================================
        if (!$this->hasTable('award_nominees')) {
            $this->table('award_nominees', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('category_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('award_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('image', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('display_order', 'integer', ['default' => 0, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['category_id'])
                ->addIndex(['award_id'])
                ->addForeignKey('category_id', 'award_categories', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('award_id', 'awards', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 11. AWARD VOTES TABLE
        // =====================================================
        if (!$this->hasTable('award_votes')) {
            $this->table('award_votes', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('nominee_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('category_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('award_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('number_of_votes', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('cost_per_vote', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('gross_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('admin_share_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('admin_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('organizer_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('payment_fee', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('status', 'enum', [
                    'values' => ['pending', 'paid'],
                    'default' => 'pending',
                    'null' => false
                ])
                ->addColumn('reference', 'text', ['null' => false])
                ->addColumn('voter_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('voter_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('voter_phone', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['nominee_id'])
                ->addIndex(['category_id'])
                ->addIndex(['award_id'])
                ->addForeignKey('nominee_id', 'award_nominees', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('category_id', 'award_categories', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('award_id', 'awards', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 12. TICKET TYPES TABLE
        // =====================================================
        if (!$this->hasTable('ticket_types')) {
            $this->table('ticket_types', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('event_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('organizer_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('sale_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('quantity', 'integer', ['null' => false, 'default' => 0])
                ->addColumn('remaining', 'integer', ['null' => false, 'default' => 0])
                ->addColumn('dynamic_fee', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0.00])
                ->addColumn('sale_start', 'datetime', ['null' => true])
                ->addColumn('sale_end', 'datetime', ['null' => true])
                ->addColumn('max_per_user', 'integer', ['default' => 10])
                ->addColumn('ticket_image', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'deactivated'],
                    'default' => 'active',
                    'null' => false
                ])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['event_id'])
                ->addIndex(['organizer_id'])
                ->addIndex(['sale_start'])
                ->addIndex(['sale_end'])
                ->addForeignKey('organizer_id', 'organizers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 13. ORDERS TABLE
        // =====================================================
        if (!$this->hasTable('orders')) {
            $this->table('orders', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('subtotal', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('fees', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('status', 'enum', [
                    'values' => ['pending', 'paid', 'failed', 'refunded', 'cancelled'],
                    'default' => 'pending'
                ])
                ->addColumn('payment_reference', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('customer_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('customer_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('customer_phone', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('paid_at', 'datetime', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['user_id'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 14. ORDER ITEMS TABLE
        // =====================================================
        if (!$this->hasTable('order_items')) {
            $this->table('order_items', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('order_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('event_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('ticket_type_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('quantity', 'integer', ['null' => false])
                ->addColumn('unit_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                ->addColumn('total_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                ->addColumn('admin_share_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('admin_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('organizer_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('payment_fee', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['order_id'])
                ->addIndex(['event_id'])
                ->addIndex(['ticket_type_id'])
                ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('ticket_type_id', 'ticket_types', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 15. TICKETS TABLE
        // =====================================================
        if (!$this->hasTable('tickets')) {
            $this->table('tickets', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('order_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('event_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('ticket_type_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('ticket_code', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('status', 'enum', [
                    'values' => ['active', 'used', 'cancelled'],
                    'default' => 'active'
                ])
                ->addColumn('admitted_by', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('admitted_at', 'timestamp', ['null' => true])
                ->addColumn('attendee_id', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['ticket_code'], ['unique' => true])
                ->addIndex(['order_id'])
                ->addIndex(['event_id'])
                ->addIndex(['ticket_type_id'])
                ->addIndex(['attendee_id'])
                ->addIndex(['admitted_by'])
                ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('ticket_type_id', 'ticket_types', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('attendee_id', 'attendees', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->addForeignKey('admitted_by', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 16. EVENT REVIEWS TABLE
        // =====================================================
        if (!$this->hasTable('event_reviews')) {
            $this->table('event_reviews', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('event_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('reviewer_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('rating', 'integer', ['null' => false, 'default' => 0])
                ->addColumn('comment', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['event_id'])
                ->addIndex(['reviewer_id'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('reviewer_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 17. ORGANIZER FOLLOWERS TABLE
        // =====================================================
        if (!$this->hasTable('organizer_followers')) {
            $this->table('organizer_followers', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('organizer_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('follower_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['organizer_id'])
                ->addIndex(['follower_id'])
                ->addForeignKey('organizer_id', 'organizers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('follower_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 18. ORGANIZER BALANCES TABLE
        // =====================================================
        if (!$this->hasTable('organizer_balances')) {
            $this->table('organizer_balances', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('organizer_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('available_balance', 'decimal', [
                    'precision' => 10,
                    'scale' => 2,
                    'default' => 0.00,
                    'null' => false,
                    'comment' => 'Ready for withdrawal'
                ])
                ->addColumn('pending_balance', 'decimal', [
                    'precision' => 10,
                    'scale' => 2,
                    'default' => 0.00,
                    'null' => false,
                    'comment' => 'Within hold period'
                ])
                ->addColumn('total_earned', 'decimal', [
                    'precision' => 10,
                    'scale' => 2,
                    'default' => 0.00,
                    'null' => false,
                    'comment' => 'Lifetime earnings'
                ])
                ->addColumn('total_withdrawn', 'decimal', [
                    'precision' => 10,
                    'scale' => 2,
                    'default' => 0.00,
                    'null' => false,
                    'comment' => 'Total payouts completed'
                ])
                ->addColumn('last_payout_at', 'timestamp', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['organizer_id'], ['unique' => true])
                ->addForeignKey('organizer_id', 'organizers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 19. PAYOUT REQUESTS TABLE
        // =====================================================
        if (!$this->hasTable('payout_requests')) {
            $this->table('payout_requests', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('organizer_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('event_id', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('award_id', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('payout_type', 'enum', [
                    'values' => ['event', 'award'],
                    'default' => 'event',
                    'null' => false
                ])
                ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('gross_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('admin_fee', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'null' => false])
                ->addColumn('payment_method', 'enum', [
                    'values' => ['bank_transfer', 'mobile_money'],
                    'default' => 'bank_transfer',
                    'null' => false
                ])
                ->addColumn('account_number', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('account_name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('bank_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('status', 'enum', [
                    'values' => ['pending', 'processing', 'completed', 'rejected'],
                    'default' => 'pending',
                    'null' => false
                ])
                ->addColumn('processed_by', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('processed_at', 'timestamp', ['null' => true])
                ->addColumn('rejection_reason', 'text', ['null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['organizer_id'])
                ->addIndex(['event_id'])
                ->addIndex(['award_id'])
                ->addIndex(['processed_by'])
                ->addForeignKey('organizer_id', 'organizers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('award_id', 'awards', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_payout_award'])
                ->addForeignKey('processed_by', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_payout_processor'])
                ->create();
        }

        // =====================================================
        // 20. TRANSACTIONS TABLE
        // =====================================================
        if (!$this->hasTable('transactions')) {
            $this->table('transactions', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('reference', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('transaction_type', 'enum', [
                    'values' => ['ticket_sale', 'vote_purchase', 'payout', 'refund'],
                    'null' => false
                ])
                ->addColumn('organizer_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('event_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('award_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('order_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('order_item_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('vote_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('payout_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('gross_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('admin_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('organizer_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('payment_fee', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00])
                ->addColumn('status', 'enum', [
                    'values' => ['pending', 'completed', 'failed', 'refunded'],
                    'default' => 'pending',
                    'null' => false
                ])
                ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('metadata', 'json', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['reference'], ['unique' => true])
                ->addIndex(['transaction_type'])
                ->addIndex(['organizer_id'])
                ->addIndex(['event_id'])
                ->addIndex(['award_id'])
                ->addIndex(['status'])
                ->addIndex(['created_at'])
                ->addIndex(['order_id'])
                ->addIndex(['order_item_id'])
                ->addIndex(['vote_id'])
                ->addIndex(['payout_id'])
                ->addForeignKey('organizer_id', 'organizers', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->addForeignKey('award_id', 'awards', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->addForeignKey('order_item_id', 'order_items', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->addForeignKey('vote_id', 'award_votes', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->addForeignKey('payout_id', 'payout_requests', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 21. PLATFORM SETTINGS TABLE
        // =====================================================
        if (!$this->hasTable('platform_settings')) {
            $this->table('platform_settings', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('setting_key', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('setting_value', 'text', ['null' => false])
                ->addColumn('setting_type', 'enum', [
                    'values' => ['string', 'number', 'boolean', 'json'],
                    'default' => 'string',
                    'null' => false
                ])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['setting_key'], ['unique' => true])
                ->create();
        }

        // =====================================================
        // 22. PASSWORD RESETS TABLE
        // =====================================================
        if (!$this->hasTable('password_resets')) {
            $this->table('password_resets', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('token', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['email', 'token'], ['name' => 'password_resets_email_token'])
                ->addIndex(['created_at'], ['name' => 'password_resets_created_at'])
                ->create();
        }

        // =====================================================
        // 23. REFRESH TOKENS TABLE
        // =====================================================
        if (!$this->hasTable('refresh_tokens')) {
            $this->table('refresh_tokens', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('token_hash', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('device_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('user_agent', 'text', ['null' => true])
                ->addColumn('expires_at', 'timestamp', ['null' => false])
                ->addColumn('revoked', 'boolean', ['default' => false])
                ->addColumn('revoked_at', 'timestamp', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['token_hash'], ['unique' => true])
                ->addIndex(['user_id'])
                ->addIndex(['expires_at'])
                ->addIndex(['revoked'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 24. AUDIT LOGS TABLE
        // =====================================================
        if (!$this->hasTable('audit_logs')) {
            $this->table('audit_logs', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('user_id', 'integer', ['null' => true, 'signed' => false])
                ->addColumn('action', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => false])
                ->addColumn('user_agent', 'text', ['null' => true])
                ->addColumn('metadata', 'json', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['user_id'])
                ->addIndex(['action'])
                ->addIndex(['created_at'])
                ->addIndex(['ip_address'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 25. SCANNER ASSIGNMENTS TABLE
        // =====================================================
        if (!$this->hasTable('scanner_assignments')) {
            $this->table('scanner_assignments', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('event_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('organizer_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addIndex(['user_id'])
                ->addIndex(['event_id'])
                ->addIndex(['organizer_id'])
                ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addForeignKey('organizer_id', 'organizers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // =====================================================
        // 26. POS ASSIGNMENTS TABLE
        // =====================================================
        if (!$this->hasTable('pos_assignments')) {
            $this->table('pos_assignments', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->addColumn('user_id', 'integer', ['null' => true])
                ->addColumn('event_id', 'integer', ['null' => true])
                ->addColumn('organizer_id', 'integer', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
                ->create();
        }
    }

    public function down(): void
    {
        // Drop tables in reverse order (respecting foreign key constraints)
        $tables = [
            'pos_assignments',
            'scanner_assignments',
            'audit_logs',
            'refresh_tokens',
            'password_resets',
            'platform_settings',
            'transactions',
            'payout_requests',
            'organizer_balances',
            'organizer_followers',
            'event_reviews',
            'tickets',
            'order_items',
            'orders',
            'ticket_types',
            'award_votes',
            'award_nominees',
            'award_categories',
            'awards_images',
            'awards',
            'event_images',
            'events',
            'event_types',
            'attendees',
            'organizers',
            'users',
        ];

        foreach ($tables as $tableName) {
            if ($this->hasTable($tableName)) {
                $this->table($tableName)->drop()->save();
            }
        }
    }
}
