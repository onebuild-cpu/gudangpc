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
    $pageTitle = 'Hubungi Kami';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="fw-bold mb-4">Hubungi Kami</h1>
    <p>Punya pertanyaan, masukan, atau ingin bekerja sama dengan kami? Silakan hubungi kami di: <strong>[Masukkan Email Anda di sini]</strong> atau melalui DM Instagram kami di <strong>[@GudangPC]</strong>.</p>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
