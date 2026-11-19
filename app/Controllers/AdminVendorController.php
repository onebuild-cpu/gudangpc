<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Models\OfferModel;
use App\Models\VendorModel;
use App\Models\StoreModel;
use App\Models\LocationModel;
use Exception;

/**
 * Manages vendors (sellers) for admins and root users.
 */
class AdminVendorController extends Controller
{
    private VendorModel $vendorModel;
    private StoreModel $storeModel;
    private LocationModel $locationModel;
    private OfferModel $offerModel;

    /**
     * Inject the database connection and build the required models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->vendorModel = new VendorModel($pdo);
        $this->storeModel = new StoreModel($pdo);
        $this->locationModel = new LocationModel($pdo);
        $this->offerModel = new OfferModel($pdo);
    }

    /**
     * List all vendors with their store names.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $vendors = $this->vendorModel->findAllWithStore();

        $this->view('dashboard/vendors/index', [
            'pageTitle' => 'Vendors',
            'metaDescription' => 'Manage marketplace vendors',
            'currentPage' => 'vendors',
            'vendors' => $vendors,
        ]);
    }

    /**
     * Show the create vendor form.
     */
    public function create(): void
    {
        $this->requireAdmin();

        $stores = $this->storeModel->findAll('name ASC');
        $locations = $this->locationModel->findAll('name ASC');

        $this->view('dashboard/vendors/create', [
            'pageTitle' => 'Create Vendor',
            'metaDescription' => 'Create a new marketplace vendor',
            'currentPage' => 'vendors',
            'stores' => $stores,
            'locations' => $locations,
            'error' => '',
            'old' => $this->emptyFormData(),
        ]);
    }

    /**
     * Create a new vendor from the submitted form.
     */
    public function store(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/vendors');
            return;
        }

        CsrfProtection::requireValidToken();

        try {
            $data = $this->collectInput(0);

            $this->vendorModel->insert($data);
            $_SESSION['success'] = 'Vendor created successfully.';
            $this->redirect('/dashboard/vendors');
        } catch (Exception $e) {
            $stores = $this->storeModel->findAll('name ASC');
            $locations = $this->locationModel->findAll('name ASC');

            $this->view('dashboard/vendors/create', [
                'pageTitle' => 'Create Vendor',
                'metaDescription' => 'Create a new marketplace vendor',
                'currentPage' => 'vendors',
                'stores' => $stores,
                'locations' => $locations,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->emptyFormData()),
            ]);
        }
    }

    /**
     * Show the edit vendor form.
     */
    public function edit(string $id): void
    {
        $this->requireAdmin();

        $id = (int) $id;
        $vendor = $this->vendorModel->findById($id);

        if (!$vendor) {
            $_SESSION['error'] = 'Vendor not found.';
            $this->redirect('/dashboard/vendors');
            return;
        }

        $stores = $this->storeModel->findAll('name ASC');
        $locations = $this->locationModel->findAll('name ASC');

        $this->view('dashboard/vendors/edit', [
            'pageTitle' => 'Edit Vendor',
            'metaDescription' => 'Edit a marketplace vendor',
            'currentPage' => 'vendors',
            'vendor' => $vendor,
            'stores' => $stores,
            'locations' => $locations,
            'error' => '',
            'old' => $this->formDataForVendor($vendor),
        ]);
    }

    /**
     * Update an existing vendor from the submitted form.
     */
    public function update(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/vendors');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $vendor = $this->vendorModel->findById($id);

        if (!$vendor) {
            $_SESSION['error'] = 'Vendor not found.';
            $this->redirect('/dashboard/vendors');
            return;
        }

        try {
            $data = $this->collectInput($id);

            $submittedStoreId = 0;
            if (isset($data['store_id'])) {
                $submittedStoreId = (int) $data['store_id'];
            }
            $currentStoreId = 0;
            if (isset($vendor['store_id'])) {
                $currentStoreId = (int) $vendor['store_id'];
            }
            if ($submittedStoreId !== $currentStoreId && $this->offerModel->countByVendorId($id) > 0) {
                throw new Exception('Cannot change the store of a vendor that already has offers.');
            }

            $this->vendorModel->update($id, $data);
            $_SESSION['success'] = 'Vendor updated successfully.';
            $this->redirect('/dashboard/vendors');
        } catch (Exception $e) {
            $stores = $this->storeModel->findAll('name ASC');
            $locations = $this->locationModel->findAll('name ASC');
            $this->view('dashboard/vendors/edit', [
                'pageTitle' => 'Edit Vendor',
                'metaDescription' => 'Edit a marketplace vendor',
                'currentPage' => 'vendors',
                'vendor' => $vendor,
                'stores' => $stores,
                'locations' => $locations,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->formDataForVendor($vendor)),
            ]);
        }
    }

    /**
     * Delete a vendor.
     */
    public function delete(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/vendors');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $vendor = $this->vendorModel->findById($id);

        if (!$vendor) {
            $_SESSION['error'] = 'Vendor not found.';
            $this->redirect('/dashboard/vendors');
            return;
        }

        if ($this->offerModel->countByVendorId($id) > 0) {
            $_SESSION['error'] = 'Cannot delete: offers still use this vendor.';
            $this->redirect('/dashboard/vendors');
            return;
        }

        try {
            $this->vendorModel->delete($id);
            $_SESSION['success'] = 'Vendor deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        $this->redirect('/dashboard/vendors');
    }

    /**
     * Validate submitted form fields and return vendor data.
     */
    private function collectInput(int $ignoreId): array
    {
        $rawStoreId = '';
        if (isset($_POST['store_id'])) {
            $rawStoreId = $_POST['store_id'];
        }
        if (!Validator::integer($rawStoreId, 1)) {
            throw new Exception('Store is required.');
        }
        $storeId = (int) $rawStoreId;

        $store = $this->storeModel->findById($storeId);
        if (!$store) {
            throw new Exception('Selected store does not exist.');
        }

        $rawLocationId = '';
        if (isset($_POST['location_id'])) {
            $rawLocationId = $_POST['location_id'];
        }
        $locationId = null;
        if ($rawLocationId !== '') {
            if (!Validator::integer($rawLocationId, 1)) {
                throw new Exception('Invalid location.');
            }
            $locationId = (int) $rawLocationId;
            $location = $this->locationModel->findById($locationId);
            if (!$location) {
                throw new Exception('Selected location does not exist.');
            }
        }

        $rawName = '';
        if (isset($_POST['name'])) {
            $rawName = $_POST['name'];
        }
        $name = Validator::sanitize($rawName, 255);

        if ($name === '') {
            throw new Exception('Vendor name is required.');
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
            throw new Exception('Vendor slug is required.');
        }

        if ($this->vendorModel->slugExists($slug, $ignoreId)) {
            throw new Exception('Slug is already in use.');
        }

        $rawDescription = '';
        if (isset($_POST['description']) && is_string($_POST['description'])) {
            $rawDescription = $_POST['description'];
        }
        $description = null;
        if ($rawDescription !== '') {
            $measuredDescription = Validator::sanitize($rawDescription, 100000);
            if (mb_strlen($measuredDescription, 'UTF-8') > 1000) {
                throw new Exception('Description must be at most 1000 characters.');
            }
            $description = Validator::sanitize($rawDescription, 1000);
        }

        $rawRating = '';
        if (isset($_POST['rating'])) {
            $rawRating = $_POST['rating'];
        }
        $rating = null;
        if ($rawRating !== '') {
            if (!is_numeric($rawRating)) {
                throw new Exception('Rating must be a number.');
            }
            $rating = (float) $rawRating;
            if ($rating < 0 || $rating > 5) {
                throw new Exception('Rating must be between 0 and 5.');
            }
        }

        return [
            'store_id' => $storeId,
            'location_id' => $locationId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'rating' => $rating,
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
            'store_id' => '',
            'location_id' => '',
            'name' => '',
            'slug' => '',
            'description' => '',
            'rating' => '',
        ];
    }

    /**
     * Copy raw submitted POST values over the base form data for repopulation.
     */
    private function collectOldInput(array $base): array
    {
        $old = $base;

        if (isset($_POST['store_id'])) {
            $old['store_id'] = $_POST['store_id'];
        }
        if (isset($_POST['location_id'])) {
            $old['location_id'] = $_POST['location_id'];
        }
        if (isset($_POST['name'])) {
            $old['name'] = $_POST['name'];
        }
        if (isset($_POST['slug'])) {
            $old['slug'] = $_POST['slug'];
        }
        if (isset($_POST['description']) && is_string($_POST['description'])) {
            $old['description'] = $_POST['description'];
        }
        if (isset($_POST['rating'])) {
            $old['rating'] = $_POST['rating'];
        }

        return $old;
    }

    /**
     * Build the old form data for the edit view from a vendor.
     */
    private function formDataForVendor(array $vendor): array
    {
        $old = [
            'store_id' => '',
            'location_id' => '',
            'name' => '',
            'slug' => '',
            'description' => '',
            'rating' => '',
        ];

        if (isset($vendor['store_id'])) {
            $old['store_id'] = $vendor['store_id'];
        }
        if (isset($vendor['location_id'])) {
            $old['location_id'] = $vendor['location_id'];
        }
        if (isset($vendor['name'])) {
            $old['name'] = $vendor['name'];
        }
        if (isset($vendor['slug'])) {
            $old['slug'] = $vendor['slug'];
        }
        if (isset($vendor['description'])) {
            $old['description'] = $vendor['description'];
        }
        if (isset($vendor['rating'])) {
            $old['rating'] = $vendor['rating'];
        }

        return $old;
    }
}
