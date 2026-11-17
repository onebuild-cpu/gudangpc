<?php
/**
 * Base Controller Class
 * Provides common functionality for all controllers
 */
namespace App\Core;

use PDO;
use Exception;

/**
 * Abstract base controller providing view rendering, redirection, and auth helpers
 */
abstract class Controller
{
    /** @var PDO Database connection */
    protected PDO $pdo;

    /**
     * Constructor - injects database connection
     * 
     * @param PDO $pdo Database connection instance
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Render a view file with optional data
     * 
     * @param string $path View path relative to views/ directory
     * @param array $data Data to extract into view scope
     * @throws Exception If view file not found
     */
    protected function view(string $path, array $data = []): void
    {
        $file = __DIR__ . "/../../views/{$path}.php";

        if (!file_exists($file)) {
            throw new Exception("View not found: {$path}");
        }

        extract($data, EXTR_SKIP);
        require $file;
    }

    /**
     * Redirect to a URL
     * Prepends base URL if not an absolute URL
     * 
     * @param string $url Target URL (relative or absolute)
     * @return never
     */
    protected function redirect(string $url): void
    {
        // Prepend base URL if not absolute
        if (!preg_match('~^https?://~', $url)) {
            $base = '';
            if (isset($GLOBALS['base'])) {
                $base = $GLOBALS['base'];
            }
            $url = $base . '/' . ltrim($url, '/');
        }
        header("Location: {$url}");
        exit();
    }

    /**
     * Return JSON response with status code
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return never
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }

    /**
     * Require user to be logged in
     * Redirects to login if not authenticated
     * 
     * @return never if not logged in
     */
    protected function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            $_SESSION['error'] = 'Please login first.';
            $this->redirect('/login');
        }
    }

    /**
     * Require admin/root role
     * Redirects to home if not authorized
     * 
     * @return never if not admin
     */
    protected function requireAdmin(): void
    {
        $this->requireLogin();

        $role = '';
        if (isset($_SESSION['role'])) {
            $role = $_SESSION['role'];
        }
        if (!in_array($role, ['admin', 'root'], true)) {
            $_SESSION['error'] = 'Access denied. Admin privileges required.';
            $this->redirect('/');
        }
    }

    /**
     * Require root role
     * Redirects to the dashboard if not root
     *
     * @return never if not root
     */
    protected function requireRoot(): void
    {
        $this->requireLogin();

        $role = '';
        if (isset($_SESSION['role'])) {
            $role = $_SESSION['role'];
        }
        if ($role !== 'root') {
            $_SESSION['error'] = 'Access denied. Root privileges required.';
            $this->redirect('/dashboard');
        }
    }

    /**
     * Validate an uploaded image, convert it to WebP, and store it under public/.
     */
    protected function processAndSaveImage(array $file, string $uploadDir, string $prefix): string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload failed or file was incomplete.');
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;

        if ($file['size'] > $maxSize) {
            throw new Exception('File size must be less than 2MB');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WebP');
        }

        $source = null;
        if ($mime === 'image/jpeg') {
            $source = imagecreatefromjpeg($file['tmp_name']);
        } elseif ($mime === 'image/png') {
            $source = imagecreatefrompng($file['tmp_name']);
        } elseif ($mime === 'image/gif') {
            $source = imagecreatefromgif($file['tmp_name']);
        } elseif ($mime === 'image/webp') {
            $source = imagecreatefromwebp($file['tmp_name']);
        }

        if (!$source) {
            throw new Exception('Failed to read image');
        }

        if (!imagepalettetotruecolor($source)) {
            imagedestroy($source);
            throw new Exception('Failed to process image');
        }

        imagealphablending($source, true);
        imagesavealpha($source, true);

        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.webp';

        $relativeDir = trim($uploadDir, '/\\');
        $absoluteDir = __DIR__ . '/../../public/' . $relativeDir . '/';
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $destination = $absoluteDir . $filename;
        $saved = imagewebp($source, $destination, 85);
        if ($saved && !is_file($destination)) {
            $saved = false;
        }
        if ($saved && filesize($destination) === 0) {
            $saved = false;
            @unlink($destination);
        }
        imagedestroy($source);

        if (!$saved) {
            throw new Exception('Failed to save image');
        }

        return '/' . $relativeDir . '/' . $filename;
    }
}