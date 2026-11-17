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
    $pageTitle = 'Brands';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Manage product brands';
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
                <h1 class="h2">Brands</h1>
                <a class="btn btn-primary" href="<?= $base ?>/dashboard/brands/create">
                    <i class="bi bi-plus-lg"></i> New Brand
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Logo Thumbnail</th>
                            <th scope="col">Name</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Website URL</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($brands)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No brands found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($brands as $brand): ?>
                            <?php
                            $statusBadge = 'bg-secondary';
                            if ($brand['status'] === 'published') {
                                $statusBadge = 'bg-success';
                            } elseif ($brand['status'] === 'draft') {
                                $statusBadge = 'bg-warning text-dark';
                            }

                            $logoValue = '';
                            if (isset($brand['logo'])) {
                                $logoValue = (string) $brand['logo'];
                            }

                            $websiteUrlValue = '';
                            if (isset($brand['website_url'])) {
                                $websiteUrlValue = (string) $brand['website_url'];
                            }

                            $logoAlt = (string) $brand['name'];
                            ?>
                        <tr>
                            <td><?= (int) $brand['id'] ?></td>
                            <td>
                                <?php if ($logoValue !== ''): ?>
                                    <img src="<?= $base . htmlspecialchars($logoValue) ?>" alt="<?= htmlspecialchars($logoAlt) ?>" class="img-thumbnail" width="50" height="50">
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string) $brand['name']) ?></td>
                            <td><?= htmlspecialchars((string) $brand['slug']) ?></td>
                            <td>
                                <?php if ($websiteUrlValue !== ''): ?>
                                    <?= htmlspecialchars($websiteUrlValue) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars(ucfirst((string) $brand['status'])) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/dashboard/brands/<?= (int) $brand['id'] ?>/edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteBrand<?= (int) $brand['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($brands as $brand): ?>
            <div class="modal fade" id="deleteBrand<?= (int) $brand['id'] ?>" tabindex="-1" aria-labelledby="deleteBrandLabel<?= (int) $brand['id'] ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="<?= $base ?>/dashboard/brands/<?= (int) $brand['id'] ?>/delete">
                            <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteBrandLabel<?= (int) $brand['id'] ?>">Delete Brand</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete the brand <?= htmlspecialchars((string) $brand['name']) ?>?
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
