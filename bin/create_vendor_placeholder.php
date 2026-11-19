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
 * Find one vendor by its slug.
 */
function findVendorBySlug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, slug, image FROM vendors WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor === false) {
        return null;
    }

    return $vendor;
}

/**
 * Return every vendor that has no image.
 */
function findVendorsWithoutImage(PDO $pdo): array
{
    return $pdo->query("SELECT id, name, slug, image FROM vendors WHERE image IS NULL OR image = '' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Draw a square WebP placeholder with the vendor name and save it.
 */
function createVendorPlaceholder(string $filePath, string $label, string $fontPath): bool
{
    $width = 800;
    $height = 800;

    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 244, 246, 250);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);

    $accent = imagecolorallocate($image, 230, 120, 0);
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

$fontPath = __DIR__ . '/../public/assets/fonts/Poppins-Bold.ttf';
if (!file_exists($fontPath)) {
    fwrite(STDERR, 'Font not found: ' . $fontPath . PHP_EOL);
    exit(1);
}

$uploadDir = __DIR__ . '/../public/uploads/vendors/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$vendors = [];
if (isset($argv[1])) {
    $slug = $argv[1];
    if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
        fwrite(STDERR, 'Invalid vendor slug: ' . $slug . PHP_EOL);
        exit(1);
    }
    $vendor = findVendorBySlug($pdo, $slug);
    if ($vendor === null) {
        fwrite(STDERR, 'Vendor not found: ' . $slug . PHP_EOL);
        exit(1);
    }
    $vendors[] = $vendor;
} else {
    $vendors = findVendorsWithoutImage($pdo);
}

if (count($vendors) === 0) {
    echo 'No vendors need a placeholder image.' . PHP_EOL;
    exit(0);
}

$updateStatement = $pdo->prepare("UPDATE vendors SET image = ? WHERE id = ? AND (image IS NULL OR image = '')");
$totalUpdated = 0;

foreach ($vendors as $vendor) {
    $imageValue = '';
    if (isset($vendor['image']) && $vendor['image'] !== null) {
        $imageValue = (string) $vendor['image'];
    }
    if ($imageValue !== '') {
        echo 'Vendor ' . $vendor['slug'] . ' already has an image: ' . $imageValue . PHP_EOL;
        continue;
    }

    $fileName = 'placeholder-' . $vendor['slug'] . '.webp';
    $absolutePath = $uploadDir . $fileName;
    $relativePath = '/uploads/vendors/' . $fileName;

    $created = createVendorPlaceholder($absolutePath, (string) $vendor['name'], $fontPath);
    if (!$created) {
        echo 'Failed to create the image for vendor: ' . $vendor['slug'] . PHP_EOL;
        continue;
    }

    $updateStatement->execute([$relativePath, (int) $vendor['id']]);
    $updated = $updateStatement->rowCount();
    $totalUpdated += $updated;

    echo 'Vendor ' . $vendor['slug'] . ': saved ' . $relativePath . ', updated ' . $updated . ' vendor row(s)' . PHP_EOL;
}

echo PHP_EOL . 'Total vendors updated: ' . $totalUpdated . PHP_EOL;
