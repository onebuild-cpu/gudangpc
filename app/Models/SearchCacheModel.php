<?php
namespace App\Models;

use App\Core\Model;

/**
 * Stores parsed AI search queries for reuse.
 */
class SearchCacheModel extends Model
{
    protected string $table = 'search_cache';

    /**
     * Find a cache row by the original query text.
     */
    public function findByQueryText(string $queryText): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE query_text = ? LIMIT 1";

        return $this->queryOne($sql, [$queryText]);
    }

    /**
     * Find a cache row by its shareable query slug.
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE query_slug = ? LIMIT 1";

        return $this->queryOne($sql, [$slug]);
    }

    /**
     * Insert a new cache row and return its ID.
     */
    public function insert(array $data): int
    {
        return parent::insert($data);
    }

    /**
     * Update an existing cache row.
     */
    public function update(int $id, array $data): bool
    {
        return parent::update($id, $data);
    }

    /**
     * Update only the cached result count for a row.
     */
    public function updateResultCount(int $id, int $count): bool
    {
        return parent::update($id, ['result_count' => $count]);
    }

    /**
     * Return cache rows with results for the public sitemap.
     */
    public function getForSitemap(): array
    {
        $sql = "SELECT query_slug, updated_at FROM {$this->table}
                WHERE is_indexed = 1 AND result_count > 0
                ORDER BY updated_at DESC";

        return $this->query($sql);
    }

    /**
     * Return paginated cache rows for the admin search analytics table.
     */
    public function getAllPaginated(int $offset, int $limit, string $search = ''): array
    {
        if ($offset < 0) {
            $offset = 0;
        }
        if ($limit < 1) {
            $limit = 20;
        }

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE query_text LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $sql = "SELECT * FROM {$this->table} " . $where . "
                ORDER BY created_at DESC, id DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $position = 1;
        foreach ($params as $param) {
            $stmt->bindValue($position, $param);
            $position++;
        }
        $stmt->bindValue($position, $limit, \PDO::PARAM_INT);
        $position++;
        $stmt->bindValue($position, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count cache rows for the admin search analytics table.
     */
    public function countAll(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table}";
        $params = [];
        if ($search !== '') {
            $sql = $sql . ' WHERE query_text LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Set whether a cached query is included in the sitemap.
     */
    public function toggleIndexed(int $id, int $status): bool
    {
        if ($status !== 0 && $status !== 1) {
            return false;
        }

        return parent::update($id, ['is_indexed' => $status]);
    }
}
