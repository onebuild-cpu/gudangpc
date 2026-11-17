<?php
$base = '';
if (isset($GLOBALS['base'])) {
    $base = $GLOBALS['base'];
}
$nonce = '';
if (isset($GLOBALS['csp_nonce'])) {
    $nonce = $GLOBALS['csp_nonce'];
}
// Set a default page title only if the controller did not provide one
if (!isset($pageTitle)) {
    $pageTitle = 'GudangPC';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
     $metaDescription = 'Perbandingan harga GudangPC';
}

$phoneValue = '';
if (isset($_POST['phone'])) {
    $phoneValue = $_POST['phone'];
}
?>
<?php $bodyClass = 'bg-light'; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="text-center mb-4">Masuk</h2>
                        <form method="POST" action="<?= $base ?>/login/submit" novalidate>
                            <?= \App\Core\CsrfProtection::getHiddenInput() ?>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Email atau Telepon</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($phoneValue) ?>" 
                                       placeholder="Email atau +628123456789" required autocomplete="username">
                                <div class="form-text">Gunakan email (anggota) atau nomor telepon dengan kode negara (misalnya +628123456789).</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Kata Sandi</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       required autocomplete="current-password">
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Ingat saya</label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Masuk</button>
                            </div>
                        </form>
                        <div class="mt-3 text-center">
                            <span class="me-2" style="color: #4285F4;">G</span>
                            <a href="/auth/google/redirect" class="btn btn-danger">Masuk dengan Google</a>
                            <a href="/auth/facebook/redirect" class="btn btn-primary d-none">Masuk dengan Facebook</a>
                        </div>
                        <p class="text-center mt-3">
                            Belum punya akun? <a href="<?= $base ?>/register">Daftar</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>