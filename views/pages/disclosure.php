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
    $pageTitle = 'Pengungkapan Afiliasi';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="fw-bold mb-4">Pengungkapan Afiliasi</h1>

    <hr>

    <p>GudangPC adalah situs perbandingan harga yang berpartisipasi dalam program afiliasi dari berbagai marketplace (seperti Tokopedia, Shopee, Lazada, dll).</p>

    <h2 class="h4 mt-4">Artinya</h2>
    <p>Jika Anda mengklik link produk di situs kami dan melakukan pembelian, kami mungkin menerima komisi dari penjual atau platform tersebut. Komisi ini <strong>tidak menambah biaya apapun</strong> yang harus Anda bayarkan.</p>

    <h2 class="h4 mt-4">Integritas</h2>
    <p>Komisi ini membantu kami membiayai operasional situs dan menjaga agar layanan perbandingan harga ini tetap gratis untuk Anda. Kami berkomitmen untuk menampilkan harga dan perbandingan secara jujur dan objektif.</p>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
