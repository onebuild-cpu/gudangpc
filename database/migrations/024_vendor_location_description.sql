-- Migration 024: add seller location and description; run migration 023 first.
ALTER TABLE `vendors`
  ADD COLUMN `location_id` int(11) DEFAULT NULL AFTER `store_id`,
  ADD COLUMN `description` varchar(1000) DEFAULT NULL,
  ADD KEY `vendors_location_id` (`location_id`),
  ADD CONSTRAINT `vendors_location_fk` FOREIGN KEY (`location_id`)
    REFERENCES `locations` (`id`) ON DELETE RESTRICT;
