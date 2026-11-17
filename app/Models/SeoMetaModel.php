<?php
namespace App\Models;

use App\Core\Model;

/**
 * SEO meta model for storing per-entity SEO fields.
 */
class SeoMetaModel extends Model
{
    protected string $table = 'seo_meta';

    /**
     * Insert or update the SEO meta row for an entity.
     */
    public function saveMeta(string $metaType, int $metaId, array $data): bool
    {
        $filtered = $this->filterAllowed($data);
        $existing = $this->findByMeta($metaType, $metaId);

        if ($existing) {
            if (empty($filtered)) {
                return true;
            }

            return $this->update((int) $existing['id'], $filtered);
        }

        $filtered['meta_type'] = $metaType;
        $filtered['meta_id'] = $metaId;

        $newId = $this->insert($filtered);

        if ($newId > 0) {
            return true;
        }

        return false;
    }

    /**
     * Find the SEO meta row for an entity.
     */
    public function findByMeta(string $metaType, int $metaId): ?array
    {
        return $this->queryOne(
            "SELECT * FROM {$this->table} WHERE meta_type = ? AND meta_id = ? LIMIT 1",
            [$metaType, $metaId]
        );
    }

    /**
     * Keep only the SEO columns that may be saved.
     */
    private function filterAllowed(array $data): array
    {
        $allowed = [
            'seo_title',
            'seo_description',
            'canonical_url',
            'robots_tag',
            'og_title',
            'og_description',
            'og_image',
            'twitter_title',
            'twitter_description',
            'schema_markup',
            'custom_head_tags',
            'custom_body',
            'custom_footer',
        ];

        $filtered = [];
        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                $filtered[$key] = $data[$key];
            }
        }

        return $filtered;
    }
}
