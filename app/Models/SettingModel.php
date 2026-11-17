<?php
namespace App\Models;

use App\Core\Model;

/**
 * Key-value model for global application settings.
 */
class SettingModel extends Model
{
    protected string $table = 'settings';

    /**
     * Return the stored value for a setting key, or an empty string when missing.
     */
    public function getValue(string $key): string
    {
        $row = $this->queryOne(
            "SELECT setting_value FROM {$this->table} WHERE setting_key = ? LIMIT 1",
            [$key]
        );

        $value = '';
        if ($row !== null && isset($row['setting_value'])) {
            $value = (string) $row['setting_value'];
        }

        return $value;
    }

    /**
     * Insert a setting when it does not exist, otherwise update its value.
     */
    public function saveSetting(string $key, string $value): bool
    {
        $existing = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE setting_key = ? LIMIT 1",
            [$key]
        );

        if ($existing !== null) {
            return $this->update((int) $existing['id'], ['setting_value' => $value]);
        }

        $newId = $this->insert([
            'setting_key' => $key,
            'setting_value' => $value,
        ]);

        if ($newId > 0) {
            return true;
        }

        return false;
    }
}
