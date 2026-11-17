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
 * Insert a row with prepared statements and return its new id.
 */
function insertRow(PDO $pdo, string $table, array $data): int
{
    $columns = array_keys($data);
    $placeholders = array_fill(0, count($columns), '?');
    $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));

    return (int) $pdo->lastInsertId();
}

/**
 * Insert a row only when the lookup value does not exist yet.
 */
function ensureRow(PDO $pdo, string $table, string $lookupColumn, string $lookupValue, array $data): int
{
    $existingId = fetchId($pdo, $table, $lookupColumn, $lookupValue);
    if ($existingId !== null) {
        echo "Skip existing {$table}: {$lookupValue} (id {$existingId})" . PHP_EOL;
        return $existingId;
    }

    $newId = insertRow($pdo, $table, $data);
    echo "Inserted {$table}: {$lookupValue} (id {$newId})" . PHP_EOL;

    return $newId;
}

/**
 * Insert an offer only when the product/vendor pair does not exist yet.
 */
function ensureOffer(PDO $pdo, int $productId, int $vendorId, int $storeId, int $priceIdr, string $affiliateUrl): int
{
    $sql = "SELECT id FROM `offers` WHERE `product_id` = ? AND `vendor_id` = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId, $vendorId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        echo "Skip existing offer: product {$productId}, vendor {$vendorId} (id {$existingId})" . PHP_EOL;
        return (int) $existingId;
    }

    $newId = insertRow($pdo, 'offers', [
        'product_id' => $productId,
        'store_id' => $storeId,
        'vendor_id' => $vendorId,
        'price_idr' => $priceIdr,
        'affiliate_url' => $affiliateUrl,
        'in_stock' => 1,
        'last_checked_at' => date('Y-m-d H:i:s'),
        'status' => 'published',
    ]);
    echo "Inserted offer: product {$productId}, vendor {$vendorId} (id {$newId})" . PHP_EOL;

    return $newId;
}

$passwordAlgorithm = PASSWORD_DEFAULT;
if (defined('PASSWORD_ARGON2ID')) {
    $passwordAlgorithm = PASSWORD_ARGON2ID;
}

$adminPasswordHash = password_hash('password123', $passwordAlgorithm);
$adminId = ensureRow($pdo, 'users', 'phone', '+628111111111', [
    'phone' => '+628111111111',
    'username' => 'seedadmin',
    'password' => $adminPasswordHash,
    'role' => 'admin',
    'is_verified' => 1,
]);

$settingDefaults = [
    'default_og_desc' => 'GudangPC - compare prices across Indonesian marketplaces.',
    'default_twitter_desc' => 'GudangPC - compare prices across Indonesian marketplaces.',
    'default_schema' => '',
    'default_custom_head' => '',
    'default_custom_body' => '',
    'default_custom_footer' => '',
];
foreach ($settingDefaults as $settingKey => $settingValue) {
    ensureRow($pdo, 'settings', 'setting_key', $settingKey, [
        'setting_key' => $settingKey,
        'setting_value' => $settingValue,
    ]);
}

$categoryLaptopsId = ensureRow($pdo, 'categories', 'slug', 'laptops', [
    'name' => 'Laptops',
    'slug' => 'laptops',
    'description' => 'Portable computers for work and play.',
    'sort_order' => 1,
    'status' => 'published',
]);
$categorySmartphonesId = ensureRow($pdo, 'categories', 'slug', 'smartphones', [
    'name' => 'Smartphones',
    'slug' => 'smartphones',
    'description' => 'Mobile phones and flagship devices.',
    'sort_order' => 2,
    'status' => 'published',
]);
$categoryMonitorsId = ensureRow($pdo, 'categories', 'slug', 'monitors', [
    'name' => 'Monitors',
    'slug' => 'monitors',
    'description' => 'Displays for gaming, work, and entertainment.',
    'sort_order' => 3,
    'status' => 'published',
]);

$brandAppleId = ensureRow($pdo, 'brands', 'slug', 'apple', [
    'name' => 'Apple',
    'slug' => 'apple',
    'description' => 'Apple products and accessories.',
    'status' => 'published',
]);
$brandSamsungId = ensureRow($pdo, 'brands', 'slug', 'samsung', [
    'name' => 'Samsung',
    'slug' => 'samsung',
    'description' => 'Samsung electronics and devices.',
    'status' => 'published',
]);
$brandAsusId = ensureRow($pdo, 'brands', 'slug', 'asus', [
    'name' => 'Asus',
    'slug' => 'asus',
    'description' => 'Asus computers and gaming gear.',
    'status' => 'published',
]);

$storeShopeeId = ensureRow($pdo, 'stores', 'slug', 'shopee', [
    'name' => 'Shopee',
    'slug' => 'shopee',
    'description' => 'Shopee online marketplace.',
    'base_url' => 'https://shopee.co.id',
    'status' => 'published',
]);
$storeTokopediaId = ensureRow($pdo, 'stores', 'slug', 'tokopedia', [
    'name' => 'Tokopedia',
    'slug' => 'tokopedia',
    'description' => 'Tokopedia online marketplace.',
    'base_url' => 'https://www.tokopedia.com',
    'status' => 'published',
]);
$storeTravelokaId = ensureRow($pdo, 'stores', 'slug', 'traveloka', [
    'name' => 'Traveloka',
    'slug' => 'traveloka',
    'description' => 'Traveloka online platform.',
    'base_url' => 'https://www.traveloka.com',
    'status' => 'published',
]);

$vendorIboxId = ensureRow($pdo, 'vendors', 'slug', 'ibox-official-store', [
    'store_id' => $storeShopeeId,
    'name' => 'iBox Official Store',
    'slug' => 'ibox-official-store',
    'rating' => 4.90,
    'status' => 'active',
]);
$vendorRandomGadgetId = ensureRow($pdo, 'vendors', 'slug', 'random-gadget-store', [
    'store_id' => $storeShopeeId,
    'name' => 'Random Gadget Store',
    'slug' => 'random-gadget-store',
    'rating' => 4.20,
    'status' => 'active',
]);
$vendorTokopediaOfficialId = ensureRow($pdo, 'vendors', 'slug', 'tokopedia-official', [
    'store_id' => $storeTokopediaId,
    'name' => 'Tokopedia Official',
    'slug' => 'tokopedia-official',
    'rating' => 4.80,
    'status' => 'active',
]);

$productMacbookId = ensureRow($pdo, 'products', 'slug', 'macbook-pro-m3-14', [
    'category_id' => $categoryLaptopsId,
    'brand_id' => $brandAppleId,
    'name' => 'MacBook Pro M3 14-inch',
    'slug' => 'macbook-pro-m3-14',
    'model_number' => 'MRX33',
    'short_description' => '14-inch MacBook Pro with the Apple M3 chip, 8GB unified memory, and 512GB SSD.',
    'description' => '<p>The MacBook Pro M3 14-inch delivers pro-level performance in a portable design.</p>',
    'image' => null,
    'image_alt' => 'MacBook Pro M3 14-inch',
    'status' => 'published',
    'created_by' => $adminId,
    'updated_by' => $adminId,
]);
$productSamsungId = ensureRow($pdo, 'products', 'slug', 'samsung-galaxy-s24-ultra', [
    'category_id' => $categorySmartphonesId,
    'brand_id' => $brandSamsungId,
    'name' => 'Samsung Galaxy S24 Ultra',
    'slug' => 'samsung-galaxy-s24-ultra',
    'model_number' => 'SM-S928B',
    'short_description' => 'Flagship Galaxy S24 Ultra with S Pen, 200MP camera, and Snapdragon 8 Gen 3.',
    'description' => '<p>The Samsung Galaxy S24 Ultra combines a large display, S Pen support, and advanced cameras.</p>',
    'image' => null,
    'image_alt' => 'Samsung Galaxy S24 Ultra',
    'status' => 'published',
    'created_by' => $adminId,
    'updated_by' => $adminId,
]);
$productAsusId = ensureRow($pdo, 'products', 'slug', 'asus-rog-swift-27', [
    'category_id' => $categoryMonitorsId,
    'brand_id' => $brandAsusId,
    'name' => 'Asus ROG Swift 27"',
    'slug' => 'asus-rog-swift-27',
    'model_number' => 'PG27AQDM',
    'short_description' => '27-inch ROG Swift OLED gaming monitor with a 240Hz refresh rate.',
    'description' => '<p>The Asus ROG Swift 27-inch OLED monitor is built for high-refresh gaming.</p>',
    'image' => null,
    'image_alt' => 'Asus ROG Swift 27-inch monitor',
    'status' => 'published',
    'created_by' => $adminId,
    'updated_by' => $adminId,
]);

$seedCategoryRows = [
    ['cpu', 'CPU', 4],
    ['gpu', 'GPU', 5],
    ['motherboard', 'Motherboard', 6],
    ['ram', 'RAM', 7],
    ['ssd', 'SSD', 8],
    ['psu', 'PSU', 9],
    ['case', 'Case', 10],
    ['cpu-cooler', 'CPU Cooler', 11],
    ['monitor', 'Monitor', 12],
    ['peripherals', 'Peripherals', 13],
];
foreach ($seedCategoryRows as $seedCategoryRow) {
    $seedCategorySlug = $seedCategoryRow[0];
    $seedCategoryName = $seedCategoryRow[1];
    $seedCategoryOrder = $seedCategoryRow[2];
    ensureRow($pdo, 'categories', 'slug', $seedCategorySlug, [
        'name' => $seedCategoryName,
        'slug' => $seedCategorySlug,
        'description' => '',
        'parent_id' => null,
        'image' => null,
        'image_alt' => null,
        'sort_order' => $seedCategoryOrder,
        'status' => 'published',
    ]);
}

$seedBrands = [];
$seedBrandRows = [
    ['amd', 'AMD'],
    ['intel', 'Intel'],
    ['nvidia', 'NVIDIA'],
    ['msi', 'MSI'],
    ['gigabyte', 'Gigabyte'],
    ['corsair', 'Corsair'],
    ['kingston', 'Kingston'],
    ['logitech', 'Logitech'],
    ['seasonic', 'Seasonic'],
    ['nzxt', 'NZXT'],
];
foreach ($seedBrandRows as $seedBrandRow) {
    $seedBrandSlug = $seedBrandRow[0];
    $seedBrandName = $seedBrandRow[1];
    $seedBrands[$seedBrandSlug] = ensureRow($pdo, 'brands', 'slug', $seedBrandSlug, [
        'name' => $seedBrandName,
        'slug' => $seedBrandSlug,
        'logo' => null,
        'website_url' => null,
        'description' => '',
        'status' => 'published',
    ]);
}

$seedStores = [];
$seedStoreRows = [
    ['lazada', 'Lazada', 'https://www.lazada.co.id'],
    ['blibli', 'Blibli', 'https://www.blibli.com'],
    ['official', 'Official Store', null],
];
foreach ($seedStoreRows as $seedStoreRow) {
    $seedStoreSlug = $seedStoreRow[0];
    $seedStoreName = $seedStoreRow[1];
    $seedStoreBaseUrl = $seedStoreRow[2];
    $seedStores[$seedStoreSlug] = ensureRow($pdo, 'stores', 'slug', $seedStoreSlug, [
        'name' => $seedStoreName,
        'slug' => $seedStoreSlug,
        'logo' => null,
        'description' => '',
        'base_url' => $seedStoreBaseUrl,
        'status' => 'published',
    ]);
}

$seedVendors = [];
$seedVendorRows = [
    ['techzone-id', 'TechZone ID', 'shopee', 4.80],
    ['enter-komputer', 'Enter Komputer', 'shopee', 4.50],
    ['nanokomputer', 'Nanokomputer', 'tokopedia', 4.70],
    ['gadget-store-id', 'Gadget Store ID', 'tokopedia', 4.30],
    ['lazada-official', 'Lazada Official', 'lazada', 4.60],
    ['digital-store', 'Digital Store', 'lazada', 4.20],
    ['blibli-official', 'Blibli Official', 'blibli', 4.75],
    ['audiostream', 'AudioStream', 'blibli', 4.40],
    ['amd-official', 'AMD Official', 'official', 4.90],
];
foreach ($seedVendorRows as $seedVendorRow) {
    $seedVendorSlug = $seedVendorRow[0];
    $seedVendorName = $seedVendorRow[1];
    $seedVendorStoreSlug = $seedVendorRow[2];
    $seedVendorRating = $seedVendorRow[3];
    $seedVendorStoreId = fetchId($pdo, 'stores', 'slug', $seedVendorStoreSlug);
    if ($seedVendorStoreId === null) {
        throw new Exception('Missing store for vendor: ' . $seedVendorSlug);
    }
    $seedVendorId = ensureRow($pdo, 'vendors', 'slug', $seedVendorSlug, [
        'store_id' => $seedVendorStoreId,
        'name' => $seedVendorName,
        'slug' => $seedVendorSlug,
        'rating' => $seedVendorRating,
        'status' => 'active',
    ]);
    $seedVendors[$seedVendorSlug] = [
        'id' => $seedVendorId,
        'store_id' => $seedVendorStoreId,
    ];
}

$seedProducts = [
    [
        'AMD Ryzen 7 7800X3D',
        'amd-ryzen-7-7800x3d',
        'cpu',
        'amd',
        '100-100000593WOF',
        '8-core 16-thread Zen 4 gaming processor with 3D V-Cache and boost up to 5.0 GHz.',
        '<p>The AMD Ryzen 7 7800X3D leads gaming with eight Zen 4 cores and 3D V-Cache.</p>',
        6500000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'AMD Ryzen 5 7600X',
        'amd-ryzen-5-7600x',
        'cpu',
        'amd',
        '100-100000927WOF',
        '6-core 12-thread Zen 4 desktop processor with boost up to 5.1 GHz.',
        '<p>The AMD Ryzen 5 7600X is an efficient six-core processor for gaming and everyday builds.</p>',
        3400000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'Intel Core i5-14600K',
        'intel-core-i5-14600k',
        'cpu',
        'intel',
        'BX8071514600K',
        '14th generation 14-core desktop processor with 20 threads and 5.3 GHz boost.',
        '<p>The Intel Core i5-14600K balances gaming and productivity with 20 threads on a modern platform.</p>',
        4900000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'Intel Core i9-14900K',
        'intel-core-i9-14900k',
        'cpu',
        'intel',
        'BX8071514900K',
        '24-core flagship desktop processor with 32 threads for heavy workloads.',
        '<p>The Intel Core i9-14900K handles demanding rendering, streaming and gaming workloads.</p>',
        11000000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'AMD Ryzen 9 7950X',
        'amd-ryzen-9-7950x',
        'cpu',
        'amd',
        '100-100000514WOF',
        '16-core 32-thread Zen 4 flagship processor for gaming and content creation.',
        '<p>The AMD Ryzen 9 7950X pairs 16 Zen 4 cores with heavy multithreading performance.</p>',
        9500000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'NVIDIA GeForce RTX 4090',
        'nvidia-geforce-rtx-4090',
        'gpu',
        'nvidia',
        '900-1G136-2530-000',
        '24GB GDDR6X flagship graphics card with ray tracing and DLSS 3 frame generation.',
        '<p>The NVIDIA GeForce RTX 4090 sets the reference point for 4K gaming and ray traced rendering.</p>',
        55000000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'NVIDIA GeForce RTX 4080 Super',
        'nvidia-geforce-rtx-4080-super',
        'gpu',
        'nvidia',
        '900-1G136-2460-000',
        '16GB GDDR6X graphics card built for high refresh 4K gaming.',
        '<p>The NVIDIA GeForce RTX 4080 Super delivers 4K ray tracing performance with 16GB of memory.</p>',
        22000000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'NVIDIA GeForce RTX 4070 Ti Super',
        'nvidia-geforce-rtx-4070-ti-super',
        'gpu',
        'nvidia',
        '900-1G136-2445-000',
        '16GB GDDR6X graphics card with strong 4K ray tracing performance.',
        '<p>The NVIDIA GeForce RTX 4070 Ti Super pairs 16GB GDDR6X with DLSS 3 for smooth 4K frames.</p>',
        16000000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'AMD Radeon RX 7900 XTX',
        'amd-radeon-rx-7900-xtx',
        'gpu',
        'amd',
        '100-100000396WOF',
        '24GB RDNA 3 flagship graphics card for 4K raster and ray traced gaming.',
        '<p>The AMD Radeon RX 7900 XTX brings 24GB of VRAM to high refresh 4K gaming.</p>',
        23000000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'MSI Gaming X Trio RTX 4070',
        'msi-geforce-rtx-4070-gaming-x-trio',
        'gpu',
        'msi',
        'RTX 4070 12G GAMING X TRIO OC',
        '12GB GDDR6X graphics card with the tri-fan Gaming X Trio cooler.',
        '<p>The MSI Gaming X Trio RTX 4070 keeps a 12GB card cool with three axial fans.</p>',
        12500000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'ASUS ROG Strix X670E-E',
        'asus-rog-strix-x670e-e',
        'motherboard',
        'asus',
        'ROG STRIX X670E-E GAMING WIFI',
        'AM5 ATX motherboard with 18+2 power phases and WiFi 6E support.',
        '<p>The ASUS ROG Strix X670E-E is a premium AM5 board with heavy power delivery for top tier Ryzen CPUs.</p>',
        7500000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'MSI MAG B650 Tomahawk WiFi',
        'msi-mag-b650-tomahawk-wifi',
        'motherboard',
        'msi',
        'MAG B650 TOMAHAWK WIFI',
        'AM5 ATX motherboard with DDR5 support, WiFi 6E and a heavy duty VRM.',
        '<p>The MSI MAG B650 Tomahawk WiFi is a well equipped AM5 board for midrange Ryzen builds.</p>',
        4200000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'Gigabyte B760M DS3H',
        'gigabyte-b760m-ds3h',
        'motherboard',
        'gigabyte',
        'B760M DS3H rev. 1.0',
        'Micro ATX B760 motherboard with DDR5 support and 2.5GbE networking.',
        '<p>The Gigabyte B760M DS3H offers a compact mATX board for budget oriented gaming builds.</p>',
        1800000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'ASUS TUF Gaming Z790-Plus',
        'asus-tuf-gaming-z790-plus',
        'motherboard',
        'asus',
        'TUF GAMING Z790-PLUS WIFI',
        'ATX Z790 motherboard with PCIe 5.0 slots for 13th and 14th gen Intel CPUs.',
        '<p>The ASUS TUF Gaming Z790-Plus is a durable ATX board for high end Intel platforms.</p>',
        4600000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'NZXT N7 B650E',
        'nzxt-n7-b650e',
        'motherboard',
        'nzxt',
        'N7 B650E (N7B65E-UK)',
        'ATX AM5 motherboard with a clean white design and an integrated I/O cover.',
        '<p>The NZXT N7 B650E pairs a tidy white layout with full AM5 and PCIe 5.0 support.</p>',
        5200000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'Corsair Vengeance RGB 32GB DDR5-6000',
        'corsair-vengeance-rgb-32gb-ddr5-6000',
        'ram',
        'corsair',
        'CM-0000005-06100',
        '32GB (2x16GB) DDR5-6000 CL30 RGB memory with iCUE lighting.',
        '<p>The Corsair Vengeance RGB 32GB kit runs at DDR5-6000 with tight timings and iCUE lighting.</p>',
        1450000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'Kingston Fury Beast 16GB DDR5-5600',
        'kingston-fury-beast-16gb-ddr5-5600',
        'ram',
        'kingston',
        'KF556C40BBK2-16',
        '16GB (2x8GB) DDR5-5600 CL40 memory for modern gaming builds.',
        '<p>The Kingston Fury Beast 16GB kit is a straightforward DDR5 upgrade for entry level platforms.</p>',
        700000,
        ['techzone-id', 'nanokomputer'],
    ],
    [
        'G.Skill Trident Z5 RGB 64GB DDR5-6400',
        'gskill-trident-z5-rgb-64gb-ddr5-6400',
        'ram',
        'corsair',
        'F5-6400J3239G64GT-RS',
        '64GB (2x32GB) DDR5-6400 CL32 RGB memory for high end workstations.',
        '<p>The G.Skill Trident Z5 RGB 64GB kit gives workstations and creators plenty of headroom.</p>',
        3200000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Corsair Dominator Titanium 32GB DDR5',
        'corsair-dominator-titanium-32gb-ddr5',
        'ram',
        'corsair',
        'CMT32GX5M2B6000C30',
        '32GB (2x16GB) DDR5-6000 premium RGB memory with a polished heat spreader.',
        '<p>The Corsair Dominator Titanium 32GB kit is premium DDR5 with matching iCUE RGB modules.</p>',
        2200000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Kingston Server Premier 16GB DDR4',
        'kingston-server-premier-16gb-ddr4',
        'ram',
        'kingston',
        'KSM26S19/16HD',
        '16GB DDR4 ECC unbuffered server memory for stable workstation builds.',
        '<p>The Kingston Server Premier 16GB module brings ECC reliability to entry servers and workstations.</p>',
        950000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Samsung 990 Pro 2TB',
        'samsung-990-pro-2tb',
        'ssd',
        'samsung',
        'MZ-V9P2T0BW',
        '2TB PCIe 4.0 NVMe M.2 SSD with up to 7450 MB/s sequential read speed.',
        '<p>The Samsung 990 Pro 2TB is a high speed PCIe 4.0 drive for gaming libraries and editing.</p>',
        3200000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Kingston NV2 1TB',
        'kingston-nv2-1tb',
        'ssd',
        'kingston',
        'SNV2S/1000G',
        '1TB PCIe 4.0 NVMe M.2 SSD with fast boot and application load times.',
        '<p>The Kingston NV2 1TB is an affordable NVMe drive for everyday builds and laptops.</p>',
        950000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Corsair MP600 Pro XT 2TB',
        'corsair-mp600-pro-xt-2tb',
        'ssd',
        'corsair',
        'CSSD-F4000GBMP600PXT',
        '2TB PCIe 4.0 M.2 SSD with a heatsink and up to 7000 MB/s sequential reads.',
        '<p>The Corsair MP600 Pro XT 2TB ships with an aluminum heatsink for sustained transfers.</p>',
        2800000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Crucial P3 Plus 1TB',
        'crucial-p3-plus-1tb',
        'ssd',
        'samsung',
        'CT1000P3PSSD8',
        '1TB PCIe 4.0 NVMe M.2 SSD with up to 5000 MB/s read speed.',
        '<p>The Crucial P3 Plus 1TB gives fast load times without breaking the budget.</p>',
        1150000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Samsung 870 EVO 1TB SATA',
        'samsung-870-evo-1tb-sata',
        'ssd',
        'samsung',
        'MZ-77E1T0B-AM',
        '1TB 2.5 inch SATA SSD with up to 560 MB/s read speed.',
        '<p>The Samsung 870 EVO 1TB remains a dependable SATA upgrade for older systems.</p>',
        1050000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Corsair RM850x 850W Gold',
        'corsair-rm850x-850w-gold',
        'psu',
        'corsair',
        'CP-9020261-NA',
        '850W 80 PLUS Gold modular power supply with a fully modular cable set.',
        '<p>The Corsair RM850x 850W Gold delivers quiet, efficient power for high end graphics builds.</p>',
        2400000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Seasonic Focus GX-750 750W Gold',
        'seasonic-focus-gx-750-750w-gold',
        'psu',
        'seasonic',
        'SSR-750FX',
        '750W 80 PLUS Gold ATX 3.0 power supply with hybrid fan control.',
        '<p>The Seasonic Focus GX-750 is a compact and very quiet ATX 3.0 power supply.</p>',
        1850000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'MSI MPG A1000G PCIE5 1000W',
        'msi-mpg-a1000g-pcie5-1000w',
        'psu',
        'msi',
        'MPG A1000G 1000W',
        '1000W 80 PLUS Gold modular power supply with a native 12VHPWR cable.',
        '<p>The MSI MPG A1000G 1000W comfortably powers flagship GPUs with a native 12VHPWR cable.</p>',
        3200000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Gigabyte UD750GM 750W Gold',
        'gigabyte-ud750gm-750w-gold',
        'psu',
        'gigabyte',
        'GP-UD750GM',
        '750W 80 PLUS Gold modular power supply with silent thermal control.',
        '<p>The Gigabyte UD750GM 750W Gold is a quiet modular unit for midrange gaming rigs.</p>',
        1750000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'NZXT C850 Gold 850W',
        'nzxt-c850-gold-850w',
        'psu',
        'nzxt',
        'C850 Gold (C850G)',
        '850W 80 PLUS Gold modular power supply in a clean white design.',
        '<p>The NZXT C850 Gold 850W keeps a tidy white build with fully modular cables.</p>',
        2100000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'NZXT H9 Flow White',
        'nzxt-h9-flow-white',
        'case',
        'nzxt',
        'H9 Flow (2022)',
        'Full tower airflow case with three 140mm RGB fans and tempered glass panels.',
        '<p>The NZXT H9 Flow White keeps cooling focus with three 140mm RGB fans and a mesh front.</p>',
        2300000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Corsair 4000D Airflow Black',
        'corsair-4000d-airflow-black',
        'case',
        'corsair',
        'CC-9011200-WW',
        'Mid tower airflow case with a high perforated front panel for strong cooling.',
        '<p>The Corsair 4000D Airflow is a proven mid tower with excellent airflow and a clean front.</p>',
        1750000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Lian Li O11 Dynamic EVO',
        'lian-li-o11-dynamic-evo',
        'case',
        'corsair',
        'O11 DYNAMIC EVO XL',
        'Dual chamber mid tower case with vertical GPU mounting and three RGB fans.',
        '<p>The Lian Li O11 Dynamic EVO shows off the hardware with a dual chamber glass layout.</p>',
        1900000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'Fractal Design North',
        'fractal-design-north',
        'case',
        'nzxt',
        'North (Mesh)',
        'Mid tower case with a wood front panel and a balanced mesh airflow layout.',
        '<p>The Fractal Design North blends a natural wood front with airflow focused internals.</p>',
        2200000,
        ['lazada-official', 'blibli-official'],
    ],
    [
        'MSI MPG Velox 100P',
        'msi-mpg-velox-100p',
        'case',
        'msi',
        'MPG VELOX 100P AIRFLOW',
        'Mid tower case with a mesh front panel and four ARGB fans included.',
        '<p>The MSI MPG Velox 100P arrives with four ARGB fans and a roomy mid tower layout.</p>',
        1250000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Corsair iCUE H150i Elite LCD',
        'corsair-icue-h150i-elite-lcd',
        'cpu-cooler',
        'corsair',
        'CW-9060058-WW',
        '360mm liquid cooler with a customizable LCD pump head and RGB fans.',
        '<p>The Corsair iCUE H150i Elite LCD pairs a 360mm radiator with a programmable display.</p>',
        1800000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'NZXT Kraken 360 RGB',
        'nzxt-kraken-360-rgb',
        'cpu-cooler',
        'nzxt',
        'RL-KR360-RGB',
        '360mm liquid CPU cooler with three RGB fans and an LCD display.',
        '<p>The NZXT Kraken 360 RGB cools high power CPUs and shows live data on its LCD.</p>',
        1900000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'MSI MAG CORELIQUID 240R V2',
        'msi-mag-coreliquid-240r-v2',
        'cpu-cooler',
        'msi',
        'MAG CORELIQUID 240R V2',
        '240mm liquid cooler with a quiet pump and RGB lighting.',
        '<p>The MSI MAG CORELIQUID 240R V2 is a quiet 240mm liquid cooler for gaming rigs.</p>',
        950000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Deepcool AK620',
        'deepcool-ak620',
        'cpu-cooler',
        'corsair',
        'R-AK620-BKAMNF-G-1',
        'Dual tower air cooler with six heat pipes and dual fans.',
        '<p>The Deepcool AK620 handles hot CPUs with six heat pipes and two quiet fans.</p>',
        650000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'AMD Wraith Prism RGB',
        'amd-wraith-prism-rgb',
        'cpu-cooler',
        'amd',
        'WRAITH PRISM',
        'Stock style air cooler with RGB lighting for AM4 and AM5 sockets.',
        '<p>The AMD Wraith Prism RGB keeps entry builds cool with a lit four heat pipe cooler.</p>',
        450000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'ASUS ROG Swift OLED 27"',
        'asus-rog-swift-oled-27',
        'monitor',
        'asus',
        'PG27AQDM',
        '27-inch 1440p OLED gaming monitor with a 240Hz refresh rate.',
        '<p>The ASUS ROG Swift OLED 27-inch pairs a fast QD-OLED panel with 240Hz refresh.</p>',
        12500000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Samsung Odyssey G7 32"',
        'samsung-odyssey-g7-32',
        'monitor',
        'samsung',
        'LC32G700TUXRXEN',
        '32-inch 1440p curved gaming monitor with a 240Hz refresh rate.',
        '<p>The Samsung Odyssey G7 32-inch wraps a sharp 1440p panel in a curved frame.</p>',
        7500000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'MSI Optix MAG274QRF-QD 27"',
        'msi-optix-mag274qrf-qd-27',
        'monitor',
        'msi',
        'MAG 274QRF QD',
        '27-inch 1440p rapid IPS monitor with 240Hz refresh and HDR support.',
        '<p>The MSI Optix MAG274QRF-QD 27-inch is a quick IPS panel with vivid HDR.</p>',
        6000000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Gigabyte M27Q 27"',
        'gigabyte-m27q-27',
        'monitor',
        'gigabyte',
        'M27Q (rev. 2.0)',
        '27-inch 1440p IPS monitor with 170Hz refresh rate and a KVM switch.',
        '<p>The Gigabyte M27Q 27-inch is a colour accurate IPS display with a built in KVM.</p>',
        5500000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'LG UltraGear 27GP850',
        'lg-ultragear-27gp850',
        'monitor',
        'samsung',
        '27GP850-B',
        '27-inch 1440p Nano IPS gaming monitor with a 180Hz refresh rate.',
        '<p>The LG UltraGear 27GP850 offers fast IPS motion handling at a competitive price.</p>',
        5800000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Logitech G Pro X Superlight',
        'logitech-g-pro-x-superlight',
        'peripherals',
        'logitech',
        'G-PRO-X-SUPERLIGHT',
        '60g wireless gaming mouse with the HERO 2 sensor and 95 hour battery.',
        '<p>The Logitech G Pro X Superlight is an ultra light 60g wireless mouse for esports.</p>',
        1900000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Logitech MX Keys',
        'logitech-mx-keys',
        'peripherals',
        'logitech',
        '920-009237',
        'Low profile wireless keyboard with backlit keys and multi device pairing.',
        '<p>The Logitech MX Keys is a slim wireless keyboard that pairs with three devices.</p>',
        1500000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Razer BlackWidow V4 Pro',
        'razer-blackwidow-v4-pro',
        'peripherals',
        'logitech',
        'RZ03-04640800-R3G1',
        'Full size mechanical gaming keyboard with an adjustable wrist rest and macro column.',
        '<p>The Razer BlackWidow V4 Pro is a full size mechanical board with eight macro keys.</p>',
        2100000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Corsair K100 RGB Keyboard',
        'corsair-k100-rgb-keyboard',
        'peripherals',
        'corsair',
        'CH-9100245-NA',
        'Low profile membrane gaming keyboard with RGB backlighting.',
        '<p>The Corsair K100 RGB is a durable low profile gaming keyboard for everyday play.</p>',
        800000,
        ['gadget-store-id', 'digital-store'],
    ],
    [
        'Logitech G502 X Plus',
        'logitech-g502-x-plus',
        'peripherals',
        'logitech',
        '910-006431',
        '12000 DPI wireless gaming mouse with dual mode connectivity.',
        '<p>The Logitech G502 X Plus is a versatile wireless mouse with a 12000 DPI sensor.</p>',
        1400000,
        ['gadget-store-id', 'digital-store'],
    ],
];

$seedProductIds = [];
foreach ($seedProducts as $seedProduct) {
    $seedProductCategoryId = fetchId($pdo, 'categories', 'slug', $seedProduct[2]);
    if ($seedProductCategoryId === null) {
        throw new Exception('Missing category: ' . $seedProduct[2]);
    }
    $seedProductBrandId = fetchId($pdo, 'brands', 'slug', $seedProduct[3]);
    if ($seedProductBrandId === null) {
        throw new Exception('Missing brand: ' . $seedProduct[3]);
    }
    $seedProductId = ensureRow($pdo, 'products', 'slug', $seedProduct[1], [
        'category_id' => $seedProductCategoryId,
        'brand_id' => $seedProductBrandId,
        'name' => $seedProduct[0],
        'slug' => $seedProduct[1],
        'model_number' => $seedProduct[4],
        'short_description' => $seedProduct[5],
        'description' => $seedProduct[6],
        'image' => null,
        'image_alt' => $seedProduct[0],
        'status' => 'published',
        'created_by' => $adminId,
        'updated_by' => $adminId,
    ]);
    $seedProductIds[$seedProduct[1]] = $seedProductId;
}

foreach ($seedProducts as $seedProductIndex => $seedProduct) {
    $seedProductId = $seedProductIds[$seedProduct[1]];
    $seedVendorSlugs = $seedProduct[8];
    $seedPriceFactor = 1.04;
    if ($seedProductIndex % 2 === 1) {
        $seedPriceFactor = 0.97;
    }
    $seedFirstPrice = (int) $seedProduct[7];
    $seedSecondPrice = (int) round($seedProduct[7] * $seedPriceFactor);
    $seedOfferIndex = 0;
    foreach ($seedVendorSlugs as $seedVendorSlug) {
        if (!isset($seedVendors[$seedVendorSlug])) {
            throw new Exception('Missing vendor: ' . $seedVendorSlug);
        }
        $seedVendor = $seedVendors[$seedVendorSlug];
        $seedOfferPrice = $seedFirstPrice;
        if ($seedOfferIndex === 1) {
            $seedOfferPrice = $seedSecondPrice;
        }
        ensureOffer($pdo, $seedProductId, $seedVendor['id'], $seedVendor['store_id'], $seedOfferPrice, 'https://example.com/affiliate/' . $seedProduct[1] . '-' . $seedVendorSlug);
        $seedOfferIndex++;
    }
}

$seedImageSlugs = ['macbook-pro-m3-14', 'samsung-galaxy-s24-ultra', 'asus-rog-swift-27'];
$clearImageStatement = $pdo->prepare("UPDATE `products` SET `image` = NULL WHERE `slug` = ?");
foreach ($seedImageSlugs as $seedImageSlug) {
    $clearImageStatement->execute([$seedImageSlug]);
}
echo 'Cleared seeded product images for fallback testing.' . PHP_EOL;

ensureOffer($pdo, $productMacbookId, $vendorIboxId, $storeShopeeId, 24500000, 'https://example.com/affiliate/macbook-ibox');
ensureOffer($pdo, $productMacbookId, $vendorRandomGadgetId, $storeShopeeId, 23900000, 'https://example.com/affiliate/macbook-random-gadget');
ensureOffer($pdo, $productMacbookId, $vendorTokopediaOfficialId, $storeTokopediaId, 24100000, 'https://example.com/affiliate/macbook-tokopedia');
ensureOffer($pdo, $productSamsungId, $vendorIboxId, $storeShopeeId, 19500000, 'https://example.com/affiliate/samsung-ibox');
ensureOffer($pdo, $productAsusId, $vendorTokopediaOfficialId, $storeTokopediaId, 7800000, 'https://example.com/affiliate/asus-tokopedia');

echo PHP_EOL . 'Seed completed successfully.' . PHP_EOL;
echo 'Admin login: +628111111111 / password123' . PHP_EOL;
echo 'Check the homepage at / to see the seeded products.' . PHP_EOL;
