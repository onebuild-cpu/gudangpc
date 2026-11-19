<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4 fw-bold moved-poppins-heading">Semua Merek</h1>
    <div class="row g-4">
        <?php if (empty($brands)): ?>
            <div class="col-12"><p class="text-muted">Merek tidak ditemukan.</p></div>
        <?php else: ?>
            <?php foreach ($brands as $brand): ?>
                <?php
                $brandLogo = '';
                if (isset($brand['logo'])) {
                    $brandLogo = (string) $brand['logo'];
                }
                ?>
                <div class="col-md-3">
                    <a href="<?= $base ?>/brand/<?= htmlspecialchars((string) $brand['slug']) ?>" class="category-card h-100 text-decoration-none text-dark d-flex flex-column align-items-center justify-content-center p-4 border rounded">
                        <?php if ($brandLogo !== ''): ?>
                            <img src="<?= $base . htmlspecialchars((string) $brandLogo) ?>" alt="<?= htmlspecialchars((string) $brand['name']) ?> logo" class="img-fluid mb-3 moved-thumb-sm">
                        <?php else: ?>
                            <div class="mb-3 text-primary moved-icon-lg"><i class="bi bi-award"></i></div>
                        <?php endif; ?>
                        <h5 class="category-card-name text-center mb-0"><?= htmlspecialchars((string) $brand['name']) ?></h5>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?> 