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
    $pageTitle = 'Profil Saya';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Edit profil anggota Anda';
}

$userEmail = '';
if (isset($user['email'])) {
    $userEmail = $user['email'];
}

$userStatus = '';
if (isset($user['status'])) {
    $userStatus = $user['status'];
}

$userAvatar = '';
if (!empty($user['avatar'])) {
    $userAvatar = $user['avatar'];
}

$usernameValue = '';
if (isset($user['username'])) {
    $usernameValue = $user['username'];
}

$statusLabel = $userStatus;
if ($userStatus === 'active') {
    $statusLabel = 'Aktif';
} elseif ($userStatus === 'banned') {
    $statusLabel = 'Diblokir';
} elseif ($userStatus === 'pending') {
    $statusLabel = 'Menunggu';
}

$statusBadgeClass = 'bg-secondary';
if ($userStatus === 'active') {
    $statusBadgeClass = 'bg-success';
} elseif ($userStatus === 'banned') {
    $statusBadgeClass = 'bg-danger';
}

$errorMessage = '';
if (isset($error)) {
    $errorMessage = $error;
}

$successMessage = '';
if (isset($success)) {
    $successMessage = $success;
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container py-4">
    <h1 class="h3 mb-4">Profil Saya</h1>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <?php if ($userAvatar !== ''): ?>
                        <img src="<?= $base . htmlspecialchars($userAvatar) ?>" alt="Avatar" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-3" style="width: 120px; height: 120px;">
                            <i class="bi bi-person-fill text-white" style="font-size: 3rem;"></i>
                        </div>
                    <?php endif; ?>
                    <h2 class="h5 mb-2"><?= htmlspecialchars($userEmail) ?></h2>
                    <span class="badge <?= htmlspecialchars($statusBadgeClass) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-4">Perbarui Profil</h2>

                    <form method="POST" action="<?= $base ?>/member/profile" enctype="multipart/form-data">
                        <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="mb-3">
                            <label for="username" class="form-label">Nama Pengguna (opsional)</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($usernameValue) ?>">
                            <div class="form-text">3-50 karakter. Hanya huruf, angka, dan garis bawah.</div>
                        </div>

                        <div class="mb-3">
                            <label for="avatar" class="form-label">Avatar</label>
                            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                            <div class="form-text">JPG, PNG, GIF, atau WebP. Maksimal 2MB.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-4">Ubah Kata Sandi</h2>

                    <form method="POST" action="<?= $base ?>/member/profile">
                        <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Kata Sandi Saat Ini</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Kata Sandi Baru</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                            <div class="form-text">Minimal 8 karakter.</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-primary">Ubah Kata Sandi</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
