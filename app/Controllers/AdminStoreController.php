<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Models\OfferModel;
use App\Models\StoreModel;
use App\Models\SeoMetaModel;
use App\Models\SettingModel;
use App\Models\VendorModel;
use Exception;

/**
 * Manages catalog stores for admins and root users.
 */
class AdminStoreController extends Controller
{
    private StoreModel $storeModel;
    private OfferModel $offerModel;
    private VendorModel $vendorModel;
    private SeoMetaModel $seoMetaModel;
    private SettingModel $settingModel;

    /**
     * Inject the database connection and build the store and SEO models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->storeModel = new StoreModel($pdo);
        $this->offerModel = new OfferModel($pdo);
        $this->vendorModel = new VendorModel($pdo);
        $this->seoMetaModel = new SeoMetaModel($pdo);
        $this->settingModel = new SettingModel($pdo);
    }

    /**
     * List all stores ordered by name.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $stores = $this->storeModel->findAll('name ASC');

        $this->view('dashboard/stores/index', [
            'pageTitle' => 'Stores',
            'metaDescription' => 'Manage marketplace stores',
            'currentPage' => 'stores',
            'stores' => $stores,
        ]);
    }

    /**
     * Show the create store form.
     */
    public function create(): void
    {
        $this->requireAdmin();

        $this->view('dashboard/stores/create', [
            'pageTitle' => 'Create Store',
            'metaDescription' => 'Create a new marketplace store',
            'currentPage' => 'stores',
            'loadSummernote' => true,
            'error' => '',
            'old' => $this->emptyFormData(),
        ]);
    }

    /**
     * Create a new store from the submitted form.
     */
    public function store(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/stores');
            return;
        }

        CsrfProtection::requireValidToken();

        $newFile = '';
        try {
            $data = $this->collectInput(0);
            $data['created_by'] = (int) $_SESSION['user_id'];

            $seoData = $this->collectSeoInput($data['slug']);

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $newFile = $this->handleLogoUpload($_FILES['logo']);
                $data['logo'] = $newFile;
            }

            $newId = $this->storeModel->insert($data);
            $this->seoMetaModel->saveMeta('store', $newId, $seoData);
            $_SESSION['success'] = 'Store created successfully.';
            $this->redirect('/dashboard/stores');
        } catch (Exception $e) {
            if ($newFile !== '') {
                $this->deleteLogoFile($newFile);
            }
            $this->view('dashboard/stores/create', [
                'pageTitle' => 'Create Store',
                'metaDescription' => 'Create a new marketplace store',
                'currentPage' => 'stores',
                'loadSummernote' => true,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->emptyFormData()),
            ]);
        }
    }

    /**
     * Show the edit store form.
     */
    public function edit(string $id): void
    {
        $this->requireAdmin();

        $id = (int) $id;
        $store = $this->storeModel->findById($id);

        if (!$store) {
            $_SESSION['error'] = 'Store not found.';
            $this->redirect('/dashboard/stores');
            return;
        }

        $seo = $this->seoMetaModel->findByMeta('store', $id);

        $this->view('dashboard/stores/edit', [
            'pageTitle' => 'Edit Store',
            'metaDescription' => 'Edit a marketplace store',
            'currentPage' => 'stores',
            'loadSummernote' => true,
            'store' => $store,
            'error' => '',
            'old' => $this->formDataForStore($store, $seo),
        ]);
    }

    /**
     * Update an existing store from the submitted form.
     */
    public function update(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/stores');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $store = $this->storeModel->findById($id);

        if (!$store) {
            $_SESSION['error'] = 'Store not found.';
            $this->redirect('/dashboard/stores');
            return;
        }

        $newFile = '';
        try {
            $data = $this->collectInput($id);
            $data['updated_by'] = (int) $_SESSION['user_id'];

            $seoData = $this->collectSeoInput($data['slug']);

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $newFile = $this->handleLogoUpload($_FILES['logo']);
                $data['logo'] = $newFile;
            }

            $this->storeModel->update($id, $data);
            if ($newFile !== '') {
                $oldLogo = '';
                if (isset($store['logo'])) {
                    $oldLogo = (string) $store['logo'];
                }
                $this->deleteLogoFile($oldLogo);
            }
            $this->seoMetaModel->saveMeta('store', $id, $seoData);
            $_SESSION['success'] = 'Store updated successfully.';
            $this->redirect('/dashboard/stores');
        } catch (Exception $e) {
            if ($newFile !== '') {
                $this->deleteLogoFile($newFile);
            }
            $seo = $this->seoMetaModel->findByMeta('store', $id);
            $this->view('dashboard/stores/edit', [
                'pageTitle' => 'Edit Store',
                'metaDescription' => 'Edit a marketplace store',
                'currentPage' => 'stores',
                'loadSummernote' => true,
                'store' => $store,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->formDataForStore($store, $seo)),
            ]);
        }
    }

    /**
     * Delete a store and its SEO meta.
     */
    public function delete(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/stores');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $store = $this->storeModel->findById($id);

        if (!$store) {
            $_SESSION['error'] = 'Store not found.';
            $this->redirect('/dashboard/stores');
            return;
        }

        if ($this->offerModel->countByStoreId($id) > 0 || $this->vendorModel->countByStoreId($id) > 0) {
            $_SESSION['error'] = 'Cannot delete: offers or vendors still use this store.';
            $this->redirect('/dashboard/stores');
            return;
        }

        try {
            $this->storeModel->delete($id);
            $_SESSION['success'] = 'Store deleted successfully.';
            $seo = $this->seoMetaModel->findByMeta('store', $id);
            if ($seo) {
                $this->seoMetaModel->delete((int) $seo['id']);
            }

            $oldLogo = '';
            if (isset($store['logo'])) {
                $oldLogo = (string) $store['logo'];
            }
            $this->deleteLogoFile($oldLogo);
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        $this->redirect('/dashboard/stores');
    }

    /**
     * Validate submitted form fields and return store data.
     */
    private function collectInput(int $ignoreId): array
    {
        $rawName = '';
        if (isset($_POST['name'])) {
            $rawName = $_POST['name'];
        }
        $name = Validator::sanitize($rawName, 100);

        if ($name === '') {
            throw new Exception('Store name is required.');
        }

        $rawSlug = '';
        if (isset($_POST['slug'])) {
            $rawSlug = $_POST['slug'];
        }
        $slug = $this->generateSlug($rawSlug);
        if ($slug === '') {
            $slug = $this->generateSlug($name);
        }
        if ($slug === '') {
            throw new Exception('Store slug is required.');
        }

        if ($this->storeModel->slugExists($slug, $ignoreId)) {
            throw new Exception('Slug is already in use.');
        }

        $rawBaseUrl = '';
        if (isset($_POST['base_url'])) {
            $rawBaseUrl = $_POST['base_url'];
        }
        $baseUrl = Validator::sanitize($rawBaseUrl, 255);
        if ($baseUrl !== '' && !Validator::url($baseUrl)) {
            throw new Exception('Base URL is not valid.');
        }

        $rawAffiliateTemplate = '';
        if (isset($_POST['affiliate_url_template'])) {
            $rawAffiliateTemplate = $_POST['affiliate_url_template'];
        }
        $affiliateUrlTemplate = Validator::sanitize($rawAffiliateTemplate, 500);
        if ($affiliateUrlTemplate !== '') {
            $hasPlaceholder = false;
            if (strpos($affiliateUrlTemplate, '{url}') !== false) {
                $hasPlaceholder = true;
            }
            if (strpos($affiliateUrlTemplate, '{url_encoded}') !== false) {
                $hasPlaceholder = true;
            }
            if (strpos($affiliateUrlTemplate, '{external_id}') !== false) {
                $hasPlaceholder = true;
            }
            if (strpos($affiliateUrlTemplate, 'https://') !== 0 || !$hasPlaceholder) {
                throw new Exception('Template must start with https:// and contain {url}, {url_encoded} or {external_id}.');
            }
        }

        $rawDescription = '';
        if (isset($_POST['description'])) {
            $rawDescription = $_POST['description'];
        }
        $description = Validator::sanitize($rawDescription, 5000, true);

        $status = 'draft';
        if (isset($_POST['status']) && $_POST['status'] !== '') {
            $status = Validator::sanitize($_POST['status'], 20);
        }
        if (!Validator::inArray($status, ['draft', 'published'])) {
            throw new Exception('Invalid status.');
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'base_url' => $baseUrl,
            'affiliate_url_template' => $affiliateUrlTemplate,
            'status' => $status,
        ];
    }

    /**
     * Validate submitted SEO fields and return SEO meta data.
     */
    private function collectSeoInput(string $slug): array
    {
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

        $rawSchemaMarkup = '';
        if (isset($_POST['schema_markup'])) {
            $rawSchemaMarkup = $_POST['schema_markup'];
        }
        $schemaMarkup = Validator::sanitize($rawSchemaMarkup, 5000);

        $rawCustomHeadTags = '';
        if (isset($_POST['custom_head_tags'])) {
            $rawCustomHeadTags = $_POST['custom_head_tags'];
        }
        $customHeadTags = Validator::sanitize($rawCustomHeadTags, 5000, true);

        $rawCustomBody = '';
        if (isset($_POST['custom_body'])) {
            $rawCustomBody = $_POST['custom_body'];
        }
        $customBody = Validator::sanitize($rawCustomBody, 5000, true);

        $rawCustomFooter = '';
        if (isset($_POST['custom_footer'])) {
            $rawCustomFooter = $_POST['custom_footer'];
        }
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
     * Return the blank form data used by the create form and as the base for old input.
     */
    private function emptyFormData(): array
    {
        return [
            'name' => '',
            'slug' => '',
            'description' => '',
            'base_url' => '',
            'affiliate_url_template' => '',
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

        if (isset($_POST['name'])) {
            $old['name'] = $_POST['name'];
        }
        if (isset($_POST['slug'])) {
            $old['slug'] = $_POST['slug'];
        }
        if (isset($_POST['description'])) {
            $old['description'] = $_POST['description'];
        }
        if (isset($_POST['base_url'])) {
            $old['base_url'] = $_POST['base_url'];
        }
        if (isset($_POST['affiliate_url_template'])) {
            $old['affiliate_url_template'] = $_POST['affiliate_url_template'];
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
     * Build the old form data for the edit view from a store and its SEO meta.
     */
    private function formDataForStore(array $store, ?array $seo): array
    {
        $old = [
            'name' => '',
            'slug' => '',
            'description' => '',
            'base_url' => '',
            'affiliate_url_template' => '',
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

        if (isset($store['name'])) {
            $old['name'] = $store['name'];
        }
        if (isset($store['slug'])) {
            $old['slug'] = $store['slug'];
        }
        if (isset($store['description'])) {
            $old['description'] = $store['description'];
        }
        if (isset($store['base_url'])) {
            $old['base_url'] = $store['base_url'];
        }
        if (isset($store['affiliate_url_template'])) {
            $old['affiliate_url_template'] = $store['affiliate_url_template'];
        }
        if (isset($store['status'])) {
            $old['status'] = $store['status'];
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

    /**
     * Delete a stored store logo file if it exists.
     */
    private function deleteLogoFile(string $logo): void
    {
        if ($logo === '') {
            return;
        }
        if (strpos($logo, '/uploads/stores/') !== 0) {
            return;
        }

        $filename = basename($logo);
        $path = __DIR__ . '/../../public/uploads/stores/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Validate and store an uploaded store logo, returning its public path.
     */
    private function handleLogoUpload(array $file): string
    {
        return $this->processAndSaveImage($file, 'uploads/stores/', 'store');
    }
}
