<?php
namespace App\Models;

use App\Core\Model;

/**
 * Offer model for the products and offers module.
 */
class OfferModel extends Model
{
    protected string $table = 'offers';

    /**
     * Return all offers for a product with their store names.
     */
    public function findByProductId(int $productId): array
    {
        $sql = "SELECT o.*, s.name AS store_name, v.name AS vendor_name, v.rating AS vendor_rating
                FROM {$this->table} o
                LEFT JOIN stores s ON s.id = o.store_id
                LEFT JOIN vendors v ON v.id = o.vendor_id
                WHERE o.product_id = ?
                ORDER BY o.price_idr ASC";

        return $this->query($sql, [$productId]);
    }

    /**
     * Return the SQL fragment that defines a publicly active offer.
     */
    public static function activeCondition(): string
    {
        return "o.status = 'published' AND o.in_stock = 1 AND s.status = 'published' AND (v.id IS NULL OR v.status = 'active') AND (o.last_checked_at IS NULL OR o.last_checked_at >= DATE_SUB(NOW(), INTERVAL 14 DAY))";
    }

    /**
     * Return all active offers for a product with their store names.
     */
    public function findPublicByProductId(int $productId): array
    {
        $activeCondition = self::activeCondition();
        $sql = "SELECT o.*, s.name AS store_name, v.name AS vendor_name, v.rating AS vendor_rating, v.store_rating AS vendor_store_rating, l.name AS vendor_location_name
                FROM {$this->table} o
                LEFT JOIN stores s ON s.id = o.store_id
                LEFT JOIN vendors v ON v.id = o.vendor_id
                LEFT JOIN locations l ON l.id = v.location_id
                WHERE o.product_id = ? AND " . $activeCondition . "
                ORDER BY o.price_idr ASC";

        return $this->query($sql, [$productId]);
    }

    /**
     * Return one active offer by id, or null when it is not publicly available.
     */
    public function findActiveById(int $offerId): ?array
    {
        $activeCondition = self::activeCondition();
        $sql = "SELECT o.*, s.name AS store_name, v.name AS vendor_name, v.rating AS vendor_rating, v.store_rating AS vendor_store_rating
                FROM {$this->table} o
                LEFT JOIN stores s ON s.id = o.store_id
                LEFT JOIN vendors v ON v.id = o.vendor_id
                WHERE o.id = ? AND " . $activeCondition . "
                LIMIT 1";

        $result = $this->queryOne($sql, [$offerId]);

        if ($result) {
            return $result;
        }

        return null;
    }

    /**
     * Count the offers that are publicly active.
     */
    public function countActive(): int
    {
        $activeCondition = self::activeCondition();
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} o
                INNER JOIN stores s ON s.id = o.store_id
                LEFT JOIN vendors v ON v.id = o.vendor_id
                WHERE " . $activeCondition;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Count published, in-stock offers whose last check is older than 14 days.
     */
    public function countStale(): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} o
                WHERE o.status = 'published' AND o.in_stock = 1
                    AND o.last_checked_at < DATE_SUB(NOW(), INTERVAL 14 DAY)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Count how many offers reference a store.
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
     * Count how many offers reference a vendor.
     */
    public function countByVendorId(int $vendorId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE vendor_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$vendorId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }
}
