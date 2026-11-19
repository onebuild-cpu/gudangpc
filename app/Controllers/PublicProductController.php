<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\Seo;
use App\Models\ProductModel;
use App\Models\OfferModel;
use App\Models\SeoMetaModel;
use App\Models\BrandModel;
use App\Models\ClickModel;
use App\Models\WishlistModel;

/**
 * Serves the public product detail page and tracked affiliate redirects.
 */
class PublicProductController extends Controller
{
    private ProductModel $productModel;
    private OfferModel $offerModel;
    private SeoMetaModel $seoMetaModel;
    private BrandModel $brandModel;
    private ClickModel $clickModel;
    private WishlistModel $wishlistModel;

    /**
     * Inject the database connection and build all required models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->offerModel = new OfferModel($pdo);
        $this->seoMetaModel = new SeoMetaModel($pdo);
        $this->brandModel = new BrandModel($pdo);
        $this->clickModel = new ClickModel($pdo);
        $this->wishlistModel = new WishlistModel($pdo);
    }

    /**
     * Show a single published product by its category and product slugs.
     */
    public function show(string $categorySlug, string $productSlug): void
    {
        $product = $this->productModel->findBySlug($productSlug);

        if (!$product) {
            $this->showNotFound();
            return;
        }

        if ($product['status'] !== 'published') {
            $this->showNotFound();
            return;
        }

        $productId = (int) $product['id'];
        $full = $this->productModel->findByIdWithRelations($productId);

        if ($full === null) {
            $this->showNotFound();
            return;
        }

        $categoryStatus = '';
        if (isset($full['category_status'])) {
            $categoryStatus = (string) $full['category_status'];
        }
        if ($categoryStatus !== 'published') {
            $this->showNotFound();
            return;
        }

        if ($full['category_slug'] !== $categorySlug) {
            $correctCategorySlug = '';
            if (isset($full['category_slug'])) {
                $correctCategorySlug = (string) $full['category_slug'];
            }
            $correctProductSlug = '';
            if (isset($full['slug'])) {
                $correctProductSlug = (string) $full['slug'];
            }
            $base = '';
            if (isset($GLOBALS['base'])) {
                $base = $GLOBALS['base'];
            }
            header('Location: ' . $base . '/' . $correctCategorySlug . '/' . $correctProductSlug, true, 301);
            exit;
        }

        $seo = $this->seoMetaModel->findByMeta('product', $productId);

        $brandName = '';
        if (isset($product['brand_id']) && $product['brand_id'] !== null) {
            $brand = $this->brandModel->findById((int) $product['brand_id']);
            if ($brand) {
                if (isset($brand['name'])) {
                    $brandName = (string) $brand['name'];
                }
            }
        }

        $offers = $this->offerModel->findPublicByProductId($productId);

        $related = $this->productModel->findRelated((int) $product['category_id'], $productId, 4);

        $inWishlist = false;
        if (!empty($_SESSION['user_id'])) {
            $wishlistRole = '';
            if (isset($_SESSION['role'])) {
                $wishlistRole = (string) $_SESSION['role'];
            }
            if ($wishlistRole === 'visitor') {
                $inWishlist = $this->wishlistModel->exists((int) $_SESSION['user_id'], $productId);
            }
        }

        $productName = '';
        if (isset($product['name'])) {
            $productName = (string) $product['name'];
        }

$pageTitle = '';
if (isset($seo['seo_title']) && $seo['seo_title'] !== '') {
    $pageTitle = (string) $seo['seo_title'];
} elseif ($productName !== '') {
    $pageTitle = $productName . ' - Bandingkan Harga | GudangPC';
}

$metaDescription = '';
if (isset($product['short_description']) && $product['short_description'] !== '') {
    $metaDescription = (string) $product['short_description'];
}
if ($metaDescription === '') {
    $metaDescription = Seo::plainText('Bandingkan harga ' . $productName . '.', 155);
}
if (isset($seo['seo_description']) && $seo['seo_description'] !== '') {
    $metaDescription = (string) $seo['seo_description'];
}

        $ogImage = '';
        $productImage = '';
        if (isset($product['image'])) {
            $productImage = (string) $product['image'];
        }
        if ($productImage !== '') {
            $ogImage = Seo::absoluteUrl($productImage);
        }

        $canonicalCategorySlug = '';
        if (isset($full['category_slug'])) {
            $canonicalCategorySlug = (string) $full['category_slug'];
        }
        $canonicalProductSlug = '';
        if (isset($product['slug'])) {
            $canonicalProductSlug = (string) $product['slug'];
        }

        $canonicalUrl = Seo::absoluteUrl('/' . $canonicalCategorySlug . '/' . $canonicalProductSlug);
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

        $categoryNameValue = '';
        if (isset($full['category_name'])) {
            $categoryNameValue = (string) $full['category_name'];
        }

        $breadcrumbs = [
            ['label' => 'Home', 'url' => $basePath . '/'],
            ['label' => $categoryNameValue, 'url' => $basePath . '/' . $canonicalCategorySlug],
            ['label' => $productName, 'url' => ''],
        ];

        $crumbs = [
            ['label' => 'Home', 'path' => '/'],
            ['label' => $categoryNameValue, 'path' => '/' . $canonicalCategorySlug],
            ['label' => $productName, 'path' => ''],
        ];

        $jsonLd = [];

        $productJsonLd = Seo::productJsonLd($product, $brandName, $offers, '/' . $canonicalCategorySlug . '/' . $canonicalProductSlug, $productImage);
        if ($productJsonLd !== '') {
            $jsonLd[] = $productJsonLd;
        }

        $breadcrumbJsonLd = Seo::breadcrumbJsonLd($crumbs);
        if ($breadcrumbJsonLd !== '') {
            $jsonLd[] = $breadcrumbJsonLd;
        }

        $this->view('public/products/show', [
            'pageTitle' => $pageTitle,
            'metaDescription' => $metaDescription,
            'product' => $product,
            'brandName' => $brandName,
            'offers' => $offers,
            'related' => $related,
            'inWishlist' => $inWishlist,
            'seo' => $seo,
            'canonicalUrl' => $canonicalUrl,
            'ogImage' => $ogImage,
            'ogType' => 'product',
            'breadcrumbs' => $breadcrumbs,
            'jsonLd' => $jsonLd,
        ]);
    }

    /**
     * Log the click and redirect to the offer's affiliate URL.
     */
    public function go(string $offerId): void
    {
        $offerId = (int) $offerId;
        $offer = $this->offerModel->findActiveById($offerId);

        if ($offer === null) {
            $existingOffer = $this->offerModel->findById($offerId);

            if ($existingOffer) {
                $product = $this->productModel->findByIdWithRelations((int) $existingOffer['product_id']);

                if ($product !== null && $product['status'] === 'published') {
                    $_SESSION['error'] = 'That offer is no longer available.';
                    $categorySlug = '';
                    if (isset($product['category_slug'])) {
                        $categorySlug = (string) $product['category_slug'];
                    }
                    $productSlug = '';
                    if (isset($product['slug'])) {
                        $productSlug = (string) $product['slug'];
                    }
                    $this->redirect('/' . $categorySlug . '/' . $productSlug);
                    return;
                }
            }

            $this->showNotFound();
            return;
        }

        $product = $this->productModel->findByIdWithRelations((int) $offer['product_id']);

        if ($product === null) {
            $this->showNotFound();
            return;
        }

        $productStatus = '';
        if (isset($product['status'])) {
            $productStatus = (string) $product['status'];
        }
        if ($productStatus !== 'published') {
            $this->showNotFound();
            return;
        }

        $categoryStatus = '';
        if (isset($product['category_status'])) {
            $categoryStatus = (string) $product['category_status'];
        }
        if ($categoryStatus !== 'published') {
            $this->showNotFound();
            return;
        }

        $affiliateUrl = '';
        if (isset($offer['affiliate_url'])) {
            $affiliateUrl = (string) $offer['affiliate_url'];
        }

        if (preg_match('~^https?://~i', $affiliateUrl) !== 1) {
            $this->showNotFound();
            return;
        }

        $remoteAddress = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $remoteAddress = (string) $_SERVER['REMOTE_ADDR'];
        }
        $appSalt = \App\Core\Dotenv::get('APP_SALT', '');
        $appSalt = (string) $appSalt;
        $ipHash = '';
        if ($appSalt === '' || $appSalt === 'change-me') {
            error_log('APP_SALT is not set; click IP hash skipped.');
        } else {
            $ipHash = hash('sha256', $remoteAddress . $appSalt);
        }

        $userAgentRaw = '';
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $userAgentRaw = substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255);
        }

        $userAgent = null;
        if ($userAgentRaw !== '') {
            $userAgent = $userAgentRaw;
        }

        $referer = null;
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = substr((string) $_SERVER['HTTP_REFERER'], 0, 255);
        }

        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');

        if (!$this->isBot($userAgentRaw)) {
            $this->clickModel->logClick($offerId, $ipHash, $userAgent, $referer);
        }

        $this->redirect($affiliateUrl);
    }

    /**
     * Return true when the user agent looks like a crawler or a script.
     */
    private function isBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return true;
        }

        $needles = ['bot', 'crawl', 'spider', 'slurp', 'preview', 'facebookexternalhit', 'curl', 'wget', 'python-requests', 'headless'];
        $lowercase = strtolower($userAgent);

        foreach ($needles as $needle) {
            if (strpos($lowercase, $needle) !== false) {
                return true;
            }
        }

        return false;
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
