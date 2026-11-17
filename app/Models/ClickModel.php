<?php
namespace App\Models;

use App\Core\Model;

/**
 * Click model for affiliate offer click tracking.
 */
class ClickModel extends Model
{
    protected string $table = 'offer_clicks';
    protected bool $timestamps = false;

    /**
     * Record a single offer click.
     */
    public function logClick(int $offerId, string $ipHash, ?string $userAgent, ?string $referer): bool
    {
        $newId = $this->insert([
            'offer_id' => $offerId,
            'clicked_at' => date('Y-m-d H:i:s'),
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent,
            'referer' => $referer,
        ]);

        if ($newId > 0) {
            return true;
        }

        return false;
    }

    /**
     * Count the recorded clicks of one offer.
     */
    public function countByOfferId(int $offerId): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM `offer_clicks` WHERE `offer_id` = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$offerId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Count the recorded clicks across all offers of one product.
     */
    public function countByProductId(int $productId): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM `offer_clicks` oc
                INNER JOIN `offers` o ON o.id = oc.offer_id
                WHERE o.product_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$productId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Return click totals per product for dashboard analytics.
     */
    public function getProductClickStats(string $sort, string $dir): array
    {
        $direction = 'DESC';
        if ($dir === 'asc') {
            $direction = 'ASC';
        }

        $orderBy = 'clicks DESC, p.id ASC';
        if ($sort === 'clicks') {
            $secondary = 'ASC';
            if ($direction === 'DESC') {
                $secondary = 'ASC';
            }
            $orderBy = 'clicks ' . $direction . ', p.id ASC';
        } elseif ($sort === 'name') {
            $orderBy = 'p.name ' . $direction . ', clicks DESC';
        } elseif ($sort === 'id') {
            $orderBy = 'p.id ' . $direction . ', clicks DESC';
        }

        $sql = "SELECT p.id, p.name, COUNT(oc.id) AS clicks
                FROM products p
                LEFT JOIN offers o ON o.product_id = p.id
                LEFT JOIN offer_clicks oc ON oc.offer_id = o.id
                GROUP BY p.id, p.name
                ORDER BY " . $orderBy;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
