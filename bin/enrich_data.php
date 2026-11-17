<?php
// Enriches the catalog with search tags so the public search can match on keywords
// (for example "lengkung" matching a "curved" monitor) instead of the product name only.
// Existing short descriptions and descriptions are kept; missing ones are generated.

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
 * Build the keyword list of a product from its category tags and its name.
 */
function buildSearchTags(string $text): string
{
    $normalized = strtolower($text);
    $tokens = [];

    $parts = preg_split('/[^a-z0-9.+-]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($parts as $part) {
        $tokens[] = $part;
    }

    $patterns = [
        '/(\d{2})\s*(?:inch|")/',
        '/(\d+)\s*(?:gb|tb)/',
        '/(\d{4,5})\s*mhz/',
        '/(\d{3})\s*hz/',
        '/(\d{3,4})\s*w(?![a-z])/',
        '/(\d{3})\s*mm/',
        '/(\d+)\s*core/',
        '/(\d+)\s*thread/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $normalized, $matches)) {
            foreach ($matches[0] as $match) {
                $token = preg_replace('/[^a-z0-9]/', '', strtolower($match));
                $tokens[] = $token;
            }
        }
    }

    $unique = [];
    foreach ($tokens as $token) {
        if ($token === '' || isset($unique[$token])) {
            continue;
        }
        $unique[$token] = true;
    }

    return implode(' ', array_keys($unique));
}

/**
 * Build a short description for a product that does not have one yet.
 */
function buildShortDescription(string $name, string $modelNumber, string $categoryLabel): string
{
    $description = $name . ' is a ' . $categoryLabel . ' built for Indonesian PC builds.';

    if ($modelNumber !== '') {
        $description .= ' Model number ' . $modelNumber . '.';
    }

    return $description;
}

/**
 * Build an HTML description for a product that does not have one yet.
 */
function buildDescription(string $shortDescription): string
{
    return '<p>' . $shortDescription . '</p>';
}

$categoryTags = [
    'monitor' => 'monitor layar curved lengkung flat ips oled 27 inch 144hz 165hz',
    'monitors' => 'monitor layar curved lengkung flat ips oled 27 inch 144hz 165hz',
    'cpu' => 'cpu processor amd intel am5 lga1700 8 core 16 thread zen',
    'gpu' => 'gpu graphics nvidia amd rtx radeon gddr6 12gb 24gb',
    'motherboard' => 'motherboard mainboard mobo atx matx itx am5 lga1700 ddr5',
    'case' => 'case casing mid-tower atx micro-atx white black tempered glass',
    'peripherals' => 'peripheral mouse keyboard tetikus wireless wired mechanical rgb',
    'ram' => 'ram memory ddr5 ddr4 16gb 32gb 64gb 6000mhz',
    'ssd' => 'ssd storage nvme sata 1tb 2tb pcie4 pcie5',
    'psu' => 'psu power supply catu daya 650w 750w 850w 1000w gold atx3 80plus',
    'cpu-cooler' => 'cooler pendingin cpu cooler air liquid aio 240mm 360mm rgb',
    'laptops' => 'laptop notebook gaming ultrabook processor',
    'smartphones' => 'smartphone hp phone android processor camera',
];

$categoryLabels = [
    'monitor' => 'monitor',
    'monitors' => 'monitor',
    'cpu' => 'processor',
    'gpu' => 'graphics card',
    'motherboard' => 'motherboard',
    'case' => 'computer case',
    'peripherals' => 'peripheral',
    'ram' => 'memory kit',
    'ssd' => 'solid state drive',
    'psu' => 'power supply',
    'cpu-cooler' => 'CPU cooler',
    'laptops' => 'laptop',
    'smartphones' => 'smartphone',
];

$products = $pdo->query(
    "SELECT p.id, p.name, p.model_number, p.short_description, p.description, c.slug AS category_slug
     FROM `products` p
     LEFT JOIN `categories` c ON c.id = p.category_id
     ORDER BY p.id"
)->fetchAll();

$updateStatement = $pdo->prepare(
    "UPDATE `products` SET `short_description` = ?, `description` = ?, `search_tags` = ? WHERE `id` = ?"
);

$updated = 0;
foreach ($products as $product) {
    $categorySlug = '';
    if (isset($product['category_slug'])) {
        $categorySlug = (string) $product['category_slug'];
    }

    $baseTags = 'produk pc hardware';
    if (isset($categoryTags[$categorySlug])) {
        $baseTags = $categoryTags[$categorySlug];
    }

    $name = '';
    if (isset($product['name'])) {
        $name = (string) $product['name'];
    }

    $modelNumber = '';
    if (isset($product['model_number'])) {
        $modelNumber = (string) $product['model_number'];
    }

    $categoryLabel = 'component';
    if (isset($categoryLabels[$categorySlug])) {
        $categoryLabel = $categoryLabels[$categorySlug];
    }

    $shortDescription = '';
    if (isset($product['short_description'])) {
        $shortDescription = (string) $product['short_description'];
    }
    if ($shortDescription === '') {
        $shortDescription = buildShortDescription($name, $modelNumber, $categoryLabel);
    }

    $description = '';
    if (isset($product['description'])) {
        $description = (string) $product['description'];
    }
    if ($description === '') {
        $description = buildDescription($shortDescription);
    }

    $searchTags = buildSearchTags($baseTags . ' ' . $name . ' ' . $modelNumber);

    $updateStatement->execute([$shortDescription, $description, $searchTags, (int) $product['id']]);
    $updated++;

    echo "Enriched product #{$product['id']} ({$categorySlug}): {$searchTags}" . PHP_EOL;
}

echo PHP_EOL . "Enrichment completed successfully. Updated {$updated} product(s)." . PHP_EOL;
