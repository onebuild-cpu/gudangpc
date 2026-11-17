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
    $pageTitle = 'Tentang Kami';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="fw-bold mb-4">Tentang Kami</h1>
    <p>GudangPC didirikan dengan satu misi: membuat proses perakitan PC di Indonesia menjadi lebih mudah dan terjangkau. Kami menyadari bahwa komponen PC bisa memiliki selisih harga yang cukup jauh antar marketplace. Oleh karena itu, kami mengumpulkan dan membandingkan harga dari Tokopedia, Shopee, dan Lazada dalam satu platform yang bersih dan mudah dibaca.</p>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
