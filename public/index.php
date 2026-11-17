<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Load environment variables FIRST
require_once __DIR__ . '/../app/Core/Autoloader.php';
App\Core\Autoloader::register();
App\Core\Autoloader::addNamespace('App', __DIR__ . '/../app');

App\Core\Dotenv::load();

// Load security configuration
$securityConfig = require __DIR__ . '/../config/security.php';

// Configure session
if ($securityConfig['cookie_httponly']) {
    ini_set('session.cookie_httponly', '1');
} else {
    ini_set('session.cookie_httponly', '0');
}
if ($securityConfig['cookie_secure']) {
    ini_set('session.cookie_secure', '1');
} else {
    ini_set('session.cookie_secure', '0');
}
if ($securityConfig['use_strict_mode']) {
    ini_set('session.use_strict_mode', '1');
} else {
    ini_set('session.use_strict_mode', '0');
}
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', $securityConfig['cookie_samesite']);
ini_set('session.gc_maxlifetime', (string)$securityConfig['session_timeout']);
//ini_set('session.name', 'AITRSID');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/', 
    'domain' => '',
    //'domain' => $_SERVER['HTTP_HOST'], // Dynamically set the domain
    'secure' => $securityConfig['cookie_secure'],
    'httponly' => $securityConfig['cookie_httponly'],
    'samesite' => $securityConfig['cookie_samesite']
]);

// Set session save path for shared hosting (must be writable)
$sessionPath = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_writable($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > $securityConfig['session_regenerate_interval']) {
        $sessionBackup = $_SESSION;
        session_regenerate_id(false);
        $_SESSION = $sessionBackup;
        $_SESSION['last_regeneration'] = time();
    }
}

// Load core files
require_once __DIR__ . '/../app/Core/FrameworkHeader.php';

// Get PDO connection
$pdo = require_once __DIR__ . '/../config/database.php';

// Initialize router
$router = new \App\Core\Router($pdo);

// Home routes
$router->addRoute('', 'HomeController', 'index');
$router->addRoute('search', 'PublicSearchController', 'index');
$router->addRoute('search/([a-z0-9-]+)', 'PublicSearchController', 'show');

// Public catalog routes
$router->addRoute('categories', 'PublicCategoryController', 'index');
$router->addRoute('brands', 'PublicBrandController', 'index');
$router->addRoute('brand/([a-z0-9-]+)', 'PublicBrandController', 'show');
$router->addRoute('go/(\d+)', 'PublicProductController', 'go');
$router->addRoute('sitemap\.xml', 'SitemapController', 'index');

// Static informational and legal pages
$router->addRoute('about', 'PageController', 'about');
$router->addRoute('how-it-works', 'PageController', 'howItWorks');
$router->addRoute('contact', 'PageController', 'contact');
$router->addRoute('privacy', 'PageController', 'privacy');
$router->addRoute('terms', 'PageController', 'terms');
$router->addRoute('disclosure', 'PageController', 'disclosure');

// Auth routes
// $router->addRoute('register', 'AuthController', 'showRegister');        // GET /register
// $router->addRoute('login', 'AuthController', 'showLogin');              // GET /login
// $router->addRoute('register/submit', 'AuthController', 'register');     // POST /register
// $router->addRoute('login/submit', 'AuthController', 'login');           // POST /login
// $router->addRoute('logout', 'AuthController', 'logout');                // GET /logout
// Auth routes with registration is now dashboard-only
$router->addRoute('login', 'AuthController', 'showLogin');              // GET /login
$router->addRoute('login/submit', 'AuthController', 'login');           // POST /login
$router->addRoute('logout', 'AuthController', 'logout');                // POST /logout

// Member registration routes
$router->addRoute('register', 'AuthController', 'showRegister');              // GET /register
$router->addRoute('register/submit', 'AuthController', 'register');          // POST /register
$router->addRoute('verify-email/([a-zA-Z0-9]+)', 'AuthController', 'verifyEmail'); // GET /verify-email/<token>
$router->addRoute('wishlist/add/(\d+)', 'WishlistController', 'add');
$router->addRoute('wishlist/remove/(\d+)', 'WishlistController', 'remove');

// Social auth routes
$router->addRoute('auth/google/redirect', 'SocialAuthController', 'redirectToGoogle');
$router->addRoute('auth/google/callback', 'SocialAuthController', 'handleGoogleCallback');
$router->addRoute('auth/facebook/redirect', 'SocialAuthController', 'redirectToFacebook');
$router->addRoute('auth/facebook/callback', 'SocialAuthController', 'handleFacebookCallback');

// Member area (visitor only)
$router->addRoute('member', 'MemberAreaController', 'index');
$router->addRoute('member/profile', 'MemberAreaController', 'profile');

// Protected route example
$router->addRoute('dashboard', 'DashboardController', 'index');
$router->addRoute('dashboard/profile', 'DashboardController', 'profile');
$router->addRoute('dashboard/clicks', 'DashboardController', 'clicks');

// Search analytics module (admin, root)
$router->addRoute('dashboard/search', 'AdminSearchController', 'index');
$router->addRoute('dashboard/search/(\d+)/toggle', 'AdminSearchController', 'toggleSitemap');

// Admin users module (root only)
$router->addRoute('dashboard/admins', 'AdminUserController', 'index');
$router->addRoute('dashboard/admins/create', 'AdminUserController', 'create');
$router->addRoute('dashboard/admins/store', 'AdminUserController', 'store');
$router->addRoute('dashboard/admins/(\d+)/edit', 'AdminUserController', 'edit');
$router->addRoute('dashboard/admins/(\d+)/update', 'AdminUserController', 'update');
$router->addRoute('dashboard/admins/(\d+)/delete', 'AdminUserController', 'delete');
$router->addRoute('dashboard/admins/(\d+)/verify', 'AdminUserController', 'verify');
$router->addRoute('dashboard/admins/(\d+)/unverify', 'AdminUserController', 'unverify');

// Members module (root only)
$router->addRoute('dashboard/members', 'MemberController', 'index');
$router->addRoute('dashboard/members/(\d+)/activate', 'MemberController', 'activate');
$router->addRoute('dashboard/members/(\d+)/ban', 'MemberController', 'ban');

// Categories module (admin, root)
$router->addRoute('dashboard/categories', 'AdminCategoryController', 'index');
$router->addRoute('dashboard/categories/create', 'AdminCategoryController', 'create');
$router->addRoute('dashboard/categories/store', 'AdminCategoryController', 'store');
$router->addRoute('dashboard/categories/(\d+)/edit', 'AdminCategoryController', 'edit');
$router->addRoute('dashboard/categories/(\d+)/update', 'AdminCategoryController', 'update');
$router->addRoute('dashboard/categories/(\d+)/delete', 'AdminCategoryController', 'delete');

// Brands module (admin, root)
$router->addRoute('dashboard/brands', 'AdminBrandController', 'index');
$router->addRoute('dashboard/brands/create', 'AdminBrandController', 'create');
$router->addRoute('dashboard/brands/store', 'AdminBrandController', 'store');
$router->addRoute('dashboard/brands/(\d+)/edit', 'AdminBrandController', 'edit');
$router->addRoute('dashboard/brands/(\d+)/update', 'AdminBrandController', 'update');
$router->addRoute('dashboard/brands/(\d+)/delete', 'AdminBrandController', 'delete');

// Stores module (admin, root)
$router->addRoute('dashboard/stores', 'AdminStoreController', 'index');
$router->addRoute('dashboard/stores/create', 'AdminStoreController', 'create');
$router->addRoute('dashboard/stores/store', 'AdminStoreController', 'store');
$router->addRoute('dashboard/stores/(\d+)/edit', 'AdminStoreController', 'edit');
$router->addRoute('dashboard/stores/(\d+)/update', 'AdminStoreController', 'update');
$router->addRoute('dashboard/stores/(\d+)/delete', 'AdminStoreController', 'delete');

// Vendors module (admin, root)
$router->addRoute('dashboard/vendors', 'AdminVendorController', 'index');
$router->addRoute('dashboard/vendors/create', 'AdminVendorController', 'create');
$router->addRoute('dashboard/vendors/store', 'AdminVendorController', 'store');
$router->addRoute('dashboard/vendors/(\d+)/edit', 'AdminVendorController', 'edit');
$router->addRoute('dashboard/vendors/(\d+)/update', 'AdminVendorController', 'update');
$router->addRoute('dashboard/vendors/(\d+)/delete', 'AdminVendorController', 'delete');

// Products module (admin, root)
$router->addRoute('dashboard/products', 'AdminProductController', 'index');
$router->addRoute('dashboard/products/create', 'AdminProductController', 'create');
$router->addRoute('dashboard/products/store', 'AdminProductController', 'store');
$router->addRoute('dashboard/products/import', 'AdminProductImportController', 'index');
$router->addRoute('dashboard/products/import/store', 'AdminProductImportController', 'store');
$router->addRoute('dashboard/products/(\d+)/edit', 'AdminProductController', 'edit');
$router->addRoute('dashboard/products/(\d+)/update', 'AdminProductController', 'update');
$router->addRoute('dashboard/products/(\d+)/delete', 'AdminProductController', 'delete');
$router->addRoute('dashboard/products/(\d+)/offer/store', 'AdminProductController', 'storeOffer');
$router->addRoute('dashboard/products/(\d+)/offer/(\d+)/update', 'AdminProductController', 'updateOffer');
$router->addRoute('dashboard/products/(\d+)/offer/(\d+)/delete', 'AdminProductController', 'deleteOffer');

// Settings module (admin, root)
$router->addRoute('dashboard/settings', 'AdminSettingController', 'index');
$router->addRoute('dashboard/settings/update', 'AdminSettingController', 'update');

// Clean public catalog routes (wildcards must stay last)
$router->addRoute('([a-z0-9-]+)', 'PublicCategoryController', 'show');
$router->addRoute('([a-z0-9-]+)/([a-z0-9-]+)', 'PublicProductController', 'show');

// Dispatch request - FIXED FOR SUBDIRECTORY
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptDir !== '/' && $scriptDir !== '\\') {
    if (stripos($requestUri, $scriptDir) === 0) {
        $requestUri = substr($requestUri, strlen($scriptDir));
    }
}

if (stripos($requestUri, '/public') === 0) {
    $requestUri = substr($requestUri, strlen('/public'));
}

$requestUri = trim($requestUri, '/');

// Make requestUri available to views
$GLOBALS['requestUri'] = $requestUri;

$router->dispatch($requestUri);