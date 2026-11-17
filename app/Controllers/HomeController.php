<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\Seo;
use App\Models\BrandModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;

/**
 * Serves the public homepage.
 */
class HomeController extends Controller
{
    private CategoryModel $categoryModel;
    private BrandModel $brandModel;
    private ProductModel $productModel;

    /**
     * Inject the database connection and build the catalog models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->categoryModel = new CategoryModel($pdo);
        $this->brandModel = new BrandModel($pdo);
        $this->productModel = new ProductModel($pdo);
    }

    /**
     * Render the homepage with the latest published categories, brands, and products.
     */
    public function index(): void
    {
        $categoryRows = $this->categoryModel->findAllPublished('sort_order ASC');
        $categories = array_slice($categoryRows, 0, 8);

        $newestProducts = $this->productModel->findNewestPublished(8);

        $categorySections = [];
        $sectionIndex = 0;
        foreach ($categoryRows as $categoryRow) {
            if ($sectionIndex >= 6) {
                break;
            }
            $sectionIndex++;

            $sectionProducts = $this->productModel->findLatestActiveByCategoryId((int) $categoryRow['id'], 8);
            if (count($sectionProducts) < 1) {
                continue;
            }

            $categorySections[] = [
                'category' => $categoryRow,
                'products' => $sectionProducts,
            ];
        }

        $brands = $this->brandModel->findPublishedWithProducts(12);

        $this->view('home/index', [
            'pageTitle' => 'GudangPC - Bandingkan Harga Komponen PC',
            'metaDescription' => 'Bandingkan harga komponen PC di berbagai marketplace Indonesia.',
            'seo' => null,
            'canonicalUrl' => Seo::absoluteUrl('/'),
            'ogImage' => '',
            'categories' => $categories,
            'newestProducts' => $newestProducts,
            'categorySections' => $categorySections,
            'brands' => $brands,
        ]);
    }
}
