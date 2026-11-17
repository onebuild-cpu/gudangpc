-- Creates the AI search cache table.
-- The legacy search_cache table is renamed first so its data is preserved.

RENAME TABLE `search_cache` TO `search_cache_legacy`;

CREATE TABLE `search_cache` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `query_text` varchar(255) NOT NULL,
  `query_slug` varchar(255) NOT NULL,
  `ai_response_json` json NOT NULL,
  `result_count` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_query_text` (`query_text`),
  UNIQUE KEY `uniq_query_slug` (`query_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
