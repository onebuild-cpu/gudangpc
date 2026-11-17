<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <h1 class="display-1 text-warning fw-bold">419</h1>
            <h2 class="mb-4">Halaman Kedaluwarsa</h2>
            <p class="text-muted mb-4">
                Sesi Anda telah berakhir atau token keamanan tidak valid.
                Ini adalah langkah keamanan untuk melindungi data Anda.
            </p>
            <a href="<?= $base ?>" class="btn btn-primary">
                <i class="bi bi-house-door me-2"></i>Kembali ke Beranda
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>