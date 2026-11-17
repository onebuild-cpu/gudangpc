ALTER TABLE search_cache ADD COLUMN is_indexed TINYINT(1) DEFAULT 1 AFTER result_count;
