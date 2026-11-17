<?php
namespace App\Models;

use App\Core\Model;

/**
 * Category model for the catalog base module.
 */
class CategoryModel extends Model
{
    protected string $table = 'categories';

    /**
     * Return all categories ordered by sort order, then name.
     */
    public function findAllOrdered(): array
    {
        return $this->query(
            "SELECT * FROM {$this->table} ORDER BY sort_order ASC, name ASC"
        );
    }

    /**
     * Return all published categories ordered by the given column.
     */
    public function findAllPublished(string $orderBy = 'name ASC'): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+ (ASC|DESC)$/i', $orderBy)) {
            $orderBy = 'name ASC';
        }

        return $this->query(
            "SELECT * FROM {$this->table} WHERE status = ? ORDER BY {$orderBy}",
            ['published']
        );
    }

    /**
     * Check whether a slug is already used by another category.
     */
    public function slugExists(string $slug, int $ignoreId = 0): bool
    {
        $category = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE slug = ? AND id != ?",
            [$slug, $ignoreId]
        );

        if ($category) {
            return true;
        }

        return false;
    }
}
