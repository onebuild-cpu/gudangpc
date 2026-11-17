<?php
namespace App\Models;

use App\Core\Model;

/**
 * Stores price snapshots of offers.
 */
class PriceHistoryModel extends Model
{
    protected string $table = 'price_history';
    protected bool $timestamps = false;

    /**
     * Record the price of an offer at the current time.
     */
    public function record(int $offerId, int $priceIdr): bool
    {
        $newId = $this->insert([
            'offer_id' => $offerId,
            'price_idr' => $priceIdr,
            'recorded_at' => date('Y-m-d H:i:s'),
        ]);

        return $newId > 0;
    }
}
