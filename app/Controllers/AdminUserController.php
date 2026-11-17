<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Models\UserModel;
use Exception;

/**
 * Manages admin user accounts for root users.
 */
class AdminUserController extends Controller
{
    private UserModel $userModel;

    /**
     * Inject the database connection and build the user model.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->userModel = new UserModel($pdo);
    }

    /**
     * List all admin users (never root).
     */
    public function index(): void
    {
        $this->requireRoot();

        $allUsers = $this->userModel->findAll('id ASC');
        $admins = [];
        foreach ($allUsers as $user) {
            if ($user['role'] === 'admin') {
                unset($user['password']);
                $admins[] = $user;
            }
        }

        $this->view('dashboard/admins/index', [
            'pageTitle' => 'Admins',
            'metaDescription' => 'Manage admin users',
            'currentPage' => 'admins',
            'admins' => $admins,
        ]);
    }

    /**
     * Show the create admin form.
     */
    public function create(): void
    {
        $this->requireRoot();

        $this->view('dashboard/admins/create', [
            'pageTitle' => 'Create Admin',
            'metaDescription' => 'Create a new admin user',
            'currentPage' => 'admins',
            'error' => '',
            'old' => [
                'phone' => '',
                'username' => '',
            ],
        ]);
    }

    /**
     * Create a new admin from the submitted form.
     */
    public function store(): void
    {
        $this->requireRoot();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/admins');
            return;
        }

        CsrfProtection::requireValidToken();

        $rawPhone = '';
        if (isset($_POST['phone'])) {
            $rawPhone = $_POST['phone'];
        }
        $phone = Validator::sanitize($rawPhone, 50);

        $rawUsername = '';
        if (isset($_POST['username'])) {
            $rawUsername = $_POST['username'];
        }
        $username = Validator::sanitize($rawUsername, 50);

        $password = '';
        if (isset($_POST['password'])) {
            $password = $_POST['password'];
        }

        $confirmPassword = '';
        if (isset($_POST['confirm_password'])) {
            $confirmPassword = $_POST['confirm_password'];
        }

        $error = '';
        if ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $passwordCheck = Validator::passwordStrength($password);
            if (!$passwordCheck['valid']) {
                $error = implode('; ', $passwordCheck['errors']);
            }
        }

        if ($error !== '') {
            $this->view('dashboard/admins/create', [
                'pageTitle' => 'Create Admin',
                'metaDescription' => 'Create a new admin user',
                'currentPage' => 'admins',
                'error' => $error,
                'old' => [
                    'phone' => $phone,
                    'username' => $username,
                ],
            ]);
            return;
        }

        try {
            $newId = $this->userModel->register($phone, $password, 'admin');
            if ($username !== '') {
                $this->userModel->updateUsername($newId, $username);
            }
            $_SESSION['success'] = 'Admin created successfully.';
            $this->redirect('/dashboard/admins');
        } catch (Exception $e) {
            $this->view('dashboard/admins/create', [
                'pageTitle' => 'Create Admin',
                'metaDescription' => 'Create a new admin user',
                'currentPage' => 'admins',
                'error' => $e->getMessage(),
                'old' => [
                    'phone' => $phone,
                    'username' => $username,
                ],
            ]);
        }
    }

    /**
     * Show the edit admin form.
     */
    public function edit(string $id): void
    {
        $this->requireRoot();

        $id = (int) $id;
        $admin = $this->userModel->findById($id);

        if (!$admin) {
            $_SESSION['error'] = 'Admin not found.';
            $this->redirect('/dashboard/admins');
            return;
        }

        if ($admin['role'] === 'root') {
            $_SESSION['error'] = 'Access denied.';
            $this->redirect('/dashboard/admins');
            return;
        }

        unset($admin['password']);

        $this->view('dashboard/admins/edit', [
            'pageTitle' => 'Edit Admin',
            'metaDescription' => 'Edit an admin user',
            'currentPage' => 'admins',
            'admin' => $admin,
            'error' => '',
        ]);
    }

    /**
     * Update an existing admin from the submitted form.
     */
    public function update(string $id): void
    {
        $this->requireRoot();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/admins');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $admin = $this->userModel->findById($id);

        if (!$admin) {
            $_SESSION['error'] = 'Admin not found.';
            $this->redirect('/dashboard/admins');
            return;
        }

        if ($admin['role'] === 'root') {
            $_SESSION['error'] = 'Access denied.';
            $this->redirect('/dashboard/admins');
            return;
        }

        $rawUsername = '';
        if (isset($_POST['username'])) {
            $rawUsername = $_POST['username'];
        }
        $username = Validator::sanitize($rawUsername, 50);

        $newPassword = '';
        if (isset($_POST['new_password'])) {
            $newPassword = $_POST['new_password'];
        }

        $confirmPassword = '';
        if (isset($_POST['confirm_password'])) {
            $confirmPassword = $_POST['confirm_password'];
        }

        $error = '';
        if ($newPassword !== '') {
            if ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match';
            } else {
                $passwordCheck = Validator::passwordStrength($newPassword);
                if (!$passwordCheck['valid']) {
                    $error = implode('; ', $passwordCheck['errors']);
                }
            }
        }

        if ($error !== '') {
            unset($admin['password']);
            $this->view('dashboard/admins/edit', [
                'pageTitle' => 'Edit Admin',
                'metaDescription' => 'Edit an admin user',
                'currentPage' => 'admins',
                'admin' => $admin,
                'error' => $error,
            ]);
            return;
        }

        try {
            $this->userModel->updateUsername($id, $username);
            if ($newPassword !== '') {
                $this->userModel->resetPassword($id, $newPassword);
            }
            $_SESSION['success'] = 'Admin updated successfully.';
            $this->redirect('/dashboard/admins');
        } catch (Exception $e) {
            unset($admin['password']);
            $this->view('dashboard/admins/edit', [
                'pageTitle' => 'Edit Admin',
                'metaDescription' => 'Edit an admin user',
                'currentPage' => 'admins',
                'admin' => $admin,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark an admin account as verified.
     */
    public function verify(string $id): void
    {
        $this->requireRoot();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/admins');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $admin = $this->userModel->findById($id);

        if (!$admin || $admin['role'] !== 'admin') {
            $_SESSION['error'] = 'Admin not found';
            $this->redirect('/dashboard/admins');
            return;
        }

        try {
            $this->userModel->markVerified($id);
            $_SESSION['success'] = 'Admin verified successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        $this->redirect('/dashboard/admins');
    }

    /**
     * Mark an admin account as unverified.
     */
    public function unverify(string $id): void
    {
        $this->requireRoot();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/admins');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $admin = $this->userModel->findById($id);

        if (!$admin || $admin['role'] !== 'admin') {
            $_SESSION['error'] = 'Admin not found';
            $this->redirect('/dashboard/admins');
            return;
        }

        try {
            $this->userModel->update($id, ['is_verified' => 0]);
            $_SESSION['success'] = 'Admin unverified successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        $this->redirect('/dashboard/admins');
    }

    /**
     * Delete an admin account.
     */
    public function delete(string $id): void
    {
        $this->requireRoot();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/admins');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;
        $admin = $this->userModel->findById($id);

        if (!$admin) {
            $_SESSION['error'] = 'Admin not found.';
            $this->redirect('/dashboard/admins');
            return;
        }

        if ($admin['role'] === 'root') {
            $_SESSION['error'] = 'Access denied.';
            $this->redirect('/dashboard/admins');
            return;
        }

        try {
            $this->userModel->delete($id);
            $_SESSION['success'] = 'Admin deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        $this->redirect('/dashboard/admins');
    }
}
