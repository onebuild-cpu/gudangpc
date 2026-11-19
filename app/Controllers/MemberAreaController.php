<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Models\MemberModel;
use App\Models\WishlistModel;
use Exception;

/**
 * Member area for logged-in visitors.
 */
class MemberAreaController extends Controller
{
    private MemberModel $memberModel;
    private WishlistModel $wishlistModel;

    /**
     * Require a logged-in visitor and build the member model.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->memberModel = new MemberModel($pdo);
        $this->wishlistModel = new WishlistModel($pdo);

        $loggedIn = false;
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != 0) {
            $loggedIn = true;
        }
        $role = '';
        if (isset($_SESSION['role'])) {
            $role = $_SESSION['role'];
        }
        if (!$loggedIn || $role !== 'visitor') {
            $this->redirect('/login');
        }
    }

    /**
     * Show the member dashboard.
     */
    public function index(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $member = $this->memberModel->findById($userId);

        if (!$member) {
            $this->redirect('/login');
            return;
        }

        $wishlist = [];
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != 0) {
            $wishlist = $this->wishlistModel->findAllByMember($userId);
        }

        $memberUsername = null;
        if (isset($member['username'])) {
            $memberUsername = $member['username'];
        }
        $_SESSION['username'] = $memberUsername;

        $displayName = '';
        if (isset($member['email'])) {
            $displayName = $member['email'];
        }
        if ($memberUsername !== null && $memberUsername !== '') {
            $displayName = $memberUsername;
        }

        unset($member['password']);

        $this->view('member/dashboard', [
            'pageTitle' => 'Area Anggota',
            'metaDescription' => 'Area anggota Anda',
            'member' => $member,
            'displayName' => $displayName,
            'wishlist' => $wishlist,
        ]);
    }

    /**
     * Show and handle the member profile form.
     */
    public function profile(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $member = $this->memberModel->findById($userId);

        if (!$member) {
            $this->redirect('/login');
            return;
        }

        $memberUsername = null;
        if (isset($member['username'])) {
            $memberUsername = $member['username'];
        }
        $_SESSION['username'] = $memberUsername;

        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::requireValidToken();

            $action = '';
            if (isset($_POST['action'])) {
                $action = $_POST['action'];
            }

            try {
                if ($action === 'update_profile') {
                    $username = '';
                    if (isset($_POST['username'])) {
                        $username = Validator::sanitize($_POST['username'], 50);
                    }

                    $this->memberModel->updateUsername($userId, $username);

                    $sessionUsername = null;
                    if ($username !== '') {
                        $sessionUsername = $username;
                    }
                    $_SESSION['username'] = $sessionUsername;

                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $oldAvatar = '';
                        if (isset($member['avatar'])) {
                            $oldAvatar = (string) $member['avatar'];
                        }

                        try {
                            $avatarPath = $this->handleAvatarUpload($_FILES['avatar'], $userId);
                        } catch (Exception $e) {
                            throw new Exception('Failed to upload avatar: ' . $e->getMessage());
                        }
                        $this->memberModel->updateAvatar($userId, $avatarPath);
                        $this->deleteAvatarFile($oldAvatar);
                        $_SESSION['avatar'] = $avatarPath;
                    }

                    $success = 'Profile updated successfully';
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

                    if (!password_verify($currentPassword, $member['password'])) {
                        throw new Exception('Current password is incorrect');
                    }
                    if ($newPassword !== $confirmPassword) {
                        throw new Exception('New passwords do not match');
                    }
                    if (strlen($newPassword) < 8) {
                        throw new Exception('Password must be at least 8 characters');
                    }

                    $this->memberModel->updatePassword($userId, $newPassword);
                    $success = 'Password changed successfully';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }

            $member = $this->memberModel->findById($userId);
            if (!$member) {
                $this->redirect('/login');
                return;
            }

            $memberUsername = null;
            if (isset($member['username'])) {
                $memberUsername = $member['username'];
            }
            $_SESSION['username'] = $memberUsername;
        }

        unset($member['password']);

        $this->view('member/profile', [
            'pageTitle' => 'Profil Saya',
            'metaDescription' => 'Ubah profil anggota Anda',
            'user' => $member,
            'error' => $error,
            'success' => $success,
        ]);
    }

    /**
     * Validate and store an uploaded avatar, returning its public path.
     */
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
