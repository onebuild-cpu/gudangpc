<?php
$cardImagePath = '';
if (isset($product['image'])) {
    $cardImagePath = (string) $product['image'];
}
$cardName = '';
if (isset($product['name'])) {
    $cardName = (string) $product['name'];
}
$cardBrandName = '';
if (isset($product['brand_name'])) {
    $cardBrandName = (string) $product['brand_name'];
}
$cardCategorySlug = '';
if (isset($product['category_slug'])) {
    $cardCategorySlug = (string) $product['category_slug'];
}
$cardSlug = '';
if (isset($product['slug'])) {
    $cardSlug = (string) $product['slug'];
}
$cardProductId = 0;
if (isset($product['id'])) {
    $cardProductId = (int) $product['id'];
}
$cardOfferCount = 0;
if (isset($product['offer_count'])) {
    $cardOfferCount = (int) $product['offer_count'];
}
$cardLowestPrice = 0;
if (isset($product['lowest_price'])) {
    $cardLowestPrice = (int) $product['lowest_price'];
}
$cardDimClass = '';
if ($cardOfferCount <= 0) {
    $cardDimClass = 'product-card-dim';
}
?>
<div class="col-md-3">
    <div class="product-card h-100 border rounded-3 overflow-hidden <?= $cardDimClass ?>" style="background: #fff; display: flex; flex-direction: column;">
        <?php
        $productUrl = '';
        if ($cardCategorySlug !== '' && $cardSlug !== '') {
            $productUrl = $base . '/' . $cardCategorySlug . '/' . $cardSlug;
        }
        ?>
        <?php if ($cardImagePath === ''): ?>
            <div class="product-card-placeholder">
                <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
            </div>
        <?php else: ?>
            <?php if ($productUrl !== ''): ?>
                <a href="<?= htmlspecialchars($productUrl) ?>" class="product-card-link" style="width: 100%; height: 100%; display: block;">
                    <img src="<?= $base . htmlspecialchars($cardImagePath) ?>" alt="<?= htmlspecialchars($cardName) ?>" class="product-card-img" loading="lazy">
                </a>
            <?php else: ?>
                <img src="<?= $base . htmlspecialchars($cardImagePath) ?>" alt="<?= htmlspecialchars($cardName) ?>" class="product-card-img" loading="lazy">
            <?php endif; ?>
        <?php endif; ?>

        <div class="p-3 d-flex flex-column flex-grow-1">
            <div class="product-brand small text-muted fw-normal"><?= htmlspecialchars($cardBrandName) ?></div>
            <?php if ($productUrl !== ''): ?>
                <a href="<?= htmlspecialchars($productUrl) ?>" class="product-name-link" style="color: inherit; text-decoration: none;">
                    <h3 class="product-name mt-3 small fw-bold"><?= htmlspecialchars($cardName) ?></h3>
                </a>
            <?php else: ?>
                <h3 class="product-name mt-3 small fw-bold"><?= htmlspecialchars($cardName) ?></h3>
            <?php endif; ?>

            <div class="mt-2">
                <?php if ($cardOfferCount > 0): ?>
                    <div class="fw-bold">Mulai Rp <?= number_format($cardLowestPrice, 0, ',', '.') ?></div>
                    <div class="small text-muted"><?= $cardOfferCount ?> penjual</div>
                <?php else: ?>
                    <div class="small text-muted">Belum ada penawaran aktif</div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 gap-2">
                <?php if ($productUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($productUrl) ?>" class="btn btn-outline-primary flex-grow-1" style="color: inherit; text-decoration: none;">Lihat Detail</a>
                    <a href="<?= htmlspecialchars($productUrl) ?>" class="btn btn-link p-0 text-danger"><i class="bi bi-heart-fill"></i></a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>