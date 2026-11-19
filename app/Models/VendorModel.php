<?php
namespace App\Models;

use App\Core\Model;
use App\Models\OfferModel;

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

    /**
     * Return locations that have sellers with active offers for the homepage.
     */
    public function findHomepageLocations(int $limit): array
    {
        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT l.id, l.name, COUNT(DISTINCT v.id) AS seller_count
                FROM locations l
                INNER JOIN vendors v ON v.location_id = l.id AND v.status = 'active'
                WHERE EXISTS (
                    SELECT 1 FROM products p
                    INNER JOIN offers o ON o.product_id = p.id
                    INNER JOIN stores s ON s.id = o.store_id
                    WHERE p.status = 'published' AND o.vendor_id = v.id AND " . $activeCondition . "
                )
                GROUP BY l.id, l.name
                ORDER BY seller_count DESC, l.name ASC
                LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Return a location's sellers with active offers for the homepage.
     */
    public function findHomepageSellersByLocation(int $locationId, int $limit): array
    {
        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT v.id, v.name, v.slug, s.name AS store_name, v.rating, v.store_rating,
                    (SELECT COUNT(DISTINCT p.id) FROM products p
                        INNER JOIN offers o ON o.product_id = p.id
                        INNER JOIN stores s ON s.id = o.store_id
                        WHERE p.status = 'published' AND o.vendor_id = v.id AND " . $activeCondition . ") AS product_count
                FROM {$this->table} v
                INNER JOIN stores s ON s.id = v.store_id
                WHERE v.status = 'active' AND v.location_id = ?
                HAVING product_count > 0
                ORDER BY product_count DESC, v.name ASC
                LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $locationId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Return all locations that have sellers with active offers.
     */
    public function findLocationsWithSellerCounts(): array
    {
        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT l.id, l.name, l.slug, COUNT(DISTINCT v.id) AS seller_count
                FROM locations l
                INNER JOIN vendors v ON v.location_id = l.id AND v.status = 'active'
                WHERE EXISTS (
                    SELECT 1 FROM products p
                    INNER JOIN offers o ON o.product_id = p.id
                    INNER JOIN stores s ON s.id = o.store_id
                    WHERE p.status = 'published' AND o.vendor_id = v.id AND " . $activeCondition . "
                )
                GROUP BY l.id, l.name, l.slug
                ORDER BY seller_count DESC, l.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Return all sellers with a location and an active offer.
     */
    public function findEligibleSellersWithLocation(): array
    {
        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT v.id, v.name, v.slug, v.location_id, s.name AS store_name, v.rating, v.store_rating,
                    (SELECT COUNT(DISTINCT p.id) FROM products p
                        INNER JOIN offers o ON o.product_id = p.id
                        INNER JOIN stores s ON s.id = o.store_id
                        WHERE p.status = 'published' AND o.vendor_id = v.id AND " . $activeCondition . ") AS product_count
                FROM {$this->table} v
                INNER JOIN stores s ON s.id = v.store_id
                WHERE v.status = 'active' AND v.location_id IS NOT NULL
                HAVING product_count > 0
                ORDER BY product_count DESC, v.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
