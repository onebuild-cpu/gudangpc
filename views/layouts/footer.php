    </main>

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

    <footer class="site-footer bg-dark text-light pt-5 pb-3 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5 class="fw-bold mb-3"><span class="text-white">Gudang</span><span class="text-danger">PC</span></h5>
                    <p class="small">GudangPC adalah situs perbandingan harga PC dan gadget terlengkap di Indonesia. Temukan harga terbaik dari Tokopedia, Shopee, dan Lazada.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-light" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-light" aria-label="TikTok"><i class="bi bi-tiktok"></i></a>
                        <a href="#" class="text-light" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Kategori Populer</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/gpu">GPU</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/cpu">CPU</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/motherboard">Motherboard</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/ram">RAM</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/monitor">Monitor</a></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Informasi</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/about">Tentang Kami</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/how-it-works">Cara Kerja</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/contact">Hubungi Kami</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/blog">Blog</a></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Legal &amp; Pengungkapan</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/privacy">Kebijakan Privasi</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/terms">Syarat &amp; Ketentuan</a></li>
                        <li class="mb-2"><a class="text-light text-decoration-none" href="<?= $base ?>/disclosure">Pengungkapan Afiliasi</a></li>
                    </ul>
                </div>
            </div>

            <hr class="border-secondary">

            <div class="text-center small">
                <p class="mb-1">&copy; <?= date('Y') ?> GudangPC. Hak cipta dilindungi.</p>
                <p class="text-white-50 mb-0">GudangPC mungkin menerima komisi dari pembelian yang dilakukan melalui link di situs ini. Harga dan ketersediaan produk dapat berubah sewaktu-waktu.</p>
            </div>
        </div>
    </footer>

    <script src="<?= $base ?>/assets/js/jquery.min.js?v=1.0.1" nonce="<?= $nonce ?>"></script>
    <script src="<?= $base ?>/assets/js/bootstrap.bundle.min.js?v=1.0.1" nonce="<?= $nonce ?>"></script>
    <?php if (!isset($loadSummernote)) { $loadSummernote = false; } ?>
    <?php if ($loadSummernote): ?>
    <script src="<?= $base ?>/assets/js/summernote-bs5.min.js?v=1.0.1" nonce="<?= $nonce ?>"></script>
    <?php endif; ?>
    <?php if (isset($seo) && is_array($seo)): ?>
        <?php if (isset($seo['custom_footer']) && $seo['custom_footer'] !== ''): ?>
        <?php /* Admin-authored footer snippet from seo_meta, printed raw on purpose. */ ?>
        <?= $seo['custom_footer'] ?>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
