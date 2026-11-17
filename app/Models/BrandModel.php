<?php
namespace App\Models;

use App\Core\Model;

/**
 * Brand model for the catalog base module.
 */
class BrandModel extends Model
{
    protected string $table = 'brands';

    /**
     * Return all published brands ordered by the given column.
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
     * Return published brands that own at least one published product.
     */
    public function findPublishedWithProducts(int $limit): array
    {
        if ($limit < 1) {
            $limit = 12;
        }

        $sql = "SELECT b.* FROM {$this->table} b
                WHERE b.status = 'published'
                    AND EXISTS (SELECT 1 FROM products p WHERE p.brand_id = b.id AND p.status = 'published')
                ORDER BY b.name ASC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check whether a slug is already used by another brand.
     */
    public function slugExists(string $slug, int $ignoreId = 0): bool
    {
        $brand = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE slug = ? AND id != ?",
            [$slug, $ignoreId]
        );

        if ($brand) {
            return true;
        }

        return false;
    }
}
