-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 23, 2025 at 04:19 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `eventic`
--
CREATE DATABASE IF NOT EXISTS `eventic` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `eventic`;

-- --------------------------------------------------------

--
-- Table structure for table `attendees`
--

DROP TABLE IF EXISTS `attendees`;
CREATE TABLE IF NOT EXISTS `attendees` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `email` (`email`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`),
  KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `awards`
--

DROP TABLE IF EXISTS `awards`;
CREATE TABLE IF NOT EXISTS `awards` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizer_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `venue_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `map_url` varchar(255) DEFAULT NULL,
  `ceremony_date` datetime NOT NULL COMMENT 'Awards ceremony date',
  `voting_start` datetime NOT NULL COMMENT 'Global voting start',
  `voting_end` datetime NOT NULL COMMENT 'Global voting end',
  `status` enum('draft','completed','pending','published','cancelled') DEFAULT 'draft',
  `show_results` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether to show voting results publicly',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `admin_share_percent` decimal(5,2) NOT NULL DEFAULT 15.00 COMMENT 'Admin/platform share percentage (0-100). Organizer gets remainder.',
  `country` varchar(255) NOT NULL DEFAULT 'Ghana',
  `region` varchar(255) NOT NULL DEFAULT 'Greater Accra',
  `city` varchar(255) NOT NULL DEFAULT 'Accra',
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `organizer_id` (`organizer_id`),
  KEY `status` (`status`),
  KEY `is_featured` (`is_featured`),
  KEY `voting_start` (`voting_start`),
  KEY `voting_end` (`voting_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `awards_images`
--

DROP TABLE IF EXISTS `awards_images`;
CREATE TABLE IF NOT EXISTS `awards_images` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `award_id` int(11) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `award_id` (`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `award_categories`
--

DROP TABLE IF EXISTS `award_categories`;
CREATE TABLE IF NOT EXISTS `award_categories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `award_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `image` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cost_per_vote` decimal(10,2) NOT NULL DEFAULT 1.00,
  `voting_start` datetime DEFAULT NULL,
  `voting_end` datetime DEFAULT NULL,
  `status` enum('active','deactivated') NOT NULL DEFAULT 'active',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `award_id` (`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `award_nominees`
--

DROP TABLE IF EXISTS `award_nominees`;
CREATE TABLE IF NOT EXISTS `award_nominees` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int(11) UNSIGNED NOT NULL,
  `award_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `award_id` (`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `award_votes`
--

DROP TABLE IF EXISTS `award_votes`;
CREATE TABLE IF NOT EXISTS `award_votes` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nominee_id` int(11) UNSIGNED NOT NULL,
  `category_id` int(11) UNSIGNED NOT NULL,
  `award_id` int(11) UNSIGNED NOT NULL,
  `number_of_votes` int(11) UNSIGNED NOT NULL,
  `cost_per_vote` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gross_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `admin_share_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `admin_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `organizer_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `reference` text NOT NULL,
  `voter_name` varchar(255) DEFAULT NULL,
  `voter_email` varchar(255) DEFAULT NULL,
  `voter_phone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `nominee_id` (`nominee_id`),
  KEY `category_id` (`category_id`),
  KEY `award_id` (`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizer_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type_id` int(11) UNSIGNED DEFAULT NULL,
  `venue_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `map_url` varchar(255) DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('draft','completed','pending','published','cancelled') DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `admin_share_percent` decimal(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Admin/platform share percentage (0-100). Organizer gets remainder.',
  `audience` varchar(255) DEFAULT NULL,
  `language` varchar(255) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `website` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `country` varchar(255) NOT NULL DEFAULT 'Ghana',
  `region` varchar(255) NOT NULL DEFAULT 'Greater Accra',
  `city` varchar(255) NOT NULL DEFAULT 'Accra',
  `views` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `event_type_id` (`event_type_id`),
  KEY `organizer_id` (`organizer_id`),
  KEY `is_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_images`
--

DROP TABLE IF EXISTS `event_images`;
CREATE TABLE IF NOT EXISTS `event_images` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(11) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_reviews`
--

DROP TABLE IF EXISTS `event_reviews`;
CREATE TABLE IF NOT EXISTS `event_reviews` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(11) UNSIGNED NOT NULL,
  `reviewer_id` int(11) UNSIGNED NOT NULL,
  `rating` int(11) NOT NULL DEFAULT 0,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_types`
--

DROP TABLE IF EXISTS `event_types`;
CREATE TABLE IF NOT EXISTS `event_types` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','paid','failed','refunded','cancelled') DEFAULT 'pending',
  `payment_reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `fees` decimal(10,2) DEFAULT 0.00,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(11) UNSIGNED NOT NULL,
  `event_id` int(11) UNSIGNED NOT NULL,
  `ticket_type_id` int(11) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `admin_share_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `admin_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `organizer_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `event_id` (`event_id`),
  KEY `ticket_type_id` (`ticket_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organizers`
--

DROP TABLE IF EXISTS `organizers`;
CREATE TABLE IF NOT EXISTS `organizers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `social_facebook` varchar(255) DEFAULT NULL,
  `social_instagram` varchar(255) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organizer_balances`
--

DROP TABLE IF EXISTS `organizer_balances`;
CREATE TABLE IF NOT EXISTS `organizer_balances` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizer_id` int(11) UNSIGNED NOT NULL,
  `available_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ready for withdrawal',
  `pending_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Within hold period',
  `total_earned` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Lifetime earnings',
  `total_withdrawn` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total payouts completed',
  `last_payout_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `organizer_id` (`organizer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organizer_followers`
--

DROP TABLE IF EXISTS `organizer_followers`;
CREATE TABLE IF NOT EXISTS `organizer_followers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizer_id` int(11) UNSIGNED NOT NULL,
  `follower_id` int(11) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `organizer_id` (`organizer_id`),
  KEY `follower_id` (`follower_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  KEY `email` (`email`,`token`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payout_requests`
--

DROP TABLE IF EXISTS `payout_requests`;
CREATE TABLE IF NOT EXISTS `payout_requests` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizer_id` int(11) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `award_id` int(11) UNSIGNED DEFAULT NULL,
  `payout_type` enum('event','award') NOT NULL DEFAULT 'event',
  `amount` decimal(10,2) DEFAULT 0.00,
  `gross_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `admin_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('bank_transfer','mobile_money') NOT NULL DEFAULT 'bank_transfer',
  `account_number` varchar(255) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
  `processed_by` int(11) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `organizer_id` (`organizer_id`),
  KEY `event_id` (`event_id`),
  KEY `fk_payout_award` (`award_id`),
  KEY `fk_payout_processor` (`processed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phinxlog`
--

DROP TABLE IF EXISTS `phinxlog`;
CREATE TABLE IF NOT EXISTS `phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

DROP TABLE IF EXISTS `platform_settings`;
CREATE TABLE IF NOT EXISTS `platform_settings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('string','number','boolean','json') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_assignments`
--

DROP TABLE IF EXISTS `pos_assignments`;
CREATE TABLE IF NOT EXISTS `pos_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `organizer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refresh_tokens`
--

DROP TABLE IF EXISTS `refresh_tokens`;
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revoked` tinyint(1) DEFAULT 0,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  KEY `revoked` (`revoked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scanner_assignments`
--

DROP TABLE IF EXISTS `scanner_assignments`;
CREATE TABLE IF NOT EXISTS `scanner_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `event_id` int(11) UNSIGNED NOT NULL,
  `organizer_id` int(11) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_id` (`event_id`),
  KEY `organizer_id` (`organizer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(11) UNSIGNED NOT NULL,
  `event_id` int(11) UNSIGNED NOT NULL,
  `ticket_type_id` int(11) UNSIGNED NOT NULL,
  `ticket_code` varchar(255) NOT NULL,
  `status` enum('active','used','cancelled') DEFAULT 'active',
  `admitted_by` int(11) UNSIGNED DEFAULT NULL,
  `admitted_at` timestamp NULL DEFAULT NULL,
  `attendee_id` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_code` (`ticket_code`),
  KEY `order_id` (`order_id`),
  KEY `event_id` (`event_id`),
  KEY `ticket_type_id` (`ticket_type_id`),
  KEY `attendee_id` (`attendee_id`),
  KEY `admitted_by` (`admitted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_types`
--

DROP TABLE IF EXISTS `ticket_types`;
CREATE TABLE IF NOT EXISTS `ticket_types` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(11) UNSIGNED NOT NULL,
  `organizer_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `remaining` int(11) NOT NULL DEFAULT 0,
  `dynamic_fee` decimal(5,2) DEFAULT 0.00,
  `sale_start` datetime DEFAULT NULL,
  `sale_end` datetime DEFAULT NULL,
  `max_per_user` int(11) DEFAULT 10,
  `ticket_image` varchar(255) DEFAULT NULL,
  `status` enum('active','deactivated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sale_price` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `organizer_id` (`organizer_id`),
  KEY `sale_start` (`sale_start`),
  KEY `sale_end` (`sale_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` varchar(100) NOT NULL,
  `transaction_type` enum('ticket_sale','vote_purchase','payout','refund') NOT NULL,
  `organizer_id` int(11) UNSIGNED DEFAULT NULL,
  `event_id` int(11) UNSIGNED DEFAULT NULL,
  `award_id` int(11) UNSIGNED DEFAULT NULL,
  `order_id` int(11) UNSIGNED DEFAULT NULL,
  `order_item_id` int(11) UNSIGNED DEFAULT NULL,
  `vote_id` int(11) UNSIGNED DEFAULT NULL,
  `payout_id` int(11) UNSIGNED DEFAULT NULL,
  `gross_amount` decimal(10,2) DEFAULT 0.00,
  `admin_amount` decimal(10,2) DEFAULT 0.00,
  `organizer_amount` decimal(10,2) DEFAULT 0.00,
  `payment_fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `description` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `transaction_type` (`transaction_type`),
  KEY `organizer_id` (`organizer_id`),
  KEY `event_id` (`event_id`),
  KEY `award_id` (`award_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `order_id` (`order_id`),
  KEY `order_item_id` (`order_item_id`),
  KEY `vote_id` (`vote_id`),
  KEY `payout_id` (`payout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `role` enum('admin','organizer','attendee','pos','scanner') NOT NULL DEFAULT 'attendee',
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `first_login` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendees`
--
ALTER TABLE `attendees`
  ADD CONSTRAINT `attendees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `awards`
--
ALTER TABLE `awards`
  ADD CONSTRAINT `awards_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `awards_images`
--
ALTER TABLE `awards_images`
  ADD CONSTRAINT `awards_images_ibfk_1` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `award_categories`
--
ALTER TABLE `award_categories`
  ADD CONSTRAINT `award_categories_ibfk_2` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `award_nominees`
--
ALTER TABLE `award_nominees`
  ADD CONSTRAINT `award_nominees_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `award_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `award_nominees_ibfk_3` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `award_votes`
--
ALTER TABLE `award_votes`
  ADD CONSTRAINT `award_votes_ibfk_1` FOREIGN KEY (`nominee_id`) REFERENCES `award_nominees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `award_votes_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `award_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `award_votes_ibfk_4` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `event_images`
--
ALTER TABLE `event_images`
  ADD CONSTRAINT `event_images_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `event_reviews`
--
ALTER TABLE `event_reviews`
  ADD CONSTRAINT `event_reviews_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `event_reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `organizers`
--
ALTER TABLE `organizers`
  ADD CONSTRAINT `organizers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `organizer_balances`
--
ALTER TABLE `organizer_balances`
  ADD CONSTRAINT `organizer_balances_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `organizer_followers`
--
ALTER TABLE `organizer_followers`
  ADD CONSTRAINT `organizer_followers_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `organizer_followers_ibfk_2` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD CONSTRAINT `fk_payout_award` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payout_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `payout_requests_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `payout_requests_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD CONSTRAINT `refresh_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `scanner_assignments`
--
ALTER TABLE `scanner_assignments`
  ADD CONSTRAINT `scanner_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `scanner_assignments_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `scanner_assignments_ibfk_3` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`attendee_id`) REFERENCES `attendees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`admitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ticket_types`
--
ALTER TABLE `ticket_types`
  ADD CONSTRAINT `ticket_types_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ticket_types_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_5` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_6` FOREIGN KEY (`vote_id`) REFERENCES `award_votes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_7` FOREIGN KEY (`payout_id`) REFERENCES `payout_requests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;
