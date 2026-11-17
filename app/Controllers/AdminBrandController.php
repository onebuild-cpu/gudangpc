<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Models\BrandModel;
use App\Models\SeoMetaModel;
use App\Models\SettingModel;
use Exception;

/**
 * Manages catalog brands for admins and root users.
 */
class AdminBrandController extends Controller
{
    private BrandModel $brandModel;
    private SeoMetaModel $seoMetaModel;
    private SettingModel $settingModel;

    /**
     * Inject the database connection and build the brand and SEO models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->brandModel = new BrandModel($pdo);
        $this->seoMetaModel = new SeoMetaModel($pdo);
        $this->settingModel = new SettingModel($pdo);
    }

    /**
     * List all brands ordered by name.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $brands = $this->brandModel->findAll('name ASC');

        $this->view('dashboard/brands/index', [
            'pageTitle' => 'Brands',
            'metaDescription' => 'Manage product brands',
            'currentPage' => 'brands',
            'brands' => $brands,
        ]);
    }

    /**
     * Show the create brand form.
     */
    public function create(): void
    {
        $this->requireAdmin();

        $this->view('dashboard/brands/create', [
            'pageTitle' => 'Create Brand',
            'metaDescription' => 'Create a new product brand',
            'currentPage' => 'brands',
            'loadSummernote' => true,
            'error' => '',
            'old' => $this->emptyFormData(),
        ]);
    }

    /**
     * Create a new brand from the submitted form.
     */
    public function store(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/brands');
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

            $newId = $this->brandModel->insert($data);
            $this->seoMetaModel->saveMeta('brand', $newId, $seoData);
            $_SESSION['success'] = 'Brand created successfully.';
            $this->redirect('/dashboard/brands');
        } catch (Exception $e) {
            if ($newFile !== '') {
                $this->deleteLogoFile($newFile);
            }
            $this->view('dashboard/brands/create', [
                'pageTitle' => 'Create Brand',
                'metaDescription' => 'Create a new product brand',
                'currentPage' => 'brands',
                'loadSummernote' => true,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->emptyFormData()),
            ]);
        }
    }

    /**
     * Show the edit brand form.
     */
    public function edit(string $id): void
    {
        $this->requireAdmin();

        $id = (int) $id;
        $brand = $this->brandModel->findById($id);

        if (!$brand) {
            $_SESSION['error'] = 'Brand not found.';
            $this->redirect('/dashboard/brands');
            return;
        }

        $seo = $this->seoMetaModel->findByMeta('brand', $id);

        $this->view('dashboard/brands/edit', [
            'pageTitle' => 'Edit Brand',
            'metaDescription' => 'Edit a product brand',
            'currentPage' => 'brands',
            'loadSummernote' => true,
            'brand' => $brand,
            'error' => '',
            'old' => $this->formDataForBrand($brand, $seo),
        ]);
    }

    /**
     * Update an existing brand from the submitted form.
     */
    public function update(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/brands');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $brand = $this->brandModel->findById($id);

        if (!$brand) {
            $_SESSION['error'] = 'Brand not found.';
            $this->redirect('/dashboard/brands');
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

            $this->brandModel->update($id, $data);
            if ($newFile !== '') {
                $oldLogo = '';
                if (isset($brand['logo'])) {
                    $oldLogo = (string) $brand['logo'];
                }
                $this->deleteLogoFile($oldLogo);
            }
            $this->seoMetaModel->saveMeta('brand', $id, $seoData);
            $_SESSION['success'] = 'Brand updated successfully.';
            $this->redirect('/dashboard/brands');
        } catch (Exception $e) {
            if ($newFile !== '') {
                $this->deleteLogoFile($newFile);
            }
            $seo = $this->seoMetaModel->findByMeta('brand', $id);
            $this->view('dashboard/brands/edit', [
                'pageTitle' => 'Edit Brand',
                'metaDescription' => 'Edit a product brand',
                'currentPage' => 'brands',
                'loadSummernote' => true,
                'brand' => $brand,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->formDataForBrand($brand, $seo)),
            ]);
        }
    }

    /**
     * Delete a brand and its SEO meta.
     */
    public function delete(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/brands');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $brand = $this->brandModel->findById($id);

        if (!$brand) {
            $_SESSION['error'] = 'Brand not found.';
            $this->redirect('/dashboard/brands');
            return;
        }

        $oldLogo = '';
        if (isset($brand['logo'])) {
            $oldLogo = (string) $brand['logo'];
        }

        try {
            $this->brandModel->delete($id);
        } catch (Exception $e) {
            $_SESSION['error'] = 'Could not delete: ' . $e->getMessage();
            $this->redirect('/dashboard/brands');
            return;
        }

        $_SESSION['success'] = 'Brand deleted successfully.';

        try {
            $seo = $this->seoMetaModel->findByMeta('brand', $id);
            if ($seo) {
                $this->seoMetaModel->delete((int) $seo['id']);
            }
        } catch (Exception $e) {
            error_log('SEO meta delete failed for brand ' . $id . ': ' . $e->getMessage());
            $_SESSION['warning'] = 'Record deleted, but its SEO meta could not be removed.';
        }

        $this->deleteLogoFile($oldLogo);
        $this->redirect('/dashboard/brands');
    }

    /**
     * Validate submitted form fields and return brand data.
     */
    private function collectInput(int $ignoreId): array
    {
        $rawName = '';
        if (isset($_POST['name'])) {
            $rawName = $_POST['name'];
        }
        $name = Validator::sanitize($rawName, 100);

        if ($name === '') {
            throw new Exception('Brand name is required.');
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
            throw new Exception('Brand slug is required.');
        }

        if ($this->brandModel->slugExists($slug, $ignoreId)) {
            throw new Exception('Slug is already in use.');
        }
        if (\App\Core\ReservedSlugs::isReserved($slug)) {
            throw new Exception('This slug is reserved. Please choose another.');
        }

        $rawWebsiteUrl = '';
        if (isset($_POST['website_url'])) {
            $rawWebsiteUrl = $_POST['website_url'];
        }
        $websiteUrl = Validator::sanitize($rawWebsiteUrl, 255);
        if ($websiteUrl !== '' && !Validator::url($websiteUrl)) {
            throw new Exception('Website URL is not valid.');
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
            'website_url' => $websiteUrl,
            'description' => $description,
            'status' => $status,
        ];
    }

    /**
     * Validate submitted SEO fields and return SEO meta data.
     */
    private function collectSeoInput(string $slug): array
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
     * Return the blank form data used by the create form and as the base for old input.
     */
    private function emptyFormData(): array
    {
        return [
            'name' => '',
            'slug' => '',
            'website_url' => '',
            'description' => '',
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
        if (isset($_POST['website_url'])) {
            $old['website_url'] = $_POST['website_url'];
        }
        if (isset($_POST['description'])) {
            $old['description'] = $_POST['description'];
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
     * Build the old form data for the edit view from a brand and its SEO meta.
     */
    private function formDataForBrand(array $brand, ?array $seo): array
    {
        $old = [
            'name' => '',
            'slug' => '',
            'website_url' => '',
            'description' => '',
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

        if (isset($brand['name'])) {
            $old['name'] = $brand['name'];
        }
        if (isset($brand['slug'])) {
            $old['slug'] = $brand['slug'];
        }
        if (isset($brand['website_url'])) {
            $old['website_url'] = $brand['website_url'];
        }
        if (isset($brand['description'])) {
            $old['description'] = $brand['description'];
        }
        if (isset($brand['status'])) {
            $old['status'] = $brand['status'];
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
     * Delete a stored brand logo file if it exists.
     */
    private function deleteLogoFile(string $logo): void
    {
        if ($logo === '') {
            return;
        }
        if (strpos($logo, '/uploads/brands/') !== 0) {
            return;
        }

        $filename = basename($logo);
        $path = __DIR__ . '/../../public/uploads/brands/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Validate and store an uploaded brand logo, returning its public path.
     */
    private function handleLogoUpload(array $file): string
    {
        return $this->processAndSaveImage($file, 'uploads/brands/', 'brand');
    }
}
