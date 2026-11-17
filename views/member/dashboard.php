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
    $pageTitle = 'Area Anggota';
}
// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Area anggota Anda';
}

$memberEmail = '';
if (isset($member['email'])) {
    $memberEmail = $member['email'];
}

$memberStatus = '';
if (isset($member['status'])) {
    $memberStatus = $member['status'];
}
$memberStatusLabel = $memberStatus;
if ($memberStatus === 'active') {
    $memberStatusLabel = 'Aktif';
} elseif ($memberStatus === 'banned') {
    $memberStatusLabel = 'Diblokir';
} elseif ($memberStatus === 'pending') {
    $memberStatusLabel = 'Menunggu';
}

$memberAvatar = '';
if (!empty($member['avatar'])) {
    $memberAvatar = $member['avatar'];
}

if (!isset($displayName)) {
    $displayName = $memberEmail;
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-4">Area Anggota</h1>

                    <div class="d-flex align-items-center mb-4">
                        <?php if ($memberAvatar !== ''): ?>
                            <img src="<?= $base . htmlspecialchars($memberAvatar) ?>" alt="Avatar" class="rounded-circle me-3" style="width: 64px; height: 64px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                                <i class="bi bi-person-fill text-white fs-3"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h2 class="h5 mb-1"><?= htmlspecialchars($displayName) ?></h2>
                            <span class="text-muted">Status: <?= htmlspecialchars($memberStatusLabel) ?></span>
                        </div>
                    </div>

                    <p class="text-muted">Selamat datang di akun anggota Anda. Gunakan tombol di bawah untuk memperbarui profil.</p>

                    <a class="btn btn-primary" href="<?= $base ?>/member/profile">
                        <i class="bi bi-person me-2"></i>Profil Saya
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mt-4">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="h4 mb-4">Daftar Keinginan Saya</h2>
                <?php if (empty($wishlist)): ?>
                    <p class="text-muted">Anda belum menyimpan produk apa pun.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($wishlist as $item): ?>
                            <div class="col-md-6">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars((string) $item['product_name']) ?></h5>
                                        <p class="card-text text-muted small"><?= htmlspecialchars((string) $item['brand_name']) ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="<?= $base ?>/<?= htmlspecialchars((string) $item['category_slug']) ?>/<?= htmlspecialchars((string) $item['product_slug']) ?>" class="btn btn-sm btn-primary">Lihat Produk</a>
                                            <form method="POST" action="<?= $base ?>/wishlist/remove/<?= (int) $item['product_id'] ?>" class="m-0">
                                                <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>