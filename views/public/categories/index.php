<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4 fw-bold" style="font-family: 'Poppins', sans-serif;">Semua Kategori</h1>
    <div class="row g-4">
        <?php if (empty($categories)): ?>
            <div class="col-12"><p class="text-muted">Kategori tidak ditemukan.</p></div>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <?php
                $categoryImage = '';
                if (isset($category['image'])) {
                    $categoryImage = (string) $category['image'];
                }
                ?>
                <div class="col-md-3">
                    <a href="<?= $base ?>/<?= htmlspecialchars((string) $category['slug']) ?>" class="category-card h-100 text-decoration-none text-dark d-flex flex-column align-items-center justify-content-center p-4 border rounded">
                        <?php if ($categoryImage !== ''): ?>
                            <img src="<?= $base . htmlspecialchars((string) $categoryImage) ?>" alt="<?= htmlspecialchars((string) $category['name']) ?>" class="img-fluid mb-3" style="max-height: 80px; object-fit: contain;">
                        <?php else: ?>
                            <div class="mb-3 text-primary" style="font-size: 3rem;"><i class="bi bi-tag"></i></div>
                        <?php endif; ?>
                        <h5 class="category-card-name text-center mb-0"><?= htmlspecialchars((string) $category['name']) ?></h5>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?> 