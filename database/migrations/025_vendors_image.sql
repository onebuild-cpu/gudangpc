-- Migration 025: add an optional image path for vendors.
ALTER TABLE `vendors`
  ADD COLUMN `image` varchar(255) DEFAULT NULL AFTER `description`;
