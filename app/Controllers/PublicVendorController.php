<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\Seo;
use App\Core\Validator;
use App\Models\VendorModel;
use App\Models\ProductModel;

/**
 * Serves public seller pages.
 */
class PublicVendorController extends Controller
{
    private VendorModel $vendorModel;
    private ProductModel $productModel;

    /**
     * Inject the database connection and build the vendor and product models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->vendorModel = new VendorModel($pdo);
        $this->productModel = new ProductModel($pdo);
    }

    /**
     * Show one active seller by slug with its products.
     */
    public function show(string $slug): void
    {
        $vendor = $this->vendorModel->findActiveBySlug($slug);

        if (!$vendor) {
            $this->showNotFound();
            return;
        }

        $page = 1;
        if (isset($_GET['page'])) {
            if (Validator::integer($_GET['page'], 1)) {
                $page = (int) $_GET['page'];
            }
        }

        $perPage = 20;
        $vendorId = (int) $vendor['id'];
        $total = $this->productModel->countPublishedByVendorId($vendorId);
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($page > $totalPages && $page > 1) {
            $this->showNotFound();
            return;
        }

        $offset = ($page - 1) * $perPage;
        $products = $this->productModel->findPublishedByVendorId($vendorId, $perPage, $offset);

        $sellerName = '';
        if (isset($vendor['name'])) {
            $sellerName = (string) $vendor['name'];
        }

        $sellerDescription = '';
        if (isset($vendor['description']) && $vendor['description'] !== null) {
            $sellerDescription = (string) $vendor['description'];
        }

        $plainSellerDescription = '';
        if (trim($sellerDescription) !== '') {
            $plainSellerDescription = Seo::plainText($sellerDescription, 155);
        }

        $pageTitle = '';
        if ($sellerName !== '') {
            $pageTitle = 'Produk ' . $sellerName . ' - Bandingkan Harga | GudangPC';
        }

        $metaDescription = '';
        if ($plainSellerDescription !== '') {
            $metaDescription = $plainSellerDescription;
        } else {
            $metaDescription = 'Bandingkan harga produk ' . $sellerName . ' di berbagai marketplace Indonesia.';
        }

        $canonicalUrl = Seo::absoluteUrl('/seller/' . $slug);
        if ($page > 1) {
            $canonicalUrl = Seo::absoluteUrl('/seller/' . $slug . '?page=' . $page);
        }

        $this->view('public/vendors/show', [
            'pageTitle' => $pageTitle,
            'metaDescription' => $metaDescription,
            'vendor' => $vendor,
            'products' => $products,
            'seo' => null,
            'canonicalUrl' => $canonicalUrl,
            'ogImage' => '',
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'pagePath' => '/seller/' . $slug,
            'pageParams' => [],
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
