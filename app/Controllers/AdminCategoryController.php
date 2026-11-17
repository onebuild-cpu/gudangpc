<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\SeoMetaModel;
use App\Models\SettingModel;
use Exception;

/**
 * Manages catalog categories for admins and root users.
 */
class AdminCategoryController extends Controller
{
    private CategoryModel $categoryModel;
    private ProductModel $productModel;
    private SeoMetaModel $seoMetaModel;
    private SettingModel $settingModel;

    /**
     * Inject the database connection and build the category and SEO models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->categoryModel = new CategoryModel($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->seoMetaModel = new SeoMetaModel($pdo);
        $this->settingModel = new SettingModel($pdo);
    }

    /**
     * List all categories ordered by sort order, then name.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $categories = $this->categoryModel->findAllOrdered();

        $this->view('dashboard/categories/index', [
            'pageTitle' => 'Categories',
            'metaDescription' => 'Manage product categories',
            'currentPage' => 'categories',
            'categories' => $categories,
        ]);
    }

    /**
     * Show the create category form.
     */
    public function create(): void
    {
        $this->requireAdmin();

        $this->view('dashboard/categories/create', [
            'pageTitle' => 'Create Category',
            'metaDescription' => 'Create a new product category',
            'currentPage' => 'categories',
            'loadSummernote' => true,
            'error' => '',
            'old' => $this->emptyFormData(),
        ]);
    }

    /**
     * Create a new category from the submitted form.
     */
    public function store(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/categories');
            return;
        }

        CsrfProtection::requireValidToken();

        $newFile = '';
        try {
            $data = $this->collectInput(0);

            $seoData = $this->collectSeoInput($data['slug']);

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $newFile = $this->handleImageUpload($_FILES['image']);
                $data['image'] = $newFile;
            }

            $id = $this->categoryModel->insert($data);
            $this->seoMetaModel->saveMeta('category', $id, $seoData);
            $_SESSION['success'] = 'Category created successfully.';
            $this->redirect('/dashboard/categories');
        } catch (Exception $e) {
            if ($newFile !== '') {
                $this->deleteImageFile($newFile);
            }
            $this->view('dashboard/categories/create', [
                'pageTitle' => 'Create Category',
                'metaDescription' => 'Create a new product category',
                'currentPage' => 'categories',
                'loadSummernote' => true,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->emptyFormData()),
            ]);
        }
    }

    /**
     * Show the edit category form.
     */
    public function edit(string $id): void
    {
        $this->requireAdmin();

        $id = (int) $id;
        $category = $this->categoryModel->findById($id);

        if (!$category) {
            $_SESSION['error'] = 'Category not found.';
            $this->redirect('/dashboard/categories');
            return;
        }

        $seo = $this->seoMetaModel->findByMeta('category', $id);

        $this->view('dashboard/categories/edit', [
            'pageTitle' => 'Edit Category',
            'metaDescription' => 'Edit a product category',
            'currentPage' => 'categories',
            'loadSummernote' => true,
            'category' => $category,
            'error' => '',
            'old' => $this->formDataForCategory($category, $seo),
        ]);
    }

    /**
     * Update an existing category from the submitted form.
     */
    public function update(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/categories');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $category = $this->categoryModel->findById($id);

        if (!$category) {
            $_SESSION['error'] = 'Category not found.';
            $this->redirect('/dashboard/categories');
            return;
        }

        $newFile = '';
        try {
            $data = $this->collectInput($id);

            $seoData = $this->collectSeoInput($data['slug']);

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $newFile = $this->handleImageUpload($_FILES['image']);
                $data['image'] = $newFile;
            }

            $this->categoryModel->update($id, $data);
            if ($newFile !== '') {
                $oldImage = '';
                if (isset($category['image'])) {
                    $oldImage = (string) $category['image'];
                }
                $this->deleteImageFile($oldImage);
            }
            $this->seoMetaModel->saveMeta('category', $id, $seoData);
            $_SESSION['success'] = 'Category updated successfully.';
            $this->redirect('/dashboard/categories');
        } catch (Exception $e) {
            if ($newFile !== '') {
                $this->deleteImageFile($newFile);
            }
            $seo = $this->seoMetaModel->findByMeta('category', $id);
            $this->view('dashboard/categories/edit', [
                'pageTitle' => 'Edit Category',
                'metaDescription' => 'Edit a product category',
                'currentPage' => 'categories',
                'loadSummernote' => true,
                'category' => $category,
                'error' => $e->getMessage(),
                'old' => $this->formDataForCategory($category, $seo),
            ]);
        }
    }

    /**
     * Delete a category.
     */
    public function delete(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/categories');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $category = $this->categoryModel->findById($id);

        if (!$category) {
            $_SESSION['error'] = 'Category not found.';
            $this->redirect('/dashboard/categories');
            return;
        }

        if ($this->productModel->countByCategoryId($id) > 0) {
            $_SESSION['error'] = 'Cannot delete: products still use this category.';
            $this->redirect('/dashboard/categories');
            return;
        }

        try {
            $this->categoryModel->delete($id);
            $_SESSION['success'] = 'Category deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/dashboard/categories');
            return;
        }

        $seo = $this->seoMetaModel->findByMeta('category', $id);
        if ($seo) {
            $this->seoMetaModel->delete((int) $seo['id']);
        }

        $oldImage = '';
        if (isset($category['image'])) {
            $oldImage = (string) $category['image'];
        }
        $this->deleteImageFile($oldImage);

        $this->redirect('/dashboard/categories');
    }

    /**
     * Validate submitted form fields and return category data.
     */
    private function collectInput(int $ignoreId): array
    {
        $rawName = '';
        if (isset($_POST['name'])) {
            $rawName = $_POST['name'];
        }
        $name = Validator::sanitize($rawName, 100);

        if ($name === '') {
            throw new Exception('Category name is required.');
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
            throw new Exception('Category slug is required.');
        }

        if (Validator::inArray($slug, ['login','logout','register','verify-email','member','dashboard','categories',
        'brands','brand','compare','go','wishlist','csp-report','uploads','assets','favicon','robots','category',
        'product','store','search','auth','sitemap'])) {
            throw new Exception('This slug is reserved. Please choose another.');
        }

        if ($this->categoryModel->slugExists($slug, $ignoreId)) {
            throw new Exception('Slug is already in use.');
        }

        $rawDescription = '';
        if (isset($_POST['description'])) {
            $rawDescription = $_POST['description'];
        }
        $description = Validator::sanitize($rawDescription, 5000, true);

        $rawImageAlt = '';
        if (isset($_POST['image_alt'])) {
            $rawImageAlt = $_POST['image_alt'];
        }
        $imageAlt = Validator::sanitize($rawImageAlt, 255);

        $sortOrder = 0;
        if (isset($_POST['sort_order']) && $_POST['sort_order'] !== '') {
            if (!Validator::integer($_POST['sort_order'])) {
                throw new Exception('Sort order must be an integer.');
            }
            $sortOrder = (int) $_POST['sort_order'];
        }

        $status = 'draft';
        if (isset($_POST['status']) && $_POST['status'] !== '') {
            $status = $_POST['status'];
        }
        if (!Validator::inArray($status, ['draft', 'published'])) {
            throw new Exception('Invalid status.');
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'image_alt' => $imageAlt,
            'sort_order' => $sortOrder,
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

        if (isset($_POST['name'])) {
            $old['name'] = $_POST['name'];
        }
        if (isset($_POST['slug'])) {
            $old['slug'] = $_POST['slug'];
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
     * Build the old form data for the edit view from a category and its SEO meta.
     */
    private function formDataForCategory(array $category, ?array $seo): array
    {
        $old = [
            'name' => '',
            'slug' => '',
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

        if (isset($category['name'])) {
            $old['name'] = $category['name'];
        }
        if (isset($category['slug'])) {
            $old['slug'] = $category['slug'];
        }
        if (isset($category['description'])) {
            $old['description'] = $category['description'];
        }
        if (isset($category['status'])) {
            $old['status'] = $category['status'];
        }
        if (isset($category['image_alt'])) {
            $old['image_alt'] = $category['image_alt'];
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
     * Validate and store an uploaded category image, returning its public path.
     */
    private function handleImageUpload(array $file): string
    {
        return $this->processAndSaveImage($file, 'uploads/categories/', 'category');
    }

    /**
     * Delete a stored category image file if it exists.
     */
    private function deleteImageFile(string $image): void
    {
        if ($image === '') {
            return;
        }
        if (strpos($image, '/uploads/categories/') !== 0) {
            return;
        }

        $filename = basename($image);
        $path = __DIR__ . '/../../public/uploads/categories/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
