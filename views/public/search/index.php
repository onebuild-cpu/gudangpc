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
    $pageTitle = 'Cari';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}
if (!isset($query)) {
    $query = '';
}
if (!isset($products)) {
    $products = [];
}

$sortValue = 'newest';
if (isset($sort)) {
    $sortValue = (string) $sort;
}
$sortNewestSelected = '';
$sortPriceAscSelected = '';
$sortPriceDescSelected = '';
if ($sortValue === 'price_asc') {
    $sortPriceAscSelected = 'selected';
} elseif ($sortValue === 'price_desc') {
    $sortPriceDescSelected = 'selected';
} else {
    $sortNewestSelected = 'selected';
}

$totalValue = 0;
if (isset($total)) {
    $totalValue = (int) $total;
}
$resultLabel = 'hasil';
?>
<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>

<div class="py-5">
    <div class="container">
        <?php if ($query === ''): ?>
            <h1 class="fw-bold mb-4">Cari</h1>
            <p class="text-muted">Masukkan kata kunci untuk mencari produk.</p>
            <a class="btn btn-primary" href="<?= $base ?>/categories">Jelajahi semua kategori</a>
        <?php else: ?>
            <h1 class="fw-bold mb-2">Hasil pencarian untuk: <?= htmlspecialchars((string) $query) ?></h1>
            <p class="text-muted mb-4"><?= $totalValue ?> <?= $resultLabel ?></p>

            <form method="GET" action="<?= $base ?>/search" class="d-flex align-items-center gap-2 mb-3">
                <input type="hidden" name="q" value="<?= htmlspecialchars((string) $query) ?>">
                <label class="form-label mb-0 small text-muted" for="sort">Urutkan</label>
                <select class="form-select form-select-sm w-auto" id="sort" name="sort">
                    <option value="newest" <?= $sortNewestSelected ?>>Terbaru</option>
                    <option value="price_asc" <?= $sortPriceAscSelected ?>>Harga: rendah ke tinggi</option>
                    <option value="price_desc" <?= $sortPriceDescSelected ?>>Harga: tinggi ke rendah</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Urutkan</button>
            </form>

            <?php if (empty($products)): ?>
                <p class="text-muted mb-2">Tidak ada hasil untuk "<?= htmlspecialchars((string) $query) ?>".</p>
                <p class="text-muted">Coba kata kunci lain, periksa ejaan, atau jelajahi berdasarkan kategori.</p>
                <a class="btn btn-outline-primary btn-sm" href="<?= $base ?>/categories">Jelajahi semua kategori</a>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $product): ?>
                    <?php include __DIR__ . '/../_product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?php include __DIR__ . '/../_pagination.php'; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
