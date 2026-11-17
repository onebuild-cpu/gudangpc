<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\AiSearchService;
use App\Core\Validator;
use App\Models\ProductModel;
use App\Models\SearchCacheModel;

/**
 * Serves the public product search page.
 *
 * Existing /search is handled by index(); the shareable /search/{slug} page is
 * handled by show() and uses the cached AI response.
 */
class PublicSearchController extends Controller
{
    private ProductModel $productModel;
    private SearchCacheModel $searchCacheModel;
    private ?AiSearchService $aiSearchService;

    /**
     * Inject the database connection and build the product and cache models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->searchCacheModel = new SearchCacheModel($pdo);
        $this->aiSearchService = null;

        $apiKey = '';
        if (isset($_ENV['GEMINI_API_KEY'])) {
            $apiKey = (string) $_ENV['GEMINI_API_KEY'];
        }
        try {
            $this->aiSearchService = new AiSearchService($apiKey);
        } catch (\Exception $e) {
            $this->aiSearchService = null;
        }
    }

    /**
     * Parse the query with the configured AI provider, cache it, and redirect.
     */
    public function index(): void
    {
        $query = '';
        if (isset($_GET['q'])) {
            $query = Validator::sanitize($_GET['q'], 255);
        }

        if ($query === '') {
            $this->renderSearchPage('', [], 0, 1, 1, 'newest', '/search', [], '');
            return;
        }

        if ($this->aiSearchService === null) {
            $this->renderFallback($query);
            return;
        }

        $normalized = $this->aiSearchService->normalizeQuery($query);
        if ($normalized === '') {
            $this->renderFallback($query);
            return;
        }

        $cache = $this->searchCacheModel->findByQueryText($normalized);
        if ($cache !== null) {
            $slug = '';
            if (isset($cache['query_slug'])) {
                $slug = (string) $cache['query_slug'];
            }
            if ($slug !== '') {
                $this->redirect('/search/' . $slug);
                return;
            }
        }

        try {
            $limiter = new \App\Core\RateLimiter($this->pdo);
            $limiter->consume('ai_search', 'ai_search');
        } catch (\App\Core\RateLimitException $e) {
            $_SESSION['error'] = 'Too many searches. Please wait '
                . \App\Core\RateLimiter::formatRetryAfter($e->getRetryAfter())
                . ' and try again.';
            $this->redirect('/search');
            return;
        }

        $parsed = $this->aiSearchService->parseQuery($normalized);
        if ($parsed === null) {
            $this->renderFallback($normalized);
            return;
        }

        $slug = $this->aiSearchService->generateSlug($normalized);
        if ($slug === '') {
            $this->renderFallback($normalized);
            return;
        }

        $aiJson = json_encode($parsed);
        if (!is_string($aiJson)) {
            $this->renderFallback($normalized);
            return;
        }

        try {
            $this->searchCacheModel->insert([
                'query_text' => $normalized,
                'query_slug' => $slug,
                'ai_response_json' => $aiJson,
                'result_count' => 0,
            ]);
        } catch (\Exception $e) {
            $this->renderFallback($normalized);
            return;
        }

        $this->redirect('/search/' . $slug);
    }

    /**
     * Show the canonical search page for a cached query slug.
     */
    public function show(string $slug): void
    {
        $slug = Validator::sanitize($slug, 255);

        $cache = $this->searchCacheModel->findBySlug($slug);
        if ($cache === null) {
            $_SESSION['error'] = 'Query belum pernah dicari';
            $this->redirect('/search');
            return;
        }

        $aiParams = [];
        if (isset($cache['ai_response_json'])) {
            $decoded = json_decode((string) $cache['ai_response_json'], true);
            if (is_array($decoded)) {
                $aiParams = $decoded;
            }
        }

        if (isset($aiParams['category_slug'])) {
            $categoryMap = [
                'processor' => 'cpu',
                'graphics-card' => 'gpu',
                'memory' => 'ram',
                'power-supply' => 'psu',
                'storage' => 'ssd',
                'casing' => 'case',
                'keyboard' => 'peripherals',
                'mouse' => 'peripherals',
                'monitor' => 'monitor',
                'motherboard' => 'motherboard',
            ];

            $categorySlug = (string) $aiParams['category_slug'];
            $mappedSlug = $categorySlug;
            if (isset($categoryMap[$categorySlug])) {
                $mappedSlug = $categoryMap[$categorySlug];
            }

            if ($mappedSlug === '') {
                unset($aiParams['category_slug']);
            } else {
                $aiParams['category_slug'] = $mappedSlug;
            }
        }

        $page = 1;
        if (isset($_GET['page'])) {
            if (Validator::integer($_GET['page'], 1)) {
                $page = (int) $_GET['page'];
            }
        }

        $perPage = 20;
        $searchParams = $aiParams;
        $total = $this->productModel->countWithAiParams($searchParams);

        if ($total === 0) {
            $hasKeywords = false;
            if (isset($searchParams['keywords']) && is_array($searchParams['keywords'])) {
                if (count($searchParams['keywords']) > 0) {
                    $hasKeywords = true;
                }
            }

            $hasCategory = false;
            if (isset($searchParams['category_slug']) && (string) $searchParams['category_slug'] !== '') {
                $hasCategory = true;
            }

            if ($hasKeywords && $hasCategory) {
                $categoryOnlyParams = $searchParams;
                unset($categoryOnlyParams['keywords']);
                $categoryOnlyTotal = $this->productModel->countWithAiParams($categoryOnlyParams);
                if ($categoryOnlyTotal > 0) {
                    $searchParams = $categoryOnlyParams;
                    $total = $categoryOnlyTotal;
                }
            }
        }

        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($page > $totalPages && $page > 1) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $products = $this->productModel->searchWithAiParams($searchParams, $perPage, $offset);

        $cacheId = 0;
        if (isset($cache['id'])) {
            $cacheId = (int) $cache['id'];
        }
        if ($cacheId > 0) {
            $this->searchCacheModel->updateResultCount($cacheId, $total);
        }

        $query = '';
        if (isset($cache['query_text'])) {
            $query = (string) $cache['query_text'];
        }

        $sort = 'popular';
        if (isset($aiParams['sort'])) {
            $sort = (string) $aiParams['sort'];
        }

        $this->renderSearchPage(
            $query,
            $products,
            $total,
            $page,
            $totalPages,
            $sort,
            '/search/' . $slug,
            [],
            '/search/' . $slug,
            $aiParams
        );
    }

    /**
     * Parse a query with the configured AI provider, cache it, and return JSON.
     */
    public function aiSearch(): void
    {
        $query = '';
        if (isset($_GET['q'])) {
            $query = Validator::sanitize($_GET['q'], 255);
        }

        if ($query === '') {
            $this->json([
                'error' => 'Query is required.',
            ]);
            return;
        }

        if ($this->aiSearchService === null) {
            $this->json([
                'cached' => false,
                'query' => $query,
                'error' => 'Unable to parse the search query.',
            ]);
            return;
        }

        $cache = $this->searchCacheModel->findByQueryText($query);
        if ($cache !== null) {
            $cachedResponse = null;
            if (isset($cache['ai_response_json'])) {
                $decoded = json_decode((string) $cache['ai_response_json'], true);
                if (is_array($decoded)) {
                    $cachedResponse = $decoded;
                }
            }

            $resultCount = 0;
            if (isset($cache['result_count'])) {
                $resultCount = (int) $cache['result_count'];
            }

            $cachedSlug = '';
            if (isset($cache['query_slug'])) {
                $cachedSlug = (string) $cache['query_slug'];
            }

            $this->json([
                'cached' => true,
                'query' => $query,
                'query_slug' => $cachedSlug,
                'result_count' => $resultCount,
                'ai_response' => $cachedResponse,
            ]);
            return;
        }

        try {
            $limiter = new \App\Core\RateLimiter($this->pdo);
            $limiter->consume('ai_search', 'ai_search');
        } catch (\App\Core\RateLimitException $e) {
            $_SESSION['error'] = 'Too many searches. Please wait '
                . \App\Core\RateLimiter::formatRetryAfter($e->getRetryAfter())
                . ' and try again.';
            $this->redirect('/search');
            return;
        }

        $aiResponse = $this->aiSearchService->parseQuery($query);
        if ($aiResponse === null) {
            $this->json([
                'cached' => false,
                'query' => $query,
                'error' => 'Unable to parse the search query.',
            ]);
            return;
        }

        $querySlug = $this->aiSearchService->generateSlug($query);

        $aiJson = json_encode($aiResponse);
        if (!is_string($aiJson)) {
            $aiJson = '{}';
        }

        $cacheId = 0;
        try {
            $cacheId = $this->searchCacheModel->insert([
                'query_text' => $query,
                'query_slug' => $querySlug,
                'ai_response_json' => $aiJson,
                'result_count' => 0,
            ]);
        } catch (\Exception $e) {
            $this->json([
                'cached' => false,
                'query' => $query,
                'query_slug' => $querySlug,
                'error' => 'Unable to save the search cache.',
                'ai_response' => $aiResponse,
            ]);
            return;
        }

        $this->json([
            'cached' => false,
            'cache_id' => $cacheId,
            'query' => $query,
            'query_slug' => $querySlug,
            'result_count' => 0,
            'ai_response' => $aiResponse,
        ]);
    }

    /**
     * Run a plain LIKE search without touching the cache.
     */
    private function fallbackSearch(string $query): array
    {
        return $this->productModel->search($query, 20, 0, 'newest');
    }

    /**
     * Render plain LIKE search results when AI parsing is unavailable.
     */
    private function renderFallback(string $query): void
    {
        $perPage = 20;
        $total = $this->productModel->countSearch($query);
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }

        $products = [];
        if ($query !== '') {
            $products = $this->fallbackSearch($query);
        }

        $this->renderSearchPage($query, $products, $total, 1, $totalPages, 'newest', '/search', ['q' => $query], '');
    }

    /**
     * Render the shared public search view with the given page state.
     */
    private function renderSearchPage(
        string $query,
        array $products,
        int $total,
        int $page,
        int $totalPages,
        string $sort,
        string $pagePath,
        array $pageParams,
        string $canonicalUrl,
        array $aiParams = []
    ): void {
        $pageTitle = 'Search';
        if ($query !== '') {
            $pageTitle = 'Search results for ' . $query;
        }

        $this->view('public/search/index', [
            'pageTitle' => $pageTitle,
            'metaDescription' => 'Search products',
            'query' => $query,
            'products' => $products,
            'results' => $products,
            'page' => $page,
            'totalPages' => $totalPages,
            'sort' => $sort,
            'total' => $total,
            'pagePath' => $pagePath,
            'pageParams' => $pageParams,
            'robotsOverride' => 'noindex, follow',
            'canonicalUrl' => $canonicalUrl,
            'aiParams' => $aiParams,
        ]);
    }
}
