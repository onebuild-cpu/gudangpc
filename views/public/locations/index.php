<?php
$base = '';
if (isset($GLOBALS['base'])) {
    $base = $GLOBALS['base'];
}
$nonce = '';
if (isset($GLOBALS['csp_nonce'])) {
    $nonce = $GLOBALS['csp_nonce'];
}
?>
<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4 fw-bold" style="font-family: 'Poppins', sans-serif;">Lokasi Toko</h1>
    <?php if (empty($locationSections)): ?>
        <p class="text-muted">Belum ada toko yang terdaftar di lokasi mana pun.</p>
    <?php else: ?>
        <?php foreach ($locationSections as $locationSection): ?>
            <section class="mb-5" id="<?= htmlspecialchars((string) $locationSection['slug']) ?>">
                <h2 class="h5 mb-3 fw-bold"><?= htmlspecialchars((string) $locationSection['name']) ?> (<?= (int) $locationSection['seller_count'] ?> Toko)</h2>
                <div class="row g-4">
                    <?php foreach ($locationSection['sellers'] as $seller): ?>
                    <?php include __DIR__ . '/../_seller_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
