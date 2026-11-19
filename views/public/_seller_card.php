<?php
$cardSellerName = '';
if (isset($seller['name'])) {
    $cardSellerName = (string) $seller['name'];
}
$cardSellerSlug = '';
if (isset($seller['slug'])) {
    $cardSellerSlug = (string) $seller['slug'];
}
$cardStoreName = '';
if (isset($seller['store_name'])) {
    $cardStoreName = (string) $seller['store_name'];
}
$cardRatingText = '';
if (isset($seller['rating_text'])) {
    $cardRatingText = (string) $seller['rating_text'];
}
$cardProductCount = 0;
if (isset($seller['product_count'])) {
    $cardProductCount = (int) $seller['product_count'];
}
$cardSellerUrl = $base . '/seller/' . $cardSellerSlug;
?>
<div class="col-md-3">
    <div class="product-card h-100 border rounded-3 overflow-hidden p-3 d-flex flex-column">
        <div class="small text-muted mb-2"><i class="bi bi-shop"></i></div>
        <h3 class="product-name small fw-bold mb-1">
            <a href="<?= htmlspecialchars($cardSellerUrl) ?>" class="stretched-link text-decoration-none link-dark"><?= htmlspecialchars($cardSellerName) ?></a>
        </h3>
        <div class="small text-muted"><?= htmlspecialchars($cardStoreName) ?></div>
        <?php if ($cardRatingText !== ''): ?>
            <div class="small mt-2"><i class="bi bi-star-fill text-warning"></i> <?= htmlspecialchars($cardRatingText) ?></div>
        <?php endif; ?>
        <div class="small text-muted mt-2"><?= $cardProductCount ?> produk</div>
    </div>
</div>
