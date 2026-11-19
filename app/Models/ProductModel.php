<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Validator;

/**
 * Product model for the products and offers module.
 */
class ProductModel extends Model
{
    protected string $table = 'products';

    private const KEYWORD_SYNONYMS = [
        'kartu grafis' => ['gpu', 'vga', 'graphics card', 'graphics'],
        'gpu' => ['vga', 'graphics card', 'graphics'],
        'vga' => ['gpu', 'graphics card', 'graphics'],
        'graphics card' => ['gpu', 'vga', 'graphics'],
        'prosesor' => ['cpu', 'processor'],
        'processor' => ['cpu'],
        'cpu' => ['processor'],
        'memori' => ['ram', 'memory'],
        'memory' => ['ram'],
        'ram' => ['memory'],
        'penyimpanan' => ['ssd', 'storage', 'hard disk', 'hardisk'],
        'storage' => ['ssd'],
        'ssd' => ['storage'],
        'hardisk' => ['hard disk', 'ssd', 'storage'],
        'hard disk' => ['hardisk', 'ssd', 'storage'],
        'casing' => ['case', 'chassis'],
        'case' => ['casing', 'chassis'],
        'power supply' => ['psu'],
        'psu' => ['power supply'],
        'pendingin' => ['cooler', 'cpu cooler'],
        'cooler' => ['cpu cooler', 'pendingin'],
        'layar' => ['monitor'],
        'monitor' => ['layar'],
        'motherboard' => ['mainboard'],
        'mainboard' => ['motherboard'],
        'laptop' => ['notebook'],
        'notebook' => ['laptop'],
    ];

    /**
     * Check whether a slug is already used by another product.
     */
    public function slugExists(string $slug, int $ignoreId = 0): bool
    {
        $product = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE slug = ? AND id != ?",
            [$slug, $ignoreId]
        );

        if ($product) {
            return true;
        }

        return false;
    }

    /**
     * Return all published products with category and brand names.
     */
    public function getAllWithRelations(): array
    {
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                ORDER BY p.id DESC";

        return $this->query($sql);
    }

    /**
     * Return the total number of products.
     */
    public function countAll(): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Return a filtered page of products with category and brand names.
     */
    public function getAllWithRelationsPaginated(int $offset, int $limit, string $search = '', string $status = ''): array
    {
        $conditions = [];
        $params = [];

        if ($search !== '') {
            $conditions[] = "(p.name LIKE ? ESCAPE '\\\\' OR p.short_description LIKE ? ESCAPE '\\\\')";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($status !== '') {
            $conditions[] = "p.status = ?";
            $params[] = $status;
        }

        $where = '';
        if ($conditions !== []) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                $where
                ORDER BY p.id DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        return $this->query($sql, $params);
    }

    /**
     * Return all published products with category and brand names.
     */
    public function getPublishedWithRelations(): array
    {
        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.status = 'published'
                ORDER BY p.id DESC";

        return $this->query($sql);
    }

    /**
     * Return the most recently created published products.
     */
    public function findNewestPublished(int $limit): array
    {
        if ($limit < 1) {
            $limit = 8;
        }

        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.status = 'published'
                ORDER BY p.id DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Return the latest published products in a category that have an active offer.
     */
    public function findLatestActiveByCategoryId(int $categoryId, int $limit): array
    {
        if ($limit < 1) {
            $limit = 8;
        }

        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.category_id = ? AND p.status = 'published'
                    AND EXISTS (SELECT 1 FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ")
                ORDER BY p.id DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $categoryId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Return published products in the same category that have an active offer.
     */
    public function findRelated(int $categoryId, int $excludeId, int $limit): array
    {
        if ($limit < 1) {
            $limit = 4;
        }

        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.category_id = ? AND p.id != ? AND p.status = 'published'
                    AND EXISTS (SELECT 1 FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ")
                ORDER BY p.id DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $categoryId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $excludeId, \PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Return published products that have at least one publicly active offer.
     */
    public function findAllPublishedWithActiveOffers(): array
    {
        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT DISTINCT p.id, p.slug, p.updated_at, c.slug AS category_slug
                FROM {$this->table} p
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN offers o ON o.product_id = p.id
                INNER JOIN stores s ON s.id = o.store_id
                LEFT JOIN vendors v ON v.id = o.vendor_id
                WHERE p.status = 'published' AND c.status = 'published' AND " . $activeCondition . "
                ORDER BY p.id DESC";

        return $this->query($sql);
    }

    /**
     * Return a single published product with category and brand names by ID.
     */
    public function findByIdWithRelations(int $id): ?array
    {
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.status AS category_status, b.name AS brand_name
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.id = ?
                LIMIT 1";

        $result = $this->queryOne($sql, [$id]);

        if ($result) {
            return $result;
        }

        return null;
    }

    /**
     * Return all published products that belong to the given category slug.
     */
    public function findAllByCategorySlug(string $slug, int $limit = 20, int $offset = 0, string $sort = 'newest'): array
    {
        $activeCondition = OfferModel::activeCondition();
        $orderBy = $this->sortClause($sort);
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE c.slug = ? AND p.status = ?
                ORDER BY " . $orderBy . "
                LIMIT ? OFFSET ?";

        return $this->queryPaginated($sql, [$slug, 'published'], $limit, $offset);
    }

    /**
     * Return all published products that belong to the given brand slug.
     */
    public function findAllByBrandSlug(string $slug, int $limit = 20, int $offset = 0, string $sort = 'newest'): array
    {
        $activeCondition = OfferModel::activeCondition();
        $orderBy = $this->sortClause($sort);
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE b.slug = ? AND p.status = ?
                ORDER BY " . $orderBy . "
                LIMIT ? OFFSET ?";

        return $this->queryPaginated($sql, [$slug, 'published'], $limit, $offset);
    }

    /**
     * Count published products in a category slug.
     */
    public function countByCategorySlug(string $slug): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE c.slug = ? AND p.status = 'published'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Count published products in a brand slug.
     */
    public function countByBrandSlug(string $slug): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} p
                INNER JOIN brands b ON b.id = p.brand_id
                WHERE b.slug = ? AND p.status = 'published'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Count how many products belong to a category.
     */
    public function countByCategoryId(int $categoryId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE category_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Find published products whose name, short description or search tags match the query.
     */
    public function search(string $query, int $limit = 20, int $offset = 0, string $sort = 'newest'): array
    {
        $patterns = $this->searchPatterns($query);
        if (empty($patterns)) {
            return [];
        }

        $activeCondition = OfferModel::activeCondition();
        $orderBy = $this->sortClause($sort);

        $conditions = [];
        $params = [];
        foreach ($patterns as $pattern) {
            $conditions[] = "(p.name LIKE ? ESCAPE '\\\\' OR p.model_number LIKE ? ESCAPE '\\\\' OR p.search_tags LIKE ? ESCAPE '\\\\' OR b.name LIKE ? ESCAPE '\\\\' OR p.short_description LIKE ? ESCAPE '\\\\')";
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
        }

        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.status = 'published'
                    AND " . implode(' AND ', $conditions) . "
                ORDER BY " . $orderBy . "
                LIMIT ? OFFSET ?";

        return $this->queryPaginated($sql, $params, $limit, $offset);
    }

    /**
     * Count published products matching the search query.
     */
    public function countSearch(string $query): int
    {
        $patterns = $this->searchPatterns($query);
        if (empty($patterns)) {
            return 0;
        }

        $conditions = [];
        $params = [];
        foreach ($patterns as $pattern) {
            $conditions[] = "(p.name LIKE ? ESCAPE '\\\\' OR p.model_number LIKE ? ESCAPE '\\\\' OR p.search_tags LIKE ? ESCAPE '\\\\' OR b.name LIKE ? ESCAPE '\\\\' OR p.short_description LIKE ? ESCAPE '\\\\')";
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
        }

        $sql = "SELECT COUNT(*) AS c FROM {$this->table} p
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.status = 'published'
                    AND " . implode(' AND ', $conditions);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Return published products matching the structured AI search parameters.
     */
    public function searchWithAiParams(array $params, int $limit = 20, int $offset = 0): array
    {
        $built = $this->buildAiConditions($params);
        $conditions = $built['conditions'];
        $bindings = $built['bindings'];

        $sort = 'popular';
        if (isset($params['sort'])) {
            $sort = (string) $params['sort'];
        }
        $orderBy = $this->aiSortClause($sort);

        $activeCondition = OfferModel::activeCondition();
        $where = "p.status = 'published'";
        if ($conditions !== []) {
            $where = $where . ' AND ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE " . $where . "
                ORDER BY " . $orderBy . "
                LIMIT ? OFFSET ?";

        return $this->queryPaginated($sql, $bindings, $limit, $offset);
    }

    /**
     * Count published products matching the structured AI search parameters.
     */
    public function countWithAiParams(array $params): int
    {
        $built = $this->buildAiConditions($params);
        $conditions = $built['conditions'];
        $bindings = $built['bindings'];

        $where = "p.status = 'published'";
        if ($conditions !== []) {
            $where = $where . ' AND ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT COUNT(*) AS c FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE " . $where;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }

    /**
     * Build WHERE conditions and bindings for structured AI search parameters.
     */
    private function buildAiConditions(array $params): array
    {
        $conditions = [];
        $bindings = [];

        $categorySlug = '';
        if (isset($params['category_slug'])) {
            $categorySlug = (string) $params['category_slug'];
        }
        if ($categorySlug !== '') {
            $conditions[] = 'c.slug = ?';
            $bindings[] = $categorySlug;
        }

        $keywords = [];
        if (isset($params['keywords']) && is_array($params['keywords'])) {
            $keywords = $params['keywords'];
        }
        $keywordConditions = [];
        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }
            $keyword = trim($keyword);
            if ($keyword === '') {
                continue;
            }

            $variants = $this->keywordVariants($keyword);
            $variantConditions = [];
            foreach ($variants as $variant) {
                $escaped = str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $variant);
                $pattern = '%' . $escaped . '%';
                $variantConditions[] = "(p.name LIKE ? ESCAPE '\\\\' OR p.model_number LIKE ? ESCAPE '\\\\' OR p.search_tags LIKE ? ESCAPE '\\\\' OR p.short_description LIKE ? ESCAPE '\\\\' OR b.name LIKE ? ESCAPE '\\\\')";
                $bindings[] = $pattern;
                $bindings[] = $pattern;
                $bindings[] = $pattern;
                $bindings[] = $pattern;
                $bindings[] = $pattern;
            }
            if ($variantConditions !== []) {
                $keywordConditions[] = '(' . implode(' OR ', $variantConditions) . ')';
            }
        }
        if ($keywordConditions !== []) {
            $conditions[] = '(' . implode(' OR ', $keywordConditions) . ')';
        }

        $activeCondition = OfferModel::activeCondition();
        $lowestPriceSql = "(SELECT MIN(o.price_idr) FROM offers o
                            INNER JOIN stores s ON s.id = o.store_id
                            LEFT JOIN vendors v ON v.id = o.vendor_id
                            WHERE o.product_id = p.id AND " . $activeCondition . ")";

        if (isset($params['max_price'])) {
            if ($params['max_price'] !== null) {
                $conditions[] = $lowestPriceSql . ' <= ?';
                $bindings[] = (int) $params['max_price'];
            }
        }

        if (isset($params['min_price'])) {
            if ($params['min_price'] !== null) {
                $conditions[] = $lowestPriceSql . ' >= ?';
                $bindings[] = (int) $params['min_price'];
            }
        }

        return [
            'conditions' => $conditions,
            'bindings' => $bindings,
        ];
    }

    /**
     * Expand an Indonesian or English keyword into catalog-friendly variants.
     */
    private function keywordVariants(string $keyword): array
    {
        $variants = [$keyword];
        $lookup = strtolower(trim($keyword));

        if (isset(self::KEYWORD_SYNONYMS[$lookup])) {
            foreach (self::KEYWORD_SYNONYMS[$lookup] as $synonym) {
                if (!in_array($synonym, $variants, true)) {
                    $variants[] = $synonym;
                }
            }
        }

        return $variants;
    }

    /**
     * Map a whitelisted AI sort value to a fixed ORDER BY clause.
     */
    private function aiSortClause(string $sort): string
    {
        if (!Validator::inArray($sort, ['price_asc', 'price_desc', 'newest', 'popular'])) {
            $sort = 'popular';
        }

        switch ($sort) {
            case 'price_asc':
                return 'lowest_price IS NULL, lowest_price ASC, p.id DESC';
            case 'price_desc':
                return 'lowest_price IS NULL, lowest_price DESC, p.id DESC';
            case 'newest':
                return 'p.id DESC';
        }

        return 'offer_count DESC, p.id DESC';
    }

    /**
     * Split a search query into escaped LIKE patterns (max 6 tokens).
     */
    private function searchPatterns(string $query): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $trimmed);
        if (!is_array($tokens)) {
            return [];
        }
        $tokens = array_slice($tokens, 0, 6);

        $patterns = [];
        foreach ($tokens as $token) {
            $escaped = str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $token);
            $patterns[] = '%' . $escaped . '%';
        }

        return $patterns;
    }

    /**
     * Map a whitelisted sort value to a fixed ORDER BY clause.
     */
    private function sortClause(string $sort): string
    {
        if (!Validator::inArray($sort, ['newest', 'price_asc', 'price_desc'])) {
            $sort = 'newest';
        }

        switch ($sort) {
            case 'price_asc':
                return 'lowest_price IS NULL, lowest_price ASC, p.id DESC';
            case 'price_desc':
                return 'lowest_price IS NULL, lowest_price DESC, p.id DESC';
        }

        return 'p.id DESC';
    }

    /**
     * Run a paginated query with integer LIMIT and OFFSET bindings.
     */
    private function queryPaginated(string $sql, array $params, int $limit, int $offset): array
    {
        if ($limit < 1) {
            $limit = 20;
        }
        if ($offset < 0) {
            $offset = 0;
        }

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
     * Return published products that have an active offer from one vendor.
     */
    public function findPublishedByVendorId(int $vendorId, int $limit, int $offset): array
    {
        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name,
                    (SELECT MIN(o.price_idr) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND o.vendor_id = ? AND " . $activeCondition . ") AS lowest_price,
                    (SELECT COUNT(*) FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND " . $activeCondition . ") AS offer_count
                FROM {$this->table} p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.status = 'published'
                    AND EXISTS (SELECT 1 FROM offers o
                        INNER JOIN stores s ON s.id = o.store_id
                        LEFT JOIN vendors v ON v.id = o.vendor_id
                        WHERE o.product_id = p.id AND o.vendor_id = ? AND " . $activeCondition . ")
                ORDER BY p.id DESC
                LIMIT ? OFFSET ?";

        return $this->queryPaginated($sql, [$vendorId, $vendorId], $limit, $offset);
    }

    /**
     * Count published products that have an active offer from one vendor.
     */
    public function countPublishedByVendorId(int $vendorId): int
    {
        $activeCondition = OfferModel::activeCondition();
        $sql = "SELECT COUNT(DISTINCT p.id) AS c FROM {$this->table} p
                INNER JOIN offers o ON o.product_id = p.id
                INNER JOIN stores s ON s.id = o.store_id
                LEFT JOIN vendors v ON v.id = o.vendor_id
                WHERE p.status = 'published' AND o.vendor_id = ? AND " . $activeCondition;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$vendorId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) $row['c'];
    }
}