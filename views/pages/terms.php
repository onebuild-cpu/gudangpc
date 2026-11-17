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
    $pageTitle = 'Syarat & Ketentuan';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="fw-bold mb-4">Syarat &amp; Ketentuan</h1>

    <hr>

    <h2 class="h4 mt-4">Penerimaan Syarat</h2>
    <p>Dengan mengakses GudangPC, Anda menyetujui syarat dan ketentuan ini.</p>

    <h2 class="h4 mt-4">Penggunaan Situs</h2>
    <p>Anda setuju untuk menggunakan situs ini hanya untuk tujuan yang sah dan tidak melanggar hukum.</p>

    <h2 class="h4 mt-4">Akurasi Harga</h2>
    <p>Kami berusaha memberikan harga yang paling akurat. Namun, harga dan ketersediaan stok dapat berubah sewaktu-waktu tanpa pemberitahuan. Harga final adalah harga yang tertera di halaman checkout toko online terkait (Tokopedia, Shopee, dll).</p>

    <h2 class="h4 mt-4">Hak Kekayaan Intelektual</h2>
    <p>Seluruh konten, desain, dan antarmuka di GudangPC adalah milik kami dan dilindungi oleh hukum. Namun, seluruh logo merek (brand) dan toko (store) yang ditampilkan di situs ini adalah milik masing-masing pemiliknya dan digunakan hanya untuk tujuan identifikasi produk.</p>

    <h2 class="h4 mt-4">Batasan Tanggung Jawab</h2>
    <p>GudangPC tidak bertanggung jawab atas kerugian yang timbul dari pembelian yang dilakukan di situs pihak ketiga.</p>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
