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
    $pageTitle = 'Cara Kerja';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="fw-bold mb-4">Cara Kerja</h1>

    <hr>

    <p>Kami menggunakan sistem otomatis untuk memindai harga dari berbagai toko online. Data harga diperbarui secara berkala. Jika Anda menemukan harga yang tidak sesuai, silakan hubungi kami.</p>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
