<?php
/**
 * CSV importer for products and offers.
 *
 * Usage:
 *   php bin/import_products.php <path-to-csv>
 *   php bin/import_products.php <path-to-csv> --dry-run
 *
 * The import logic lives in app/Core/ProductImporter.php and is shared with
 * the dashboard upload page. Categories, brands, stores and vendors are never
 * created here. A row that cannot be validated is rejected, nothing is
 * written for it, and the reason is printed in the final report.
 */

require_once __DIR__ . '/../app/Core/Autoloader.php';
App\Core\Autoloader::register();
App\Core\Autoloader::addNamespace('App', __DIR__ . '/../app');

App\Core\Dotenv::load();

$csvPath = '';
$dryRun = false;

foreach ($argv as $argumentIndex => $argument) {
    if ($argumentIndex === 0) {
        continue;
    }
    if ($argument === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if ($csvPath === '') {
        $csvPath = $argument;
    }
}

if ($csvPath === '') {
    fwrite(STDERR, 'Usage: php bin/import_products.php <path-to-csv> [--dry-run]' . PHP_EOL);
    exit(1);
}

if (!is_file($csvPath) || !is_readable($csvPath)) {
    fwrite(STDERR, 'CSV file cannot be read: ' . $csvPath . PHP_EOL);
    exit(1);
}

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

try {
    $importer = new App\Core\ProductImporter($pdo, $dryRun);
    $result = $importer->importFile($csvPath);
} catch (PDOException $e) {
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Total rows read: ' . $result['total_rows'] . PHP_EOL;
echo 'Created products: ' . $result['created_products'] . PHP_EOL;
echo 'Updated offers: ' . $result['updated_offers'] . PHP_EOL;
echo 'Created offers: ' . $result['created_offers'] . PHP_EOL;
echo 'Skipped rows: ' . ($result['skipped_identical_offers'] + $result['skipped_existing_products']) . PHP_EOL;
echo '  - identical offer: ' . $result['skipped_identical_offers'] . PHP_EOL;
echo '  - product already exists: ' . $result['skipped_existing_products'] . PHP_EOL;
echo 'Rejected rows: ' . count($result['rejections']) . PHP_EOL;

foreach ($result['rejections'] as $rejection) {
    $slugText = '(no slug)';
    if ($rejection['slug'] !== '') {
        $slugText = $rejection['slug'];
    }
    echo 'row ' . $rejection['row'] . ': ' . $rejection['reason'] . ' (' . $slugText . ')' . PHP_EOL;
}

if ($dryRun) {
    echo 'Dry run completed (no changes written).' . PHP_EOL;
} else {
    echo 'Import completed.' . PHP_EOL;
}

exit(0);
