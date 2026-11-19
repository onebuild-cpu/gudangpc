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
    $pageTitle = 'Products';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Manage products and offers';
}

$statusUrlValue = urlencode((string) $status);
?>
<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css" nonce="<?= $nonce ?>">

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/../_sidebar.php'; ?>

        <!-- Main content -->
        <section class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Products</h1>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars((string) $search) ?>" placeholder="Search products...">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">All Statuses</option>
                        <option value="published"<?php if ($statusPublishedSelected) { echo ' selected'; } ?>>Published</option>
                        <option value="draft"<?php if ($statusDraftSelected) { echo ' selected'; } ?>>Draft</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <a class="btn btn-primary w-100" href="<?= $base ?>/dashboard/products/create">
                        <i class="bi bi-plus-lg"></i> New Product
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Image Thumbnail</th>
                            <th scope="col">Name</th>
                            <th scope="col">Category</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No products found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $imageValue = '';
                            if (isset($product['image'])) {
                                $imageValue = (string) $product['image'];
                            }

                            $imageAlt = (string) $product['name'];
                            if (isset($product['image_alt']) && $product['image_alt'] !== '') {
                                $imageAlt = (string) $product['image_alt'];
                            }

                            $statusBadge = 'bg-secondary';
                            if ($product['status'] === 'published') {
                                $statusBadge = 'bg-success';
                            } elseif ($product['status'] === 'draft') {
                                $statusBadge = 'bg-warning text-dark';
                            }
                            ?>
                        <tr>
                            <td><?= (int) $product['id'] ?></td>
                            <td>
                                <?php if ($imageValue !== ''): ?>
                                    <img src="<?= $base . htmlspecialchars($imageValue) ?>" alt="<?= htmlspecialchars($imageAlt) ?>" class="img-thumbnail" width="50" height="50">
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string) $product['name']) ?></td>
                            <td><?= htmlspecialchars((string) $product['category_name']) ?></td>
                            <td><?= htmlspecialchars((string) $product['brand_name']) ?></td>
                            <td><span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars(ucfirst((string) $product['status'])) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/dashboard/products/<?= (int) $product['id'] ?>/edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProduct<?= (int) $product['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <nav aria-label="Product pagination">
                <ul class="pagination justify-content-center">
                    <?php
                    $isDisabledPrev = false;
                    if ($page <= 1) {
                        $isDisabledPrev = true;
                    }
                    $isDisabledNext = false;
                    if ($page >= $totalPages) {
                        $isDisabledNext = true;
                    }
                    ?>
                    <?php if (!$isDisabledPrev): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $base ?>/dashboard/products?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $statusUrlValue ?>" aria-label="Previous">
                            <span aria-hidden="true">«</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php
                        $isActive = false;
                        if ($i === $page) {
                            $isActive = true;
                        }
                        ?>
                        <?php
$pageItemClass = 'page-item';
if ($isActive) {
    $pageItemClass .= ' active';
}
?>
                        <li class="page-item <?= $pageItemClass ?>">
                            <a class="page-link" href="<?= $base ?>/dashboard/products?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $statusUrlValue ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if (!$isDisabledNext): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $base ?>/dashboard/products?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $statusUrlValue ?>" aria-label="Next">
                            <span aria-hidden="true">»</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    </ul>
                </nav>

                <small class="text-muted">Showing <?= ($page - 1) * $perPage + 1 ?>-<?= min($page * $perPage, $total )?> of <?= $total ?> products</small>
            </nav>

            <?php foreach ($products as $product): ?>
            <div class="modal fade" id="deleteProduct<?= (int) $product['id'] ?>" tabindex="-1" aria-labelledby="deleteProductLabel<?= (int) $product['id'] ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="<?= $base ?>/dashboard/products/<?= (int) $product['id'] ?>/delete">
                            <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteProductLabel<?= (int) $product['id'] ?>">Delete Product</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete the product <?= htmlspecialchars((string) $product['name']) ?>?
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>