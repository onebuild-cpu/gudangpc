<?php
namespace App\Models;

use App\Core\Model;

/**
 * Store model for the catalog base module.
 */
class StoreModel extends Model
{
    protected string $table = 'stores';

    /**
     * Check whether a slug is already used by another store.
     */
    public function slugExists(string $slug, int $ignoreId = 0): bool
    {
        $store = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE slug = ? AND id != ?",
            [$slug, $ignoreId]
        );

        if ($store) {
            return true;
        }

        return false;
    }
}
