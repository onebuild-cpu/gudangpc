<?php
require_once __DIR__ . '/../app/Core/Autoloader.php';
App\Core\Autoloader::register();
App\Core\Autoloader::addNamespace('App', __DIR__ . '/../app');

App\Core\Dotenv::load();

$dbHost = App\Core\Dotenv::get('DB_HOST', '127.0.0.1');
$dbName = App\Core\Dotenv::get('DB_NAME', 'gudangpc');
$dbUser = App\Core\Dotenv::get('DB_USER', 'root');
$dbPass = App\Core\Dotenv::get('DB_PASS', '');
$dbCharset = App\Core\Dotenv::get('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * Find one category by its slug.
 */
function findCategoryBySlug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, slug, image FROM categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($category === false) {
        return null;
    }

    return $category;
}

/**
 * Draw a square WebP placeholder with the category name and save it.
 */
function createCategoryPlaceholder(string $filePath, string $label, string $fontPath): bool
{
    $width = 800;
    $height = 800;

    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 244, 246, 250);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);

    $accent = imagecolorallocate($image, 230, 0, 18);
    imagefilledrectangle($image, 0, 0, $width, 14, $accent);
    imagefilledrectangle($image, 0, $height - 14, $width, $height, $accent);

    $labelColor = imagecolorallocate($image, 52, 62, 80);
    $footColor = imagecolorallocate($image, 138, 148, 162);

    $labelSize = 46;
    if (mb_strlen($label, 'UTF-8') > 12) {
        $labelSize = 32;
    }
    $labelBox = imagettfbbox($labelSize, 0, $fontPath, $label);
    $labelWidth = $labelBox[2] - $labelBox[0];
    $labelHeight = $labelBox[1] - $labelBox[5];
    $labelX = (int) (($width - $labelWidth) / 2) - $labelBox[0];
    $labelY = (int) (($height - $labelHeight) / 2) + $labelBox[5];
    imagettftext($image, $labelSize, 0, $labelX, $labelY, $labelColor, $fontPath, $label);

    $footText = 'GudangPC';
    $footSize = 22;
    $footBox = imagettfbbox($footSize, 0, $fontPath, $footText);
    $footWidth = $footBox[2] - $footBox[0];
    $footX = (int) (($width - $footWidth) / 2) - $footBox[0];
    $footY = $height - 58;
    imagettftext($image, $footSize, 0, $footX, $footY, $footColor, $fontPath, $footText);

    $saved = imagewebp($image, $filePath, 85);
    imagedestroy($image);

    return (bool) $saved;
}

$slug = 'gaming';
if (isset($argv[1])) {
    $slug = $argv[1];
}

if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
    fwrite(STDERR, 'Invalid category slug: ' . $slug . PHP_EOL);
    exit(1);
}

$category = findCategoryBySlug($pdo, $slug);
if ($category === null) {
    fwrite(STDERR, 'Category not found: ' . $slug . PHP_EOL);
    exit(1);
}

$imageValue = '';
if (isset($category['image']) && $category['image'] !== null) {
    $imageValue = (string) $category['image'];
}
if ($imageValue !== '') {
    echo 'Category ' . $slug . ' already has an image: ' . $imageValue . PHP_EOL;
    exit(0);
}

$fontPath = __DIR__ . '/../public/assets/fonts/Poppins-Bold.ttf';
if (!file_exists($fontPath)) {
    fwrite(STDERR, 'Font not found: ' . $fontPath . PHP_EOL);
    exit(1);
}

$uploadDir = __DIR__ . '/../public/uploads/categories/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'placeholder-' . $slug . '.webp';
$absolutePath = $uploadDir . $fileName;
$relativePath = '/uploads/categories/' . $fileName;

$created = createCategoryPlaceholder($absolutePath, (string) $category['name'], $fontPath);
if (!$created) {
    fwrite(STDERR, 'Failed to create the image for category: ' . $slug . PHP_EOL);
    exit(1);
}

$updateStatement = $pdo->prepare("UPDATE categories SET image = ? WHERE id = ? AND (image IS NULL OR image = '')");
$updateStatement->execute([$relativePath, (int) $category['id']]);

echo 'Category ' . $slug . ': saved ' . $relativePath . ', updated ' . $updateStatement->rowCount() . ' category row(s)' . PHP_EOL;
