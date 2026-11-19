<?php
namespace App\Models;

use App\Core\Model;

/**
 * Location model for the locations catalog module.
 */
class LocationModel extends Model
{
    protected string $table = 'locations';

    /**
     * Check whether a slug is already used by another location.
     */
    public function slugExists(string $slug, int $ignoreId = 0): bool
    {
        $location = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE slug = ? AND id != ?",
            [$slug, $ignoreId]
        );

        if ($location) {
            return true;
        }

        return false;
    }

    /**
     * Check whether a name is already used by another location.
     */
    public function nameExists(string $name, int $ignoreId = 0): bool
    {
        $location = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE LOWER(name) = LOWER(?) AND id != ?",
            [$name, $ignoreId]
        );

        if ($location) {
            return true;
        }

        return false;
    }
}
