-- ==============================================================================
-- SaaS INVENTORY MANAGEMENT SYSTEM SCHEMA
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ==============================================================================
-- 1. SAAS GLOBAL & BILLING TABLES
-- ==============================================================================

DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `themeColor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#000000',
  `isPopular` tinyint(1) NOT NULL DEFAULT '0',
  
  -- Pricing Configuration
  `monthlyPrice` decimal(10,2) NOT NULL DEFAULT '0.00',
  `yearlyPrice` decimal(10,2) NOT NULL DEFAULT '0.00',
  `trialDays` int NOT NULL DEFAULT '14',
  
  -- Usage Limits (-1 = Unlimited)
  `maxUsers` int NOT NULL DEFAULT '-1',
  `maxProducts` int NOT NULL DEFAULT '-1',
  `maxOrdersPerMonth` int NOT NULL DEFAULT '-1',
  `maxCategories` int NOT NULL DEFAULT '-1',
  `maxSuppliers` int NOT NULL DEFAULT '-1',
  `maxCustomers` int NOT NULL DEFAULT '-1',
  `maxLocations` int NOT NULL DEFAULT '1',
  `maxStorageMb` int NOT NULL DEFAULT '1024',
  
  -- Features Arrays (JSON)
  `marketingFeatures` json DEFAULT NULL,
  `systemCapabilities` json DEFAULT NULL,
  
  `status` enum('active','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `businesses`;
CREATE TABLE `businesses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','on_trial','suspended','canceled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'on_trial',
  `usedStorageMb` decimal(12,2) NOT NULL DEFAULT '0.00',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `planId` int unsigned NOT NULL,
  `billingCycle` enum('monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `mrr` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('trialing','active','past_due','canceled','unpaid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trialing',
  `trialEndsAt` datetime DEFAULT NULL,
  `currentPeriodStart` datetime DEFAULT NULL,
  `currentPeriodEnd` datetime DEFAULT NULL,
  `cancelAtPeriodEnd` tinyint(1) NOT NULL DEFAULT '0',
  `gatewayCustomerId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gatewaySubscriptionId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paymentMethodBrand` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paymentMethodLast4` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `businessId` (`businessId`),
  KEY `planId` (`planId`),
  CONSTRAINT `fk_sub_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_plan` FOREIGN KEY (`planId`) REFERENCES `plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 2. AUTHENTICATION & USERS (Tenant Scoped)
-- ==============================================================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned DEFAULT NULL, -- NULL indicates a Super Admin
  `isSuperAdmin` tinyint(1) NOT NULL DEFAULT '0',
  `firstName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('owner','ceo','manager','salesperson') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'salesperson',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `passwordHash` text COLLATE utf8mb4_unicode_ci,
  `profileImage` text COLLATE utf8mb4_unicode_ci,
  `emailVerified` tinyint(1) DEFAULT '0',
  `mustChangePassword` tinyint(1) NOT NULL DEFAULT '0',
  `lastLogin` datetime DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_email` (`businessId`, `email`), -- Allows same email across different businesses if desired
  CONSTRAINT `fk_users_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE `user_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userId` int unsigned NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_category` (`userId`,`category`),
  CONSTRAINT `fk_us_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `refreshTokens`;
CREATE TABLE `refreshTokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userId` int unsigned NOT NULL,
  `tokenHash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deviceName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipAddress` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userAgent` text COLLATE utf8mb4_unicode_ci,
  `expiresAt` datetime NOT NULL,
  `revoked` tinyint(1) DEFAULT '0',
  `revokedAt` datetime DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  CONSTRAINT `fk_rt_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 3. CORE INVENTORY TABLES (Tenant Scoped)
-- ==============================================================================

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_category` (`businessId`, `category`),
  CONSTRAINT `fk_settings_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_category_name` (`businessId`, `name`),
  CONSTRAINT `fk_categories_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `unitsOfMeasure`;
CREATE TABLE `unitsOfMeasure` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbreviation` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_uom_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `contactPerson` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` text COLLATE utf8mb4_unicode_ci,
  `rating` int DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_suppliers_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `firstName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `businessName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customerType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loyaltyPoints` int DEFAULT '0',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_customers_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `categoryId` int unsigned DEFAULT NULL,
  `supplierId` int unsigned DEFAULT NULL,
  `unitOfMeasureId` int unsigned DEFAULT NULL,
  `sellingPrice` decimal(12,2) NOT NULL DEFAULT '0.00',
  `costPrice` decimal(12,2) NOT NULL DEFAULT '0.00',
  `barcode` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` text COLLATE utf8mb4_unicode_ci,
  `reorderLevel` int DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_sku` (`businessId`, `sku`), -- Crucial for SaaS Isolation
  KEY `categoryId` (`categoryId`),
  KEY `supplierId` (`supplierId`),
  KEY `unitOfMeasureId` (`unitOfMeasureId`),
  CONSTRAINT `fk_products_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`categoryId`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplierId`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_uom` FOREIGN KEY (`unitOfMeasureId`) REFERENCES `unitsOfMeasure` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `productId` int unsigned NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `warehouseLocation` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('in_stock','low_stock','out_of_stock') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock',
  `lastUpdated` datetime DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_product` (`businessId`, `productId`),
  CONSTRAINT `fk_inv_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_product` FOREIGN KEY (`productId`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 4. SALES, PURCHASES, & FINANCE (Tenant Scoped)
-- ==============================================================================

DROP TABLE IF EXISTS `discounts`;
CREATE TABLE `discounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `discount` decimal(12,2) DEFAULT '0.00',
  `discountAmount` decimal(12,2) DEFAULT '0.00',
  `discountType` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `discountCode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `startDate` datetime DEFAULT NULL,
  `endDate` datetime DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `maxUses` int DEFAULT NULL,
  `uses` int NOT NULL DEFAULT '0',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_discount_code` (`businessId`, `discountCode`),
  CONSTRAINT `fk_discounts_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `orderNumber` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customerId` int unsigned DEFAULT NULL,
  `createdBy` int unsigned DEFAULT NULL,
  `status` enum('pending','completed','cancelled','partially_fulfilled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GHS',
  `discountId` int unsigned DEFAULT NULL,
  `discountPercentage` decimal(12,2) DEFAULT NULL,
  `discountAmount` decimal(12,2) DEFAULT NULL,
  `discountType` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `discountedPrice` decimal(12,2) DEFAULT '0.00',
  `discountedTotalPrice` decimal(12,2) DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_orderNumber` (`businessId`, `orderNumber`),
  KEY `customerId` (`customerId`),
  KEY `createdBy` (`createdBy`),
  CONSTRAINT `fk_orders_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`createdBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_discount` FOREIGN KEY (`discountId`) REFERENCES `discounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orderItems`;
CREATE TABLE `orderItems` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `orderId` int unsigned NOT NULL,
  `productId` int unsigned DEFAULT NULL,
  `costPrice` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sellingPrice` decimal(12,2) NOT NULL DEFAULT '0.00',
  `quantity` int NOT NULL DEFAULT '0',
  `fulfilledQuantity` int NOT NULL DEFAULT '0',
  `refundedQuantity` int NOT NULL DEFAULT '0',
  `fulfillmentStatus` enum('pending','partial','fulfilled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `totalPrice` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `orderId` (`orderId`),
  KEY `productId` (`productId`),
  CONSTRAINT `fk_orderItems_order` FOREIGN KEY (`orderId`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orderItems_product` FOREIGN KEY (`productId`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `supplierId` int unsigned DEFAULT NULL,
  `createdBy` int unsigned DEFAULT NULL,
  `purchaseNumber` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waybillNumber` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batchNumber` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchaseDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `dueDate` datetime DEFAULT NULL,
  `expectedDeliveryDate` datetime DEFAULT NULL,
  `receivedDate` datetime DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(12,2) NOT NULL DEFAULT '0.00',
  `shippingCost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `totalAmount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','ordered','received','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GHS',
  `paymentStatus` enum('unpaid','partial','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `paymentMethodId` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_purchaseNumber` (`businessId`, `purchaseNumber`),
  KEY `supplierId` (`supplierId`),
  CONSTRAINT `fk_purchases_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_purchases_supplier` FOREIGN KEY (`supplierId`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_purchases_user` FOREIGN KEY (`createdBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_purchases_payment_method` FOREIGN KEY (`paymentMethodId`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchaseItems`;
CREATE TABLE `purchaseItems` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `purchaseId` int unsigned NOT NULL,
  `productId` int unsigned NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `remainingQuantity` int NOT NULL DEFAULT '0' COMMENT 'Tracks current stock available for this specific batch',
  `costPrice` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sellingPrice` decimal(12,2) NOT NULL DEFAULT '0.00',
  `totalPrice` decimal(12,2) NOT NULL DEFAULT '0.00',
  `expiryDate` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchaseId` (`purchaseId`),
  KEY `productId` (`productId`),
  CONSTRAINT `fk_pi_purchase` FOREIGN KEY (`purchaseId`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_product` FOREIGN KEY (`productId`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `orderId` int unsigned NOT NULL,
  `customerId` int unsigned DEFAULT NULL,
  `createdBy` int unsigned DEFAULT NULL,
  `refundType` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'partial',
  `paymentMethodId` int unsigned DEFAULT NULL,
  `refundAmount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `refundDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `refundReason` text COLLATE utf8mb4_unicode_ci,
  `items` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `isRestocked` tinyint(1) NOT NULL DEFAULT '0',
  `refundStatus` enum('pending','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GHS',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `orderId` (`orderId`),
  CONSTRAINT `fk_refunds_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_refunds_order` FOREIGN KEY (`orderId`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_refunds_customer` FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_refunds_user` FOREIGN KEY (`createdBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refunds_payment_method` FOREIGN KEY (`paymentMethodId`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 5. EXPENSES & TRANSACTIONS
-- ==============================================================================

DROP TABLE IF EXISTS `expenseCategories`;
CREATE TABLE `expenseCategories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ec_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expenseSchedules`;
CREATE TABLE `expenseSchedules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `expenseCategoryId` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` enum('none','daily','weekly','monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `startDate` date DEFAULT NULL,
  `nextDueDate` date DEFAULT NULL,
  `endDate` date DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `paymentMethodId` int unsigned DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_es_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_category` FOREIGN KEY (`expenseCategoryId`) REFERENCES `expenseCategories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_payment_method` FOREIGN KEY (`paymentMethodId`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `expenseScheduleId` int unsigned DEFAULT NULL,
  `expenseCategoryId` int unsigned NOT NULL,
  `createdBy` int unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `transactionDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('paid','pending','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GHS',
  `evidence` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` text COLLATE utf8mb4_unicode_ci,
  `paymentMethodId` int unsigned DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_expenses_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expenses_cat` FOREIGN KEY (`expenseCategoryId`) REFERENCES `expenseCategories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expenses_sched` FOREIGN KEY (`expenseScheduleId`) REFERENCES `expenseSchedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`createdBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expenses_payment_method` FOREIGN KEY (`paymentMethodId`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `methodCode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'internal',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_pm_code` (`businessId`, `methodCode`),
  CONSTRAINT `fk_pm_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `orderId` int unsigned DEFAULT NULL,
  `expenseId` int unsigned DEFAULT NULL,
  `purchaseId` int unsigned DEFAULT NULL,
  `refundId` int unsigned DEFAULT NULL,
  `adjustmentId` int unsigned DEFAULT NULL,
  `transactionType` enum('order','purchase','expense','adjustment','refunds','stock_loss') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'order',
  `paymentMethodId` int unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','completed','cancelled','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GHS',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_trans_payment_method` FOREIGN KEY (`paymentMethodId`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trans_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_order` FOREIGN KEY (`orderId`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_expense` FOREIGN KEY (`expenseId`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_purchase` FOREIGN KEY (`purchaseId`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_refund` FOREIGN KEY (`refundId`) REFERENCES `refunds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 6. MESSAGING & NOTIFICATIONS
-- ==============================================================================

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `userId` int unsigned DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `isRead` tinyint(1) NOT NULL DEFAULT '0',
  `readAt` datetime DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_notif_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messaging_templates`;
CREATE TABLE `messaging_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multi',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_mt_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messaging_campaigns`;
CREATE TABLE `messaging_campaigns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `createdBy` int unsigned DEFAULT NULL,
  `templateId` int unsigned DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `channels` json NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `recipientCount` int unsigned NOT NULL DEFAULT '0',
  `deliveredCount` int unsigned NOT NULL DEFAULT '0',
  `failedCount` int unsigned NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_mc_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mc_user` FOREIGN KEY (`createdBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mc_template` FOREIGN KEY (`templateId`) REFERENCES `messaging_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messaging_campaign_recipients`;
CREATE TABLE `messaging_campaign_recipients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned NOT NULL,
  `campaignId` int unsigned NOT NULL,
  `customerId` int unsigned DEFAULT NULL,
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error` text COLLATE utf8mb4_unicode_ci,
  `providerMessageId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sentAt` datetime DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_mcr_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mcr_campaign` FOREIGN KEY (`campaignId`) REFERENCES `messaging_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mcr_customer` FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 7. AUDITING & LOGS
-- ==============================================================================

DROP TABLE IF EXISTS `auditLogs`;
CREATE TABLE `auditLogs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned DEFAULT NULL, -- Nullable for super admin actions
  `userId` int unsigned DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ipAddress` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userAgent` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_audit_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `businessId` int unsigned DEFAULT NULL,
  `level` int unsigned NOT NULL,
  `level_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` json DEFAULT NULL,
  `extra` json DEFAULT NULL,
  `channel` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'app',
  `request_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_logs_business` FOREIGN KEY (`businessId`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 8. GLOBAL UTILITY TABLES (No Tenant Scope)
-- ==============================================================================

DROP TABLE IF EXISTS `exchange_rate_history`;
CREATE TABLE `exchange_rate_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `baseCurrency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `targetCurrency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `source` enum('api','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'api',
  `effectiveDate` date NOT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `baseCurrency` (`baseCurrency`,`targetCurrency`,`effectiveDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Other necessary utility tables like Password Resets and Email Verifications
DROP TABLE IF EXISTS `passwordResets`;
CREATE TABLE `passwordResets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `emailVerificationTokens`;
CREATE TABLE `emailVerificationTokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userId` int unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiresAt` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_evt_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `push_subscriptions`;
CREATE TABLE `push_subscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userId` int unsigned NOT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `p256dhKey` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `authKey` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ps_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;