<?php
namespace App\Models;

use App\Core\Model;
use PDO;

class WishlistModel extends Model
{
    protected string $table = 'wishlists';

    /**
     * Save a product to a member's wishlist.
     */
    public function add(int $memberId, int $productId): bool
    {
        $sql = "INSERT INTO {$this->table} (member_id, product_id) VALUES (?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$memberId, $productId]);
    }

    /**
     * Remove a product from a member's wishlist.
     */
    public function remove(int $memberId, int $productId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE member_id = ? AND product_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$memberId, $productId]);
    }

    /**
     * Check whether a member already saved a product to the wishlist.
     */
    public function exists(int $memberId, int $productId): bool
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE member_id = ? AND product_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$memberId, $productId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'] > 0;
    }

    /**
     * Return all published wishlist products for one member.
     */
    public function findAllByMember(int $memberId): array
    {
        $sql = "SELECT w.product_id, p.name AS product_name, p.slug AS product_slug, p.image, 
                        b.name AS brand_name, c.slug AS category_slug
                FROM {$this->table} w
                JOIN products p ON p.id = w.product_id
                LEFT JOIN brands b ON b.id = p.brand_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE w.member_id = ? AND p.status = 'published'
                ORDER BY w.created_at DESC";
        return $this->query($sql, [$memberId]);
    }
}