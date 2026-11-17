<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Core\RateLimiter;
use App\Core\RateLimitException;
use App\Models\SearchCacheModel;
use Exception;

/**
 * Provides search analytics and sitemap visibility controls for admins.
 */
class AdminSearchController extends Controller
{
    private SearchCacheModel $searchCacheModel;

    /**
     * Inject the database connection and build the search cache model.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->searchCacheModel = new SearchCacheModel($pdo);
    }

    /**
     * List visitor search queries with pagination and text filtering.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $search = '';
        if (isset($_GET['search'])) {
            $search = Validator::sanitize($_GET['search'], 255);
        }

        $page = 1;
        if (isset($_GET['page'])) {
            if (Validator::integer($_GET['page'], 1)) {
                $page = (int) $_GET['page'];
            }
        }

        $perPage = 20;
        $total = $this->searchCacheModel->countAll($search);
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($page > $totalPages && $page > 1) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $queries = $this->searchCacheModel->getAllPaginated($offset, $perPage, $search);

        $this->view('dashboard/search/index', [
            'pageTitle' => 'Search Analytics',
            'metaDescription' => 'Review visitor search queries and sitemap visibility',
            'currentPage' => 'search',
            'queries' => $queries,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }

    /**
     * Toggle whether a cached query is included in the sitemap.
     */
    public function toggleSitemap(string $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/search');
            return;
        }

        CsrfProtection::requireValidToken();

        try {
            $limiter = new RateLimiter($this->pdo);
            $limiter->consume('admin_write:' . (int) $_SESSION['user_id'], 'admin_write');
        } catch (RateLimitException $e) {
            $_SESSION['error'] = 'Too many changes. Please wait ' . RateLimiter::formatRetryAfter($e->getRetryAfter()) . '.';
            $this->redirect('/dashboard/search');
            return;
        }

        $id = (int) $id;

        $statusRaw = '';
        if (isset($_POST['status'])) {
            $statusRaw = (string) $_POST['status'];
        }

        $status = -1;
        if ($statusRaw === '1') {
            $status = 1;
        } elseif ($statusRaw === '0') {
            $status = 0;
        }

        if ($id < 1 || $status < 0) {
            $_SESSION['error'] = 'Invalid search cache selection.';
            $this->redirect('/dashboard/search');
            return;
        }

        $cache = $this->searchCacheModel->findById($id);
        if ($cache === null) {
            $_SESSION['error'] = 'Search query not found.';
            $this->redirect('/dashboard/search');
            return;
        }

        try {
            $this->searchCacheModel->toggleIndexed($id, $status);
        } catch (Exception $e) {
            $_SESSION['error'] = 'Unable to update the sitemap status.';
            $this->redirect('/dashboard/search');
            return;
        }

        if ($status === 1) {
            $_SESSION['success'] = 'Search query is now included in the sitemap.';
        } else {
            $_SESSION['success'] = 'Search query is now hidden from the sitemap.';
        }

        $this->redirect('/dashboard/search');
    }
}
