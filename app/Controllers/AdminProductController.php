<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Core\RateLimiter;
use App\Core\RateLimitException;
use App\Models\ProductModel;
use App\Models\OfferModel;
use App\Models\SeoMetaModel;
use App\Models\CategoryModel;
use App\Models\BrandModel;
use App\Models\StoreModel;
use App\Models\VendorModel;
use App\Models\SettingModel;
use App\Models\ClickModel;
use App\Models\PriceHistoryModel;
use Exception;

/**
 * Manages products and their offers for admins and root users.
 */
class AdminProductController extends Controller
{
    private ProductModel $productModel;
    private OfferModel $offerModel;
    private SeoMetaModel $seoMetaModel;
    private CategoryModel $categoryModel;
    private BrandModel $brandModel;
    private StoreModel $storeModel;
    private VendorModel $vendorModel;
    private SettingModel $settingModel;
    private ClickModel $clickModel;
    private PriceHistoryModel $priceHistoryModel;

    /**
     * Inject the database connection and build all required models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->offerModel = new OfferModel($pdo);
        $this->seoMetaModel = new SeoMetaModel($pdo);
        $this->categoryModel = new CategoryModel($pdo);
        $this->brandModel = new BrandModel($pdo);
        $this->storeModel = new StoreModel($pdo);
        $this->vendorModel = new VendorModel($pdo);
        $this->settingModel = new SettingModel($pdo);
        $this->clickModel = new ClickModel($pdo);
        $this->priceHistoryModel = new PriceHistoryModel($pdo);
    }

    /**
     * List all products with their category and brand names.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $search = '';
        if (isset($_GET['search'])) {
            $search = Validator::sanitize($_GET['search'], 100);
        }

        $status = '';
        if (isset($_GET['status'])) {
            $status = Validator::sanitize($_GET['status'], 20);
        }

        $page = 1;
        if (isset($_GET['page'])) {
            if (Validator::integer($_GET['page'], 1)) {
                $page = (int) $_GET['page'];
            }
        }

        $perPage = 20;
        $total = $this->productModel->countAll();
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $products = $this->productModel->getAllWithRelationsPaginated($offset, $perPage, $search, $status);

        $statusPublishedSelected = '';
        $statusDraftSelected = '';
        if ($status === 'published') {
            $statusPublishedSelected = 'selected';
        } elseif ($status === 'draft') {
            $statusDraftSelected = 'selected';
        }

        $this->view('dashboard/products/index', [
            'pageTitle' => 'Products',
            'metaDescription' => 'Manage products and offers',
            'currentPage' => 'products',
            'products' => $products,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'total' => $total,
            'page' => $page,
            'search' => $search,
            'status' => $status,
            'statusPublishedSelected' => $statusPublishedSelected,
            'statusDraftSelected' => $statusDraftSelected,
        ]);
    }

    /**
     * Show the create product form.
     */
    public function create(): void
    {
        $this->requireAdmin();

        $categories = $this->categoryModel->findAll('name ASC');
        $brands = $this->brandModel->findAll('name ASC');
        $stores = $this->storeModel->findAll('name ASC');
        $vendors = $this->vendorModel->findAllWithStore();

        $this->view('dashboard/products/create', [
            'pageTitle' => 'Create Product',
            'metaDescription' => 'Create a new product',
            'currentPage' => 'products',
            'loadSummernote' => true,
            'categories' => $categories,
            'brands' => $brands,
            'stores' => $stores,
            'vendors' => $vendors,
            'error' => '',
            'old' => $this->emptyFormData(),
        ]);
    }

    /**
     * Create a new product from the submitted form.
     */
    public function store(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/products');
            return;
        }

        CsrfProtection::requireValidToken();

        $newFile = '';
        try {
            $data = $this->collectInput(0);
            $data['created_by'] = (int) $_SESSION['user_id'];

            $category = $this->categoryModel->findById((int) $data['category_id']);
            $categorySlug = '';
            if ($category) {
                $categorySlug = (string) $category['slug'];
            }

            $seoData = $this->collectSeoInput($data['slug'], $categorySlug);

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $newFile = $this->handleImageUpload($_FILES['image']);
                $data['image'] = $newFile;
            }

            $newId = $this->productModel->insert($data);
            $this->seoMetaModel->saveMeta('product', $newId, $seoData);
            $_SESSION['success'] = 'Product created. You can now add offers.';
            $this->redirect('/dashboard/products/' . $newId . '/edit');
        } catch (Exception $e) {
            if ($newFile !== '') {
                $this->deleteImageFile($newFile);
            }
            $categories = $this->categoryModel->findAll('name ASC');
            $brands = $this->brandModel->findAll('name ASC');
            $stores = $this->storeModel->findAll('name ASC');
            $vendors = $this->vendorModel->findAllWithStore();

            $this->view('dashboard/products/create', [
                'pageTitle' => 'Create Product',
                'metaDescription' => 'Create a new product',
                'currentPage' => 'products',
                'loadSummernote' => true,
                'categories' => $categories,
                'brands' => $brands,
                'stores' => $stores,
                'vendors' => $vendors,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->emptyFormData()),
            ]);
        }
    }

    /**
     * Show the edit product form with its offers.
     */
    public function edit(string $id): void
    {
        $this->requireAdmin();

        $id = (int) $id;
        $product = $this->productModel->findById($id);

        if (!$product) {
            $_SESSION['error'] = 'Product not found.';
            $this->redirect('/dashboard/products');
            return;
        }

        $seo = $this->seoMetaModel->findByMeta('product', $id);

        $categories = $this->categoryModel->findAll('name ASC');
        $brands = $this->brandModel->findAll('name ASC');
        $stores = $this->storeModel->findAll('name ASC');
        $vendors = $this->vendorModel->findAllWithStore();
        $offers = $this->offerModel->findByProductId($id);

        $this->view('dashboard/products/edit', [
            'pageTitle' => 'Edit Product',
            'metaDescription' => 'Edit a product and its offers',
            'currentPage' => 'products',
            'loadSummernote' => true,
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'stores' => $stores,
            'vendors' => $vendors,
            'offers' => $offers,
            'error' => '',
            'old' => $this->formDataForProduct($product, $seo),
        ]);
    }

    /**
     * Update an existing product from the submitted form.
     */
    public function update(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/products');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $product = $this->productModel->findById($id);

        if (!$product) {
            $_SESSION['error'] = 'Product not found.';
            $this->redirect('/dashboard/products');
            return;
        }

        $newFile = '';
        try {
            $data = $this->collectInput($id);
            $data['updated_by'] = (int) $_SESSION['user_id'];

            $category = $this->categoryModel->findById((int) $data['category_id']);
            $categorySlug = '';
            if ($category) {
                $categorySlug = (string) $category['slug'];
            }

            $seoData = $this->collectSeoInput($data['slug'], $categorySlug);

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $newFile = $this->handleImageUpload($_FILES['image']);
                $data['image'] = $newFile;
            }

            $this->productModel->update($id, $data);
            if ($newFile !== '') {
                $oldImage = '';
                if (isset($product['image'])) {
                    $oldImage = (string) $product['image'];
                }
                $this->deleteImageFile($oldImage);
            }
            $this->seoMetaModel->saveMeta('product', $id, $seoData);
            $_SESSION['success'] = 'Product updated successfully.';
            $this->redirect('/dashboard/products/' . $id . '/edit');
        } catch (Exception $e) {
            if ($newFile !== '') {
                $this->deleteImageFile($newFile);
            }
            $seo = $this->seoMetaModel->findByMeta('product', $id);
            $categories = $this->categoryModel->findAll('name ASC');
            $brands = $this->brandModel->findAll('name ASC');
            $stores = $this->storeModel->findAll('name ASC');
            $vendors = $this->vendorModel->findAllWithStore();
            $offers = $this->offerModel->findByProductId($id);

            $this->view('dashboard/products/edit', [
                'pageTitle' => 'Edit Product',
                'metaDescription' => 'Edit a product and its offers',
                'currentPage' => 'products',
                'loadSummernote' => true,
                'product' => $product,
                'categories' => $categories,
                'brands' => $brands,
                'stores' => $stores,
                'vendors' => $vendors,
                'offers' => $offers,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->formDataForProduct($product, $seo)),
            ]);
        }
    }

    /**
     * Delete a product, its SEO meta, and its image.
     */
    public function delete(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/products');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $product = $this->productModel->findById($id);

        if (!$product) {
            $_SESSION['error'] = 'Product not found.';
            $this->redirect('/dashboard/products');
            return;
        }

        if ($this->clickModel->countByProductId($id) > 0) {
            $_SESSION['error'] = 'This product has offers with click history. Set it to Draft instead.';
            $this->redirect('/dashboard/products');
            return;
        }

        foreach ($this->offerModel->findByProductId($id) as $existingOffer) {
            $offerClicks = $this->clickModel->countByOfferId((int) $existingOffer['id']);
            if ($offerClicks > 0) {
                $_SESSION['error'] = 'This product has offers with click history. Set it to Draft instead.';
                $this->redirect('/dashboard/products');
                return;
            }
        }

        $oldImage = '';
        if (isset($product['image'])) {
            $oldImage = (string) $product['image'];
        }

        try {
            $this->productModel->delete($id);
        } catch (Exception $e) {
            $_SESSION['error'] = 'Could not delete: ' . $e->getMessage();
            $this->redirect('/dashboard/products');
            return;
        }

        $_SESSION['success'] = 'Product deleted successfully.';

        try {
            $seo = $this->seoMetaModel->findByMeta('product', $id);
            if ($seo) {
                $this->seoMetaModel->delete((int) $seo['id']);
            }
        } catch (Exception $e) {
            error_log('SEO meta delete failed for product ' . $id . ': ' . $e->getMessage());
            $_SESSION['warning'] = 'Record deleted, but its SEO meta could not be removed.';
        }

        $this->deleteImageFile($oldImage);
        $this->redirect('/dashboard/products');
    }

    /**
     * Add a store offer to a product.
     */
    public function storeOffer(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/products');
            return;
        }

        CsrfProtection::requireValidToken();

        try {
            $limiter = new RateLimiter($this->pdo);
            $limiter->consume('admin_write:' . (int) $_SESSION['user_id'], 'admin_write');
        } catch (RateLimitException $e) {
            $_SESSION['error'] = 'Too many changes. Please wait ' . RateLimiter::formatRetryAfter($e->getRetryAfter()) . '.';
            $this->redirect('/dashboard/products/' . (int) $id . '/edit');
            return;
        }

        $productId = (int) $id;
        $product = $this->productModel->findById($productId);

        if (!$product) {
            $_SESSION['error'] = 'Product not found.';
            $this->redirect('/dashboard/products');
            return;
        }

        try {
            $rawPriceIdr = '';
            if (isset($_POST['price_idr'])) {
                $rawPriceIdr = $_POST['price_idr'];
            }
            if (!Validator::integer($rawPriceIdr, 1)) {
                throw new Exception('Price must be a positive integer.');
            }
            $priceIdr = (int) $rawPriceIdr;

            $rawAffiliateUrl = '';
            if (isset($_POST['affiliate_url'])) {
                $rawAffiliateUrl = $_POST['affiliate_url'];
            }
            $affiliateUrl = $this->validateAffiliateUrl($rawAffiliateUrl);

            $rawVendorId = '';
            if (isset($_POST['vendor_id'])) {
                $rawVendorId = $_POST['vendor_id'];
            }
            $vendorId = 0;
            if ($rawVendorId !== '') {
                if (!Validator::integer($rawVendorId, 1)) {
                    throw new Exception('Invalid vendor.');
                }
                $vendorId = (int) $rawVendorId;
            }
            if ($vendorId === 0) {
                throw new Exception('Vendor is required.');
            }

            $inStock = 1;
            if (!isset($_POST['in_stock'])) {
                $inStock = 0;
            }

            $this->insertProductOffer($productId, $vendorId, $priceIdr, $affiliateUrl, $inStock);
            $_SESSION['success'] = 'Offer added successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect('/dashboard/products/' . $productId . '/edit');
    }

    /**
     * Update the editable fields of an offer that belongs to a product.
     */
    public function updateOffer(string $productId, string $offerId): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/products');
            return;
        }

        CsrfProtection::requireValidToken();

        try {
            $limiter = new RateLimiter($this->pdo);
            $limiter->consume('admin_write:' . (int) $_SESSION['user_id'], 'admin_write');
        } catch (RateLimitException $e) {
            $_SESSION['error'] = 'Too many changes. Please wait ' . RateLimiter::formatRetryAfter($e->getRetryAfter()) . '.';
            $this->redirect('/dashboard/products/' . (int) $productId . '/edit');
            return;
        }

        $productId = (int) $productId;
        $offerId = (int) $offerId;

        $offer = $this->offerModel->findById($offerId);

        if (!$offer || (int) $offer['product_id'] !== $productId) {
            $_SESSION['error'] = 'Offer not found.';
            $this->redirect('/dashboard/products/' . $productId . '/edit');
            return;
        }

        try {
            $rawPriceIdr = '';
            if (isset($_POST['price_idr'])) {
                $rawPriceIdr = $_POST['price_idr'];
            }
            if (!Validator::integer($rawPriceIdr, 1)) {
                throw new Exception('Price must be a positive integer.');
            }
            $priceIdr = (int) $rawPriceIdr;

            $rawAffiliateUrl = '';
            if (isset($_POST['affiliate_url'])) {
                $rawAffiliateUrl = $_POST['affiliate_url'];
            }
            $affiliateUrl = $this->validateAffiliateUrl($rawAffiliateUrl);

            $inStock = 1;
            if (!isset($_POST['in_stock'])) {
                $inStock = 0;
            }

            $status = 'published';
            if (isset($offer['status'])) {
                $status = (string) $offer['status'];
            }
            if (isset($_POST['status'])) {
                $postedStatus = Validator::sanitize($_POST['status'], 20);
                if (!Validator::inArray($postedStatus, ['draft', 'published'])) {
                    throw new Exception('Invalid status.');
                }
                $status = $postedStatus;
            }

            $this->offerModel->update($offerId, [
                'price_idr' => $priceIdr,
                'affiliate_url' => $affiliateUrl,
                'in_stock' => $inStock,
                'status' => $status,
                'last_checked_at' => date('Y-m-d H:i:s'),
            ]);
            if ((int) $offer['price_idr'] !== $priceIdr) {
                $this->priceHistoryModel->record($offerId, $priceIdr);
            }
            $_SESSION['success'] = 'Offer updated successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect('/dashboard/products/' . $productId . '/edit');
    }

    /**
     * Delete an offer that belongs to a product.
     */
    public function deleteOffer(string $productId, string $offerId): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/products');
            return;
        }

        CsrfProtection::requireValidToken();

        try {
            $limiter = new RateLimiter($this->pdo);
            $limiter->consume('admin_write:' . (int) $_SESSION['user_id'], 'admin_write');
        } catch (RateLimitException $e) {
            $_SESSION['error'] = 'Too many changes. Please wait ' . RateLimiter::formatRetryAfter($e->getRetryAfter()) . '.';
            $this->redirect('/dashboard/products/' . (int) $productId . '/edit');
            return;
        }

        $productId = (int) $productId;
        $offerId = (int) $offerId;

        try {
            $offer = $this->offerModel->findById($offerId);

            if (!$offer || (int) $offer['product_id'] !== $productId) {
                throw new Exception('Offer not found for this product.');
            }

            if ($this->clickModel->countByOfferId($offerId) > 0) {
                $_SESSION['error'] = 'This offer has click history. Set it to Draft or Out of stock instead.';
                $this->redirect('/dashboard/products/' . $productId . '/edit');
                return;
            }

            $this->offerModel->delete($offerId);
            $_SESSION['success'] = 'Offer deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect('/dashboard/products/' . $productId . '/edit');
    }

    /**
     * Sanitize and validate a manually entered affiliate URL.
     */
    private function validateAffiliateUrl(string $rawAffiliateUrl): string
    {
        $affiliateUrl = Validator::sanitize($rawAffiliateUrl, 5000);
        if ($affiliateUrl === '') {
            throw new Exception('Affiliate URL is not valid.');
        }
        if (!Validator::url($affiliateUrl)) {
            throw new Exception('Affiliate URL is not valid.');
        }
        if (preg_match('#^https?://#i', $affiliateUrl) !== 1) {
            throw new Exception('Affiliate URL is not valid.');
        }

        return $affiliateUrl;
    }

    /**
     * Insert one offer for a product and record its first price.
     */
    private function insertProductOffer(int $productId, int $vendorId, int $priceIdr, string $affiliateUrl, int $inStock): int
    {
        $vendor = $this->vendorModel->findById($vendorId);
        if (!$vendor) {
            throw new Exception('Selected vendor does not exist.');
        }

        $storeId = 0;
        if (isset($vendor['store_id'])) {
            $storeId = (int) $vendor['store_id'];
        }
        $store = $this->storeModel->findById($storeId);
        if (!$store) {
            throw new Exception('Selected store does not exist.');
        }

        $existingOffers = $this->offerModel->findByProductId($productId);
        foreach ($existingOffers as $existingOffer) {
            if ($existingOffer['vendor_id'] !== null && (int) $existingOffer['vendor_id'] === $vendorId) {
                throw new Exception('This vendor already has an offer for this product.');
            }
        }

        try {
            $newOfferId = $this->offerModel->insert([
                'product_id' => $productId,
                'store_id' => $storeId,
                'vendor_id' => $vendorId,
                'price_idr' => $priceIdr,
                'affiliate_url' => $affiliateUrl,
                'in_stock' => $inStock,
                'status' => 'published',
                'last_checked_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new Exception('This vendor already has an offer for this product.');
            }
            throw $e;
        }

        $this->priceHistoryModel->record($newOfferId, $priceIdr);

        return $newOfferId;
    }

    /**
     * Validate submitted form fields and return product data.
     */
    private function collectInput(int $ignoreId): array
    {
        $rawName = '';
        if (isset($_POST['name'])) {
            $rawName = $_POST['name'];
        }
        $name = Validator::sanitize($rawName, 200);

        if ($name === '') {
            throw new Exception('Product name is required.');
        }

        $rawSlug = '';
        if (isset($_POST['slug'])) {
            $rawSlug = Validator::sanitize($_POST['slug'], 220);
        }
        $slug = $this->generateSlug($rawSlug);
        if ($slug === '') {
            $slug = $this->generateSlug($name);
        }
        if ($slug === '') {
            throw new Exception('Product slug is required.');
        }

        if ($this->productModel->slugExists($slug, $ignoreId)) {
            throw new Exception('Slug is already in use.');
        }
        if (\App\Core\ReservedSlugs::isReserved($slug)) {
            throw new Exception('This slug is reserved. Please choose another.');
        }

        $rawCategoryId = '';
        if (isset($_POST['category_id'])) {
            $rawCategoryId = $_POST['category_id'];
        }
        if (!Validator::integer($rawCategoryId, 1)) {
            throw new Exception('Category is required.');
        }
        $categoryId = (int) $rawCategoryId;

        $category = $this->categoryModel->findById($categoryId);
        if (!$category) {
            throw new Exception('Selected category does not exist.');
        }

        $brandId = null;
        if (isset($_POST['brand_id']) && $_POST['brand_id'] !== '') {
            if (!Validator::integer($_POST['brand_id'], 1)) {
                throw new Exception('Invalid brand.');
            }
            $brandId = (int) $_POST['brand_id'];

            $brand = $this->brandModel->findById($brandId);
            if (!$brand) {
                throw new Exception('Selected brand does not exist.');
            }
        }

        $rawModelNumber = '';
        if (isset($_POST['model_number'])) {
            $rawModelNumber = $_POST['model_number'];
        }
        $modelNumber = Validator::sanitize($rawModelNumber, 100);

        $rawShortDescription = '';
        if (isset($_POST['short_description'])) {
            $rawShortDescription = $_POST['short_description'];
        }
        $shortDescription = Validator::sanitize($rawShortDescription, 500);

        $rawDescription = '';
        if (isset($_POST['description'])) {
            $rawDescription = $_POST['description'];
        }
        $description = \App\Core\RichText::clean(Validator::sanitize($rawDescription, 20000, true));

        $rawImageAlt = '';
        if (isset($_POST['image_alt'])) {
            $rawImageAlt = $_POST['image_alt'];
        }
        $imageAlt = Validator::sanitize($rawImageAlt, 255);

        $status = 'draft';
        if (isset($_POST['status']) && $_POST['status'] !== '') {
            $status = Validator::sanitize($_POST['status'], 20);
        }
        if (!Validator::inArray($status, ['draft', 'published'])) {
            throw new Exception('Invalid status.');
        }

        return [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => $slug,
            'model_number' => $modelNumber,
            'short_description' => $shortDescription,
            'description' => $description,
            'image_alt' => $imageAlt,
            'status' => $status,
        ];
    }

    /**
     * Validate submitted SEO fields and return SEO meta data.
     */
    private function collectSeoInput(string $slug, string $categorySlug): array
    {
        $isRoot = isset($_SESSION['role']) && $_SESSION['role'] === 'root';
        if (!$isRoot) {
            $rawCustomHeadTags = '';
            $rawCustomBody = '';
            $rawCustomFooter = '';
            $rawSchemaMarkup = '';
        } else {
            if (isset($_POST['custom_head_tags'])) { $rawCustomHeadTags = $_POST['custom_head_tags']; }
            else { $rawCustomHeadTags = ''; }
            if (isset($_POST['custom_body'])) { $rawCustomBody = $_POST['custom_body']; }
            else { $rawCustomBody = ''; }
            if (isset($_POST['custom_footer'])) { $rawCustomFooter = $_POST['custom_footer']; }
            else { $rawCustomFooter = ''; }
            if (isset($_POST['schema_markup'])) { $rawSchemaMarkup = $_POST['schema_markup']; }
            else { $rawSchemaMarkup = ''; }
        }

        $rawSeoTitle = '';
        if (isset($_POST['seo_title'])) {
            $rawSeoTitle = $_POST['seo_title'];
        }
        $seoTitle = Validator::sanitize($rawSeoTitle, 255);

        $rawSeoDescription = '';
        if (isset($_POST['seo_description'])) {
            $rawSeoDescription = $_POST['seo_description'];
        }
        $seoDescription = Validator::sanitize($rawSeoDescription, 5000);

        $rawCanonicalUrl = '';
        if (isset($_POST['canonical_url'])) {
            $rawCanonicalUrl = $_POST['canonical_url'];
        }
        $canonicalUrl = Validator::sanitize($rawCanonicalUrl, 255);

        if ($canonicalUrl !== '') {
            if (!Validator::url($canonicalUrl) || !preg_match('#^https?://#i', $canonicalUrl)) {
                throw new Exception('Canonical URL is not valid. It must start with http:// or https://. Leave empty to use the page\'s own URL.');
            }
        }

        $robotsTag = 'index, follow';
        if (isset($_POST['robots_tag']) && $_POST['robots_tag'] !== '') {
            $robotsTag = Validator::sanitize($_POST['robots_tag'], 50);
        }
        if (!Validator::inArray($robotsTag, ['index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'])) {
            throw new Exception('Invalid robots tag.');
        }

        $rawOgDescription = '';
        if (isset($_POST['og_description'])) {
            $rawOgDescription = $_POST['og_description'];
        }
        $ogDescription = Validator::sanitize($rawOgDescription, 5000);

        $rawTwitterDescription = '';
        if (isset($_POST['twitter_description'])) {
            $rawTwitterDescription = $_POST['twitter_description'];
        }
        $twitterDescription = Validator::sanitize($rawTwitterDescription, 5000);

        // custom HTML fields are root-only; populated above.
        $schemaMarkup = Validator::sanitize($rawSchemaMarkup, 5000);

        if ($schemaMarkup !== '' && json_decode($schemaMarkup) === null) {
            throw new Exception('Schema markup must be valid JSON.');
        }

        $customHeadTags = Validator::sanitize($rawCustomHeadTags, 5000, true);

        $customBody = Validator::sanitize($rawCustomBody, 5000, true);

        $customFooter = Validator::sanitize($rawCustomFooter, 5000, true);

        return [
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'canonical_url' => $canonicalUrl,
            'robots_tag' => $robotsTag,
            'og_description' => $ogDescription,
            'twitter_description' => $twitterDescription,
            'schema_markup' => $schemaMarkup,
            'custom_head_tags' => $customHeadTags,
            'custom_body' => $customBody,
            'custom_footer' => $customFooter,
        ];
    }

    /**
     * Turn free text into a lowercase dash-separated slug.
     */
    private function generateSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Delete a stored product image file if it exists.
     */
    private function deleteImageFile(string $image): void
    {
        if ($image === '') {
            return;
        }
        if (strpos($image, '/uploads/products/') !== 0) {
            return;
        }

        $filename = basename($image);
        $path = __DIR__ . '/../../public/uploads/products/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Validate and store an uploaded product image, returning its public path.
     */
    private function handleImageUpload(array $file): string
    {
        return $this->processAndSaveImage($file, 'uploads/products/', 'product');
    }

    /**
     * Return the blank form data used by the create form and as the base for old input.
     */
    private function emptyFormData(): array
    {
        return [
            'category_id' => '',
            'brand_id' => '',
            'name' => '',
            'slug' => '',
            'model_number' => '',
            'short_description' => '',
            'description' => '',
            'image_alt' => '',
            'status' => 'draft',
            'seo_title' => '',
            'seo_description' => '',
            'canonical_url' => '',
            'robots_tag' => 'index, follow',
            'og_description' => $this->settingModel->getValue('default_og_desc'),
            'twitter_description' => $this->settingModel->getValue('default_twitter_desc'),
            'schema_markup' => $this->settingModel->getValue('default_schema'),
            'custom_head_tags' => $this->settingModel->getValue('default_custom_head'),
            'custom_body' => $this->settingModel->getValue('default_custom_body'),
            'custom_footer' => $this->settingModel->getValue('default_custom_footer'),
        ];
    }

    /**
     * Copy raw submitted POST values over the base form data for repopulation.
     */
    private function collectOldInput(array $base): array
    {
        $old = $base;

        if (isset($_POST['category_id'])) {
            $old['category_id'] = $_POST['category_id'];
        }
        if (isset($_POST['brand_id'])) {
            $old['brand_id'] = $_POST['brand_id'];
        }
        if (isset($_POST['name'])) {
            $old['name'] = $_POST['name'];
        }
        if (isset($_POST['slug'])) {
            $old['slug'] = $_POST['slug'];
        }
        if (isset($_POST['model_number'])) {
            $old['model_number'] = $_POST['model_number'];
        }
        if (isset($_POST['short_description'])) {
            $old['short_description'] = $_POST['short_description'];
        }
        if (isset($_POST['description'])) {
            $old['description'] = $_POST['description'];
        }
        if (isset($_POST['image_alt'])) {
            $old['image_alt'] = $_POST['image_alt'];
        }
        if (isset($_POST['status'])) {
            $old['status'] = $_POST['status'];
        }
        if (isset($_POST['seo_title'])) {
            $old['seo_title'] = $_POST['seo_title'];
        }
        if (isset($_POST['seo_description'])) {
            $old['seo_description'] = $_POST['seo_description'];
        }
        if (isset($_POST['canonical_url'])) {
            $old['canonical_url'] = $_POST['canonical_url'];
        }
        if (isset($_POST['robots_tag'])) {
            $old['robots_tag'] = $_POST['robots_tag'];
        }
        if (isset($_POST['og_description'])) {
            $old['og_description'] = $_POST['og_description'];
        }
        if (isset($_POST['twitter_description'])) {
            $old['twitter_description'] = $_POST['twitter_description'];
        }
        if (isset($_POST['schema_markup'])) {
            $old['schema_markup'] = $_POST['schema_markup'];
        }
        if (isset($_POST['custom_head_tags'])) {
            $old['custom_head_tags'] = $_POST['custom_head_tags'];
        }
        if (isset($_POST['custom_body'])) {
            $old['custom_body'] = $_POST['custom_body'];
        }
        if (isset($_POST['custom_footer'])) {
            $old['custom_footer'] = $_POST['custom_footer'];
        }

        return $old;
    }

    /**
     * Build the old form data for the edit view from a product and its SEO meta.
     */
    private function formDataForProduct(array $product, ?array $seo): array
    {
        $old = [
            'category_id' => '',
            'brand_id' => '',
            'name' => '',
            'slug' => '',
            'model_number' => '',
            'short_description' => '',
            'description' => '',
            'image_alt' => '',
            'status' => 'draft',
            'seo_title' => '',
            'seo_description' => '',
            'canonical_url' => '',
            'robots_tag' => '',
            'og_description' => '',
            'twitter_description' => '',
            'schema_markup' => '',
            'custom_head_tags' => '',
            'custom_body' => '',
            'custom_footer' => '',
        ];

        if (isset($product['category_id'])) {
            $old['category_id'] = $product['category_id'];
        }
        if (isset($product['brand_id'])) {
            $old['brand_id'] = $product['brand_id'];
        }
        if (isset($product['name'])) {
            $old['name'] = $product['name'];
        }
        if (isset($product['slug'])) {
            $old['slug'] = $product['slug'];
        }
        if (isset($product['model_number'])) {
            $old['model_number'] = $product['model_number'];
        }
        if (isset($product['short_description'])) {
            $old['short_description'] = $product['short_description'];
        }
        if (isset($product['description'])) {
            $old['description'] = $product['description'];
        }
        if (isset($product['image_alt'])) {
            $old['image_alt'] = $product['image_alt'];
        }
        if (isset($product['status'])) {
            $old['status'] = $product['status'];
        }

        if ($seo !== null) {
            if (isset($seo['seo_title'])) {
                $old['seo_title'] = $seo['seo_title'];
            }
            if (isset($seo['seo_description'])) {
                $old['seo_description'] = $seo['seo_description'];
            }
            if (isset($seo['canonical_url'])) {
                $old['canonical_url'] = $seo['canonical_url'];
            }
            if (isset($seo['robots_tag'])) {
                $old['robots_tag'] = $seo['robots_tag'];
            }
            if (isset($seo['og_description'])) {
                $old['og_description'] = $seo['og_description'];
            }
            if (isset($seo['twitter_description'])) {
                $old['twitter_description'] = $seo['twitter_description'];
            }
            if (isset($seo['schema_markup'])) {
                $old['schema_markup'] = $seo['schema_markup'];
            }
            if (isset($seo['custom_head_tags'])) {
                $old['custom_head_tags'] = $seo['custom_head_tags'];
            }
            if (isset($seo['custom_body'])) {
                $old['custom_body'] = $seo['custom_body'];
            }
            if (isset($seo['custom_footer'])) {
                $old['custom_footer'] = $seo['custom_footer'];
            }
        }

        return $old;
    }
}

