<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Models\UserModel;
use App\Models\ProductModel;
use App\Models\OfferModel;
use App\Models\MemberModel;
use App\Models\ClickModel;
use Exception;

class DashboardController extends Controller
{
    private UserModel $userModel;
    private ProductModel $productModel;
    private OfferModel $offerModel;
    private MemberModel $memberModel;
    private ClickModel $clickModel;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->userModel = new UserModel($pdo);
        $this->productModel = new ProductModel($pdo);
        $this->offerModel = new OfferModel($pdo);
        $this->memberModel = new MemberModel($pdo);
        $this->clickModel = new ClickModel($pdo);
    }

    public function index(): void
    {
        $this->requireLogin();

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'visitor') {
            $this->redirect('/member');
            return;
        }

        $productCount = $this->productModel->countAll();
        $offerCount = $this->offerModel->countActive();
        $memberCount = $this->memberModel->countByStatus('active');

        $this->view('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'metaDescription' => 'Admin Dashboard - Overview of your application',
            'productCount' => $productCount,
            'offerCount' => $offerCount,
            'memberCount' => $memberCount,
        ]);
    }

    public function clicks(): void
    {
        $this->requireLogin();

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'visitor') {
            $this->redirect('/member');
            return;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM `offer_clicks`');
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $totalClicks = (int) $row['c'];

        $sort = 'clicks';
        if (isset($_GET['sort'])) {
            $rawSort = (string) $_GET['sort'];
            if ($rawSort === 'name' || $rawSort === 'id' || $rawSort === 'clicks') {
                $sort = $rawSort;
            }
        }

        $dir = 'desc';
        if (isset($_GET['dir'])) {
            $rawDir = (string) $_GET['dir'];
            if ($rawDir === 'asc' || $rawDir === 'desc') {
                $dir = $rawDir;
            }
        }

        $clickStats = $this->clickModel->getProductClickStats($sort, $dir);

        $this->view('dashboard/clicks', [
            'pageTitle' => 'Click Analytics',
            'metaDescription' => 'Click analytics - Affiliate click tracking overview',
            'currentPage' => 'clicks',
            'totalClicks' => $totalClicks,
            'clickStats' => $clickStats,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function profile(): void
    {
        $this->requireLogin();

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'visitor') {
            $this->redirect('/member');
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        $user = $this->userModel->findById($userId);

        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \App\Core\CsrfProtection::requireValidToken();
            $action = '';
            if (isset($_POST['action'])) {
                $action = $_POST['action'];
            }

            try {
                if ($action === 'update_username') {
                    $rawUsername = '';
                    if (isset($_POST['username'])) {
                        $rawUsername = $_POST['username'];
                    }
                    $username = trim($rawUsername);
                    $this->userModel->updateUsername($userId, $username);
                    $sessionUsername = null;
                    if ($username) {
                        $sessionUsername = $username;
                    }
                    $_SESSION['username'] = $sessionUsername;
                    $success = 'Username updated successfully';
                    $user = $this->userModel->findById($userId);
                } elseif ($action === 'change_password') {
                    $currentPassword = '';
                    if (isset($_POST['current_password'])) {
                        $currentPassword = $_POST['current_password'];
                    }
                    $newPassword = '';
                    if (isset($_POST['new_password'])) {
                        $newPassword = $_POST['new_password'];
                    }
                    $confirmPassword = '';
                    if (isset($_POST['confirm_password'])) {
                        $confirmPassword = $_POST['confirm_password'];
                    }

                    if ($newPassword !== $confirmPassword) {
                        throw new Exception('New passwords do not match');
                    }

                    $this->userModel->changePassword($userId, $currentPassword, $newPassword);
                    $success = 'Password changed successfully';
                } elseif ($action === 'update_avatar') {
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $oldAvatar = '';
                        if (isset($user['avatar'])) {
                            $oldAvatar = (string) $user['avatar'];
                        }

                        $avatarPath = $this->handleAvatarUpload($_FILES['avatar'], $userId);
                        $this->userModel->updateAvatar($userId, $avatarPath);
                        $this->deleteAvatarFile($oldAvatar);
                        $_SESSION['avatar'] = $avatarPath;
                        $success = 'Avatar updated successfully';
                        $user = $this->userModel->findById($userId);
                    } else {
                        throw new Exception('Please select a valid image file');
                    }
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        $this->view('dashboard/profile', [
            'pageTitle' => 'Profile',
            'metaDescription' => 'User Profile',
            'user' => $user,
            'error' => $error,
            'success' => $success,
        ]);
    }

    private function handleAvatarUpload(array $file, int $userId): string
    {
        return $this->processAndSaveImage($file, 'uploads/avatars/', 'avatar_' . $userId);
    }

    /**
     * Delete a stored avatar file if it exists.
     */
    private function deleteAvatarFile(string $avatar): void
    {
        if ($avatar === '') {
            return;
        }
        if (strpos($avatar, '/uploads/avatars/') !== 0) {
            return;
        }

        $filename = basename($avatar);
        $path = __DIR__ . '/../../public/uploads/avatars/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}