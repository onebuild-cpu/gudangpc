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
    $pageTitle = 'Categories';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Manage product categories';
}
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
                <h1 class="h2">Categories</h1>
                <a class="btn btn-primary" href="<?= $base ?>/dashboard/categories/create">
                    <i class="bi bi-plus-lg"></i> New Category
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Image Thumbnail</th>
                            <th scope="col">Name</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No categories found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $statusBadge = 'bg-secondary';
                            if ($category['status'] === 'published') {
                                $statusBadge = 'bg-success';
                            } elseif ($category['status'] === 'draft') {
                                $statusBadge = 'bg-warning text-dark';
                            }

                            $imageValue = '';
                            if (isset($category['image'])) {
                                $imageValue = (string) $category['image'];
                            }

                            $imageAlt = (string) $category['name'];
                            if (isset($category['image_alt']) && $category['image_alt'] !== '') {
                                $imageAlt = (string) $category['image_alt'];
                            }
                            ?>
                        <tr>
                            <td><?= (int) $category['id'] ?></td>
                            <td>
                                <?php if ($imageValue !== ''): ?>
                                    <img src="<?= $base . htmlspecialchars($imageValue) ?>" alt="<?= htmlspecialchars($imageAlt) ?>" class="img-thumbnail" width="50" height="50">
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string) $category['name']) ?></td>
                            <td><?= htmlspecialchars((string) $category['slug']) ?></td>
                            <td><span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars(ucfirst((string) $category['status'])) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/dashboard/categories/<?= (int) $category['id'] ?>/edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCategory<?= (int) $category['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($categories as $category): ?>
            <div class="modal fade" id="deleteCategory<?= (int) $category['id'] ?>" tabindex="-1" aria-labelledby="deleteCategoryLabel<?= (int) $category['id'] ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="<?= $base ?>/dashboard/categories/<?= (int) $category['id'] ?>/delete">
                            <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteCategoryLabel<?= (int) $category['id'] ?>">Delete Category</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete the category <?= htmlspecialchars((string) $category['name']) ?>?
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
