<?php
// Generates one placeholder image per seeded category and attaches it to the seeded
// products that do not have an image yet. Existing images are never overwritten.

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
 * Return the id of the first row matching one column value.
 */
function fetchId(PDO $pdo, string $table, string $column, string $value): ?int
{
    $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$value]);
    $id = $stmt->fetchColumn();

    if ($id === false) {
        return null;
    }

    return (int) $id;
}

/**
 * Draw a square placeholder image with the category label and save it as WebP.
 */
function createPlaceholderImage(string $filePath, string $label, string $fontPath, int $accentRed, int $accentGreen, int $accentBlue): bool
{
    $width = 800;
    $height = 800;

    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 244, 246, 250);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);

    $accent = imagecolorallocate($image, $accentRed, $accentGreen, $accentBlue);
    imagefilledrectangle($image, 0, 0, $width, 14, $accent);
    imagefilledrectangle($image, 0, $height - 14, $width, $height, $accent);

    $labelColor = imagecolorallocate($image, 52, 62, 80);
    $footColor = imagecolorallocate($image, 138, 148, 162);

    $labelSize = 46;
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

$uploadRelativeDir = 'uploads/products';
$uploadAbsoluteDir = __DIR__ . '/../public/' . $uploadRelativeDir . '/';
if (!is_dir($uploadAbsoluteDir)) {
    mkdir($uploadAbsoluteDir, 0755, true);
}

$placeholderCategories = [
    ['slug' => 'cpu', 'label' => 'CPU', 'color' => [230, 0, 18]],
    ['slug' => 'gpu', 'label' => 'GPU', 'color' => [102, 51, 153]],
    ['slug' => 'motherboard', 'label' => 'Motherboard', 'color' => [0, 120, 135]],
    ['slug' => 'ram', 'label' => 'RAM', 'color' => [140, 60, 160]],
    ['slug' => 'ssd', 'label' => 'SSD', 'color' => [0, 110, 190]],
    ['slug' => 'psu', 'label' => 'PSU', 'color' => [200, 120, 0]],
    ['slug' => 'case', 'label' => 'Case', 'color' => [90, 95, 110]],
    ['slug' => 'cpu-cooler', 'label' => 'CPU Cooler', 'color' => [0, 150, 170]],
    ['slug' => 'monitor', 'label' => 'Monitor', 'color' => [120, 60, 180]],
    ['slug' => 'peripherals', 'label' => 'Peripherals', 'color' => [30, 130, 90]],
];

$updateStatement = $pdo->prepare("UPDATE `products` SET `image` = ? WHERE `category_id` = ? AND (`image` IS NULL OR `image` = '')");

foreach ($placeholderCategories as $placeholderCategory) {
    $categorySlug = $placeholderCategory['slug'];
    $categoryId = fetchId($pdo, 'categories', 'slug', $categorySlug);

    if ($categoryId === null) {
        echo "Skip missing category: {$categorySlug}" . PHP_EOL;
        continue;
    }

    $fileName = 'placeholder-' . $categorySlug . '.webp';
    $absolutePath = $uploadAbsoluteDir . $fileName;
    $relativePath = '/' . $uploadRelativeDir . '/' . $fileName;
    $accentColor = $placeholderCategory['color'];

    $created = createPlaceholderImage(
        $absolutePath,
        $placeholderCategory['label'],
        $fontPath,
        $accentColor[0],
        $accentColor[1],
        $accentColor[2]
    );

    if (!$created) {
        echo "Failed to create the image for category: {$categorySlug}" . PHP_EOL;
        continue;
    }

    $updateStatement->execute([$relativePath, $categoryId]);

    echo "Category {$categorySlug}: saved {$relativePath}, updated {$updateStatement->rowCount()} product(s)" . PHP_EOL;
}

echo PHP_EOL . 'Placeholder images generated successfully.' . PHP_EOL;
