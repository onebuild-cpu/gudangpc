-- Merged migrations 002-023 (in order)


-- ===== 002_create_categories_table.sql =====

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 003_create_others_table.sql =====

CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `base_url` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `price_idr` bigint(20) UNSIGNED NOT NULL,
  `affiliate_url` text NOT NULL,
  `in_stock` tinyint(1) DEFAULT 1,
  `last_checked_at` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_store` (`product_id`,`store_id`),
  KEY `store_id` (`store_id`),
  CONSTRAINT `offers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offers_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seo_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meta_type` varchar(50) NOT NULL,
  `meta_id` int(11) NOT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `robots_tag` varchar(50) DEFAULT 'index, follow',
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `twitter_title` varchar(255) DEFAULT NULL,
  `twitter_description` text DEFAULT NULL,
  `schema_markup` text DEFAULT NULL,
  `custom_head_tags` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `meta_link` (`meta_type`,`meta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 004_create_members_table.sql =====

CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `verification_token` varchar(64) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 005_add_category_image.sql =====

ALTER TABLE `categories`
  ADD COLUMN IF NOT EXISTS `image` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `image_alt` varchar(255) DEFAULT NULL;

-- ===== 006_create_settings_table.sql =====

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 007_add_seo_meta_custom_body_footer.sql =====

ALTER TABLE `seo_meta`
  ADD COLUMN IF NOT EXISTS `custom_body` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `custom_footer` text DEFAULT NULL;

-- ===== 008_add_product_image_alt.sql =====

ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `image_alt` varchar(255) DEFAULT NULL;

-- ===== 009_add_seller_to_offers.sql =====

ALTER TABLE `offers`
  ADD COLUMN IF NOT EXISTS `seller_name` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `seller_rating` decimal(3,2) DEFAULT NULL;

-- ===== 010_create_vendors_table.sql =====

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `rating` decimal(3,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `store_id` (`store_id`),
  CONSTRAINT `vendors_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 011_add_vendor_id_to_offers.sql =====

ALTER TABLE `offers`
  ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL;

ALTER TABLE `offers`
  ADD CONSTRAINT `offers_ibfk_3` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

-- ===== 012_fix_offers_constraints.sql =====

-- 1. Drop the foreign key constraints that rely on the index
ALTER TABLE `offers` DROP FOREIGN KEY `offers_ibfk_1`;
ALTER TABLE `offers` DROP FOREIGN KEY `offers_ibfk_2`;

-- 2. Drop the old unique index
ALTER TABLE `offers` DROP INDEX `product_store`;

-- 3. Add the new unique index based on vendor
ALTER TABLE `offers` ADD UNIQUE KEY `product_vendor` (`product_id`, `vendor_id`);

-- 4. Re-add the foreign key constraints
ALTER TABLE `offers` 
  ADD CONSTRAINT `offers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
ALTER TABLE `offers` 
  ADD CONSTRAINT `offers_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`);

-- ===== 013_create_offer_clicks_table.sql =====

CREATE TABLE `offer_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `offer_id` int(11) NOT NULL,
  `clicked_at` datetime NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `referer` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `offer_id` (`offer_id`),
  CONSTRAINT `offer_clicks_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 014_add_indexes.sql =====

-- Add missing performance indexes.
-- Each block checks information_schema first so the migration is safe to run
-- when an equivalent UNIQUE/FOREIGN KEY index already exists.

-- products(slug): already covered by UNIQUE KEY `slug` on the current schema.
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'products'
      AND column_name = 'slug'
      AND seq_in_index = 1
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `products` ADD INDEX `idx_products_slug` (`slug`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- offers(product_id): already covered by the leftmost column of UNIQUE KEY `product_vendor`.
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'offers'
      AND column_name = 'product_id'
      AND seq_in_index = 1
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `offers` ADD INDEX `idx_offers_product_id` (`product_id`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- offers(vendor_id): already covered by `offers_ibfk_3`.
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'offers'
      AND column_name = 'vendor_id'
      AND seq_in_index = 1
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `offers` ADD INDEX `idx_offers_vendor_id` (`vendor_id`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- seo_meta(meta_type, meta_id): already covered by UNIQUE KEY `meta_link`.
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics s1
    JOIN information_schema.statistics s2
      ON s1.table_schema = s2.table_schema
     AND s1.table_name = s2.table_name
     AND s1.index_name = s2.index_name
    WHERE s1.table_schema = DATABASE()
      AND s1.table_name = 'seo_meta'
      AND s1.column_name = 'meta_type'
      AND s1.seq_in_index = 1
      AND s2.column_name = 'meta_id'
      AND s2.seq_in_index = 2
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `seo_meta` ADD INDEX `idx_seo_meta_type_id` (`meta_type`, `meta_id`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- categories(slug): already covered by UNIQUE KEY `slug`.
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'categories'
      AND column_name = 'slug'
      AND seq_in_index = 1
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `categories` ADD INDEX `idx_categories_slug` (`slug`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- vendors(slug): already covered by UNIQUE KEY `slug`.
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'vendors'
      AND column_name = 'slug'
      AND seq_in_index = 1
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `vendors` ADD INDEX `idx_vendors_slug` (`slug`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===== 015_add_whatsapp_to_members.sql =====

-- Add whatsapp column to members table
ALTER TABLE `members` ADD COLUMN `whatsapp` varchar(20) DEFAULT NULL;

-- Create wishlists table
CREATE TABLE `wishlists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlists_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_member_product` (`member_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 016_add_search_tags_and_cache.sql =====

ALTER TABLE `products` ADD COLUMN `search_tags` text DEFAULT NULL;

CREATE TABLE `search_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `query_hash` char(64) NOT NULL,
  `original_query` varchar(255) NOT NULL,
  `parsed_intent` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `query_hash` (`query_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 017_add_seller_title_to_offers.sql =====

ALTER TABLE `offers`
  ADD COLUMN IF NOT EXISTS `seller_title` varchar(255) DEFAULT NULL AFTER `affiliate_url`;

-- ===== 018_add_ratings_to_offers_and_vendors.sql =====

ALTER TABLE `offers`
  ADD COLUMN IF NOT EXISTS `product_rating` decimal(3,2) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `sold_count` int(11) DEFAULT NULL;

ALTER TABLE `vendors`
  ADD COLUMN IF NOT EXISTS `store_rating` decimal(3,2) DEFAULT NULL;

-- ===== 019_offer_status_and_freshness.sql =====

UPDATE offers SET status = 'published' WHERE status = 'draft';
UPDATE offers SET last_checked_at = updated_at WHERE last_checked_at IS NULL;
ALTER TABLE offers MODIFY COLUMN status varchar(20) DEFAULT 'published';

-- ===== 020_clean_seo_canonical.sql =====

UPDATE seo_meta SET canonical_url = NULL WHERE canonical_url IS NOT NULL AND canonical_url NOT LIKE 'http%';

-- ===== 021_offer_listing_identity_and_affiliate_template.sql =====

-- Listing identity and affiliate templates for the import engine.
-- Safe to run more than once: columns use IF NOT EXISTS and the unique key is
-- created only when information_schema does not already list it.

ALTER TABLE `offers`
  ADD COLUMN IF NOT EXISTS `source_url` text DEFAULT NULL AFTER `affiliate_url`,
  ADD COLUMN IF NOT EXISTS `external_id` varchar(100) DEFAULT NULL;

-- Unique per marketplace listing. Legacy rows with a NULL external_id are allowed
-- any number of times because MySQL unique keys treat NULLs as distinct.
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'offers'
      AND index_name = 'offers_store_external'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `offers` ADD UNIQUE KEY `offers_store_external` (`store_id`, `external_id`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `stores`
  ADD COLUMN IF NOT EXISTS `affiliate_url_template` varchar(500) DEFAULT NULL;

-- ===== 022_create_price_history.sql =====

-- Price history for offers. Run once: the backfill below is not idempotent.
CREATE TABLE IF NOT EXISTS `price_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `offer_id` int(11) NOT NULL,
  `price_idr` bigint(20) UNSIGNED NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_price_history_offer_recorded` (`offer_id`, `recorded_at`),
  CONSTRAINT `price_history_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `price_history` (`offer_id`, `price_idr`, `recorded_at`)
SELECT o.`id`, o.`price_idr`, COALESCE(o.`last_checked_at`, o.`updated_at`)
FROM `offers` o;

-- ===== 023_create_user_social_accounts.sql =====

-- Social accounts for Google/Facebook OAuth linking
CREATE TABLE IF NOT EXISTS `user_social_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_id` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_provider_id` (`provider`, `provider_id`),
  KEY `idx_social_user` (`user_id`),
  CONSTRAINT `fk_social_user` FOREIGN KEY (`user_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
