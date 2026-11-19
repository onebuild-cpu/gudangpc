<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Models\LocationModel;
use App\Models\VendorModel;
use Exception;

/**
 * Manages catalog locations for admins and root users.
 */
class AdminLocationController extends Controller
{
    private LocationModel $locationModel;
    private VendorModel $vendorModel;

    /**
     * Inject the database connection and build the required models.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->locationModel = new LocationModel($pdo);
        $this->vendorModel = new VendorModel($pdo);
    }

    /**
     * List all locations ordered by name.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $locations = $this->locationModel->findAll('name ASC');

        $sellerCounts = [];
        foreach ($locations as $location) {
            $sellerCounts[(int) $location['id']] = $this->vendorModel->countByLocationId((int) $location['id']);
        }

        $this->view('dashboard/locations/index', [
            'pageTitle' => 'Locations',
            'metaDescription' => 'Manage seller locations',
            'currentPage' => 'locations',
            'locations' => $locations,
            'sellerCounts' => $sellerCounts,
        ]);
    }

    /**
     * Show the create location form.
     */
    public function create(): void
    {
        $this->requireAdmin();

        $this->view('dashboard/locations/create', [
            'pageTitle' => 'Create Location',
            'metaDescription' => 'Create a new seller location',
            'currentPage' => 'locations',
            'error' => '',
            'old' => $this->emptyFormData(),
        ]);
    }

    /**
     * Create a new location from the submitted form.
     */
    public function store(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/locations');
            return;
        }

        CsrfProtection::requireValidToken();

        try {
            $data = $this->collectInput(0);
            $this->locationModel->insert($data);
            $_SESSION['success'] = 'Location created successfully.';
            $this->redirect('/dashboard/locations');
        } catch (Exception $e) {
            $this->view('dashboard/locations/create', [
                'pageTitle' => 'Create Location',
                'metaDescription' => 'Create a new seller location',
                'currentPage' => 'locations',
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->emptyFormData()),
            ]);
        }
    }

    /**
     * Show the edit location form.
     */
    public function edit(string $id): void
    {
        $this->requireAdmin();

        $id = (int) $id;
        $location = $this->locationModel->findById($id);

        if (!$location) {
            $_SESSION['error'] = 'Location not found.';
            $this->redirect('/dashboard/locations');
            return;
        }

        $this->view('dashboard/locations/edit', [
            'pageTitle' => 'Edit Location',
            'metaDescription' => 'Edit a seller location',
            'currentPage' => 'locations',
            'location' => $location,
            'error' => '',
            'old' => $this->formDataForLocation($location),
        ]);
    }

    /**
     * Update an existing location from the submitted form.
     */
    public function update(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/locations');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $location = $this->locationModel->findById($id);

        if (!$location) {
            $_SESSION['error'] = 'Location not found.';
            $this->redirect('/dashboard/locations');
            return;
        }

        try {
            $data = $this->collectInput($id);
            $this->locationModel->update($id, $data);
            $_SESSION['success'] = 'Location updated successfully.';
            $this->redirect('/dashboard/locations');
        } catch (Exception $e) {
            $this->view('dashboard/locations/edit', [
                'pageTitle' => 'Edit Location',
                'metaDescription' => 'Edit a seller location',
                'currentPage' => 'locations',
                'location' => $location,
                'error' => $e->getMessage(),
                'old' => $this->collectOldInput($this->formDataForLocation($location)),
            ]);
        }
    }

    /**
     * Delete a location when no sellers reference it.
     */
    public function delete(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/locations');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $location = $this->locationModel->findById($id);

        if (!$location) {
            $_SESSION['error'] = 'Location not found.';
            $this->redirect('/dashboard/locations');
            return;
        }

        if ($this->vendorModel->countByLocationId($id) > 0) {
            $_SESSION['error'] = 'Cannot delete a location that has sellers. Reassign the sellers first.';
            $this->redirect('/dashboard/locations');
            return;
        }

        try {
            $this->locationModel->delete($id);
            $_SESSION['success'] = 'Location deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Could not delete: ' . $e->getMessage();
        }
        $this->redirect('/dashboard/locations');
    }

    /**
     * Validate submitted form fields and return location data.
     */
    private function collectInput(int $ignoreId): array
    {
        $rawName = '';
        if (isset($_POST['name'])) {
            $rawName = $_POST['name'];
        }
        $name = Validator::sanitize($rawName, 100);

        if ($name === '') {
            throw new Exception('Location name is required.');
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
            throw new Exception('Location slug is required.');
        }

        if ($this->locationModel->slugExists($slug, $ignoreId)) {
            throw new Exception('Slug is already in use.');
        }
        if ($this->locationModel->nameExists($name, $ignoreId)) {
            throw new Exception('A location with this name already exists.');
        }

        return [
            'name' => $name,
            'slug' => $slug,
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

        return $old;
    }

    /**
     * Build the old form data for the edit view from a location.
     */
    private function formDataForLocation(array $location): array
    {
        $old = [
            'name' => '',
            'slug' => '',
        ];

        if (isset($location['name'])) {
            $old['name'] = $location['name'];
        }
        if (isset($location['slug'])) {
            $old['slug'] = $location['slug'];
        }

        return $old;
    }
}
