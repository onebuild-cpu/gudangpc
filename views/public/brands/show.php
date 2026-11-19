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
    $pageTitle = 'Merek';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
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

$brandDescriptionHtml = '';
if (isset($brand['description'])) {
    $brandDescriptionHtml = \App\Core\RichText::clean((string) $brand['description']);
}
if ($brandDescriptionHtml === '' && isset($fallbackDescription)) {
    $brandDescriptionHtml = \App\Core\RichText::clean((string) $fallbackDescription);
}
?>
<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>

<div class="py-5">
    <div class="container">
        <?php include __DIR__ . '/../_breadcrumb.php'; ?>
        <?php
        $brandHeroImage = '';
        if (isset($brand['logo'])) {
            $brandHeroImage = (string) $brand['logo'];
        }
        if ($brandHeroImage === '' && isset($fallbackImage)) {
            $brandHeroImage = (string) $fallbackImage;
        }
        $brandHeroAlt = '';
        if (isset($brand['name'])) {
            $brandHeroAlt = (string) $brand['name'];
        }
        ?>

        <div class="row align-items-center mb-5 mt-3">
            <div class="col-md-8">
                <h1 class="fw-bold mb-3"><?= htmlspecialchars((string) $brand['name']) ?></h1>
                <?php if ($brandDescriptionHtml !== ''): ?>
                    <div class="brand-description text-muted lead moved-lead-lg">
                        <?php // $brandDescriptionHtml was cleaned by RichText::clean(); it is the only unescaped output on this page. ?>
                        <?= $brandDescriptionHtml ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($brandHeroImage !== ''): ?>
                <div class="col-md-4 text-center text-md-end">
                    <img src="<?= $base . htmlspecialchars((string) $brandHeroImage) ?>" alt="<?= htmlspecialchars((string) $brandHeroAlt) ?>" class="img-fluid rounded shadow-sm moved-thumb-lg">
                </div>
            <?php endif; ?>
        </div>

        <form method="GET" action="<?= $base ?>/brand/<?= htmlspecialchars((string) $brand['slug']) ?>" class="d-flex align-items-center gap-2 mb-3">
            <label class="form-label mb-0 small text-muted" for="sort">Urutkan</label>
            <select class="form-select form-select-sm w-auto" id="sort" name="sort">
                <option value="newest" <?= $sortNewestSelected ?>>Terbaru</option>
                <option value="price_asc" <?= $sortPriceAscSelected ?>>Harga: rendah ke tinggi</option>
                <option value="price_desc" <?= $sortPriceDescSelected ?>>Harga: tinggi ke rendah</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">Urutkan</button>
        </form>

        <?php if (empty($products)): ?>
            <p class="text-muted">Tidak ada produk dari merek ini.</p>
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
