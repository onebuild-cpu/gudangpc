<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\Seo;
use App\Core\Validator;
use App\Models\BrandModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\SeoMetaModel;

/**
 * Serves public brand pages.
 */
class PublicBrandController extends Controller
{
    private BrandModel $brandModel;
    private ProductModel $productModel;
    private CategoryModel $categoryModel;
    private SeoMetaModel $seoMetaModel;

    /**
     * Inject the database connection and build the brand, product and category models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->brandModel = new BrandModel($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->categoryModel = new CategoryModel($pdo);
        $this->seoMetaModel = new SeoMetaModel($pdo);
    }

    /**
     * List all published brands.
     */
    public function index(): void
    {
        $brands = $this->brandModel->findAllPublished();

        $this->view('public/brands/index', [
            'pageTitle' => 'Merek',
            'metaDescription' => 'Jelajahi semua merek produk',
            'brands' => $brands,
            'seo' => null,
            'canonicalUrl' => Seo::absoluteUrl('/brands'),
            'ogImage' => '',
        ]);
    }

    /**
     * Show a single published brand by slug.
     */
    public function show(string $slug): void
    {
        $brand = $this->brandModel->findBySlug($slug);

        if (!$brand) {
            $this->showNotFound();
            return;
        }

        if ($brand['status'] !== 'published') {
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
        $total = $this->productModel->countByBrandSlug($slug);
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($page > $totalPages && $page > 1) {
            $this->showNotFound();
            return;
        }

        $offset = ($page - 1) * $perPage;
        $products = $this->productModel->findAllByBrandSlug($slug, $perPage, $offset, $sort);

        $fallbackDescription = '';
        $fallbackImage = '';
        foreach ($products as $product) {
            if (!isset($product['category_id'])) {
                continue;
            }

            $category = $this->categoryModel->findById((int) $product['category_id']);
            if (!$category) {
                continue;
            }

            $categoryDescription = '';
            if (isset($category['description'])) {
                $categoryDescription = (string) $category['description'];
            }

            $categoryImage = '';
            if (isset($category['image'])) {
                $categoryImage = (string) $category['image'];
            }

            if ($categoryDescription === '' && $categoryImage === '') {
                continue;
            }

            $fallbackDescription = $categoryDescription;
            $fallbackImage = $categoryImage;
            break;
        }

        $seo = $this->seoMetaModel->findByMeta('brand', (int) $brand['id']);

        $brandName = '';
        if (isset($brand['name'])) {
            $brandName = (string) $brand['name'];
        }

        $plainBrandDescription = '';
        if (isset($brand['description']) && $brand['description'] !== '') {
            $plainBrandDescription = \App\Core\Seo::plainText((string) $brand['description'], 155);
        } elseif ($fallbackDescription !== '') {
            $plainBrandDescription = \App\Core\Seo::plainText($fallbackDescription, 155);
        }

$pageTitle = '';
if (isset($seo['seo_title']) && $seo['seo_title'] !== '') {
    $pageTitle = (string) $seo['seo_title'];
} elseif ($brandName !== '') {
    $pageTitle = 'Produk ' . $brandName . ' - Bandingkan Harga | GudangPC';
}

$metaDescription = '';
if (isset($seo['seo_description']) && $seo['seo_description'] !== '') {
    $metaDescription = (string) $seo['seo_description'];
} elseif ($plainBrandDescription !== '') {
    $metaDescription = $plainBrandDescription;
} else {
    $metaDescription = 'Bandingkan harga produk ' . $brandName . ' di berbagai marketplace Indonesia.';
}

        $ogImage = '';
        $brandLogo = '';
        if (isset($brand['logo'])) {
            $brandLogo = (string) $brand['logo'];
        }
        if ($brandLogo !== '') {
            $ogImage = Seo::absoluteUrl($brandLogo);
        }

        if ($seo !== null) {
            if (isset($seo['seo_title']) && $seo['seo_title'] !== '') {
                $pageTitle = (string) $seo['seo_title'];
            }
            if (isset($seo['seo_description']) && $seo['seo_description'] !== '') {
                $metaDescription = (string) $seo['seo_description'];
            }
        }

        $canonicalUrl = Seo::absoluteUrl('/brand/' . (string) $brand['slug']);
        if ($page > 1) {
            $canonicalUrl = Seo::absoluteUrl('/brand/' . (string) $brand['slug'] . '?page=' . $page);
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
            ['label' => 'Brands', 'url' => $basePath . '/brands'],
            ['label' => $brandName, 'url' => ''],
        ];

        $crumbs = [
            ['label' => 'Home', 'path' => '/'],
            ['label' => 'Brands', 'path' => '/brands'],
            ['label' => $brandName, 'path' => '/brand/' . (string) $brand['slug']],
        ];

        $jsonLd = [];
        $breadcrumbJsonLd = Seo::breadcrumbJsonLd($crumbs);
        if ($breadcrumbJsonLd !== '') {
            $jsonLd[] = $breadcrumbJsonLd;
        }

        $this->view('public/brands/show', [
            'pageTitle' => $pageTitle,
            'metaDescription' => $metaDescription,
            'brand' => $brand,
            'products' => $products,
            'fallbackDescription' => $fallbackDescription,
            'fallbackImage' => $fallbackImage,
            'seo' => $seo,
            'canonicalUrl' => $canonicalUrl,
            'ogImage' => $ogImage,
            'breadcrumbs' => $breadcrumbs,
            'jsonLd' => $jsonLd,
            'page' => $page,
            'totalPages' => $totalPages,
            'sort' => $sort,
            'total' => $total,
            'pagePath' => '/brand/' . (string) $brand['slug'],
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
