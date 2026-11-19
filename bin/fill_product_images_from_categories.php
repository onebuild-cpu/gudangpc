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

$categoriesStatement = $pdo->query("SELECT id, slug, image FROM categories WHERE image IS NOT NULL AND image <> '' ORDER BY id");
$categories = $categoriesStatement->fetchAll(PDO::FETCH_ASSOC);

if (count($categories) === 0) {
    echo 'No categories with an image found.' . PHP_EOL;
    exit(0);
}

$updateStatement = $pdo->prepare("UPDATE products SET image = ? WHERE category_id = ? AND (image IS NULL OR image = '')");
$totalUpdated = 0;

foreach ($categories as $category) {
    $updateStatement->execute([$category['image'], (int) $category['id']]);
    $updated = $updateStatement->rowCount();
    $totalUpdated += $updated;

    echo 'Category ' . $category['slug'] . ': updated ' . $updated . ' product(s) with ' . $category['image'] . PHP_EOL;
}

echo PHP_EOL . 'Total products updated: ' . $totalUpdated . PHP_EOL;
