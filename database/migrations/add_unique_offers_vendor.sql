-- Enforce one offer per (product, vendor).
-- Run once. Fails if duplicates already exist; dedupe first if needed.
ALTER TABLE offers
    ADD UNIQUE KEY uniq_offers_product_vendor (product_id, vendor_id);
