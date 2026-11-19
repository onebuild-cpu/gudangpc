<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'bin/smoke.php is a CLI-only script.' . PHP_EOL);
    exit(1);
}

require_once __DIR__ . '/../app/Core/Autoloader.php';
App\Core\Autoloader::register();
App\Core\Autoloader::addNamespace('App', __DIR__ . '/../app');

$pdo = require __DIR__ . '/../config/database.php';

/**
 * Fetch one row with a prepared, read-only SELECT.
 */
function fetchOne(PDO $pdo, string $sql, array $params): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return null;
    }

    return $row;
}

/**
 * Perform one GET request and collect response headers.
 */
function fetchUrl(string $url, array &$headers): array
{
    $headers = [];
    $ch = curl_init($url);
    if ($ch === false) {
        return [0, '', 'cURL init failed'];
    }

    $headerCallback = function ($curlHandle, string $line) use (&$headers): int {
        $length = strlen($line);
        $colon = strpos($line, ':');
        if ($colon !== false) {
            $name = strtolower(trim(substr($line, 0, $colon)));
            $headers[$name] = trim(substr($line, $colon + 1));
        }
        return $length;
    };

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'GudangPC-smoke',
        CURLOPT_COOKIE => '',
        CURLOPT_COOKIEFILE => '',
        CURLOPT_COOKIEJAR => '',
        CURLOPT_HEADERFUNCTION => $headerCallback,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [$status, '', $error];
    }

    return [$status, (string) $body, ''];
}

/**
 * Print one PASS or FAIL line and update the counters.
 */
function recordCheck(bool $ok, string $url, string $actual, string $expected, array &$counts): void
{
    $label = 'FAIL';
    if ($ok) {
        $label = 'PASS';
        $counts['pass']++;
    } else {
        $counts['fail']++;
    }
    echo $label . ' ' . $url . ' | ' . $actual . ' | expected: ' . $expected . PHP_EOL;
}

/**
 * Print one SKIP line and update the counters.
 */
function recordSkip(string $url, string $reason, array &$counts): void
{
    $counts['skip']++;
    echo 'SKIP ' . $url . ' | ' . $reason . PHP_EOL;
}

$baseUrl = '';
if (isset($argv[1])) {
    $baseUrl = $argv[1];
}
if ($baseUrl === '') {
    echo 'Usage: php bin/smoke.php <base-url>  (example: php bin/smoke.php http://gudangpc.test)' . PHP_EOL;
    exit(1);
}
$baseUrl = rtrim($baseUrl, '/');
if (strpos($baseUrl, 'http://') !== 0 && strpos($baseUrl, 'https://') !== 0) {
    echo 'Base URL must start with http:// or https://' . PHP_EOL;
    exit(1);
}

$counts = [
    'pass' => 0,
    'fail' => 0,
    'skip' => 0,
];

$categoryRow = fetchOne($pdo, 'SELECT slug FROM categories WHERE status = ? ORDER BY id ASC LIMIT 1', ['published']);
$brandRow = fetchOne($pdo, 'SELECT slug FROM brands WHERE status = ? ORDER BY id ASC LIMIT 1', ['published']);
$vendorRow = fetchOne($pdo, 'SELECT slug FROM vendors WHERE status = ? ORDER BY id ASC LIMIT 1', ['active']);
$productRow = fetchOne(
    $pdo,
    'SELECT p.slug AS product_slug, c.slug AS category_slug
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE p.status = ? AND c.status = ?
     ORDER BY p.id ASC
     LIMIT 1',
    ['published', 'published']
);

$fetched = [];
$paths200 = ['/', '/categories', '/brands', '/locations', '/login', '/register'];
foreach ($paths200 as $path) {
    $headers = [];
    $result = fetchUrl($baseUrl . $path, $headers);
    $fetched[$path] = [
        'status' => $result[0],
        'body' => $result[1],
        'headers' => $headers,
    ];
    recordCheck($result[0] === 200, $path, 'status=' . $result[0], '200', $counts);
}

$dynamicPaths = [];
if ($categoryRow === null) {
    recordSkip('/<category-slug>', 'no published category slug found', $counts);
} else {
    $path = '/' . $categoryRow['slug'];
    $dynamicPaths[] = $path;
    $headers = [];
    $result = fetchUrl($baseUrl . $path, $headers);
    $fetched[$path] = ['status' => $result[0], 'body' => $result[1], 'headers' => $headers];
    recordCheck($result[0] === 200, $path, 'status=' . $result[0], '200', $counts);
}

$brandPath = '';
if ($brandRow === null) {
    recordSkip('/brand/<slug>', 'no published brand slug found', $counts);
} else {
    $brandPath = '/brand/' . $brandRow['slug'];
    $dynamicPaths[] = $brandPath;
    $headers = [];
    $result = fetchUrl($baseUrl . $brandPath, $headers);
    $fetched[$brandPath] = ['status' => $result[0], 'body' => $result[1], 'headers' => $headers];
    recordCheck($result[0] === 200, $brandPath, 'status=' . $result[0], '200', $counts);
}

if ($vendorRow === null) {
    recordSkip('/seller/<slug>', 'no active vendor slug found', $counts);
} else {
    $path = '/seller/' . $vendorRow['slug'];
    $dynamicPaths[] = $path;
    $headers = [];
    $result = fetchUrl($baseUrl . $path, $headers);
    $fetched[$path] = ['status' => $result[0], 'body' => $result[1], 'headers' => $headers];
    recordCheck($result[0] === 200, $path, 'status=' . $result[0], '200', $counts);
}

$productPath = '';
if ($productRow === null) {
    recordSkip('/<category-slug>/<product-slug>', 'no published product with a published category found', $counts);
} else {
    $productPath = '/' . $productRow['category_slug'] . '/' . $productRow['product_slug'];
    $dynamicPaths[] = $productPath;
    $headers = [];
    $result = fetchUrl($baseUrl . $productPath, $headers);
    $fetched[$productPath] = ['status' => $result[0], 'body' => $result[1], 'headers' => $headers];
    recordCheck($result[0] === 200, $productPath, 'status=' . $result[0], '200', $counts);
}

$headers = [];
$result = fetchUrl($baseUrl . '/sitemap.xml', $headers);
$fetched['/sitemap.xml'] = ['status' => $result[0], 'body' => $result[1], 'headers' => $headers];
recordCheck($result[0] === 200, '/sitemap.xml', 'status=' . $result[0], '200', $counts);

$notFoundPaths = ['/seller/zzz-not-found', '/brand/zzz-not-found'];
foreach ($notFoundPaths as $path) {
    $headers = [];
    $result = fetchUrl($baseUrl . $path, $headers);
    recordCheck($result[0] === 404, $path, 'status=' . $result[0], '404', $counts);
}

$headers = [];
$result = fetchUrl($baseUrl . '/dashboard', $headers);
$locationValue = '';
if (isset($headers['location'])) {
    $locationValue = $headers['location'];
}
$dashboardOk = false;
if (($result[0] === 302 || $result[0] === 303) && strpos($locationValue, '/login') !== false) {
    $dashboardOk = true;
}
recordCheck($dashboardOk, '/dashboard', 'status=' . $result[0] . ' location=' . $locationValue, '302 or 303 with Location containing /login', $counts);

$forbiddenPaths = [
    '/.env',
    '/.env-example',
    '/config/database.php',
    '/AGENTS.md',
    '/.git/HEAD',
    '/storage/logs/',
    '/database/schema.sql',
    '/docs/PRODUCT_SPEC.md',
];
foreach ($forbiddenPaths as $path) {
    $headers = [];
    $result = fetchUrl($baseUrl . $path, $headers);
    recordCheck($result[0] !== 200, $path, 'status=' . $result[0], 'not 200', $counts);
}

$homeHeaders = [];
if (isset($fetched['/'])) {
    $homeHeaders = $fetched['/']['headers'];
}
$requiredHeaders = ['x-frame-options', 'x-content-type-options', 'content-security-policy'];
foreach ($requiredHeaders as $headerName) {
    $present = false;
    if (isset($homeHeaders[$headerName]) && trim($homeHeaders[$headerName]) !== '') {
        $present = true;
    }
    recordCheck($present, '/', 'header ' . $headerName, 'present', $counts);
}

$cspValue = '';
if (isset($homeHeaders['content-security-policy'])) {
    $cspValue = $homeHeaders['content-security-policy'];
}
$cspOk = false;
if (strpos($cspValue, 'report-uri') === false) {
    $cspOk = true;
}
recordCheck($cspOk, '/', 'CSP has report-uri: ' . $cspValue, 'CSP without report-uri', $counts);

$bodyPaths = ['/'];
if ($brandPath !== '') {
    $bodyPaths[] = $brandPath;
}
if ($productPath !== '') {
    $bodyPaths[] = $productPath;
}
foreach ($bodyPaths as $path) {
    $body = '';
    if (isset($fetched[$path])) {
        $body = $fetched[$path]['body'];
    }
    $langOk = false;
    if (strpos($body, '<html lang="id"') !== false) {
        $langOk = true;
    }
    recordCheck($langOk, $path, 'body', 'contains <html lang="id"', $counts);

    $englishOk = false;
    if (strpos($body, 'Compare Prices') === false) {
        $englishOk = true;
    }
    recordCheck($englishOk, $path, 'body', 'does not contain Compare Prices', $counts);
}

echo 'Summary: PASS=' . $counts['pass'] . ' FAIL=' . $counts['fail'] . ' SKIP=' . $counts['skip'] . PHP_EOL;
if ($counts['fail'] === 0) {
    exit(0);
}
exit(1);
