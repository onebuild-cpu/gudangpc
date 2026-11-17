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
    $pageTitle = 'Kebijakan Privasi';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="fw-bold mb-4">Kebijakan Privasi</h1>

    <hr>

    <h2 class="h4 mt-4">Pendahuluan</h2>
    <p>GudangPC menghormati privasi Anda. Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi informasi Anda saat Anda menggunakan situs kami.</p>

    <h2 class="h4 mt-4">Informasi yang Kami Kumpulkan</h2>
    <p>Kami dapat mengumpulkan informasi non-pribadi seperti jenis browser, halaman yang dikunjungi, dan waktu kunjungan. Jika Anda mendaftar akun, kami mengumpulkan alamat email dan nama Anda.</p>

    <h2 class="h4 mt-4">Penggunaan Cookie</h2>
    <p>Kami menggunakan cookie untuk memahami preferensi Anda dan menampilkan iklan yang relevan (melalui pihak ketiga seperti Google AdSense/Analytics).</p>

    <h2 class="h4 mt-4">Link Afiliasi</h2>
    <p>Situs kami mengandung link afiliasi. Jika Anda mengklik link tersebut, pihak ketiga (seperti Tokopedia, Shopee, Lazada) mungkin akan melacak kunjungan Anda sesuai dengan kebijakan privasi mereka sendiri.</p>

    <h2 class="h4 mt-4">Keamanan Data</h2>
    <p>Kami menggunakan langkah-langkah keamanan standar industri untuk melindungi data Anda, namun tidak ada metode transmisi internet yang 100% aman.</p>

    <h2 class="h4 mt-4">Hubungi Kami</h2>
    <p>Jika ada pertanyaan, hubungi kami di [Email Anda].</p>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
