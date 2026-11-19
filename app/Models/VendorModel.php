<?php
namespace App\Models;

use App\Core\Model;

/**
 * Vendor model for the vendors module.
 */
class VendorModel extends Model
{
    protected string $table = 'vendors';

    /**
     * Return all vendors with their store (platform) and location names.
     */
    public function findAllWithStore(): array
    {
        $sql = "SELECT v.*, s.name AS store_name, l.name AS location_name
                FROM {$this->table} v
                LEFT JOIN stores s ON s.id = v.store_id
                LEFT JOIN locations l ON l.id = v.location_id
                ORDER BY v.id DESC";

        return $this->query($sql);
    }

    /**
     * Check whether a slug is already used by another vendor.
     */
    public function slugExists(string $slug, int $ignoreId = 0): bool
    {
        $vendor = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE slug = ? AND id != ?",
            [$slug, $ignoreId]
        );

        if ($vendor) {
            return true;
        }

        return false;
    }

    /**
     * Count how many vendors belong to a store.
     */
    public function countByStoreId(int $storeId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE store_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Count how many vendors reference a location.
     */
    public function countByLocationId(int $locationId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE location_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$locationId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Return an active vendor by slug with its store and location names.
     */
    public function findActiveBySlug(string $slug): ?array
    {
        $sql = "SELECT v.*, s.name AS store_name, l.name AS location_name
                FROM {$this->table} v
                LEFT JOIN stores s ON s.id = v.store_id
                LEFT JOIN locations l ON l.id = v.location_id
                WHERE v.slug = ? AND v.status = 'active'
                LIMIT 1";
        $result = $this->queryOne($sql, [$slug]);

        if ($result) {
            return $result;
        }

        return null;
    }
}
