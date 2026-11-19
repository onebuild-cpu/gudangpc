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
    $pageTitle = 'Vendors';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Manage marketplace vendors';
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
                <h1 class="h2">Vendors</h1>
                <a class="btn btn-primary" href="<?= $base ?>/dashboard/vendors/create">
                    <i class="bi bi-plus-lg"></i> New Vendor
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Vendor Name</th>
                            <th scope="col">Platform</th>
                            <th scope="col">Location</th>
                            <th scope="col">Rating</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($vendors)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No vendors found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vendors as $vendor): ?>
                            <?php
                            $statusBadge = 'bg-secondary';
                            if ($vendor['status'] === 'active') {
                                $statusBadge = 'bg-success';
                            }

                            $storeNameValue = '';
                            if (isset($vendor['store_name'])) {
                                $storeNameValue = (string) $vendor['store_name'];
                            }

                            $locationNameValue = '';
                            if (isset($vendor['location_name'])) {
                                $locationNameValue = (string) $vendor['location_name'];
                            }

                            $ratingValue = '';
                            if (isset($vendor['rating'])) {
                                $ratingValue = (string) $vendor['rating'];
                            }
                            ?>
                        <tr>
                            <td><?= (int) $vendor['id'] ?></td>
                            <td><?= htmlspecialchars((string) $vendor['name']) ?></td>
                            <td>
                                <?php if ($storeNameValue !== ''): ?>
                                    <?= htmlspecialchars((string) $storeNameValue) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($locationNameValue !== ''): ?>
                                    <?= htmlspecialchars((string) $locationNameValue) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ratingValue !== ''): ?>
                                    <?= htmlspecialchars((string) $ratingValue) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars(ucfirst((string) $vendor['status'])) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/dashboard/vendors/<?= (int) $vendor['id'] ?>/edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteVendor<?= (int) $vendor['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($vendors as $vendor): ?>
            <div class="modal fade" id="deleteVendor<?= (int) $vendor['id'] ?>" tabindex="-1" aria-labelledby="deleteVendorLabel<?= (int) $vendor['id'] ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="<?= $base ?>/dashboard/vendors/<?= (int) $vendor['id'] ?>/delete">
                            <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteVendorLabel<?= (int) $vendor['id'] ?>">Delete Vendor</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete the vendor <?= htmlspecialchars((string) $vendor['name']) ?>?
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
