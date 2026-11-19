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
    $pageTitle = 'Locations';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Manage seller locations';
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
                <h1 class="h2">Locations</h1>
                <a class="btn btn-primary" href="<?= $base ?>/dashboard/locations/create">
                    <i class="bi bi-plus-lg"></i> New Location
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Sellers</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($locations)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No locations found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($locations as $location): ?>
                            <?php
                            $sellerCountValue = 0;
                            if (isset($sellerCounts[(int) $location['id']])) {
                                $sellerCountValue = (int) $sellerCounts[(int) $location['id']];
                            }
                            ?>
                        <tr>
                            <td><?= (int) $location['id'] ?></td>
                            <td><?= htmlspecialchars((string) $location['name']) ?></td>
                            <td><?= htmlspecialchars((string) $location['slug']) ?></td>
                            <td><?= $sellerCountValue ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/dashboard/locations/<?= (int) $location['id'] ?>/edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteLocation<?= (int) $location['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($locations as $location): ?>
            <div class="modal fade" id="deleteLocation<?= (int) $location['id'] ?>" tabindex="-1" aria-labelledby="deleteLocationLabel<?= (int) $location['id'] ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="<?= $base ?>/dashboard/locations/<?= (int) $location['id'] ?>/delete">
                            <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteLocationLabel<?= (int) $location['id'] ?>">Delete Location</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete the location <?= htmlspecialchars((string) $location['name']) ?>?
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
