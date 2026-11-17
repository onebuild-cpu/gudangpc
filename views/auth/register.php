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
    $pageTitle = 'Daftar';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Buat akun anggota';
}

$emailValue = '';
if (isset($_POST['email'])) {
    $emailValue = $_POST['email'];
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
                        <h2 class="text-center mb-4">Daftar</h2>
                        <form method="POST" action="<?= $base ?>/register/submit" novalidate>
                            <?= \App\Core\CsrfProtection::getHiddenInput() ?>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($emailValue) ?>"
                                       placeholder="you@example.com" required autocomplete="email">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Kata Sandi</label>
                                <input type="password" class="form-control" id="password" name="password"
                                       required minlength="8" autocomplete="new-password">
                                <div class="form-text">Minimal 8 karakter.</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Konfirmasi Kata Sandi</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                       required minlength="8" autocomplete="new-password">
                            </div>

                            <div class="mb-3">
                                <label for="whatsapp" class="form-label">Nomor WhatsApp</label>
                                <input type="tel" class="form-control" id="whatsapp" name="whatsapp"
                                       placeholder="+628..." required pattern="^[+0].*$">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Buat Akun</button>
                            </div>
                        </form>
                        <div class="mt-3 text-center">
                            <span class="me-2" style="color: #4285F4;">G</span>
                            <a href="/auth/google/redirect" class="btn btn-danger">Masuk dengan Google</a>
                            <a href="/auth/facebook/redirect" class="btn btn-primary d-none">Masuk dengan Facebook</a>
                        </div>
                        <p class="text-center mt-3">
                            Sudah punya akun? <a href="<?= $base ?>/login">Masuk</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
