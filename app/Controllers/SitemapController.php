<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\Dotenv;
use App\Models\ProductModel;
use App\Models\CategoryModel;
use App\Models\BrandModel;
use App\Models\SearchCacheModel;

/**
 * Outputs the XML sitemap for the public catalog.
 */
class SitemapController extends Controller
{
    private ProductModel $productModel;
    private CategoryModel $categoryModel;
    private BrandModel $brandModel;
    private SearchCacheModel $searchCacheModel;

    /**
     * Inject the database connection and build the catalog models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->categoryModel = new CategoryModel($pdo);
        $this->brandModel = new BrandModel($pdo);
        $this->searchCacheModel = new SearchCacheModel($pdo);
    }

    /**
     * Print the sitemap with every public catalog URL.
     */
    public function index(): void
    {
        $base = Dotenv::get('APP_URL', '');
        $base = rtrim((string) $base, '/');

        if ($base === '') {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'APP_URL is not set';
            return;
        }

        $urls = [];
        $urls[] = ['loc' => $base . '/', 'lastmod' => ''];
        $urls[] = ['loc' => $base . '/categories', 'lastmod' => ''];
        $urls[] = ['loc' => $base . '/brands', 'lastmod' => ''];

        foreach ($this->categoryModel->findAllPublished() as $category) {
            $slug = '';
            if (isset($category['slug'])) {
                $slug = (string) $category['slug'];
            }
            $lastmod = '';
            if (isset($category['updated_at']) && $category['updated_at'] !== null) {
                $lastmod = str_replace(' ', 'T', (string) $category['updated_at']);
            }
            $urls[] = ['loc' => $base . '/' . $slug, 'lastmod' => $lastmod];
        }

        foreach ($this->brandModel->findAllPublished() as $brand) {
            $slug = '';
            if (isset($brand['slug'])) {
                $slug = (string) $brand['slug'];
            }
            $lastmod = '';
            if (isset($brand['updated_at']) && $brand['updated_at'] !== null) {
                $lastmod = str_replace(' ', 'T', (string) $brand['updated_at']);
            }
            $urls[] = ['loc' => $base . '/brand/' . $slug, 'lastmod' => $lastmod];
        }

        foreach ($this->productModel->findAllPublishedWithActiveOffers() as $product) {
            $categorySlug = '';
            if (isset($product['category_slug'])) {
                $categorySlug = (string) $product['category_slug'];
            }
            $productSlug = '';
            if (isset($product['slug'])) {
                $productSlug = (string) $product['slug'];
            }
            $lastmod = '';
            if (isset($product['updated_at']) && $product['updated_at'] !== null) {
                $lastmod = str_replace(' ', 'T', (string) $product['updated_at']);
            }
            $urls[] = ['loc' => $base . '/' . $categorySlug . '/' . $productSlug, 'lastmod' => $lastmod];
        }

        foreach ($this->searchCacheModel->getForSitemap() as $cache) {
            $querySlug = '';
            if (isset($cache['query_slug'])) {
                $querySlug = (string) $cache['query_slug'];
            }
            if ($querySlug === '') {
                continue;
            }

            $lastmod = '';
            if (isset($cache['updated_at']) && $cache['updated_at'] !== null) {
                $lastmod = str_replace(' ', 'T', (string) $cache['updated_at']);
            }
            $urls[] = ['loc' => $base . '/search/' . $querySlug, 'lastmod' => $lastmod];
        }

        header('Content-Type: application/xml; charset=UTF-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            echo '    <url>' . "\n";
            echo '        <loc>' . htmlspecialchars($url['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            if ($url['lastmod'] !== '') {
                echo '        <lastmod>' . htmlspecialchars($url['lastmod'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
            }
            echo '    </url>' . "\n";
        }

        echo '</urlset>';
    }
}
