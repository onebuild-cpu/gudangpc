<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\Seo;
use App\Models\BrandModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\VendorModel;

/**
 * Serves the public homepage.
 */
class HomeController extends Controller
{
    private CategoryModel $categoryModel;
    private BrandModel $brandModel;
    private ProductModel $productModel;
    private VendorModel $vendorModel;

    /**
     * Inject the database connection and build the catalog models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->categoryModel = new CategoryModel($pdo);
        $this->brandModel = new BrandModel($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->vendorModel = new VendorModel($pdo);
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

        $locationSections = [];
        foreach ($this->vendorModel->findHomepageLocations(4) as $locationRow) {
            $sectionSellers = [];
            foreach ($this->vendorModel->findHomepageSellersByLocation((int) $locationRow['id'], 4) as $seller) {
                $ratingText = '';
                $storeRating = '';
                if (isset($seller['store_rating']) && $seller['store_rating'] !== null && $seller['store_rating'] !== '') {
                    $storeRating = (string) $seller['store_rating'];
                }
                if ($storeRating !== '') {
                    $ratingText = number_format((float) $storeRating, 2, '.', '');
                } elseif (isset($seller['rating']) && $seller['rating'] !== null && $seller['rating'] !== '') {
                    $ratingText = number_format((float) $seller['rating'], 2, '.', '');
                }
                $seller['rating_text'] = $ratingText;
                $sectionSellers[] = $seller;
            }
            if (count($sectionSellers) < 1) {
                continue;
            }
            $locationSections[] = [
                'location_name' => (string) $locationRow['name'],
                'sellers' => $sectionSellers,
            ];
        }

        $this->view('home/index', [
            'pageTitle' => 'GudangPC - Bandingkan Harga Komponen PC',
            'metaDescription' => 'Bandingkan harga komponen PC di berbagai marketplace Indonesia.',
            'seo' => null,
            'canonicalUrl' => Seo::absoluteUrl('/'),
            'ogImage' => '',
            'categories' => $categories,
            'newestProducts' => $newestProducts,
            'categorySections' => $categorySections,
            'locationSections' => $locationSections,
            'brands' => $brands,
        ]);
    }
}
