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
    $pageTitle = 'Members';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Manage public member accounts';
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
                <h1 class="h2">Members</h1>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Email</th>
                            <th scope="col">Username</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No members found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                        <?php
                        $usernameValue = '';
                        if (isset($member['username'])) {
                            $usernameValue = (string) $member['username'];
                        }

                        $statusBadge = 'secondary';
                        if ($member['status'] === 'active') {
                            $statusBadge = 'success';
                        } elseif ($member['status'] === 'pending') {
                            $statusBadge = 'warning text-dark';
                        } elseif ($member['status'] === 'banned') {
                            $statusBadge = 'danger';
                        }

                        $statusLabel = ucfirst((string) $member['status']);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $member['id']) ?></td>
                            <td><?= htmlspecialchars((string) $member['email']) ?></td>
                            <td>
                                <?php if ($usernameValue === ''): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($usernameValue) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusBadge ?>"><?= htmlspecialchars($statusLabel) ?></span>
                            </td>
                            <td><?= htmlspecialchars((string) $member['created_at']) ?></td>
                            <td class="text-end">
                                <?php if ($member['status'] !== 'active'): ?>
                                <form method="POST" action="<?= $base ?>/dashboard/members/<?= (int) $member['id'] ?>/activate" class="d-inline">
                                    <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-check-lg"></i> Activate
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($member['status'] !== 'banned'): ?>
                                <form method="POST" action="<?= $base ?>/dashboard/members/<?= (int) $member['id'] ?>/ban" class="d-inline">
                                    <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-slash-circle"></i> Ban
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
