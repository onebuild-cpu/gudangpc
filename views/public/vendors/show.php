<?php
$base = '';
if (isset($GLOBALS['base'])) {
    $base = $GLOBALS['base'];
}
$nonce = '';
if (isset($GLOBALS['csp_nonce'])) {
    $nonce = $GLOBALS['csp_nonce'];
}

if (!isset($pageTitle)) {
    $pageTitle = 'Penjual';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}

$sellerNameValue = '';
if (isset($vendor['name'])) {
    $sellerNameValue = (string) $vendor['name'];
}

$storeNameValue = '';
if (isset($vendor['store_name'])) {
    $storeNameValue = (string) $vendor['store_name'];
}

$sellerRatingValue = '';
if (isset($vendor['store_rating']) && $vendor['store_rating'] !== null && $vendor['store_rating'] !== '') {
    $sellerRatingValue = number_format((float) $vendor['store_rating'], 2, '.', '');
} elseif (isset($vendor['rating']) && $vendor['rating'] !== null && $vendor['rating'] !== '') {
    $sellerRatingValue = number_format((float) $vendor['rating'], 2, '.', '');
}

$sellerLocationValue = '';
if (isset($vendor['location_name']) && $vendor['location_name'] !== null) {
    $sellerLocationValue = (string) $vendor['location_name'];
}

$sellerDescriptionValue = '';
if (isset($vendor['description']) && $vendor['description'] !== null) {
    $sellerDescriptionValue = (string) $vendor['description'];
}

$sellerDescriptionHtml = '';
if (trim($sellerDescriptionValue) !== '') {
    $sellerDescriptionHtml = nl2br(htmlspecialchars($sellerDescriptionValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}
?>
<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>

<div class="py-5">
    <div class="container">
        <h1 class="fw-bold mb-3"><?= htmlspecialchars($sellerNameValue) ?></h1>

        <div class="mb-2 text-muted">
            <?= htmlspecialchars($storeNameValue) ?>
            <?php if ($sellerRatingValue !== ''): ?>
                <span class="ms-2"><i class="bi bi-star-fill text-warning"></i> <?= htmlspecialchars($sellerRatingValue) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($sellerLocationValue !== ''): ?>
            <div class="text-muted small mb-3"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($sellerLocationValue) ?></div>
        <?php endif; ?>

        <?php if ($sellerDescriptionHtml !== ''): ?>
            <div class="mb-4"><?= $sellerDescriptionHtml ?></div>
        <?php endif; ?>

        <h2 class="h5 mb-3">Produk dari <?= htmlspecialchars($sellerNameValue) ?></h2>
        <?php if (empty($products)): ?>
            <p class="text-muted">Belum ada produk dari penjual ini.</p>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                <?php include __DIR__ . '/../_product_card.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php include __DIR__ . '/../_pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
