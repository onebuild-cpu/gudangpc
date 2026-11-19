<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\Seo;
use App\Core\Validator;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\SeoMetaModel;

/**
 * Serves public category pages.
 */
class PublicCategoryController extends Controller
{
    private CategoryModel $categoryModel;
    private ProductModel $productModel;
    private SeoMetaModel $seoMetaModel;

    /**
     * Inject the database connection and build the category and product models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->categoryModel = new CategoryModel($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->seoMetaModel = new SeoMetaModel($pdo);
    }

    /**
     * List all published categories.
     */
    public function index(): void
    {
        $categories = $this->categoryModel->findAllPublished();

        $this->view('public/categories/index', [
            'pageTitle' => 'Kategori',
            'metaDescription' => 'Jelajahi semua kategori produk',
            'categories' => $categories,
            'seo' => null,
            'canonicalUrl' => Seo::absoluteUrl('/categories'),
            'ogImage' => '',
        ]);
    }

    /**
     * Show a single published category by slug.
     */
    public function show(string $slug): void
    {
        $category = $this->categoryModel->findBySlug($slug);

        if (!$category) {
            $this->showNotFound();
            return;
        }

        if ($category['status'] !== 'published') {
            $this->showNotFound();
            return;
        }

        $sort = 'newest';
        if (isset($_GET['sort'])) {
            $rawSort = (string) $_GET['sort'];
            if (Validator::inArray($rawSort, ['newest', 'price_asc', 'price_desc'])) {
                $sort = $rawSort;
            }
        }

        $page = 1;
        if (isset($_GET['page'])) {
            if (Validator::integer($_GET['page'], 1)) {
                $page = (int) $_GET['page'];
            }
        }

        $perPage = 20;
        $total = $this->productModel->countByCategorySlug($slug);
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($page > $totalPages && $page > 1) {
            $this->showNotFound();
            return;
        }

        $offset = ($page - 1) * $perPage;
        $products = $this->productModel->findAllByCategorySlug($slug, $perPage, $offset, $sort);

        $seo = $this->seoMetaModel->findByMeta('category', (int) $category['id']);

        $categoryName = '';
        if (isset($category['name'])) {
            $categoryName = (string) $category['name'];
        }

        $plainCategoryDescription = strip_tags((string) $category['description']);

$pageTitle = '';
if (isset($seo['seo_title']) && $seo['seo_title'] !== '') {
    $pageTitle = (string) $seo['seo_title'];
} elseif ($categoryName !== '') {
    $pageTitle = $categoryName . ' - Bandingkan Harga | GudangPC';
}

$metaDescription = '';
if (isset($seo['seo_description']) && $seo['seo_description'] !== '') {
    $metaDescription = (string) $seo['seo_description'];
} elseif ($plainCategoryDescription !== '') {
    $metaDescription = $plainCategoryDescription;
} else {
    $metaDescription = 'Bandingkan harga ' . $categoryName . ' di berbagai marketplace Indonesia.';
}

        $ogImage = '';
        $categoryImage = '';
        if (isset($category['image'])) {
            $categoryImage = (string) $category['image'];
        }
        if ($categoryImage !== '') {
            $ogImage = Seo::absoluteUrl($categoryImage);
        }

        if ($seo !== null) {
            if (isset($seo['seo_title']) && $seo['seo_title'] !== '') {
                $pageTitle = (string) $seo['seo_title'];
            }
            if (isset($seo['seo_description']) && $seo['seo_description'] !== '') {
                $metaDescription = (string) $seo['seo_description'];
            }
        }

        $canonicalUrl = Seo::absoluteUrl('/' . (string) $category['slug']);
        if ($page > 1) {
            $canonicalUrl = Seo::absoluteUrl('/' . (string) $category['slug'] . '?page=' . $page);
        }
        if ($seo !== null) {
            if (isset($seo['canonical_url']) && $seo['canonical_url'] !== '') {
                if (strpos((string) $seo['canonical_url'], 'http') === 0) {
                    $canonicalUrl = (string) $seo['canonical_url'];
                }
            }
        }

        $basePath = '';
        if (isset($GLOBALS['base'])) {
            $basePath = (string) $GLOBALS['base'];
        }

        $breadcrumbs = [
            ['label' => 'Home', 'url' => $basePath . '/'],
            ['label' => $categoryName, 'url' => ''],
        ];

        $crumbs = [
            ['label' => 'Home', 'path' => '/'],
            ['label' => $categoryName, 'path' => '/' . (string) $category['slug']],
        ];

        $jsonLd = [];
        $breadcrumbJsonLd = Seo::breadcrumbJsonLd($crumbs);
        if ($breadcrumbJsonLd !== '') {
            $jsonLd[] = $breadcrumbJsonLd;
        }

        $this->view('public/categories/show', [
            'pageTitle' => $pageTitle,
            'metaDescription' => $metaDescription,
            'category' => $category,
            'products' => $products,
            'seo' => $seo,
            'canonicalUrl' => $canonicalUrl,
            'ogImage' => $ogImage,
            'breadcrumbs' => $breadcrumbs,
            'jsonLd' => $jsonLd,
            'page' => $page,
            'totalPages' => $totalPages,
            'sort' => $sort,
            'total' => $total,
            'pagePath' => '/' . (string) $category['slug'],
            'pageParams' => ['sort' => $sort],
        ]);
    }

    /**
     * Render the 404 page.
     */
    private function showNotFound(): void
    {
        http_response_code(404);
        $this->view('errors/404', [
            'pageTitle' => '404 Tidak Ditemukan',
            'metaDescription' => 'Halaman yang diminta tidak ditemukan.',
        ]);
    }
}
