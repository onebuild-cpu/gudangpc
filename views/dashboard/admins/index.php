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
    $pageTitle = 'Admins';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Manage admin users';
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
                <h1 class="h2">Admins</h1>
                <a class="btn btn-primary" href="<?= $base ?>/dashboard/admins/create">
                    <i class="bi bi-plus-lg"></i> Add Admin
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Username</th>
                            <th scope="col">Verified</th>
                            <th scope="col">Last Login</th>
                            <th scope="col">Created</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($admins)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No admins found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                        <?php
                        $usernameValue = '';
                        if (isset($admin['username'])) {
                            $usernameValue = (string) $admin['username'];
                        }

                        $verifiedBadge = 'secondary';
                        $verifiedLabel = 'No';
                        if (!empty($admin['is_verified'])) {
                            $verifiedBadge = 'success';
                            $verifiedLabel = 'Yes';
                        }

                        $lastLoginValue = '';
                        if (!empty($admin['last_login_at'])) {
                            $lastLoginValue = (string) $admin['last_login_at'];
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $admin['id']) ?></td>
                            <td><?= htmlspecialchars((string) $admin['phone']) ?></td>
                            <td>
                                <?php if ($usernameValue === ''): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($usernameValue) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $verifiedBadge ?>"><?= $verifiedLabel ?></span>
                                <?php if ($admin['is_verified'] == 1): ?>
                                <form method="POST" action="<?= $base ?>/dashboard/admins/<?= (int) $admin['id'] ?>/unverify" class="d-inline">
                                    <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Unverify</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" action="<?= $base ?>/dashboard/admins/<?= (int) $admin['id'] ?>/verify" class="d-inline">
                                    <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">Verify</button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lastLoginValue === ''): ?>
                                    <span class="text-muted">Never</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($lastLoginValue) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string) $admin['created_at']) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/dashboard/admins/<?= (int) $admin['id'] ?>/edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= (int) $admin['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($admins)): ?>
                <?php foreach ($admins as $admin): ?>
                <div class="modal fade" id="deleteModal<?= (int) $admin['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= (int) $admin['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="<?= $base ?>/dashboard/admins/<?= (int) $admin['id'] ?>/delete">
                                <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                                <div class="modal-header">
                                    <h5 class="modal-title" id="deleteModalLabel<?= (int) $admin['id'] ?>">Delete Admin</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Delete admin <?= htmlspecialchars((string) $admin['phone']) ?>? This cannot be undone.
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
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
